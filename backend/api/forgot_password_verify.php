<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');

if ($email === '' || $otp === '') {
    respondError('Email and OTP are required');
}

$stmt = $db->prepare("
    SELECT id, otp_hash, expires_at, is_used
    FROM password_reset_otps
    WHERE email = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record || (int) $record['is_used'] === 1 || strtotime($record['expires_at']) < time()) {
    respondError('OTP is invalid or expired', 400);
}

if (!password_verify($otp, $record['otp_hash'])) {
    respondError('OTP is invalid or expired', 400);
}

respond([
    'success' => true,
    'message' => 'OTP verified successfully'
]);
?>
