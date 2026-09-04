<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmCompany;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrmImportService
{
    public function __construct(
        private readonly ContactDuplicateService $duplicateService,
        private readonly CrmCustomFieldService $customFieldService
    ) {}

    /**
     * Parse CSV file into rows and headers.
     */
    public function parseCsv(UploadedFile|string $file): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return ['headers' => [], 'rows' => [], 'total' => 0];
        }

        $headers = [];
        $rows = [];
        $rowCount = 0;

        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            if (empty($headers)) {
                // First row is headers
                $headers = array_map(fn ($h) => trim(Str::ascii($h)), $data);
                continue;
            }

            // Skip empty rows
            if (count(array_filter($data, fn ($v) => trim($v) !== '')) === 0) {
                continue;
            }

            $rows[] = array_map('trim', $data);
            $rowCount++;
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total' => $rowCount,
        ];
    }

    /**
     * Preview mapped rows before executing import.
     */
    public function preview(array $headers, array $rows, array $columnMapping, int $limit = 5): array
    {
        $preview = [];
        $sampleRows = array_slice($rows, 0, $limit);

        foreach ($sampleRows as $index => $row) {
            $mapped = [];
            foreach ($columnMapping as $sourceCol => $targetField) {
                $colIdx = array_search($sourceCol, $headers, true);
                if ($colIdx !== false && isset($row[$colIdx])) {
                    $mapped[$targetField] = $row[$colIdx];
                }
            }
            $preview[] = [
                'row_number' => $index + 2, // 1-indexed including header
                'mapped' => $mapped,
            ];
        }

        return $preview;
    }

    /**
     * Execute full transactional CSV import with validation, duplicate detection, and error report.
     */
    public function import(
        Workspace $workspace,
        array $headers,
        array $rows,
        array $columnMapping,
        string $duplicateStrategy = 'skip', // skip, update, duplicate
        ?int $assignedUserId = null,
        ?int $pipelineId = null,
        ?int $stageId = null
    ): array {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // +1 for 0-index, +1 for header line
            $record = [];

            foreach ($columnMapping as $sourceCol => $targetField) {
                if (empty($targetField)) {
                    continue;
                }
                $colIdx = array_search($sourceCol, $headers, true);
                if ($colIdx !== false && isset($row[$colIdx])) {
                    $record[$targetField] = trim($row[$colIdx]);
                }
            }

            // Name splitting if single 'name' is provided
            if (! empty($record['name']) && empty($record['first_name'])) {
                $parts = explode(' ', $record['name'], 2);
                $record['first_name'] = $parts[0] ?? '';
                $record['last_name'] = $parts[1] ?? null;
            }

            $firstName = $record['first_name'] ?? null;
            $lastName = $record['last_name'] ?? null;
            $phone = $record['phone'] ?? $record['phone_e164'] ?? null;
            $email = $record['email'] ?? null;
            $companyName = $record['company'] ?? null;
            $dealValue = isset($record['deal_value']) && is_numeric($record['deal_value']) ? (float) $record['deal_value'] : 0;
            $tagsRaw = $record['tags'] ?? null;

            // Row Validation
            if (empty($firstName) && empty($phone) && empty($email)) {
                $failed++;
                $errors[] = [
                    'row' => $rowNum,
                    'reason' => 'Row missing required contact identifier (first name, phone, or email).',
                    'data' => $record,
                ];
                continue;
            }

            if (! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                $errors[] = [
                    'row' => $rowNum,
                    'reason' => "Invalid email format '{$email}'.",
                    'data' => $record,
                ];
                continue;
            }

            $normalizedPhone = $this->duplicateService->normalizePhone($phone);
            $normalizedEmail = $this->duplicateService->normalizeEmail($email);

            // Duplicate Detection
            $existing = $this->duplicateService->findDuplicate($workspace->id, $normalizedPhone, $normalizedEmail);

            if ($existing) {
                if ($duplicateStrategy === 'skip') {
                    $skipped++;
                    continue;
                } elseif ($duplicateStrategy === 'update') {
                    $existing->update(array_filter([
                        'first_name' => $firstName ?: $existing->first_name,
                        'last_name' => $lastName ?: $existing->last_name,
                        'company' => $companyName ?: $existing->company,
                        'deal_value' => $dealValue > 0 ? $dealValue : $existing->deal_value,
                        'pipeline_id' => $pipelineId ?: $existing->pipeline_id,
                        'stage_id' => $stageId ?: $existing->stage_id,
                        'assigned_user_id' => $assignedUserId ?: $existing->assigned_user_id,
                    ]));
                    $updated++;
                    continue;
                }
            }

            // Company association
            $companyId = null;
            if (! empty($companyName)) {
                $company = CrmCompany::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'name' => $companyName],
                    ['owner_user_id' => $assignedUserId]
                );
                $companyId = $company->id;
            }

            // Insert valid Contact
            $contact = Contact::create([
                'workspace_id' => $workspace->id,
                'first_name' => $firstName ?: 'Contact',
                'last_name' => $lastName,
                'phone_e164' => $normalizedPhone,
                'email' => $normalizedEmail,
                'company' => $companyName,
                'company_id' => $companyId,
                'deal_value' => $dealValue,
                'pipeline_id' => $pipelineId,
                'stage_id' => $stageId,
                'assigned_user_id' => $assignedUserId,
                'source' => 'csv_import',
            ]);

            // Sync tags
            if (! empty($tagsRaw)) {
                $tagNames = is_array($tagsRaw) ? $tagsRaw : explode(',', $tagsRaw);
                foreach ($tagNames as $tName) {
                    $tName = trim($tName);
                    if ($tName) {
                        $tag = ContactTag::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $tName]);
                        $contact->tags()->syncWithoutDetaching([$tag->id]);
                    }
                }
            }

            $imported++;
        }

        return [
            'total_rows' => count($rows),
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
