<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if ($email === '' || !validateEmail($email)) {
    respondError('Valid email is required');
}

$stmt = $db->prepare("SELECT id, email, username FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    respond([
        'success' => true,
        'message' => 'If the email exists, an OTP has been sent.'
    ]);
}

$db->query("DELETE FROM password_reset_otps WHERE expires_at < NOW() OR is_used = TRUE");

$otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otpHash = password_hash($otpCode, PASSWORD_BCRYPT);
$expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$clearStmt = $db->prepare("UPDATE password_reset_otps SET is_used = TRUE WHERE user_id = ? AND is_used = FALSE");
$clearStmt->bind_param("i", $user['id']);
$clearStmt->execute();

$insertStmt = $db->prepare("
    INSERT INTO password_reset_otps (user_id, email, otp_hash, expires_at)
    VALUES (?, ?, ?, ?)
");
$insertStmt->bind_param("isss", $user['id'], $user['email'], $otpHash, $expiresAt);
$insertStmt->execute();

try {
    sendOtpEmail($user['email'], $otpCode, getAppSetting('app_name', 'Aether Vault'));
} catch (Exception $exception) {
    respondError('Failed to send OTP email: ' . $exception->getMessage(), 500);
}

respond([
    'success' => true,
    'message' => 'OTP sent to your email'
]);
?>
