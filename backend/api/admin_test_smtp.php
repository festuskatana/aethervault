<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$adminId = requireAdminUser();
$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if ($email === '' || !validateEmail($email)) {
    respondError('Valid test email is required');
}

$appName = getAppSetting('app_name', 'Aether Vault');
$otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

try {
    sendOtpEmail($email, $otpCode, $appName);
} catch (Exception $exception) {
    respondError($exception->getMessage(), 500);
}

logActivity($adminId, 'admin_test_smtp', 'Sent SMTP test email to ' . $email);

respond([
    'success' => true,
    'message' => 'Test email sent successfully'
]);
?>
