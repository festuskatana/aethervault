<?php
$composerAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
$phpMailerFallbackDir = __DIR__ . '/../lib/PHPMailer/src/';

if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    require_once $phpMailerFallbackDir . 'Exception.php';
    require_once $phpMailerFallbackDir . 'PHPMailer.php';
    require_once $phpMailerFallbackDir . 'SMTP.php';
}

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

function loadEnvFile($path) {
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $trimmed, 2);
        $name = trim($name);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function loadEnvironmentConfig() {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $rootDir = dirname(__DIR__, 2);
    loadEnvFile($rootDir . '/.env');

    $loaded = true;
}

function envValue($key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

function normalizeAppUrl($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    return rtrim($url, '/');
}

function isLoopbackHost($host) {
    $host = strtolower((string) $host);
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function requestUsesHttps() {
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $requestScheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
    $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
    $serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 80);

    return $https === 'on'
        || $https === '1'
        || $forwardedProto === 'https'
        || $requestScheme === 'https'
        || $forwardedSsl === 'on'
        || $serverPort === 443;
}

function getRequestHeadersNormalized() {
    static $headers = null;

    if ($headers !== null) {
        return $headers;
    }

    $headers = [];

    if (function_exists('getallheaders')) {
        foreach ((array) getallheaders() as $name => $value) {
            $headers[strtolower((string) $name)] = $value;
        }
    }

    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }
    }

    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
    }

    if (isset($_SERVER['CONTENT_LENGTH'])) {
        $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
    }

    return $headers;
}

function getBearerToken() {
    $headers = getRequestHeadersNormalized();
    $authHeader = trim((string) ($headers['authorization'] ?? ''));

    if ($authHeader === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        return '';
    }

    return $matches[1];
}

loadEnvironmentConfig();

$appEnv = strtolower((string) envValue('APP_ENV', 'local'));
$isDebug = in_array($appEnv, ['local', 'development', 'dev'], true);
$appTimezone = (string) envValue('APP_TIMEZONE', 'Africa/Nairobi');

date_default_timezone_set($appTimezone);

// Error reporting for development (disable in production)
error_reporting($isDebug ? E_ALL : E_ERROR | E_PARSE);
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

// Application configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', ((int) envValue('MAX_FILE_SIZE_MB', '20')) * 1024 * 1024);
define('APP_URL', normalizeAppUrl((string) envValue('APP_URL', '')));
define('APP_ENV', $appEnv);
define('APP_TIMEZONE', $appTimezone);

