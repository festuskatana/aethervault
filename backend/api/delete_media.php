<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$media_id = isset($data['media_id']) ? (int)$data['media_id'] : 0;

if (!$media_id) {
    respondError('Media ID required');
}

// Get file path first
$stmt = $db->prepare("SELECT file_path FROM media WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $media_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Delete file from server
    $filePath = UPLOAD_DIR . basename($row['file_path']);
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Delete from database
    $deleteStmt = $db->prepare("DELETE FROM media WHERE id = ? AND user_id = ?");
    $deleteStmt->bind_param("ii", $media_id, $user_id);
    
    if ($deleteStmt->execute()) {
        respond(['success' => true, 'message' => 'Media deleted successfully']);
    }
}

respondError('Media not found or unauthorized', 404);
?>