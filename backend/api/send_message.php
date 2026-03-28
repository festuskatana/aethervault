<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['receiver_id']) || !isset($data['message'])) {
    respondError('Missing required fields');
}

$receiver_id = (int)$data['receiver_id'];
$message = trim($data['message']);

if ($receiver_id <= 0) {
    respondError('Invalid recipient');
}

if ($receiver_id === $user_id) {
    respondError('You cannot message yourself');
}

if ($message === '') {
    respondError('Message cannot be empty');
}

$userCheck = $db->prepare("SELECT id FROM users WHERE id = ?");
$userCheck->bind_param("i", $receiver_id);
$userCheck->execute();
$userResult = $userCheck->get_result();

if ($userResult->num_rows === 0) {
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

$messageInsertSql = dbDriver() === 'pgsql'
    ? "
        INSERT INTO messages (sender_id, receiver_id, message, is_delivered, delivered_at)
        VALUES (?, ?, ?, " . ($isDelivered ? 'TRUE' : 'FALSE') . ", ?)
    "
    : "
        INSERT INTO messages (sender_id, receiver_id, message, is_delivered, delivered_at)
        VALUES (?, ?, ?, ?, ?)
    ";
$stmt = $db->prepare($messageInsertSql);
if (dbDriver() === 'pgsql') {
    $stmt->bind_param("iiss", $user_id, $receiver_id, $message, $deliveredAt);
} else {
    $stmt->bind_param("iisis", $user_id, $receiver_id, $message, $isDelivered, $deliveredAt);
}

if ($stmt->execute()) {
    respond([
        'success' => true,
        'message_id' => $db->insert_id,
        'created_at' => date('Y-m-d H:i:s'),
        'is_delivered' => (bool) $isDelivered,
        'delivered_at' => $deliveredAt
    ]);
} else {
    respondError('Failed to send message');
}
?>
