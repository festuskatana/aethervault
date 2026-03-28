<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$messageId = (int) ($data['message_id'] ?? 0);
$message = trim($data['message'] ?? '');

if ($messageId <= 0 || $message === '') {
    respondError('Message ID and content are required');
}

$stmt = $db->prepare("
    UPDATE messages
    SET message = ?, edited_at = NOW()
    WHERE id = ? AND sender_id = ? AND is_deleted = FALSE
");
$stmt->bind_param("sii", $message, $messageId, $user_id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    respondError('Message not found or cannot be edited', 404);
}

respond([
    'success' => true,
    'message' => 'Message updated successfully'
]);
?>
