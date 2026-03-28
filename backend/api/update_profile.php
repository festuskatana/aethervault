<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

ensureEmailVerificationOtpTable();

$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

$fullName = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$bio = trim($_POST['bio'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$location = trim($_POST['location'] ?? '');
$timezone = trim($_POST['timezone'] ?? APP_TIMEZONE);
$language = trim($_POST['language'] ?? 'en');
$privacy = trim($_POST['privacy_level'] ?? 'contacts');
$notificationsEnabled = isset($_POST['notifications_enabled']) && $_POST['notifications_enabled'] === '1' ? 1 : 0;
$removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';

if ($username === '' || !validateUsername($username)) {
    respondError('Valid username is required');
}

if ($email === '' || !validateEmail($email)) {
    respondError('Valid email is required');
}

$conflictStmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
$conflictStmt->bind_param("ssi", $username, $email, $user_id);
$conflictStmt->execute();
if ($conflictStmt->get_result()->num_rows > 0) {
    respondError('Username or email is already in use');
}

$existingAvatar = null;
$existingEmail = null;
$existingEmailVerified = 0;
$currentAvatarStmt = $db->prepare("SELECT avatar, email, email_verified FROM users WHERE id = ?");
$currentAvatarStmt->bind_param("i", $user_id);
$currentAvatarStmt->execute();
$currentAvatarRow = $currentAvatarStmt->get_result()->fetch_assoc();
$existingAvatar = $currentAvatarRow['avatar'] ?? null;
$existingEmail = $currentAvatarRow['email'] ?? null;
$existingEmailVerified = (int) ($currentAvatarRow['email_verified'] ?? 0);

$avatarPath = null;
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $avatarFile = $_FILES['avatar'];
    $avatarMimeType = mime_content_type($avatarFile['tmp_name']) ?: $avatarFile['type'];
    $allowedAvatarTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    if (!in_array($avatarMimeType, $allowedAvatarTypes, true)) {
        respondError('Unsupported avatar type');
    }

    $avatarFolder = UPLOAD_DIR . 'avatars/';
    if (!file_exists($avatarFolder)) {
        mkdir($avatarFolder, 0755, true);
    }

    $extension = pathinfo($avatarFile['name'], PATHINFO_EXTENSION);
    $avatarFilename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
    $avatarAbsolutePath = $avatarFolder . $avatarFilename;
    if (!move_uploaded_file($avatarFile['tmp_name'], $avatarAbsolutePath)) {
        respondError('Failed to save avatar');
    }
    $avatarPath = 'uploads/avatars/' . $avatarFilename;
}

$emailChanged = $existingEmail !== null && strcasecmp($existingEmail, $email) !== 0;
$emailVerified = $emailChanged ? 0 : $existingEmailVerified;

if ($removeAvatar) {
    if (dbDriver() === 'pgsql') {
        $userStmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, bio = ?, phone = ?, location = ?, avatar = NULL, email_verified = " . ($emailVerified ? 'TRUE' : 'FALSE') . " WHERE id = ?");
        $userStmt->bind_param("ssssssi", $username, $email, $fullName, $bio, $phone, $location, $user_id);
    } else {
        $userStmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, bio = ?, phone = ?, location = ?, avatar = NULL, email_verified = ? WHERE id = ?");
        $userStmt->bind_param("ssssssii", $username, $email, $fullName, $bio, $phone, $location, $emailVerified, $user_id);
    }
} elseif ($avatarPath) {
    if (dbDriver() === 'pgsql') {
        $userStmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, bio = ?, phone = ?, location = ?, avatar = ?, email_verified = " . ($emailVerified ? 'TRUE' : 'FALSE') . " WHERE id = ?");
        $userStmt->bind_param("sssssssi", $username, $email, $fullName, $bio, $phone, $location, $avatarPath, $user_id);
    } else {
        $userStmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, bio = ?, phone = ?, location = ?, avatar = ?, email_verified = ? WHERE id = ?");
        $userStmt->bind_param("sssssssii", $username, $email, $fullName, $bio, $phone, $location, $avatarPath, $emailVerified, $user_id);
    }
} else {
    if (dbDriver() === 'pgsql') {
        $userStmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, bio = ?, phone = ?, location = ?, email_verified = " . ($emailVerified ? 'TRUE' : 'FALSE') . " WHERE id = ?");
        $userStmt->bind_param("ssssssi", $username, $email, $fullName, $bio, $phone, $location, $user_id);
    } else {
        $userStmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, bio = ?, phone = ?, location = ?, email_verified = ? WHERE id = ?");
        $userStmt->bind_param("ssssssii", $username, $email, $fullName, $bio, $phone, $location, $emailVerified, $user_id);
    }
}
$userStmt->execute();

if ($emailChanged) {
    $invalidateOtpStmt = $db->prepare("UPDATE email_verification_otps SET is_used = TRUE WHERE user_id = ? AND is_used = FALSE");
    if ($invalidateOtpStmt) {
        $invalidateOtpStmt->bind_param("i", $user_id);
        $invalidateOtpStmt->execute();
    }
}

if (($removeAvatar || $avatarPath) && $existingAvatar) {
    $existingAvatarAbsolutePath = UPLOAD_DIR . ltrim(str_replace('uploads/', '', $existingAvatar), '/');
    if (is_file($existingAvatarAbsolutePath)) {
        @unlink($existingAvatarAbsolutePath);
    }
}

$settingsSql = dbDriver() === 'pgsql'
    ? "
        INSERT INTO user_settings (user_id, timezone, language, privacy_level, notifications_enabled)
        VALUES (?, ?, ?, ?, " . ($notificationsEnabled ? 'TRUE' : 'FALSE') . ")
        ON CONFLICT (user_id) DO UPDATE
        SET timezone = EXCLUDED.timezone,
            language = EXCLUDED.language,
            privacy_level = EXCLUDED.privacy_level,
            notifications_enabled = EXCLUDED.notifications_enabled
    "
    : "
        INSERT INTO user_settings (user_id, timezone, language, privacy_level, notifications_enabled)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE timezone = VALUES(timezone), language = VALUES(language),
            privacy_level = VALUES(privacy_level), notifications_enabled = VALUES(notifications_enabled)
    ";
$settingsStmt = $db->prepare($settingsSql);
if (dbDriver() === 'pgsql') {
    $settingsStmt->bind_param("isss", $user_id, $timezone, $language, $privacy);
} else {
    $settingsStmt->bind_param("isssi", $user_id, $timezone, $language, $privacy, $notificationsEnabled);
}
$settingsStmt->execute();

respond([
    'success' => true,
    'message' => 'Profile updated successfully',
    'email_verified' => (bool) $emailVerified
]);
?>
