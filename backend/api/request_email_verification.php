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

$stmt = $db->prepare("SELECT email, email_verified FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    respondError('User not found', 404);
}

$accountEmail = trim((string) $user['email']);
if ($email === '') {
    $email = $accountEmail;
}

if (strcasecmp($email, $accountEmail) !== 0) {
    respondError('Verification email must match your account email');
}

if ((bool) $user['email_verified']) {
    respond([
        'success' => true,
        'message' => 'Your email is already verified.'
    ]);
}

$db->query("DELETE FROM email_verification_otps WHERE expires_at < NOW() OR is_used = TRUE");

$clearStmt = $db->prepare("UPDATE email_verification_otps SET is_used = TRUE WHERE user_id = ? AND is_used = FALSE");
$clearStmt or respondError('Failed to prepare verification storage.');
$clearStmt->bind_param("i", $userId);
$clearStmt->execute();

$otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otpHash = password_hash($otpCode, PASSWORD_BCRYPT);
$expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$insertStmt = $db->prepare("
    INSERT INTO email_verification_otps (user_id, email, otp_hash, expires_at)
    VALUES (?, ?, ?, ?)
");
$insertStmt or respondError('Failed to prepare verification OTP request.');
$insertStmt->bind_param("isss", $userId, $accountEmail, $otpHash, $expiresAt);
$insertStmt->execute();

try {
    sendVerificationOtpEmail($accountEmail, $otpCode, getAppSetting('app_name', 'Aether Vault'));
    logActivity($userId, 'request_email_verification', 'Requested email verification OTP');
} catch (Exception $exception) {
    respondError('Failed to send verification OTP: ' . $exception->getMessage(), 500);
}

respond([
    'success' => true,
    'message' => 'Verification OTP sent to your email.'
]);
?>
