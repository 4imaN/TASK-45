<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->force_password_change) {
            if (!$request->is('api/auth/change-password') && !$request->is('api/auth/logout')) {
                return response()->json([
                    'error' => 'Password change required.',
                    'force_password_change' => true,
                ], 403);
            }
        }
        return $next($request);
    }
}
