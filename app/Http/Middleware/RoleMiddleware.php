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

            if ($request->user()->hasRole('student')) {
                abort(403, 'Access Denied: Students are not allowed in the Admin area.');
            }

            if ($request->user()->hasRole('admin')) {
                abort(403, 'Access Denied: Admins should not be in the Voting area.');
            }

            abort(403, 'Unauthorized Access.');
        }

        return $next($request);
    }
}
