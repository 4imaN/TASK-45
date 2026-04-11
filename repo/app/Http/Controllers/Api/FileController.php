<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Files\FileService;
use App\Models\FileAsset;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(protected FileService $fileService) {}

    private const ALLOWED_ATTACHABLE_TYPES = [
        'loan_request' => \App\Models\LoanRequest::class,
        'reservation' => \App\Models\ReservationRequest::class,
        'resource' => \App\Models\Resource::class,
    ];

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'attachable_type' => 'nullable|string|in:loan_request,reservation,resource',
            'attachable_id' => 'nullable|integer',
        ]);

        $attachableType = null;
        $attachableId = null;

        if ($request->filled('attachable_type') && $request->filled('attachable_id')) {
            $attachableType = self::ALLOWED_ATTACHABLE_TYPES[$request->attachable_type] ?? null;
            if (!$attachableType) {
                return response()->json(['error' => 'Invalid attachable type.'], 422);
            }
            // Verify the target record exists
            if (!$attachableType::find($request->attachable_id)) {
                return response()->json(['error' => 'Attachable record not found.'], 422);
            }
            // Verify user has access to the attach target
            if ($attachableType === \App\Models\LoanRequest::class) {
                $loan = $attachableType::find($request->attachable_id);
                if ($loan && $loan->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                    return response()->json(['error' => 'You cannot attach files to another user\'s loan.'], 403);
                }
            } elseif ($attachableType === \App\Models\ReservationRequest::class) {
                $reservation = $attachableType::find($request->attachable_id);
                if ($reservation && $reservation->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                    return response()->json(['error' => 'You cannot attach files to another user\'s reservation.'], 403);
                }
            } elseif ($attachableType === \App\Models\Resource::class) {
                if ($request->user()->isStudent()) {
                    return response()->json(['error' => 'Students cannot attach files to resources.'], 403);
                }
                if (!$request->user()->isAdmin()) {
                    $resource = \App\Models\Resource::find($request->attachable_id);
                    if ($resource) {
                        $hasScope = $request->user()->permissionScopes()->where(function ($q) use ($resource) {
                            $q->where('scope_type', 'full')
                              ->orWhere('department_id', $resource->department_id);
                        })->exists();
                        if (!$hasScope) {
                            return response()->json(['error' => 'You do not have scope on this resource\'s department.'], 403);
                        }
                    }
                }
            }
            $attachableId = $request->attachable_id;
        }

        $asset = $this->fileService->upload($request->file('file'), $request->user(), $attachableType, $attachableId);
        return response()->json(['message' => 'Uploaded.', 'file' => new \App\Http\Resources\FileAssetResource($asset)]);
    }

    public function download(FileAsset $file, Request $request)
    {
        $this->authorize('download', $file);
        $path = $this->fileService->download($file, $request->user());
        return response()->download($path, $file->original_filename, ['Content-Type' => $file->mime_type]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = FileAsset::query();

        if ($user->isStudent()) {
            // Students see only their own files
            $query->where('uploaded_by', $user->id);
        } elseif (!$user->isAdmin()) {
            // Staff see their own files + files attached to loan requests in their scope
            $hasFullScope = $user->permissionScopes()->where('scope_type', 'full')->exists();
            if (!$hasFullScope) {
                $scopedClassIds = $user->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
                $scopedAssignmentIds = $user->permissionScopes()->whereNotNull('assignment_id')->pluck('assignment_id');
                // Resolve course scopes to class IDs
                $scopedCourseIds = $user->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
                $courseClassIds = \App\Models\ClassModel::whereIn('course_id', $scopedCourseIds)->pluck('id');
                $scopedClassIds = $scopedClassIds->merge($courseClassIds)->unique();

                $query->where(function ($q) use ($user, $scopedClassIds, $scopedAssignmentIds) {
                    $q->where('uploaded_by', $user->id)
                      ->orWhere(function ($inner) use ($scopedClassIds, $scopedAssignmentIds) {
                          $inner->where('attachable_type', \App\Models\LoanRequest::class)
                                ->whereIn('attachable_id', function ($sub) use ($scopedClassIds, $scopedAssignmentIds) {
                                    $sub->select('id')->from('loan_requests')
                                        ->where(function ($w) use ($scopedClassIds, $scopedAssignmentIds) {
                                            $w->whereIn('class_id', $scopedClassIds)
                                              ->orWhereIn('assignment_id', $scopedAssignmentIds);
                                        });
                                });
                      })
                      ->orWhere(function ($inner) use ($scopedClassIds, $scopedAssignmentIds) {
                          $inner->where('attachable_type', \App\Models\ReservationRequest::class)
                                ->whereIn('attachable_id', function ($sub) use ($scopedClassIds, $scopedAssignmentIds) {
                                    $sub->select('id')->from('reservation_requests')
                                        ->where(function ($w) use ($scopedClassIds, $scopedAssignmentIds) {
                                            $w->whereIn('class_id', $scopedClassIds)
                                              ->orWhereIn('assignment_id', $scopedAssignmentIds);
                                        });
                                });
                      });
                });
            }
        }
        // Admin sees all files (no filter)

        return \App\Http\Resources\FileAssetResource::collection($query->latest()->paginate(20));
    }
}
