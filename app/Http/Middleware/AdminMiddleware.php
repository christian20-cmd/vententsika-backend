<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        $user = auth()->user();

        // Vérifiez si l'utilisateur a une relation administrateur
        if (!$user->administrateur) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Droits administrateur requis.'
            ], 403);
        }

        // Vérifiez si l'administrateur est actif
        if (!$user->administrateur->est_actif) {
            return response()->json([
                'success' => false,
                'message' => 'Compte administrateur désactivé'
            ], 403);
        }

        return $next($request);
    }
}
