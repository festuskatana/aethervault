<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

// Validate authentication
$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

// Get search query
$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';

if (strlen($query) < 2) {
    respond([
        'success' => true,
        'users' => [],
        'message' => 'Search query must be at least 2 characters'
    ]);
}

// Search users
$searchTerm = "%{$query}%";
$stmt = $db->prepare("
    SELECT 
        id, 
        username, 
        email,
        email_verified,
        avatar,
        last_active,
        (
            SELECT MAX(s.last_activity)
            FROM sessions s
            WHERE s.user_id = users.id AND s.expires_at > NOW()
        ) AS session_last_activity,
        created_at
    FROM users 
    WHERE (username LIKE ? OR email LIKE ?) 
        AND id != ?
    ORDER BY 
        CASE 
            WHEN username = ? THEN 1
            WHEN username LIKE ? THEN 2
            ELSE 3
        END,
        username ASC
    LIMIT 20
");

$stmt->bind_param("ssiss", $searchTerm, $searchTerm, $user_id, $query, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    // Get last message with this user if any
    $lastMsgStmt = $db->prepare("
        SELECT 
            CASE
                WHEN is_deleted = TRUE THEN '[Message deleted]'
                WHEN attachment_path IS NOT NULL AND message = '[Media attachment]' THEN
                    CASE
                        WHEN ? = 'pgsql' THEN '[' || attachment_type || ' attachment]'
                        ELSE CONCAT('[', attachment_type, ' attachment]')
                    END
                WHEN attachment_path IS NOT NULL THEN
                    CASE
                        WHEN ? = 'pgsql' THEN message || ' [attachment]'
                        ELSE CONCAT(message, ' [attachment]')
                    END
                ELSE message
            END AS message,
            created_at 
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $driver = dbDriver();
    $lastMsgStmt->bind_param("ssiiii", $driver, $driver, $user_id, $row['id'], $row['id'], $user_id);
    $lastMsgStmt->execute();
    $lastMsgResult = $lastMsgStmt->get_result();
    $lastMessage = $lastMsgResult->fetch_assoc();
    
    // Get unread count
    $unreadStmt = $db->prepare("
        SELECT COUNT(*) as unread 
        FROM messages 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
    ");
    $unreadStmt->bind_param("ii", $row['id'], $user_id);
    $unreadStmt->execute();
    $unreadResult = $unreadStmt->get_result();
    $unread = $unreadResult->fetch_assoc();
    
    $lastSeen = $row['session_last_activity'] ?: $row['last_active'];
    $users[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'email_verified' => (bool) $row['email_verified'],
        'avatar' => $row['avatar'] ? buildAppUrl('backend/' . ltrim($row['avatar'], '/')) : null,
        'avatar_color' => getAvatarColor($row['username']),
        'is_online' => isUserOnlineFromTimestamp($lastSeen),
        'last_seen' => $lastSeen,
        'last_seen_ago' => $lastSeen ? timeAgo($lastSeen) : null,
        'last_message' => $lastMessage ? htmlspecialchars($lastMessage['message']) : null,
        'last_message_time' => $lastMessage ? $lastMessage['created_at'] : null,
        'last_message_ago' => $lastMessage ? timeAgo($lastMessage['created_at']) : null,
        'unread_count' => (int)$unread['unread'],
        'member_since' => timeAgo($row['created_at'])
    ];
}

respond([
    'success' => true,
    'users' => $users,
    'query' => $query,
    'count' => count($users)
]);

// Helper function for avatar color
function getAvatarColor($username) {
    $hash = 0;
    for ($i = 0; $i < strlen($username); $i++) {
        $hash = ord($username[$i]) + (($hash << 5) - $hash);
    }
    $colors = ['#0A4D4C', '#D4AF37', '#E74C3C', '#3498DB', '#2ECC71', '#9B59B6', '#E67E22', '#1ABC9C'];
    return $colors[abs($hash) % count($colors)];
}
?>
