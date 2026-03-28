<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$adminId = requireAdminUser();
$publicSettings = getPublicAppSettings();

$settings = [
    'app_name' => getAppSetting('app_name', 'Aether Vault'),
    'app_logo' => getAppSetting('app_logo', ''),
    'app_logo_url' => $publicSettings['logo'],
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
];

$metricsSql = dbDriver() === 'pgsql'
    ? "
        SELECT
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM users WHERE is_admin = TRUE) AS admin_users,
            (SELECT COUNT(*) FROM users WHERE email_verified = TRUE) AS verified_users,
            (SELECT COUNT(*) FROM users WHERE last_active >= NOW() - INTERVAL '5 minutes') AS online_users,
            (SELECT COUNT(*) FROM sessions WHERE expires_at > NOW() AND last_activity >= NOW() - INTERVAL '30 minutes') AS active_sessions,
            (SELECT COUNT(*) FROM activity_logs WHERE created_at >= NOW() - INTERVAL '24 hours') AS activity_logs_24h,
            (SELECT COALESCE(SUM(file_size), 0) FROM media) AS storage_used_bytes,
            (SELECT COUNT(*) FROM messages WHERE is_deleted = FALSE) AS total_messages,
            (SELECT COUNT(*) FROM media) AS total_media
    "
    : "
        SELECT
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM users WHERE is_admin = 1) AS admin_users,
            (SELECT COUNT(*) FROM users WHERE email_verified = 1) AS verified_users,
            (SELECT COUNT(*) FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS online_users,
            (SELECT COUNT(*) FROM sessions WHERE expires_at > NOW() AND last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)) AS active_sessions,
            (SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS activity_logs_24h,
            (SELECT COALESCE(SUM(file_size), 0) FROM media) AS storage_used_bytes,
            (SELECT COUNT(*) FROM messages WHERE is_deleted = 0) AS total_messages,
            (SELECT COUNT(*) FROM media) AS total_media
    ";
$metricsQuery = $db->query($metricsSql);
$metrics = $metricsQuery ? ($metricsQuery->fetch_assoc() ?: []) : [];

$users = [];
$usersStmt = $db->prepare("
    SELECT id, username, email, full_name, is_admin, is_active, email_verified, last_active, created_at
    FROM users
    ORDER BY is_admin DESC, created_at DESC
    LIMIT 12
");
if ($usersStmt) {
    $usersStmt->execute();
    $result = $usersStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (int) $row['id'],
            'name' => $row['full_name'] ?: $row['username'],
            'email' => $row['email'],
            'role' => (bool) $row['is_admin'] ? 'Admin' : 'Member',
            'status' => (bool) $row['is_active'] ? 'active' : 'inactive',
            'email_verified' => (bool) $row['email_verified'],
            'last_active' => $row['last_active'],
            'last_active_human' => $row['last_active'] ? timeAgo($row['last_active']) : 'Never',
            'created_at' => $row['created_at']
        ];
    }
}

$logs = [];
$logsStmt = $db->prepare("
    SELECT l.action, l.details, l.created_at, u.email, u.username
    FROM activity_logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC
    LIMIT 12
");
if ($logsStmt) {
    $logsStmt->execute();
    $result = $logsStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'action' => $row['action'],
            'details' => $row['details'],
            'actor' => $row['email'] ?: $row['username'] ?: 'System',
            'created_at' => $row['created_at'],
            'time_ago' => timeAgo($row['created_at'])
        ];
    }
}

respond([
    'success' => true,
    'admin' => getCurrentUserRecord($adminId),
    'settings' => $settings,
    'metrics' => [
        'total_users' => (int) ($metrics['total_users'] ?? 0),
        'admin_users' => (int) ($metrics['admin_users'] ?? 0),
        'verified_users' => (int) ($metrics['verified_users'] ?? 0),
        'online_users' => (int) ($metrics['online_users'] ?? 0),
        'active_sessions' => (int) ($metrics['active_sessions'] ?? 0),
        'activity_logs_24h' => (int) ($metrics['activity_logs_24h'] ?? 0),
        'storage_used_bytes' => (int) ($metrics['storage_used_bytes'] ?? 0),
        'total_messages' => (int) ($metrics['total_messages'] ?? 0),
        'total_media' => (int) ($metrics['total_media'] ?? 0)
    ],
    'users' => $users,
    'logs' => $logs
]);
?>
