<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\{Hold, InterventionLog};

class RequestFrequencyGuard
{
    const MAX_REQUESTS = 5;
    const WINDOW_MINUTES = 10;

    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // Exempt auth endpoints
        if (str_starts_with($request->path(), 'api/auth/')) {
            return $next($request);
        }

        // Count actual incoming state-changing requests using a dedicated cache counter.
        // This is independent of audit log entries, so it reliably tracks all qualifying requests.
        $cacheKey = "req_freq:{$user->id}";
        $recentCount = (int) Cache::get($cacheKey, 0);

        if ($recentCount >= self::MAX_REQUESTS) {
            // Hold stage
            $existingHold = Hold::where('user_id', $user->id)
                ->where('hold_type', 'frequency')
                ->where('status', 'active')
                ->exists();

            if (!$existingHold) {
                $hold = Hold::create([
                    'user_id' => $user->id,
                    'hold_type' => 'frequency',
                    'reason' => 'More than ' . self::MAX_REQUESTS . ' state-changing requests in ' . self::WINDOW_MINUTES . ' minutes.',
                    'status' => 'active',
                    'triggered_at' => now(),
                    'expires_at' => now()->addHours(1),
                ]);

                InterventionLog::create([
                    'user_id' => $user->id,
                    'action_type' => 'hold_frequency',
                    'reason' => $hold->reason,
                    'details' => ['hold_id' => $hold->id, 'request_count' => $recentCount],
                ]);
            }

            Log::channel('operations')->warning('Request frequency hold triggered', [
                'user_id' => $user->id, 'request_count' => $recentCount,
            ]);

            return response()->json([
                'error' => 'Too many requests. A temporary hold has been placed on your account.',
            ], 429);
        }

        // Increment counter before processing the request
        if ($recentCount === 0) {
            Cache::put($cacheKey, 1, now()->addMinutes(self::WINDOW_MINUTES));
        } else {
            Cache::increment($cacheKey);
        }

        $response = $next($request);

        // Warning stage: approaching limit
        if ($recentCount >= self::MAX_REQUESTS - 1) {
            $response->headers->set('X-Rate-Warning', 'You are approaching the request rate limit.');
        }

        return $response;
    }
}
