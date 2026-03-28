<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

$stmt = $db->prepare("
    SELECT 
        COUNT(*) AS unread_total,
        COUNT(DISTINCT sender_id) AS unread_conversations
    FROM messages
    WHERE receiver_id = ? AND is_read = FALSE
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$counts = $result->fetch_assoc();

respond([
    'success' => true,
    'unread_total' => (int) ($counts['unread_total'] ?? 0),
    'unread_conversations' => (int) ($counts['unread_conversations'] ?? 0)
]);
?>
