<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

$requested_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $user_id;
if ($requested_user_id <= 0) {
    respondError('Invalid user ID');
}

$isOwnProfile = $requested_user_id === $user_id;
$currentToken = getBearerToken();

$stmt = $db->prepare("
    SELECT u.id, u.username, u.email, u.full_name, u.bio, u.phone, u.location, u.avatar, u.is_admin, u.email_verified,
           u.created_at, u.last_active,
           s.timezone, s.language, s.notifications_enabled, s.privacy_level, s.two_factor_enabled
    FROM users u
    LEFT JOIN user_settings s ON s.user_id = u.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $requested_user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    respondError('User not found', 404);
}

$sessions = [];
if ($isOwnProfile) {
    $sessionStmt = $db->prepare("
        SELECT token, device_info, ip_address, user_agent, created_at, last_activity, expires_at
        FROM sessions
        WHERE user_id = ? AND expires_at > NOW()
        ORDER BY last_activity DESC, created_at DESC
        LIMIT 5
    ");
    if ($sessionStmt) {
        $sessionStmt->bind_param("i", $requested_user_id);
        $sessionStmt->execute();
        $sessionResult = $sessionStmt->get_result();
        while ($sessionRow = $sessionResult->fetch_assoc()) {
            $label = trim((string) ($sessionRow['device_info'] ?: $sessionRow['user_agent'] ?: 'Active session'));
            $sessions[] = [
                'label' => $label,
                'ip_address' => $sessionRow['ip_address'] ?: 'Unknown IP',
                'created_at' => $sessionRow['created_at'],
                'created_at_human' => timeAgo($sessionRow['created_at']),
                'last_activity' => $sessionRow['last_activity'],
                'last_activity_human' => $sessionRow['last_activity'] ? timeAgo($sessionRow['last_activity']) : 'Unknown',
                'is_current' => $currentToken !== '' && hash_equals($currentToken, $sessionRow['token'])
            ];
        }
    }
}

$activities = [];
$activityStmt = $db->prepare("
    SELECT action, details, created_at
    FROM activity_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 8
");
if ($activityStmt) {
    $activityStmt->bind_param("i", $requested_user_id);
    $activityStmt->execute();
    $activityResult = $activityStmt->get_result();
    while ($activityRow = $activityResult->fetch_assoc()) {
        $activities[] = [
            'action' => $activityRow['action'],
            'details' => $activityRow['details'],
            'created_at' => $activityRow['created_at'],
            'created_at_human' => timeAgo($activityRow['created_at'])
        ];
    }
}

respond([
    'success' => true,
    'profile' => [
        'id' => (int) $result['id'],
        'username' => $result['username'],
        'email' => $result['email'],
        'full_name' => $result['full_name'],
        'bio' => $result['bio'],
        'phone' => $result['phone'],
        'location' => $result['location'],
        'avatar' => $result['avatar'] ? buildAppUrl('backend/' . ltrim($result['avatar'], '/')) : null,
        'is_admin' => (bool) $result['is_admin'],
        'email_verified' => (bool) $result['email_verified'],
        'timezone' => $result['timezone'] ?? APP_TIMEZONE,
        'language' => $result['language'] ?? 'en',
        'notifications_enabled' => (bool) ($result['notifications_enabled'] ?? true),
        'privacy_level' => $result['privacy_level'] ?? 'contacts',
        'two_factor_enabled' => (bool) ($result['two_factor_enabled'] ?? false),
        'created_at' => $result['created_at'],
        'created_at_human' => $result['created_at'] ? timeAgo($result['created_at']) : null,
        'last_active' => $result['last_active'],
        'last_active_human' => $result['last_active'] ? timeAgo($result['last_active']) : null,
        'is_own_profile' => $isOwnProfile
    ],
    'sessions' => $sessions,
    'activity' => $activities,
    'viewer' => [
        'id' => $user_id,
        'is_owner' => $isOwnProfile
    ]
]);
?>
