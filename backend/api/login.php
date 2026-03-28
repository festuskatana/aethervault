<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username']) || !isset($data['password'])) {
    respondError('Username and password required');
}

$username = $data['username'];
$password = $data['password'];

// Check if username is email or username
$stmt = $db->prepare("SELECT id, username, email, password_hash, is_admin, email_verified FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row['password_hash'])) {
        // Generate token
        $token = generateToken($row['id']);
        
        respond([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $row['id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'is_admin' => (bool) $row['is_admin'],
                'email_verified' => (bool) $row['email_verified']
            ]
        ]);
    }
}

respondError('Invalid credentials', 401);
?>
