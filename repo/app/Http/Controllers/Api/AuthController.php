<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Auth\AuthService;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->authenticate($request->username, $request->password);
        $user->load('roles', 'membership.tier');
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => new \App\Http\Resources\UserResource($user),
            'token' => $token,
            'force_password_change' => $user->force_password_change,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if (method_exists($token, 'delete')) {
            $token->delete();
        }
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles', 'membership.tier');

        // Active reminders: prefer persisted reminder_events (created by ProcessReminders job),
        // fall back to computed checkout count for real-time accuracy before the job runs.
        $persistedCount = \App\Models\ReminderEvent::where('user_id', $user->id)
            ->whereNull('acknowledged_at')
            ->whereIn('reminder_type', ['upcoming_due', 'overdue'])
            ->count();

        $computedCount = \App\Models\Checkout::where('checked_out_to', $user->id)
            ->whereNull('returned_at')
            ->where('due_date', '<=', now()->addHours(48))
            ->count();

        $dueSoonCount = max($persistedCount, $computedCount);

        $resource = new \App\Http\Resources\UserResource($user);

        return response()->json(array_merge(
            $resource->resolve($request),
            ['reminders_count' => $dueSoonCount]
        ));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        if ($request->user()->force_password_change) {
            $this->authService->forcePasswordChange($request->user(), $request->new_password);
        } else {
            $this->authService->changePassword($request->user(), $request->current_password, $request->new_password);
        }

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
