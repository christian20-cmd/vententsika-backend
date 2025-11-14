<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminActifMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->administrateur || !$user->administrateur->est_actif) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Votre compte administrateur n\'est pas actif.'
            ], 403);
        }

        return $next($request);
    }
}
