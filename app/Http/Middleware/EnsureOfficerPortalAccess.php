<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficerPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isAdminRole()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isRoleBasedApprover()) {
            return redirect()->route('approver.dashboard');
        }

        return $next($request);
    }
}

