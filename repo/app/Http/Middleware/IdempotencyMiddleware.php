<?php
namespace App\Http\Middleware;

use Closure;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $key = $request->header('X-Idempotency-Key');

        // Require idempotency key on all state-changing requests
        if (!$key) {
            // No exemptions — all state-changing requests require idempotency keys per prompt.
            return response()->json([
                'error' => 'X-Idempotency-Key header is required for state-changing requests.',
            ], 422);
        }

        $userId = $request->user()?->id;
        $route = $request->path();
        $payloadHash = hash('sha256', json_encode($request->except(['_token'])));

        // Scope lookup by user_id + route + key
        $existing = IdempotencyKey::where('key', $key)
            ->where('user_id', $userId)
            ->where('route', $route)
            ->first();

        if ($existing) {
            if ($existing->payload_hash !== $payloadHash) {
                Log::channel('operations')->warning('Idempotency conflict', [
                    'key' => $key, 'user_id' => $userId, 'route' => $route,
                ]);
                return response()->json([
                    'error' => 'Idempotency key conflict: different payload for same key.',
                ], 409);
            }
            // Replay stored response
            return response()->json($existing->response_body, $existing->response_code);
        }

        $response = $next($request);

        try {
            // Never persist auth tokens in idempotency response snapshots
            $responseBody = json_decode($response->getContent(), true);
            if (str_starts_with($route, 'api/auth/')) {
                $responseBody = ['_replayed' => true, '_status' => $response->getStatusCode()];
            }

            IdempotencyKey::create([
                'key' => $key,
                'user_id' => $userId,
                'route' => $route,
                'payload_hash' => $payloadHash,
                'response_code' => $response->getStatusCode(),
                'response_body' => $responseBody,
                'expires_at' => now()->addHours(24),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Ignore duplicate key errors from race conditions
        }

        return $response;
    }
}
