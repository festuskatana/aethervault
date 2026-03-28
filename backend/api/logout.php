<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

if ($token = getBearerToken()) {
    $stmt = $db->prepare("DELETE FROM sessions WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
}

respond([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>
