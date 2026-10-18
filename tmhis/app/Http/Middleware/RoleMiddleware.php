<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('*/api/*') || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Standardized Role-Based Access Control
        $hasAccess = $user instanceof \App\Models\User 
            ? $user->hasRole(...$roles) 
            : in_array(strtolower((string)($user->Role ?? '')), array_map('strtolower', $roles), true);

        if (!$hasAccess) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('*/api/*') || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access Denied: You do not have permission to access this resource.'
                ], 403);
            }

            // Redirect logged-in user to their authorized portal instead of kicking them out to login
            $redirectUrl = match (true) {
                $user instanceof \App\Models\User && $user->hasRole('chief')    => url('/director'),
                $user instanceof \App\Models\User && $user->hasRole('admin')    => url('/admin'),
                $user instanceof \App\Models\User && $user->hasRole('records')  => url('/records'),
                $user instanceof \App\Models\User && $user->hasRole('doctor')   => url('/doctor'),
                $user instanceof \App\Models\User && $user->hasRole('nurse')    => url('/nurse'),
                $user instanceof \App\Models\User && $user->hasRole('medtech')  => url('/medtech'),
                $user instanceof \App\Models\User && $user->hasRole('pharmacy') => url('/pharmacy'),
                $user instanceof \App\Models\User && $user->hasRole('billing')  => url('/billing'),
                $user instanceof \App\Models\User && $user->hasRole('register') => url('/register'),
                default                                                         => url('/')
            };

            return redirect($redirectUrl)->with('error', 'Access Denied: You do not have permission to access that section.');
        }

        return $next($request);
    }
}
