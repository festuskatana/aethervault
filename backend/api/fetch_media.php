<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

$requested_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $user_id;
if ($requested_user_id <= 0) {
    respondError('Invalid user ID');
}

$isOwnVault = $requested_user_id === $user_id;

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$stmt = $db->prepare("
    SELECT id, filename, original_filename, file_path, file_type, file_size, created_at 
    FROM media 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $requested_user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$media = [];
while ($row = $result->fetch_assoc()) {
    $media[] = [
        'id' => $row['id'],
        'filename' => $row['filename'],
        'original_filename' => $row['original_filename'],
        'file_path' => $row['file_path'],
        'url' => buildAppUrl('backend/uploads/' . $row['filename']),
        'type' => $row['file_type'],
        'file_type' => $row['file_type'],
        'size' => (int)$row['file_size'],
        'file_size' => (int)$row['file_size'],
        'size_formatted' => formatFileSize($row['file_size']),
        'created_at' => $row['created_at'],
        'created_ago' => timeAgo($row['created_at'])
    ];
}

// Get total count
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM media WHERE user_id = ?");
$countStmt->bind_param("i", $requested_user_id);
$countStmt->execute();
$countResult = $countStmt->get_result();
$total = $countResult->fetch_assoc()['total'];

$ownerStmt = $db->prepare("SELECT id, username, full_name, avatar FROM users WHERE id = ?");
$ownerStmt->bind_param("i", $requested_user_id);
$ownerStmt->execute();
$owner = $ownerStmt->get_result()->fetch_assoc();

if (!$owner) {
    respondError('User not found', 404);
}

respond([
    'success' => true,
    'media' => $media,
    'total' => (int)$total,
    'limit' => $limit,
    'offset' => $offset,
    'vault_owner' => [
        'id' => (int) $owner['id'],
        'username' => $owner['username'],
        'full_name' => $owner['full_name'],
        'avatar' => $owner['avatar'] ? buildAppUrl('backend/' . ltrim($owner['avatar'], '/')) : null
    ],
    'viewer' => [
        'id' => $user_id,
        'is_owner' => $isOwnVault
    ]
]);
?>
