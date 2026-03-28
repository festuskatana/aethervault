<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');
$password = $data['password'] ?? '';

if ($email === '' || $otp === '' || $password === '') {
    respondError('Email, OTP, and password are required');
}

if (!validatePassword($password)) {
    respondError('Password must be at least 6 characters');
}

$stmt = $db->prepare("
    SELECT id, user_id, otp_hash, expires_at, is_used
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

$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$updateStmt = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
$updateStmt->bind_param("si", $passwordHash, $record['user_id']);
$updateStmt->execute();

$usedStmt = $db->prepare("UPDATE password_reset_otps SET is_used = TRUE WHERE id = ?");
$usedStmt->bind_param("i", $record['id']);
$usedStmt->execute();

$sessionStmt = $db->prepare("DELETE FROM sessions WHERE user_id = ?");
$sessionStmt->bind_param("i", $record['user_id']);
$sessionStmt->execute();

respond([
    'success' => true,
    'message' => 'Password reset successfully'
]);
?>
