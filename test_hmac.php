<?php
require_once __DIR__ . '/config.php';

// Test data
$testPayload = [
    'date' => '2025-08-15',
    'user' => 'test@example.com',
    'project' => 'Test Project',
    'task' => 'Test Task',
    'duration_h' => 2.5,
    'notes' => 'Test notes',
    'idempotency_key' => 'test-key-123'
];

$jsonPayload = json_encode($testPayload, JSON_UNESCAPED_UNICODE);
$signature = hash_hmac('sha256', $jsonPayload, $_ENV['SIGNING_SECRET']);

echo "🔐 HMAC Signature Test\n\n";
echo "Payload:\n";
echo $jsonPayload . "\n\n";
echo "Secret: " . $_ENV['SIGNING_SECRET'] . "\n\n";
echo "Signature: " . $signature . "\n\n";

echo "📋 cURL Commands:\n\n";

// Test append endpoint
echo "# Test /worklog/append\n";
echo "curl -X POST \"http://localhost/worklog-php/public/worklog/append\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -H \"X-Signature: {$signature}\" \\\n";
echo "  -d '" . $jsonPayload . "'\n\n";

// Test update endpoint
$updatePayload = [
    'user' => 'test@example.com',
    'idempotency_key' => 'test-key-123',
    'notes' => 'Updated test notes'
];

$updateJson = json_encode($updatePayload, JSON_UNESCAPED_UNICODE);
$updateSignature = hash_hmac('sha256', $updateJson, $_ENV['SIGNING_SECRET']);

echo "# Test /worklog/update\n";
echo "curl -X POST \"http://localhost/worklog-php/public/worklog/update\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -H \"X-Signature: {$updateSignature}\" \\\n";
echo "  -d '" . $updateJson . "'\n";
