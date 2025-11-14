<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Trouver l'utilisateur par email
        $utilisateur = Utilisateur::where('email', $request->email)->first();

        // Vérifier le mot de passe
        if (!$utilisateur || !Hash::check($request->password, $utilisateur->mot_de_passe)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        // Authentifier l'utilisateur
        Auth::login($utilisateur);

        // 🔥 METTRE À JOUR LA DERNIÈRE CONNEXION ICI
        $utilisateur->update([
            'derniere_connexion' => now(),
            'ip_connexion' => request()->ip()
        ]);

        // CRÉER LE TOKEN SANCTUM
        $token = $utilisateur->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => $utilisateur,
            'token' => $token
        ], 200);
    }

    public function logout(Request $request)
    {
        // CORRECTION : Supprimer seulement le token Sanctum (pas de session pour API)
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ], 200);
    }
}

