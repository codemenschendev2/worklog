<?php

declare(strict_types=1);

namespace Worklog;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Sheets;
use Exception;

class DriveHelper
{
    private Google_Client $client;
    private Google_Service_Drive $driveService;
    private Google_Service_Sheets $sheetsService;
    private string $rootFolderId;
    private string $sheetsRange;

    public function __construct(string $rootFolderId, string $sheetsRange)
    {
        $this->rootFolderId = $rootFolderId;
        $this->sheetsRange = $sheetsRange;
        $this->initializeGoogleClient();
    }

    private function initializeGoogleClient(): void
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig($_ENV['GOOGLE_APPLICATION_CREDENTIALS']);
        $this->client->setScopes([
            Google_Service_Drive::DRIVE,
            Google_Service_Sheets::SPREADSHEETS
        ]);

        $this->driveService = new Google_Service_Drive($this->client);
        $this->sheetsService = new Google_Service_Sheets($this->client);
    }

    /**
     * Tìm spreadsheet theo tên trong folder ROOT_FOLDER_ID
     */
    public function findSpreadsheetId(string $name): ?string
    {
        try {
            $query = "name = '{$name}' and '{$this->rootFolderId}' in parents and trashed = false and mimeType = 'application/vnd.google-apps.spreadsheet'";
            $results = $this->driveService->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 1
            ]);

            $files = $results->getFiles();
            return !empty($files) ? $files[0]->getId() : null;
        } catch (Exception $e) {
            error_log("Error finding spreadsheet: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Tạo spreadsheet cho user
     */
    public function createSpreadsheetForUser(string $name): string
    {
        try {
            // Tạo spreadsheet mới
            $spreadsheet = new \Google_Service_Sheets_Spreadsheet([
                'properties' => [
                    'title' => $name
                ],
                'sheets' => [
                    [
                        'properties' => [
                            'title' => 'Logs',
                            'gridProperties' => [
                                'rowCount' => 1000,
                                'columnCount' => 7
                            ]
                        ]
                    ]
                ]
            ]);

            $spreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
            $spreadsheetId = $spreadsheet->getSpreadsheetId();

            // Di chuyển file vào ROOT_FOLDER_ID
            $this->moveFileToFolder($spreadsheetId, $this->rootFolderId);

            // Ghi header
            $this->writeHeader($spreadsheetId);

            return $spreadsheetId;
        } catch (Exception $e) {
            throw new Exception("Error creating spreadsheet: " . $e->getMessage());
        }
    }

    /**
     * Di chuyển file vào folder
     */
    private function moveFileToFolder(string $fileId, string $folderId): void
    {
        try {
            // Lấy parents hiện tại
            $file = $this->driveService->files->get($fileId, ['fields' => 'parents']);
            $parents = $file->getParents();

            // Thêm vào folder mới
            $this->driveService->files->update($fileId, null, [
                'addParents' => $folderId,
                'removeParents' => implode(',', $parents)
            ]);
        } catch (Exception $e) {
            error_log("Error moving file: " . $e->getMessage());
        }
    }

    /**
     * Ghi header cho spreadsheet
     */
    private function writeHeader(string $spreadsheetId): void
    {
        $header = ['date', 'user', 'project', 'task', 'duration_h', 'notes', 'idempotency_key'];
        $range = 'Logs!A1:G1';

        $body = new \Google_Service_Sheets_ValueRange([
            'values' => [$header]
        ]);

        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'RAW']
        );
    }

    /**
     * Lấy hoặc tạo spreadsheet cho user
     */
    public function getOrCreateUserSpreadsheetId(string $userEmail): string
    {
        $fileName = "Worklog - {$userEmail}";
        $spreadsheetId = $this->findSpreadsheetId($fileName);

        if (!$spreadsheetId) {
            $spreadsheetId = $this->createSpreadsheetForUser($fileName);
        }

        return $spreadsheetId;
    }

    /**
     * Share file cho user
     */
    public function shareWithUser(string $spreadsheetId, string $userEmail, string $role = 'writer'): bool
    {
        try {
            $permission = new \Google_Service_Drive_Permission([
                'type' => 'user',
                'role' => $role,
                'emailAddress' => $userEmail
            ]);

            $this->driveService->permissions->create($spreadsheetId, $permission, [
                'sendNotificationEmail' => false
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error sharing file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Append row vào spreadsheet
     */
    public function appendRow(string $spreadsheetId, array $rowArray): void
    {
        $body = new \Google_Service_Sheets_ValueRange([
            'values' => [$rowArray]
        ]);

        $this->sheetsService->spreadsheets_values->append(
            $spreadsheetId,
            $this->sheetsRange,
            $body,
            ['valueInputOption' => 'RAW']
        );
    }

    /**
     * Lấy tất cả rows từ spreadsheet
     */
    public function getAllRows(string $spreadsheetId): array
    {
        try {
            $response = $this->sheetsService->spreadsheets_values->get($spreadsheetId, $this->sheetsRange);
            $values = $response->getValues();

            // Bỏ qua header row
            return array_slice($values, 1);
        } catch (Exception $e) {
            error_log("Error getting rows: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cập nhật row theo index
     */
    public function updateRow(string $spreadsheetId, int $rowIndex0, array $rowArray): void
    {
        $range = "Logs!A" . ($rowIndex0 + 2); // +2 vì có header và index 0-based

        $body = new \Google_Service_Sheets_ValueRange([
            'values' => [$rowArray]
        ]);

        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'RAW']
        );
    }
}
