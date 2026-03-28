<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

// Get all conversations with last message and unread count
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
        ) AS session_last_activity,
        (
            SELECT CASE
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
            END
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = u.id) 
               OR (sender_id = u.id AND receiver_id = ?)
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT created_at 
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = u.id) 
               OR (sender_id = u.id AND receiver_id = ?)
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message_time,
        (
            SELECT COUNT(*) 
            FROM messages 
            WHERE sender_id = u.id 
              AND receiver_id = ? 
              AND is_read = FALSE
        ) as unread_count
    FROM users u
    WHERE u.id != ?
      AND EXISTS (
          SELECT 1 FROM messages 
          WHERE (sender_id = ? AND receiver_id = u.id) 
             OR (sender_id = u.id AND receiver_id = ?)
      )
    ORDER BY last_message_time DESC
");

$driver = dbDriver();
$stmt->bind_param("ssiiiiiiii", 
    $driver, $driver,    // for last message formatting
    $user_id, $user_id,  // for last message
    $user_id, $user_id,  // for last message time
    $user_id,            // for unread count
    $user_id,            // where not current user
    $user_id, $user_id   // for exists check
);
$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $lastSeen = $row['session_last_activity'] ?: $row['last_active'];
    $conversations[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'avatar' => $row['avatar'] ? buildAppUrl('backend/' . ltrim($row['avatar'], '/')) : null,
        'email_verified' => (bool) $row['email_verified'],
        'is_online' => isUserOnlineFromTimestamp($lastSeen),
        'last_seen' => $lastSeen,
        'last_seen_ago' => $lastSeen ? timeAgo($lastSeen) : null,
        'last_message' => $row['last_message'],
        'last_message_time' => $row['last_message_time'],
        'last_message_ago' => $row['last_message_time'] ? timeAgo($row['last_message_time']) : null,
        'unread_count' => (int)$row['unread_count']
    ];
}

respond([
    'success' => true,
    'conversations' => $conversations
]);
?>