require_once __DIR__ . '/Database.php';

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Database connection
$db = null;
try {
    $db = createDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Helper function to send JSON response
function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function getBaseUrl() {
    $scheme = requestUsesHttps() ? 'https' : 'http';
    $requestHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $projectPath = preg_replace('#/backend/.*$#', '', str_replace('\\', '/', dirname($scriptName)));
    $projectPath = rtrim($projectPath, '/');

    if (APP_URL !== '') {
        $configuredHost = parse_url(APP_URL, PHP_URL_HOST);
        $requestHostName = preg_replace('/:\d+$/', '', strtolower((string) $requestHost));

        if (!$configuredHost || !isLoopbackHost($configuredHost) || isLoopbackHost($requestHostName)) {
            return rtrim(APP_URL, '/');
        }
    }

    return $scheme . '://' . $requestHost . $projectPath;
}

function buildAppUrl($relativePath = '') {
    $base = getBaseUrl();
    if ($relativePath === '') {
        return $base;
    }
    return rtrim($base, '/') . '/' . ltrim($relativePath, '/');
}

function getAppSetting($key, $default = null) {
    global $db;

    $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['setting_value'] ?? $default;
}

function dbDriver() {
    global $db;
    return method_exists($db, 'getDriver') ? $db->getDriver() : (defined('DB_DRIVER') ? DB_DRIVER : 'mysql');
}

function setAppSetting($key, $value) {
    global $db;

    $sql = dbDriver() === 'pgsql'
        ? "
            INSERT INTO app_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON CONFLICT (setting_key) DO UPDATE
            SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP
        "
        : "
            INSERT INTO app_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
        ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
}

function getCurrentUserRecord($userId) {
    global $db;

    $stmt = $db->prepare("SELECT id, username, email, is_admin FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function isAdminUser($userId) {
    $user = getCurrentUserRecord($userId);
    return (bool) ($user['is_admin'] ?? false);
}

function requireAdminUser() {
    $userId = validateToken();
    if (!$userId) {
        respondError('Unauthorized', 401);
    }

    if (!isAdminUser($userId)) {
        respondError('Forbidden', 403);
    }

    return $userId;
}

function getPublicAppSettings() {
    $appName = getAppSetting('app_name', 'Aether Vault');
    $logoPath = getAppSetting('app_logo', '');

    return [
        'app_name' => $appName ?: 'Aether Vault',
        'logo' => $logoPath ? buildAppUrl('backend/' . ltrim($logoPath, '/')) : null
    ];
}

function sendOtpEmail($toEmail, $otpCode, $appName = null) {
    $host = getAppSetting('smtp_host', '');
    $port = (int) getAppSetting('smtp_port', '587');
    $username = getAppSetting('smtp_username', '');
    $password = getAppSetting('smtp_password', '');
    $secure = getAppSetting('smtp_secure', 'tls');
    $fromEmail = getAppSetting('smtp_from_email', $username);
    $fromName = getAppSetting('smtp_from_name', $appName ?: getAppSetting('app_name', 'Aether Vault'));

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
        throw new Exception('SMTP settings are incomplete');
    }

    $appName = $appName ?: getAppSetting('app_name', 'Aether Vault');
    $subject = $appName . ' password reset OTP';
    $bodyHtml = '<html><body style="font-family: Arial, sans-serif; background:#f5f7f6; padding:24px;">'
        . '<div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:18px; padding:32px; border:1px solid #e6ece9;">'
        . '<h2 style="margin:0 0 12px; color:#0A4D4C;">Password reset request</h2>'
        . '<p style="margin:0 0 18px; color:#40514d;">Use the OTP below to reset your password.</p>'
        . '<div style="font-size:32px; letter-spacing:10px; font-weight:700; color:#1A1A1A; margin:18px 0 22px;">' . htmlspecialchars($otpCode) . '</div>'
        . '<p style="margin:0; color:#66736f;">This code expires in 10 minutes. If you did not request this, you can ignore this email.</p>'
        . '</div></body></html>';
    $bodyText = "Password reset request\n\nYour OTP is: {$otpCode}\n\nThis code expires in 10 minutes.";

    sendMailMessage($toEmail, $subject, $bodyHtml, $bodyText, $fromEmail, $fromName, $host, $port, $username, $password, $secure);
}

function sendVerificationOtpEmail($toEmail, $otpCode, $appName = null) {
    $host = getAppSetting('smtp_host', '');
    $port = (int) getAppSetting('smtp_port', '587');
    $username = getAppSetting('smtp_username', '');
    $password = getAppSetting('smtp_password', '');
    $secure = getAppSetting('smtp_secure', 'tls');
    $fromEmail = getAppSetting('smtp_from_email', $username);
    $fromName = getAppSetting('smtp_from_name', $appName ?: getAppSetting('app_name', 'Aether Vault'));

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
        throw new Exception('SMTP settings are incomplete');
    }

    $appName = $appName ?: getAppSetting('app_name', 'Aether Vault');
    $subject = $appName . ' email verification OTP';
    $bodyHtml = '<html><body style="font-family: Arial, sans-serif; background:#f5f7f6; padding:24px;">'
        . '<div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:18px; padding:32px; border:1px solid #e6ece9;">'
        . '<h2 style="margin:0 0 12px; color:#0A4D4C;">Verify your email</h2>'
        . '<p style="margin:0 0 18px; color:#40514d;">Use the OTP below to verify your account email address.</p>'
        . '<div style="font-size:32px; letter-spacing:10px; font-weight:700; color:#1A1A1A; margin:18px 0 22px;">' . htmlspecialchars($otpCode) . '</div>'
        . '<p style="margin:0; color:#66736f;">This code expires in 10 minutes. If you did not request this, you can ignore this email.</p>'
        . '</div></body></html>';
    $bodyText = "Verify your email\n\nYour OTP is: {$otpCode}\n\nThis code expires in 10 minutes.";

    sendMailMessage($toEmail, $subject, $bodyHtml, $bodyText, $fromEmail, $fromName, $host, $port, $username, $password, $secure);
}

function sendMailMessage($toEmail, $subject, $bodyHtml, $bodyText, $fromEmail, $fromName, $host, $port, $username, $password, $secure) {
    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
        throw new Exception('SMTP settings are incomplete');
    }

    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = $port;
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->CharSet = 'UTF-8';
        $mailer->isHTML(true);

        if (strtolower((string) $secure) === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (strtolower((string) $secure) === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mailer->SMTPSecure = false;
            $mailer->SMTPAutoTLS = false;
        }

        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($toEmail);
        $mailer->Subject = $subject;
        $mailer->Body = $bodyHtml;
        $mailer->AltBody = $bodyText;
        $mailer->send();
    } catch (PHPMailerException $exception) {
        $errorMessage = trim((string) $exception->getMessage());
        $mailerErrorInfo = isset($mailer) ? trim((string) $mailer->ErrorInfo) : '';
        $parts = array_filter([$errorMessage, $mailerErrorInfo]);
        throw new Exception('Failed to send OTP email: ' . implode(' | ', array_unique($parts)));
    }
}

