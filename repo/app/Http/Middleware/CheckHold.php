<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckHold
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->hasActiveHold()) {
            return response()->json([
                'error' => 'Your account has an active hold. Contact an administrator.',
            ], 403);
        }
        return $next($request);
    }
}
