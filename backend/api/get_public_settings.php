<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

respond([
    'success' => true,
    'settings' => getPublicAppSettings()
]);
?>
