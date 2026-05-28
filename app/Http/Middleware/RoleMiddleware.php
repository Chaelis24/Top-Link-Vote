<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->hasRole($role)) {

            $user = $request->user();

            if ($user->hasRole('student')) {
                abort(403, 'Unauthorized Access: The administrative portal is restricted to authorized personnel only.');
            }

            if ($user->hasRole('admin')) {
                abort(403, 'Unauthorized Access: Administrative accounts are not authorized to participate in the voting process.');
            }

            abort(403, 'Unauthorized Access.');
        }

        return $next($request);
    }
}
