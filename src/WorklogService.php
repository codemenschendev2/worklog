<?php

declare(strict_types=1);

namespace Worklog;

use Exception;

class WorklogService
{
    private DriveHelper $driveHelper;

    public function __construct()
    {
        $this->driveHelper = new DriveHelper(
            $_ENV['ROOT_FOLDER_ID'],
            $_ENV['SHEETS_RANGE']
        );
    }

    /**
     * Append worklog entry
     */
    public function append(array $payload, bool $autoShare = true): array
    {
        // Validate required fields
        if (empty($payload['user'])) {
            throw new Exception('User email is required');
        }

        $userEmail = $payload['user'];
        $spreadsheetId = $this->driveHelper->getOrCreateUserSpreadsheetId($userEmail);

        // Auto-share if enabled
        if ($autoShare) {
            $this->driveHelper->shareWithUser($spreadsheetId, $userEmail, 'writer');
        }

        // Check for duplicate idempotency_key
        if (!empty($payload['idempotency_key'])) {
            $rows = $this->driveHelper->getAllRows($spreadsheetId);
            foreach ($rows as $row) {
                if (isset($row[6]) && $row[6] === $payload['idempotency_key']) {
                    return [
                        'status' => 'duplicate',
                        'spreadsheetId' => $spreadsheetId,
                        'idempotency_key' => $payload['idempotency_key']
                    ];
                }
            }
        } else {
            // Generate idempotency_key if not provided
            $payload['idempotency_key'] = uniqid('key_', true);
        }

        // Normalize row data
        $rowData = $this->normalizeRowData($payload);

        // Append row
        $this->driveHelper->appendRow($spreadsheetId, $rowData);

        return [
            'status' => 'ok',
            'spreadsheetId' => $spreadsheetId,
            'idempotency_key' => $payload['idempotency_key']
        ];
    }

    /**
     * Update worklog entry
     */
    public function update(array $payload): array
    {
        // Validate required fields
        if (empty($payload['user']) || empty($payload['idempotency_key'])) {
            throw new Exception('User email and idempotency_key are required');
        }

        $userEmail = $payload['user'];
        $idempotencyKey = $payload['idempotency_key'];
        $spreadsheetId = $this->driveHelper->getOrCreateUserSpreadsheetId($userEmail);

        // Find row to update
        $rows = $this->driveHelper->getAllRows($spreadsheetId);
        $rowIndex = null;
        $currentRow = null;

        foreach ($rows as $index => $row) {
            if (isset($row[6]) && $row[6] === $idempotencyKey) {
                $rowIndex = $index;
                $currentRow = $row;
                break;
            }
        }

        if ($rowIndex === null) {
            return [
                'status' => 'not_found',
                'spreadsheetId' => $spreadsheetId
            ];
        }

        // Update allowed fields
        $updatedRow = $this->updateRowData($currentRow, $payload);

        // Update row in spreadsheet
        $this->driveHelper->updateRow($spreadsheetId, $rowIndex, $updatedRow);

        return [
            'status' => 'ok',
            'spreadsheetId' => $spreadsheetId
        ];
    }

    /**
     * Normalize row data for append
     */
    private function normalizeRowData(array $payload): array
    {
        return [
            $payload['date'] ?? date('Y-m-d'),
            $payload['user'],
            $payload['project'] ?? '',
            $payload['task'] ?? '',
            (float)($payload['duration_h'] ?? 0),
            $payload['notes'] ?? '',
            $payload['idempotency_key']
        ];
    }

    /**
     * Update row data for existing entry
     */
    private function updateRowData(array $currentRow, array $payload): array
    {
        $updatedRow = $currentRow;

        // Update allowed fields
        if (isset($payload['date'])) {
            $updatedRow[0] = $payload['date'];
        }
        if (isset($payload['project'])) {
            $updatedRow[2] = $payload['project'];
        }
        if (isset($payload['task'])) {
            $updatedRow[3] = $payload['task'];
        }
        if (isset($payload['duration_h'])) {
            $updatedRow[4] = (float)$payload['duration_h'];
        }
        if (isset($payload['notes'])) {
            $updatedRow[5] = $payload['notes'];
        }

        return $updatedRow;
    }
}
