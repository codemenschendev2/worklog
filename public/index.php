<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Worklog\WorklogService;

// CORS headers
if (!empty($_ENV['ALLOW_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_ENV['ALLOW_ORIGIN']);
}
header('Access-Control-Allow-Headers: Content-Type, X-Signature');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Read raw body
    $rawBody = file_get_contents('php://input');
    if (empty($rawBody)) {
        throw new Exception('Request body is empty');
    }

    // Verify HMAC signature
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    if (empty($signature)) {
        throw new Exception('X-Signature header is required');
    }

    $expectedSignature = hash_hmac('sha256', $rawBody, $_ENV['SIGNING_SECRET']);
    if (!hash_equals($expectedSignature, $signature)) {
        http_response_code(401);
        echo json_encode(['status' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Parse JSON payload
    $input = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload');
    }

    // Route requests
    $path = $_SERVER['REQUEST_URI'];
    $worklogService = new WorklogService();

    if (strpos($path, '/worklog/append') !== false) {
        $result = $worklogService->append($input, true);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } elseif (strpos($path, '/worklog/update') !== false) {
        $result = $worklogService->update($input);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'not_found'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
