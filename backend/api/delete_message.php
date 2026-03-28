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

if ($messageId <= 0) {
    respondError('Message ID is required');
}

$deletedMessage = '[Message deleted]';
$stmt = $db->prepare("
    UPDATE messages
    SET is_deleted = TRUE, deleted_by = ?, message = ?, attachment_path = NULL, attachment_type = NULL, attachment_name = NULL
    WHERE id = ? AND sender_id = ? AND is_deleted = FALSE
");
$stmt->bind_param("isii", $user_id, $deletedMessage, $messageId, $user_id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    respondError('Message not found or cannot be deleted', 404);
}

respond([
    'success' => true,
    'message' => 'Message deleted successfully'
]);
?>
