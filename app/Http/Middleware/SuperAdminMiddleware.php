<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->administrateur || !$user->administrateur->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Seuls les super administrateurs peuvent effectuer cette action.'
            ], 403);
        }

        return $next($request);
    }
}
