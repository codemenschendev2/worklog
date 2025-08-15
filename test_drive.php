<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Worklog\DriveHelper;

try {
    echo "🚀 Testing Google Drive connection...\n\n";

    // Initialize DriveHelper
    $driveHelper = new DriveHelper(
        $_ENV['ROOT_FOLDER_ID'],
        $_ENV['SHEETS_RANGE']
    );

    // List files in ROOT_FOLDER_ID
    echo "📁 Listing files in Worklogs folder (ID: {$_ENV['ROOT_FOLDER_ID']})...\n\n";

    // Use reflection to access private method for testing
    $reflection = new ReflectionClass($driveHelper);
    $findMethod = $reflection->getMethod('findSpreadsheetId');
    $findMethod->setAccessible(true);

    // Test finding files
    $testFiles = [
        'Worklog - test@example.com',
        'Worklog - demo@example.com',
        'Worklog - admin@company.com'
    ];

    foreach ($testFiles as $fileName) {
        $fileId = $findMethod->invoke($driveHelper, $fileName);
        if ($fileId) {
            echo "✅ Found: {$fileName} (ID: {$fileId})\n";
        } else {
            echo "❌ Not found: {$fileName}\n";
        }
    }

    echo "\n🎉 Drive connection test completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
