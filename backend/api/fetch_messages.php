<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

// Validate authentication
$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

// Get parameters
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if (!$other_user_id) {
    respondError('User ID required');
}

// Verify other user exists
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.username,
        u.avatar,
        u.email_verified,
        u.last_active,
        (
            SELECT MAX(s.last_activity)
            FROM sessions s
            WHERE s.user_id = u.id AND s.expires_at > NOW()
        ) AS session_last_activity
    FROM users u
    WHERE u.id = ?
");
$stmt->bind_param("i", $other_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respondError('User not found', 404);
}

$otherUser = $result->fetch_assoc();

// Fetch messages between users
$stmt = $db->prepare("
    SELECT 
        m.id,
        m.sender_id,
        m.receiver_id,
        m.message,
        m.is_read,
        m.is_deleted,
        m.attachment_path,
        m.attachment_type,
        m.attachment_name,
        m.read_at,
        m.is_delivered,
        m.delivered_at,
        m.edited_at,
        m.created_at,
        u1.username as sender_name,
        u2.username as receiver_name
    FROM messages m
    JOIN users u1 ON m.sender_id = u1.id
    JOIN users u2 ON m.receiver_id = u2.id
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->bind_param("iiiiii", $user_id, $other_user_id, $other_user_id, $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'receiver_id' => $row['receiver_id'],
        'sender_name' => $row['sender_name'],
        'receiver_name' => $row['receiver_name'],
        'message' => $row['message'],
        'is_read' => (bool)$row['is_read'],
        'is_deleted' => (bool)$row['is_deleted'],
        'attachment_url' => $row['attachment_path'] ? buildAppUrl('backend/' . ltrim($row['attachment_path'], '/')) : null,
        'attachment_type' => $row['attachment_type'],
        'attachment_name' => $row['attachment_name'],
        'read_at' => $row['read_at'],
        'is_delivered' => (bool) $row['is_delivered'],
        'delivered_at' => $row['delivered_at'],
        'edited_at' => $row['edited_at'],
        'created_at' => $row['created_at'],
        'time_ago' => timeAgo($row['created_at'])
    ];
}

$deliverStmt = $db->prepare("
    UPDATE messages
    SET is_delivered = TRUE,
        delivered_at = COALESCE(delivered_at, NOW())
    WHERE receiver_id = ? AND sender_id = ? AND is_delivered = FALSE
");
$deliverStmt->bind_param("ii", $user_id, $other_user_id);
$deliverStmt->execute();

// Mark messages as read
$stmt = $db->prepare("
    UPDATE messages 
    SET is_read = TRUE,
        read_at = NOW(),
        is_delivered = TRUE,
        delivered_at = COALESCE(delivered_at, NOW())
    WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE
");
$stmt->bind_param("ii", $user_id, $other_user_id);
$stmt->execute();

// Get total count
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?)
");
$stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
$stmt->execute();
$countResult = $stmt->get_result();
$total = $countResult->fetch_assoc()['total'];

respond([
    'success' => true,
    'user' => [
        'id' => $otherUser['id'],
        'username' => $otherUser['username'],
        'avatar' => $otherUser['avatar'] ? buildAppUrl('backend/' . ltrim($otherUser['avatar'], '/')) : null,
        'email_verified' => (bool) $otherUser['email_verified'],
        'is_online' => isUserOnlineFromTimestamp($otherUser['session_last_activity'] ?: $otherUser['last_active']),
        'last_seen' => $otherUser['session_last_activity'] ?: $otherUser['last_active'],
        'last_seen_ago' => ($otherUser['session_last_activity'] ?: $otherUser['last_active']) ? timeAgo($otherUser['session_last_activity'] ?: $otherUser['last_active']) : null
    ],
    'messages' => array_reverse($messages),
    'total' => (int)$total,
    'limit' => $limit,
    'offset' => $offset
]);
?>
