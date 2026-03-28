<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

$receiver_id = (int) ($_POST['receiver_id'] ?? 0);
$messageText = trim($_POST['message'] ?? '');

if ($receiver_id <= 0 || $receiver_id === $user_id) {
    respondError('Valid recipient is required');
}

$userCheck = $db->prepare("SELECT id FROM users WHERE id = ?");
$userCheck->bind_param("i", $receiver_id);
$userCheck->execute();
if ($userCheck->get_result()->num_rows === 0) {
    respondError('Recipient not found', 404);
}

$presenceStmt = $db->prepare("
    SELECT MAX(last_activity) AS last_activity
    FROM sessions
    WHERE user_id = ? AND expires_at > NOW()
");
$presenceStmt->bind_param("i", $receiver_id);
$presenceStmt->execute();
$presenceRow = $presenceStmt->get_result()->fetch_assoc();
$receiverOnline = isUserOnlineFromTimestamp($presenceRow['last_activity'] ?? null);
$isDelivered = $receiverOnline ? 1 : 0;
$deliveredAt = $receiverOnline ? date('Y-m-d H:i:s') : null;

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    respondError('No file uploaded or upload error');
}

$file = $_FILES['file'];
$fileType = mime_content_type($file['tmp_name']) ?: $file['type'];
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$allowedVideoTypes = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/webm'];
$allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes);

if (!in_array($fileType, $allowedTypes, true)) {
    respondError('Unsupported attachment type');
}

$attachmentType = in_array($fileType, $allowedImageTypes, true) ? 'image' : 'video';
$originalName = $file['name'];
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$folder = UPLOAD_DIR . 'messages/';
if (!file_exists($folder)) {
    mkdir($folder, 0755, true);
}

$filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$filePath = $folder . $filename;
$relativePath = 'uploads/messages/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    respondError('Failed to save attachment');
}

$storedMessage = $messageText !== '' ? $messageText : '[Media attachment]';
$attachmentInsertSql = dbDriver() === 'pgsql'
    ? "
        INSERT INTO messages (sender_id, receiver_id, message, attachment_path, attachment_type, attachment_name, is_delivered, delivered_at)
        VALUES (?, ?, ?, ?, ?, ?, " . ($isDelivered ? 'TRUE' : 'FALSE') . ", ?)
    "
    : "
        INSERT INTO messages (sender_id, receiver_id, message, attachment_path, attachment_type, attachment_name, is_delivered, delivered_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
$stmt = $db->prepare($attachmentInsertSql);
if (dbDriver() === 'pgsql') {
    $stmt->bind_param("iisssss", $user_id, $receiver_id, $storedMessage, $relativePath, $attachmentType, $originalName, $deliveredAt);
} else {
    $stmt->bind_param("iissssis", $user_id, $receiver_id, $storedMessage, $relativePath, $attachmentType, $originalName, $isDelivered, $deliveredAt);
}

if (!$stmt->execute()) {
    @unlink($filePath);
    respondError('Failed to send attachment');
}

respond([
    'success' => true,
    'message_id' => $db->insert_id,
    'attachment_url' => buildAppUrl('backend/' . $relativePath),
    'attachment_type' => $attachmentType,
    'attachment_name' => $originalName,
    'is_delivered' => (bool) $isDelivered,
    'delivered_at' => $deliveredAt,
    'created_at' => date('Y-m-d H:i:s')
]);
?>