// Helper function to send error response
function respondError($message, $statusCode = 400) {
    respond(['error' => $message], $statusCode);
}

// Validate JWT token
function validateToken() {
    global $db;

    $token = getBearerToken();
    if ($token === '') {
        return false;
    }

    $stmt = $db->prepare("SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $userId = (int) $row['user_id'];
        touchUserPresence($token, $userId);
        return $userId;
    }
    
    return false;
}

// Generate new session token
function generateToken($userId) {
    global $db;
    
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $db->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $token, $expiresAt);
    $stmt->execute();
    
    return $token;
}

// Sanitize input
function sanitize($input) {
    global $db;
    return $db->real_escape_string(htmlspecialchars(trim($input)));
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Get time ago string
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return date('M j', $time);
    return date('M j, Y', $time);
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate username
function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

// Validate password strength
function validatePassword($password) {
    return strlen($password) >= 6;
}

// Log activity
function logActivity($userId, $action, $details = null) {
    global $db;
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bind_param("isss", $userId, $action, $details, $ip);
    $stmt->execute();
}

function touchUserPresence($token, $userId) {
    global $db;

    $sessionStmt = $db->prepare("UPDATE sessions SET last_activity = NOW() WHERE token = ?");
    $sessionStmt->bind_param("s", $token);
    $sessionStmt->execute();

    $userStmt = $db->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
}

function isUserOnlineFromTimestamp($timestamp) {
    if (!$timestamp) {
        return false;
    }

    return (time() - strtotime($timestamp)) <= 120;
}

function ensureSchema() {
    global $db;
    if (dbDriver() === 'pgsql') {
        ensurePostgresSchema();
    } else {
        ensureMysqlSchema();
    }

    $defaultSettings = [
        'app_name' => 'Aether Vault',
        'app_logo' => '',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_secure' => 'tls',
        'smtp_from_email' => '',
        'smtp_from_name' => 'Aether Vault',
        'auto_backup_enabled' => '1',
        'last_backup_at' => '',
        'rate_limit_per_minute' => '120',
        'log_retention_days' => '60',
        'maintenance_window' => 'Sunday 02:00-04:00 UTC',
        'enforce_admin_2fa' => '0'
    ];

    foreach ($defaultSettings as $settingKey => $settingValue) {
        $existing = getAppSetting($settingKey, null);
        if ($existing === null) {
            setAppSetting($settingKey, $settingValue);
        }
    }
}

ensureSchema();

function ensureEmailVerificationOtpTable() {
    global $db;

    if (dbDriver() === 'pgsql') {
        $db->query("
            CREATE TABLE IF NOT EXISTS email_verification_otps (
                id BIGSERIAL PRIMARY KEY,
                user_id BIGINT NOT NULL,
                email VARCHAR(120) NOT NULL,
                otp_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                is_used BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->query("CREATE INDEX IF NOT EXISTS idx_email_verification_otps_user ON email_verification_otps (user_id)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_email_verification_otps_email ON email_verification_otps (email)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_email_verification_otps_expires ON email_verification_otps (expires_at)");
        return;
    }

    $db->query("
        CREATE TABLE IF NOT EXISTS email_verification_otps (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            email VARCHAR(120) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            is_used TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_email (email),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensureMysqlSchema() {
    global $db;

    $columnChecks = [
        ['users', 'email_verified', "ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active"],
        ['users', 'is_admin', "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER avatar"],
        ['users', 'full_name', "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NULL AFTER email"],
        ['users', 'phone', "ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER bio"],
        ['users', 'location', "ALTER TABLE users ADD COLUMN location VARCHAR(120) NULL AFTER phone"],
        ['users', 'last_active', "ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL AFTER last_login"],
        ['messages', 'attachment_path', "ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(500) NULL AFTER message"],
        ['messages', 'attachment_type', "ALTER TABLE messages ADD COLUMN attachment_type ENUM('image','video') NULL AFTER attachment_path"],
        ['messages', 'attachment_name', "ALTER TABLE messages ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_type"],
        ['messages', 'edited_at', "ALTER TABLE messages ADD COLUMN edited_at TIMESTAMP NULL AFTER created_at"],
        ['messages', 'read_at', "ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL AFTER is_read"],
        ['messages', 'is_delivered', "ALTER TABLE messages ADD COLUMN is_delivered TINYINT(1) NOT NULL DEFAULT 0 AFTER read_at"],
        ['messages', 'delivered_at', "ALTER TABLE messages ADD COLUMN delivered_at TIMESTAMP NULL AFTER is_delivered"],
        ['sessions', 'last_activity', "ALTER TABLE sessions ADD COLUMN last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER expires_at"]
    ];

    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if ($stmt) {
        $schemaName = DB_NAME;
        foreach ($columnChecks as [$table, $column, $alterSql]) {
            $tableName = $table;
            $columnName = $column;
            $stmt->bind_param("sss", $schemaName, $tableName, $columnName);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ((int) ($result['count'] ?? 0) === 0) {
                $db->query($alterSql);
            }
        }
    }

    $db->query("
        CREATE TABLE IF NOT EXISTS app_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(120) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS password_reset_otps (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            email VARCHAR(120) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            is_used TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_email (email),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    ensureEmailVerificationOtpTable();

    $adminCheck = $db->query("SELECT COUNT(*) AS total_admins FROM users WHERE is_admin = 1");
    $adminCount = $adminCheck ? (int) ($adminCheck->fetch_assoc()['total_admins'] ?? 0) : 0;
    if ($adminCount === 0) {
        $db->query("UPDATE users SET is_admin = 1 WHERE id = (SELECT id FROM users ORDER BY id ASC LIMIT 1)");
    }
}

function ensurePostgresSchema() {
    global $db;

    $db->query("
        CREATE TABLE IF NOT EXISTS users (
            id BIGSERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            avatar VARCHAR(500) NULL,
            is_admin BOOLEAN NOT NULL DEFAULT FALSE,
            bio TEXT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            email_verified BOOLEAN NOT NULL DEFAULT FALSE,
            verification_token VARCHAR(255) NULL,
            reset_token VARCHAR(255) NULL,
            reset_token_expires TIMESTAMP NULL,
            last_login TIMESTAMP NULL,
            last_active TIMESTAMP NULL,
            full_name VARCHAR(100) NULL,
            phone VARCHAR(30) NULL,
            location VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS sessions (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL,
            token VARCHAR(255) UNIQUE NOT NULL,
            device_info TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS app_settings (
            id BIGSERIAL PRIMARY KEY,
            setting_key VARCHAR(120) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS password_reset_otps (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL,
            email VARCHAR(120) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            is_used BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS media (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(20) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT NOT NULL,
            width INT NULL,
            height INT NULL,
            duration INT NULL,
            thumbnail_path VARCHAR(500) NULL,
            is_public BOOLEAN NOT NULL DEFAULT FALSE,
            description TEXT NULL,
            views INT NOT NULL DEFAULT 0,
            downloads INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS messages (
            id BIGSERIAL PRIMARY KEY,
            sender_id BIGINT NOT NULL,
            receiver_id BIGINT NOT NULL,
            message TEXT NOT NULL,
            attachment_path VARCHAR(500) NULL,
            attachment_type VARCHAR(20) NULL,
            attachment_name VARCHAR(255) NULL,
            is_read BOOLEAN NOT NULL DEFAULT FALSE,
            read_at TIMESTAMP NULL,
            is_delivered BOOLEAN NOT NULL DEFAULT FALSE,
            delivered_at TIMESTAMP NULL,
            is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
            deleted_by BIGINT NULL,
            edited_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS user_settings (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL UNIQUE,
            theme VARCHAR(20) DEFAULT 'light',
            notifications_enabled BOOLEAN DEFAULT TRUE,
            email_notifications BOOLEAN DEFAULT TRUE,
            two_factor_enabled BOOLEAN DEFAULT FALSE,
            two_factor_secret VARCHAR(255) NULL,
            language VARCHAR(10) DEFAULT 'en',
            timezone VARCHAR(50) DEFAULT 'Africa/Nairobi',
            privacy_level VARCHAR(20) DEFAULT 'contacts',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    ensureEmailVerificationOtpTable();

    $db->query("CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions (token)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions (user_id)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_messages_receiver_read ON messages (receiver_id, is_read)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages (sender_id, receiver_id, created_at)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_media_user ON media (user_id)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_activity_logs_user ON activity_logs (user_id)");

    $adminCheck = $db->query("SELECT COUNT(*) AS total_admins FROM users WHERE is_admin = TRUE");
    $adminCount = $adminCheck ? (int) ($adminCheck->fetch_assoc()['total_admins'] ?? 0) : 0;
    if ($adminCount === 0) {
        $db->query("UPDATE users SET is_admin = TRUE WHERE id = (SELECT id FROM users ORDER BY id ASC LIMIT 1)");
    }
}
?>
