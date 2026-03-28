<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$adminId = requireAdminUser();

$appName = trim($_POST['app_name'] ?? '');
$smtpHost = trim($_POST['smtp_host'] ?? '');
$smtpPort = (int) ($_POST['smtp_port'] ?? 587);
$smtpUsername = trim($_POST['smtp_username'] ?? '');
$smtpPassword = trim($_POST['smtp_password'] ?? '');
$smtpSecure = trim($_POST['smtp_secure'] ?? 'tls');
$smtpFromEmail = trim($_POST['smtp_from_email'] ?? '');
$smtpFromName = trim($_POST['smtp_from_name'] ?? '');
$autoBackupEnabled = ($_POST['auto_backup_enabled'] ?? '0') === '1' ? '1' : '0';
$rateLimitPerMinute = max(1, (int) ($_POST['rate_limit_per_minute'] ?? 120));
$logRetentionDays = max(1, (int) ($_POST['log_retention_days'] ?? 60));
$maintenanceWindow = trim($_POST['maintenance_window'] ?? '');
$enforceAdmin2fa = ($_POST['enforce_admin_2fa'] ?? '0') === '1' ? '1' : '0';

if ($appName === '') {
    respondError('App name is required');
}

if (!in_array($smtpSecure, ['tls', 'ssl', 'none'], true)) {
    respondError('Invalid SMTP security option');
}

if ($smtpFromEmail !== '' && !validateEmail($smtpFromEmail)) {
    respondError('Valid sender email is required');
}

$logoPath = getAppSetting('app_logo', '');
if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
    $logoFile = $_FILES['app_logo'];
    $allowedLogoTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    if (!in_array($logoFile['type'], $allowedLogoTypes, true)) {
        respondError('Unsupported logo type');
    }

    $brandingFolder = UPLOAD_DIR . 'branding/';
    if (!file_exists($brandingFolder)) {
        mkdir($brandingFolder, 0755, true);
    }

    $extension = pathinfo($logoFile['name'], PATHINFO_EXTENSION);
    $logoFilename = 'app_logo_' . time() . '.' . $extension;
    $logoAbsolutePath = $brandingFolder . $logoFilename;
    if (!move_uploaded_file($logoFile['tmp_name'], $logoAbsolutePath)) {
        respondError('Failed to save logo');
    }

    if ($logoPath) {
        $previousLogoPath = UPLOAD_DIR . ltrim(str_replace('uploads/', '', $logoPath), '/');
        if (is_file($previousLogoPath)) {
            @unlink($previousLogoPath);
        }
    }

    $logoPath = 'uploads/branding/' . $logoFilename;
}

setAppSetting('app_name', $appName);
setAppSetting('app_logo', $logoPath);
setAppSetting('smtp_host', $smtpHost);
setAppSetting('smtp_port', (string) $smtpPort);
setAppSetting('smtp_username', $smtpUsername);
setAppSetting('smtp_secure', $smtpSecure);
setAppSetting('smtp_from_email', $smtpFromEmail);
setAppSetting('smtp_from_name', $smtpFromName !== '' ? $smtpFromName : $appName);
setAppSetting('auto_backup_enabled', $autoBackupEnabled);
setAppSetting('rate_limit_per_minute', (string) $rateLimitPerMinute);
setAppSetting('log_retention_days', (string) $logRetentionDays);
setAppSetting('maintenance_window', $maintenanceWindow);
setAppSetting('enforce_admin_2fa', $enforceAdmin2fa);

if ($smtpPassword !== '') {
    setAppSetting('smtp_password', $smtpPassword);
}

logActivity($adminId, 'admin_update_settings', 'Updated app and SMTP settings');

respond([
    'success' => true,
    'message' => 'Settings updated successfully',
    'settings' => getPublicAppSettings()
]);
?>
