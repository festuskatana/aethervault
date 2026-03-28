<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$userId = validateToken();
if (!$userId) {
    respondError('Unauthorized', 401);
}

ensureEmailVerificationOtpTable();

$data = json_decode(file_get_contents('php://input'), true);
$email = trim((string) ($data['email'] ?? ''));
$otp = trim((string) ($data['otp'] ?? ''));

if ($email === '' || $otp === '') {
    respondError('Email and OTP are required');
}

$userStmt = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    respondError('User not found', 404);
}

if (strcasecmp($email, (string) $user['email']) !== 0) {
    respondError('Verification email must match your account email');
}

$stmt = $db->prepare("
    SELECT id, otp_hash, expires_at, is_used
    FROM email_verification_otps
    WHERE user_id = ? AND email = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt or respondError('Failed to prepare verification lookup.');
$stmt->bind_param("is", $userId, $email);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    respondError('No verification OTP found. Request a new code.');
}

if ((bool) $record['is_used']) {
    respondError('That OTP has already been used. Request a new code.');
}

if (strtotime((string) $record['expires_at']) < time()) {
    respondError('OTP expired. Request a new code.');
}

if (!password_verify($otp, (string) $record['otp_hash'])) {
    respondError('Invalid OTP code');
}

$verifiedStmt = $db->prepare("UPDATE users SET email_verified = TRUE WHERE id = ?");
$verifiedStmt or respondError('Failed to update verification status.');
$verifiedStmt->bind_param("i", $userId);
$verifiedStmt->execute();

$usedStmt = $db->prepare("UPDATE email_verification_otps SET is_used = TRUE WHERE id = ?");
$usedStmt or respondError('Failed to finalize verification OTP.');
$usedStmt->bind_param("i", $record['id']);
$usedStmt->execute();

logActivity($userId, 'verify_email', 'Verified account email address');

respond([
    'success' => true,
    'message' => 'Email verified successfully.',
    'email_verified' => true
]);
?>
