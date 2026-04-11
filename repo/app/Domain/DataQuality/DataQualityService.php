<?php
namespace App\Domain\DataQuality;

use App\Models\{ImportBatch, ImportValidationResult, DuplicateCandidate, Resource, TaxonomyTerm, ProhibitedTerm, VendorAlias, ManufacturerAlias, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataQualityService
{
    public function createImportBatch(User $user, string $filename, array $rows, bool $validateOnly = false): ImportBatch
    {
        return DB::transaction(function () use ($user, $filename, $rows, $validateOnly) {
            $batch = ImportBatch::create([
                'imported_by' => $user->id,
                'filename' => $filename,
                'total_rows' => count($rows),
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $valid = 0;
            $invalid = 0;
            $duplicates = 0;

            foreach ($rows as $index => $row) {
                $errors = $this->validateRow($row);
                $isDuplicate = $this->checkDuplicate($row, $batch->id, $index + 1);

                // Check if THIS row has a near-duplicate by re-checking similarity directly
                // (checkDuplicate already created DuplicateCandidate records for near matches)
                $hasNearDuplicate = false;
                if (!$isDuplicate && !empty($row['name'])) {
                    $allResources = Resource::all();
                    foreach ($allResources as $existingRes) {
                        similar_text(Str::lower($row['name']), Str::lower($existingRes->name), $pct);
                        if ($pct >= 80.0) {
                            $hasNearDuplicate = true;
                            break;
                        }
                    }
                }

                $status = 'valid';
                if (!empty($errors)) {
                    $status = 'invalid';
                    $invalid++;
                } elseif ($isDuplicate) {
                    $status = 'duplicate';
                    $duplicates++;
                } elseif ($hasNearDuplicate) {
                    $status = 'duplicate';
                    $duplicates++;
                } else {
                    $valid++;
                }

                // Apply normalization to the stored data
                $normalizedData = $row;
                if (!empty($row['vendor'])) {
                    $normalizedData['vendor_normalized'] = $this->normalizeVendor($row['vendor']);
                }
                if (!empty($row['manufacturer'])) {
                    $normalizedData['manufacturer_normalized'] = $this->normalizeManufacturer($row['manufacturer']);
                }

                ImportValidationResult::create([
                    'batch_id' => $batch->id,
                    'row_number' => $index + 1,
                    'original_data' => $normalizedData,
                    'validation_errors' => !empty($errors) ? $errors : null,
                    'is_duplicate' => $isDuplicate !== false,
                    'duplicate_of_id' => is_int($isDuplicate) ? $isDuplicate : null,
                    'status' => $status,
                ]);
            }

            $batch->update([
                'processed_rows' => count($rows),
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'duplicate_rows' => $duplicates,
                'status' => $validateOnly ? 'validated' : 'completed',
                'completed_at' => now(),
            ]);

            // Only persist valid rows as resources on an actual import (not validate-only)
            if (!$validateOnly) {
                $validResults = ImportValidationResult::where('batch_id', $batch->id)
                    ->where('status', 'valid')
                    ->get();

                $defaultDept = \App\Models\Department::first()
                    ?? \App\Models\Department::create(['name' => 'Default', 'code' => 'DEF', 'description' => 'Default import department']);

                foreach ($validResults as $result) {
                    $rowData = $result->original_data;
                    if (!empty($rowData['name'])) {
                        // Use department from import data if provided and valid, else default
                        $dept = $defaultDept;
                        if (!empty($rowData['department_code'])) {
                            $mappedDept = \App\Models\Department::where('code', $rowData['department_code'])->first();
                            if ($mappedDept) $dept = $mappedDept;
                        } elseif (!empty($rowData['department_id'])) {
                            $mappedDept = \App\Models\Department::find($rowData['department_id']);
                            if ($mappedDept) $dept = $mappedDept;
                        }
                        Resource::create([
                            'name' => $rowData['name'],
                            'description' => $rowData['description'] ?? null,
                            'resource_type' => $rowData['resource_type'] ?? 'equipment',
                            'category' => $rowData['category'] ?? 'Uncategorized',
                            'subcategory' => $rowData['subcategory'] ?? null,
                            'department_id' => $dept?->id,
                            'vendor' => $rowData['vendor_normalized'] ?? $rowData['vendor'] ?? null,
                            'manufacturer' => $rowData['manufacturer_normalized'] ?? $rowData['manufacturer'] ?? null,
                            'model_number' => $rowData['model_number'] ?? null,
                            'status' => 'active',
                            'tags' => isset($rowData['tags']) ? (is_array($rowData['tags']) ? $rowData['tags'] : explode(',', $rowData['tags'])) : null,
                        ]);
                    }
                }
            }

            return $batch->fresh();
        });
    }

    public function validateRow(array $row): array
    {
        $errors = [];

        // Required fields
        if (empty($row['name'])) {
            $errors[] = 'Name is required.';
        }

        // Prohibited terms check
        if (!empty($row['name'])) {
            $prohibited = $this->checkProhibitedTerms($row['name']);
            foreach ($prohibited as $term) {
                $errors[] = "Prohibited term found in name: '{$term}'";
            }
        }

        if (!empty($row['notes'])) {
            $prohibited = $this->checkProhibitedTerms($row['notes']);
            foreach ($prohibited as $term) {
                $errors[] = "Prohibited term found in notes: '{$term}'";
            }
        }

        // Taxonomy validation
        if (!empty($row['tags'])) {
            $tags = is_array($row['tags']) ? $row['tags'] : explode(',', $row['tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!TaxonomyTerm::where('type', 'tag')->where('value', $tag)->where('is_active', true)->exists()) {
                    $errors[] = "Invalid tag: '{$tag}' not in controlled taxonomy.";
                }
            }
        }

        // Category taxonomy validation
        if (!empty($row['category'])) {
            if (!TaxonomyTerm::where('type', 'category')->where('value', $row['category'])->where('is_active', true)->exists()) {
                $errors[] = "Invalid category: '{$row['category']}' not in controlled taxonomy.";
            }
        }

        return $errors;
    }

    public function checkProhibitedTerms(string $text): array
    {
        $found = [];
        $terms = ProhibitedTerm::where('severity', 'block')->pluck('term');
        $lowerText = Str::lower($text);

        foreach ($terms as $term) {
            if (Str::contains($lowerText, Str::lower($term))) {
                $found[] = $term;
            }
        }

        return $found;
    }

    public function checkDuplicate(array $row, ?int $batchId = null, ?int $rowNumber = null): int|false
    {
        if (empty($row['name'])) {
            return false;
        }

        $normalized = $this->normalizeTitle($row['name']);

        // Exact match (normalized)
        $exact = Resource::whereRaw('LOWER(REPLACE(name, " ", "")) = ?', [$normalized])->first();
        if ($exact) {
            if ($batchId) {
                DuplicateCandidate::create([
                    'resource_a_id' => $exact->id,
                    'batch_id' => $batchId,
                    'match_type' => 'exact',
                    'match_score' => 100.00,
                    'status' => 'pending',
                ]);
            }
            return $exact->id;
        }

        // Near match: check existing resources for high similarity (>= 80%)
        $candidates = Resource::all();
        foreach ($candidates as $candidate) {
            similar_text(Str::lower($row['name']), Str::lower($candidate->name), $percent);
            if ($percent >= 80.0) {
                if ($batchId) {
                    DuplicateCandidate::create([
                        'resource_a_id' => $candidate->id,
                        'batch_id' => $batchId,
                        'match_type' => 'near',
                        'match_score' => round($percent, 2),
                        'status' => 'pending',
                    ]);
                }
                // Near matches are flagged for review, not hard-blocked
                // Return false so the row isn't marked as 'duplicate' (it goes to remediation instead)
            }
        }

        return false;
    }

    public function normalizeTitle(string $title): string
    {
        return Str::lower(str_replace(' ', '', $title));
    }

    public function normalizeVendor(string $vendor): string
    {
        $alias = VendorAlias::where('alias', $vendor)->where('status', 'approved')->first();
        return $alias ? $alias->canonical_name : $vendor;
    }

    public function normalizeManufacturer(string $manufacturer): string
    {
        $alias = ManufacturerAlias::where('alias', $manufacturer)->where('status', 'approved')->first();
        return $alias ? $alias->canonical_name : $manufacturer;
    }

    public function generateValidationReport(ImportBatch $batch): array
    {
        $results = ImportValidationResult::where('batch_id', $batch->id)->get();

        return [
            'batch_id' => $batch->id,
            'filename' => $batch->filename,
            'summary' => [
                'total_rows' => $batch->total_rows,
                'valid' => $batch->valid_rows,
                'invalid' => $batch->invalid_rows,
                'duplicates' => $batch->duplicate_rows,
            ],
            'issues' => $results->filter(fn($r) => $r->status !== 'valid')->map(fn($r) => [
                'row' => $r->row_number,
                'status' => $r->status,
                'errors' => $r->validation_errors,
                'data' => $r->original_data,
            ])->values()->toArray(),
        ];
    }
}
