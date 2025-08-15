<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Worklog\DriveHelper;

try {
    echo "🚀 Testing Google Sheets creation...\n\n";

    // Initialize DriveHelper
    $driveHelper = new DriveHelper(
        $_ENV['ROOT_FOLDER_ID'],
        $_ENV['SHEETS_RANGE']
    );

    $demoEmail = 'demo@example.com';
    $fileName = "Worklog - {$demoEmail}";

    echo "📝 Creating spreadsheet: {$fileName}\n";
    echo "📂 Target folder ID: {$_ENV['ROOT_FOLDER_ID']}\n\n";

    // Create spreadsheet for demo user
    $spreadsheetId = $driveHelper->createSpreadsheetForUser($fileName);

    echo "✅ Spreadsheet created successfully: {$spreadsheetId}\n";
    echo "✅ Moved to Worklogs folder & header written OK\n\n";

    // Test sharing
    echo "🔗 Testing file sharing...\n";
    $shareResult = $driveHelper->shareWithUser($spreadsheetId, $demoEmail, 'writer');

    if ($shareResult) {
        echo "✅ File shared with {$demoEmail} successfully\n";
    } else {
        echo "⚠️  File sharing failed (may already be shared)\n";
    }

    echo "\n🎉 Sheet creation test completed successfully!\n";
    echo "📊 You can now access the sheet at: https://docs.google.com/spreadsheets/d/{$spreadsheetId}\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
