<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$adminId = requireAdminUser();
$settings = getPublicAppSettings();

respond([
    'success' => true,
    'settings' => [
        'app_name' => $settings['app_name'],
        'app_logo' => getAppSetting('app_logo', ''),
        'app_logo_url' => $settings['logo'],
        'smtp_host' => getAppSetting('smtp_host', ''),
        'smtp_port' => (int) getAppSetting('smtp_port', '587'),
        'smtp_username' => getAppSetting('smtp_username', ''),
        'smtp_secure' => getAppSetting('smtp_secure', 'tls'),
        'smtp_from_email' => getAppSetting('smtp_from_email', ''),
        'smtp_from_name' => getAppSetting('smtp_from_name', getAppSetting('app_name', 'Aether Vault')),
        'smtp_has_password' => getAppSetting('smtp_password', '') !== '',
        'auto_backup_enabled' => getAppSetting('auto_backup_enabled', '1') === '1',
        'last_backup_at' => getAppSetting('last_backup_at', ''),
        'rate_limit_per_minute' => (int) getAppSetting('rate_limit_per_minute', '120'),
        'log_retention_days' => (int) getAppSetting('log_retention_days', '60'),
        'maintenance_window' => getAppSetting('maintenance_window', 'Sunday 02:00-04:00 UTC'),
        'enforce_admin_2fa' => getAppSetting('enforce_admin_2fa', '0') === '1'
    ],
    'admin' => getCurrentUserRecord($adminId)
]);
?>
