<?php
// Simple test script for API endpoints
// Run from command line: php test_api.php

function loadTestEnv($path) {
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
        $_ENV[trim($name)] = trim($value, "\"' ");
    }
}

$rootDir = dirname(__DIR__);
loadTestEnv($rootDir . '/.env');

$appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/mp', '/');
$baseUrl = $appUrl . '/backend/api/';

echo "=== Testing Aether Vault API ===\n\n";

// Test Registration
echo "1. Testing Registration...\n";
$testUser = [
    'username' => 'testuser_' . time(),
    'email' => 'test_' . time() . '@example.com',
    'password' => 'Test123!'
];

$response = makeRequest($baseUrl . 'register.php', 'POST', $testUser);
print_r($response);

if (isset($response['success']) && $response['success']) {
    $token = $response['token'];
    echo "✓ Registration successful\n";
    echo "Token: " . substr($token, 0, 20) . "...\n\n";
    
    // Test Login
    echo "2. Testing Login...\n";
    $loginResponse = makeRequest($baseUrl . 'login.php', 'POST', [
        'username' => $testUser['username'],
        'password' => $testUser['password']
    ]);
    print_r($loginResponse);
    
    if (isset($loginResponse['success']) && $loginResponse['success']) {
        $token = $loginResponse['token'];
        echo "✓ Login successful\n\n";
        
        // Test Search Users
        echo "3. Testing Search Users...\n";
        $searchResponse = makeRequest($baseUrl . 'search_users.php?q=test', 'GET', null, $token);
        print_r($searchResponse);
        echo "✓ Search completed\n\n";
        
        // Test Get Messages
        echo "4. Testing Get Messages...\n";
        $messagesResponse = makeRequest($baseUrl . 'fetch_messages.php?user_id=1&limit=10', 'GET', null, $token);
        print_r($messagesResponse);
        echo "✓ Messages fetched\n\n";
    }
}

function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "HTTP Error: $httpCode\n";
        return ['error' => "HTTP $httpCode", 'response' => $response];
    }
    
    return json_decode($response, true);
}
?>
