<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\DataQuality\DataQualityService;
use App\Models\ImportBatch;
use App\Models\ImportValidationResult;
use App\Models\DuplicateCandidate;
use App\Models\Resource;
use App\Models\VendorAlias;
use App\Models\ManufacturerAlias;
use Illuminate\Http\Request;

class DataQualityController extends Controller
{
    public function __construct(protected DataQualityService $dq) {}

    private const SUPPORTED_IMPORT_TYPES = ['resources'];

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'nullable|file|mimes:csv,txt,json',
            'rows' => 'nullable|array',
            'rows.*.name' => 'nullable|string',
            'type' => 'nullable|string',
            'validate_only' => 'nullable',
        ]);

        // Reject unsupported import types
        $type = $request->input('type', 'resources');
        if (!in_array($type, self::SUPPORTED_IMPORT_TYPES, true)) {
            return response()->json([
                'error' => "Import type '{$type}' is not supported. Supported types: " . implode(', ', self::SUPPORTED_IMPORT_TYPES) . '.',
            ], 422);
        }

        $rows = $request->input('rows');

        // If no rows provided, parse from uploaded file
        if (empty($rows) && $request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());

            if ($ext === 'json') {
                $content = file_get_contents($file->getRealPath());
                $rows = json_decode($content, true);
                if (!is_array($rows)) {
                    return response()->json(['error' => 'Invalid JSON: expected an array of objects.'], 422);
                }
            } elseif (in_array($ext, ['csv', 'txt'])) {
                $rows = [];
                $handle = fopen($file->getRealPath(), 'r');
                $headers = fgetcsv($handle);
                if (!$headers) {
                    fclose($handle);
                    return response()->json(['error' => 'CSV file is empty or has no header row.'], 422);
                }
                $headers = array_map('trim', $headers);
                while (($line = fgetcsv($handle)) !== false) {
                    if (count($line) === count($headers)) {
                        $rows[] = array_combine($headers, array_map('trim', $line));
                    }
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            return response()->json(['error' => 'No data provided. Upload a file or provide rows.'], 422);
        }

        $validateOnly = filter_var($request->input('validate_only', false), FILTER_VALIDATE_BOOLEAN);

        $batch = $this->dq->createImportBatch(
            $request->user(),
            $request->file?->getClientOriginalName() ?? 'inline',
            $rows,
            $validateOnly
        );

        $report = $this->dq->generateValidationReport($batch);
        $report['validate_only'] = $validateOnly;

        return response()->json($report);
    }

    public function batches()
    {
        return response()->json(ImportBatch::latest()->paginate(20));
    }

    public function batchReport(ImportBatch $batch)
    {
        return response()->json($this->dq->generateValidationReport($batch));
    }

    public function downloadReport(ImportBatch $batch)
    {
        $report = $this->dq->generateValidationReport($batch);
        $content = json_encode($report, JSON_PRETTY_PRINT);
        return response($content)->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename=validation_report_{$batch->id}.json");
    }

    public function remediationQueue()
    {
        $items = ImportValidationResult::whereIn('status', ['invalid', 'duplicate'])
            ->with('batch')
            ->latest()
            ->paginate(20);

        // Shape response to match frontend expectations
        $items->getCollection()->transform(function ($item) {
            $errors = $item->validation_errors ?? [];
            return [
                'id' => $item->id,
                'severity' => $item->is_duplicate ? 'medium' : 'high',
                'type' => $item->is_duplicate ? 'duplicate' : 'validation',
                'title' => $item->original_data['name'] ?? "Row #{$item->row_number}",
                'description' => is_array($errors) ? implode('; ', $errors) : ($errors ?? 'Duplicate detected'),
                'affected_resource' => $item->duplicate_of_id ? ['name' => "Resource #{$item->duplicate_of_id}"] : null,
                'auto_fixable' => $item->status === 'invalid' && !empty($errors),
                'status' => $item->status,
                'batch_id' => $item->batch_id,
                'row_number' => $item->row_number,
                'original_data' => $item->original_data,
            ];
        });

        return response()->json($items);
    }

    public function remediateItem(Request $request, ImportValidationResult $item)
    {
        $request->validate(['action' => 'required|in:remediate,skip']);
        $item->update([
            'status' => $request->action === 'remediate' ? 'remediated' : 'skipped',
            'remediated_by' => $request->user()->id,
            'remediated_at' => now(),
        ]);
        return response()->json(['message' => 'Item updated.']);
    }

    public function duplicates()
    {
        $candidates = DuplicateCandidate::where('status', 'pending')
            ->with(['resourceA'])
            ->paginate(20);

        // Shape response to match frontend expectations
        $candidates->getCollection()->transform(function ($candidate) {
            $records = [];
            if ($candidate->resourceA) {
                $records[] = [
                    'id' => $candidate->resourceA->id,
                    'name' => $candidate->resourceA->name,
                    'type' => $candidate->resourceA->resource_type,
                    'department' => $candidate->resourceA->department?->name ?? 'Unknown',
                ];
            }
            // Include the imported row data for comparison
            $importedRow = null;
            if ($candidate->batch_id) {
                // Find the specific imported row that triggered this duplicate candidate
                // by matching the resource name similarity
                $candidateName = $candidate->resourceA?->name;
                $results = ImportValidationResult::where('batch_id', $candidate->batch_id)
                    ->whereIn('status', ['duplicate', 'valid'])
                    ->get();
                foreach ($results as $result) {
                    $rowName = $result->original_data['name'] ?? '';
                    if ($candidateName && $rowName) {
                        similar_text(strtolower($rowName), strtolower($candidateName), $pct);
                        if ($pct >= 80.0) {
                            $importedRow = $result->original_data;
                            break;
                        }
                    }
                }
                // Fallback: use first result if no similarity match found
                if (!$importedRow && $results->isNotEmpty()) {
                    $importedRow = $results->first()->original_data;
                }
            }

            return [
                'id' => $candidate->id,
                'confidence' => $candidate->match_score / 100,
                'match_type' => $candidate->match_type,
                'records' => $records,
                'imported_row' => $importedRow,
                'status' => $candidate->status,
            ];
        });

        return response()->json($candidates);
    }

    public function resolveDuplicate(Request $request, DuplicateCandidate $candidate)
    {
        $request->validate(['action' => 'required|in:confirmed,dismissed']);
        $candidate->update(['status' => $request->action, 'reviewed_by' => $request->user()->id]);
        return response()->json(['message' => 'Resolved.']);
    }

    public function stats()
    {
        $totalRecords = Resource::count();
        $withIssues = ImportValidationResult::whereIn('status', ['invalid', 'duplicate'])->count();
        $duplicateCandidates = DuplicateCandidate::where('status', 'pending')->count();

        // Field completeness: compute percentage of non-null values for key resource fields
        $fields = ['description', 'category', 'vendor', 'manufacturer', 'model_number'];
        $fieldStats = [];
        foreach ($fields as $field) {
            if ($totalRecords > 0) {
                $filled = Resource::whereNotNull($field)->where($field, '!=', '')->count();
                $fieldStats[$field] = (int) round(($filled / $totalRecords) * 100);
            } else {
                $fieldStats[$field] = 0;
            }
        }

        $completeness = $totalRecords > 0
            ? (int) round((array_sum($fieldStats) / (count($fields) * 100)) * 100)
            : 0;

        return response()->json([
            'total_records' => $totalRecords,
            'records_with_issues' => $withIssues,
            'duplicate_candidates' => $duplicateCandidates,
            'completeness_pct' => $completeness,
            'field_stats' => $fieldStats,
        ]);
    }

    public function vendorAliases()
    {
        return response()->json(VendorAlias::paginate(20));
    }

    public function manufacturerAliases()
    {
        return response()->json(ManufacturerAlias::paginate(20));
    }

    public function createVendorAlias(Request $request)
    {
        $request->validate([
            'alias' => 'required|string',
            'canonical_name' => 'required|string',
        ]);
        $entry = VendorAlias::create([
            'alias' => $request->alias,
            'canonical_name' => $request->canonical_name,
            'status' => 'pending',
        ]);
        return response()->json($entry, 201);
    }

    public function createManufacturerAlias(Request $request)
    {
        $request->validate([
            'alias' => 'required|string',
            'canonical_name' => 'required|string',
        ]);
        $entry = ManufacturerAlias::create([
            'alias' => $request->alias,
            'canonical_name' => $request->canonical_name,
            'status' => 'pending',
        ]);
        return response()->json($entry, 201);
    }

    public function updateVendorAlias(Request $request, VendorAlias $alias)
    {
        $request->validate(['status' => 'required|in:approved,rejected']);
        $alias->update([
            'status' => $request->status,
            'reviewed_by' => $request->user()->id,
        ]);
        return response()->json($alias);
    }

    public function updateManufacturerAlias(Request $request, ManufacturerAlias $alias)
    {
        $request->validate(['status' => 'required|in:approved,rejected']);
        $alias->update([
            'status' => $request->status,
            'reviewed_by' => $request->user()->id,
        ]);
        return response()->json($alias);
    }
}
