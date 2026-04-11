<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Administration\ScopeService;
use App\Models\User;
use App\Models\PermissionScope;
use App\Models\Hold;
use App\Models\InterventionLog;
use App\Models\AuditLog;
use App\Models\Allowlist;
use App\Models\Blacklist;
use App\Models\LoanRequest;
use App\Models\ReservationRequest;
use App\Models\Checkout;
use App\Models\Membership;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(protected ScopeService $scopeService) {}

    public function assignScope(Request $request)
    {
        $rules = [
            'user_id' => 'required|exists:users,id',
            'scope_type' => 'required|in:full,course,class,assignment,department',
        ];

        // Enforce that scope_type matches exactly one required foreign key
        $type = $request->input('scope_type');
        if ($type === 'course') {
            $rules['course_id'] = 'required|exists:courses,id';
        } elseif ($type === 'class') {
            $rules['class_id'] = 'required|exists:classes,id';
        } elseif ($type === 'assignment') {
            $rules['assignment_id'] = 'required|exists:assignments,id';
        } elseif ($type === 'department') {
            $rules['department_id'] = 'required|exists:departments,id';
        }
        // 'full' requires no foreign key

        $request->validate($rules);

        // Strip foreign keys that don't match the scope_type to prevent mixed states
        $scopeData = [
            'scope_type' => $type,
            'course_id' => $type === 'course' ? $request->course_id : null,
            'class_id' => $type === 'class' ? $request->class_id : null,
            'assignment_id' => $type === 'assignment' ? $request->assignment_id : null,
            'department_id' => $type === 'department' ? $request->department_id : null,
        ];

        $user = User::findOrFail($request->user_id);
        $scope = $this->scopeService->assignScope($user, $scopeData, $request->user());
        return response()->json(['message' => 'Scope assigned.', 'scope' => $scope]);
    }

    public function scopes(Request $request)
    {
        $scopes = PermissionScope::with('user')->paginate(20);
        // Mask user data in response
        $scopes->getCollection()->transform(function ($scope) {
            if ($scope->relationLoaded('user') && $scope->user) {
                $scope->setRelation('user', new \App\Http\Resources\UserResource($scope->user));
            }
            return $scope;
        });
        return response()->json($scopes);
    }

    public function addAllowlist(Request $request)
    {
        $request->validate([
            'scope_type' => 'required|in:department,global', 'scope_id' => 'required|integer',
            'user_id' => 'required|exists:users,id', 'reason' => 'required|string',
        ]);
        $entry = $this->scopeService->addToAllowlist($request->scope_type, $request->scope_id, User::find($request->user_id), $request->reason, $request->user());
        return response()->json($entry);
    }

    public function addBlacklist(Request $request)
    {
        $request->validate([
            'scope_type' => 'required|in:department,global', 'scope_id' => 'required|integer',
            'user_id' => 'required|exists:users,id', 'reason' => 'required|string',
            'expires_at' => 'nullable|date',
        ]);
        $entry = $this->scopeService->addToBlacklist($request->scope_type, $request->scope_id, User::find($request->user_id), $request->reason, $request->user(), $request->expires_at ? new \DateTime($request->expires_at) : null);
        return response()->json($entry);
    }

    public function listAllowlists()
    {
        $items = Allowlist::with('user')->latest()->paginate(20);
        $items->getCollection()->transform(function ($item) {
            if ($item->relationLoaded('user') && $item->user) {
                $item->setRelation('user', new \App\Http\Resources\UserResource($item->user));
            }
            return $item;
        });
        return response()->json($items);
    }

    public function deleteAllowlist(Allowlist $allowlist)
    {
        AuditLog::create([
            'user_id' => request()->user()->id,
            'action' => 'allowlist_removed',
            'auditable_type' => Allowlist::class,
            'auditable_id' => $allowlist->id,
            'old_values' => $allowlist->toArray(),
        ]);
        $allowlist->delete();
        return response()->json(['message' => 'Allowlist entry removed.']);
    }

    public function listBlacklists()
    {
        $items = Blacklist::with('user')->latest()->paginate(20);
        $items->getCollection()->transform(function ($item) {
            if ($item->relationLoaded('user') && $item->user) {
                $item->setRelation('user', new \App\Http\Resources\UserResource($item->user));
            }
            return $item;
        });
        return response()->json($items);
    }

    public function deleteBlacklist(Blacklist $blacklist)
    {
        AuditLog::create([
            'user_id' => request()->user()->id,
            'action' => 'blacklist_removed',
            'auditable_type' => Blacklist::class,
            'auditable_id' => $blacklist->id,
            'old_values' => $blacklist->toArray(),
        ]);
        $blacklist->delete();
        return response()->json(['message' => 'Blacklist entry removed.']);
    }

    public function holds()
    {
        $holds = Hold::where('status', 'active')->with('user')->paginate(20);
        $holds->getCollection()->transform(function ($hold) {
            if ($hold->relationLoaded('user') && $hold->user) {
                $hold->setRelation('user', new \App\Http\Resources\UserResource($hold->user));
            }
            return $hold;
        });
        return response()->json($holds);
    }

    public function releaseHold(Request $request, Hold $hold)
    {
        $request->validate(['reason' => 'required|string|min:5']);
        $hold = $this->scopeService->releaseHold($hold, $request->user(), $request->reason);
        return response()->json(['message' => 'Hold released.', 'hold' => $hold]);
    }

    public function interventionLogs()
    {
        return response()->json(InterventionLog::latest()->paginate(20));
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('action')) $query->where('action', $request->action);

        // Support 'event' filter (maps to action column) from frontend
        if ($request->filled('event')) {
            $query->where('action', 'like', '%' . $request->event . '%');
        }

        // Support 'search' filter: search by user display_name, username, or action
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('display_name', 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        // Support 'range' filter for date ranges
        if ($request->filled('range')) {
            $now = now();
            match ($request->range) {
                'today' => $query->whereDate('created_at', $now->toDateString()),
                'week' => $query->where('created_at', '>=', $now->startOfWeek()),
                'month' => $query->where('created_at', '>=', $now->startOfMonth()),
                default => null,
            };
        }

        $perPage = min((int) $request->input('per_page', 25), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    private const REVEALABLE_MODELS = [
        'User' => \App\Models\User::class,
    ];

    private const REVEALABLE_FIELDS = [
        'User' => ['email', 'phone'],
    ];

    public function revealField(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'fields' => 'required|array',
            'reason' => 'required|string|min:5',
        ]);

        // Allowlist: only permit known model types and fields
        $modelKey = $request->model_type;
        // Accept both short name ("User") and FQCN ("App\Models\User")
        if (isset(self::REVEALABLE_MODELS[$modelKey])) {
            $shortKey = $modelKey;
            $modelClass = self::REVEALABLE_MODELS[$modelKey];
        } else {
            $shortKey = array_search($modelKey, self::REVEALABLE_MODELS, true);
            $modelClass = $shortKey !== false ? $modelKey : null;
        }

        if (!$modelClass) {
            return response()->json(['error' => 'Model type is not revealable.'], 422);
        }
        $allowedFields = self::REVEALABLE_FIELDS[$shortKey] ?? [];
        $requestedFields = array_intersect($request->fields, $allowedFields);

        if (empty($requestedFields)) {
            return response()->json(['error' => 'None of the requested fields are revealable.'], 422);
        }

        $record = $modelClass::find($request->model_id);
        if (!$record) {
            return response()->json(['error' => 'Record not found.'], 404);
        }

        // Scope check: admin must have a permission scope that covers this record.
        // For User reveals: the admin needs full scope, or a scope whose course/class
        // intersects with the target user's enrollments (permission scopes).
        $admin = $request->user();
        $hasFullScope = $admin->permissionScopes()->where('scope_type', 'full')->exists();
        if (!$hasFullScope) {
            if ($modelClass === \App\Models\User::class) {
                // Check if target user shares any scoped course/class with this admin
                $adminClassIds = $admin->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
                $adminCourseIds = $admin->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
                $targetClassIds = $record->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
                $targetCourseIds = $record->permissionScopes()->whereNotNull('course_id')->pluck('course_id');

                $hasOverlap = $adminClassIds->intersect($targetClassIds)->isNotEmpty()
                    || $adminCourseIds->intersect($targetCourseIds)->isNotEmpty();

                if (!$hasOverlap) {
                    return response()->json(['error' => 'You do not have scope to reveal this record.'], 403);
                }
            }
        }

        $this->scopeService->revealSensitiveField(
            $admin, $modelClass, $request->model_id, $requestedFields, $request->reason
        );

        $data = [];
        foreach ($requestedFields as $field) {
            $data[$field] = $record->$field ?? null;
        }

        return response()->json(['revealed' => $data]);
    }

    public function stats(Request $request)
    {
        return response()->json([
            'total_users' => \App\Models\User::count(),
            'total_resources' => \App\Models\Resource::count(),
            'total_members' => \App\Models\Membership::where('status', 'active')->count(),
            'active_loans' => \App\Models\LoanRequest::where('status', 'checked_out')->count(),
            'pending_approvals' => \App\Models\LoanRequest::where('status', 'pending')->count()
                + \App\Models\ReservationRequest::where('status', 'pending')->count(),
            'active_holds' => \App\Models\Hold::where('status', 'active')->count(),
            'overdue_items' => \App\Models\Checkout::whereNull('returned_at')->where('due_date', '<', now())->count(),
            'recent_audit' => \App\Models\AuditLog::latest()->limit(10)->get(),
        ]);
    }

    public function createHold(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'hold_type' => 'required|in:manual,system',
            'reason' => 'required|string|min:5',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $hold = Hold::create([
            'user_id' => $request->user_id,
            'hold_type' => $request->hold_type,
            'reason' => $request->reason,
            'status' => 'active',
            'triggered_at' => now(),
            'expires_at' => $request->expires_at,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'hold_created',
            'auditable_type' => Hold::class,
            'auditable_id' => $hold->id,
        ]);

        return response()->json($hold);
    }

    public function userScopes(Request $request)
    {
        $request->validate(['user' => 'required']);
        $user = User::where('username', $request->input('user'))->orWhere('id', $request->input('user'))->first();
        if (!$user) {
            return response()->json(['data' => []]);
        }
        return response()->json([
            'user' => new \App\Http\Resources\UserResource($user),
            'data' => PermissionScope::where('user_id', $user->id)->with(['course', 'classModel', 'assignment', 'department'])->get(),
        ]);
    }

    public function deleteScope(Request $request, PermissionScope $scope)
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'scope_deleted',
            'auditable_type' => PermissionScope::class,
            'auditable_id' => $scope->id,
            'old_values' => $scope->toArray(),
        ]);
        $scope->delete();
        return response()->json(['message' => 'Scope deleted.']);
    }

    public function exportAuditLogs(Request $request)
    {
        $query = AuditLog::query();
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('action')) $query->where('action', $request->action);
        if ($request->filled('from')) $query->where('created_at', '>=', $request->from);
        if ($request->filled('to')) $query->where('created_at', '<=', $request->to);

        $logs = $query->orderByDesc('created_at')->limit(5000)->get();
        $csv = "id,user_id,action,auditable_type,auditable_id,created_at\n";
        foreach ($logs as $log) {
            $csv .= "{$log->id},{$log->user_id},{$log->action},{$log->auditable_type},{$log->auditable_id},{$log->created_at}\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=audit_logs.csv');
    }
}