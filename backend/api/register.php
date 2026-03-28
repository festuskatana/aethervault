<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['username']) || !isset($input['email']) || !isset($input['password'])) {
    respondError('Username, email, and password are required');
}

$username = sanitize($input['username']);
$email = sanitize($input['email']);
$password = $input['password'];

// Validate username
if (!validateUsername($username)) {
    respondError('Username must be 3-20 characters and contain only letters, numbers, and underscores');
}

// Validate email
if (!validateEmail($email)) {
    respondError('Invalid email address');
}

// Validate password
if (!validatePassword($password)) {
    respondError('Password must be at least 6 characters');
}

// Check if username already exists
$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    respondError('Username already taken');
}

// Check if email already exists
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    respondError('Email already registered');
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Insert user
$stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $email, $hashedPassword);

if ($stmt->execute()) {
    $userId = $db->insert_id;

    $adminCheck = $db->query(dbDriver() === 'pgsql'
        ? "SELECT COUNT(*) AS total_admins FROM users WHERE is_admin = TRUE"
        : "SELECT COUNT(*) AS total_admins FROM users WHERE is_admin = 1");
    $adminCount = $adminCheck ? (int) ($adminCheck->fetch_assoc()['total_admins'] ?? 0) : 0;
    $isAdmin = false;
    if ($adminCount === 0) {
        $isAdmin = true;
        $promoteStmt = $db->prepare(dbDriver() === 'pgsql'
            ? "UPDATE users SET is_admin = TRUE WHERE id = ?"
            : "UPDATE users SET is_admin = 1 WHERE id = ?");
        $promoteStmt->bind_param("i", $userId);
        $promoteStmt->execute();
    }
    
    // Generate token for auto-login
    $token = generateToken($userId);
    
    // Log registration
    logActivity($userId, 'register', 'User registered successfully');
    
    respond([
        'success' => true,
        'message' => 'Registration successful',
        'token' => $token,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'is_admin' => $isAdmin,
            'email_verified' => false
        ]
    ]);
} else {
    respondError('Registration failed: ' . $db->error);
}
?>
