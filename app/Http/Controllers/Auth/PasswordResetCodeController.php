<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PasswordResetCodeController extends Controller
{
    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:utilisateurs,email'
        ]);

        // Vérifier que l'email existe
        $utilisateur = Utilisateur::where('email', $validated['email'])->first();
        if (!$utilisateur) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur trouvé avec cet email'
            ], 404);
        }

        $code = rand(100000, 999999);

        // Supprimer les anciens codes pour cet email
        PasswordResetCode::where('email', $validated['email'])->delete();

        // Créer un nouveau code
        PasswordResetCode::create([
            'email' => $validated['email'],
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        try {
            Log::info("Tentative d'envoi d'email à: " . $validated['email']);

            Mail::send('emails.reset-code', ['code' => $code], function ($message) use ($validated) {
                $message->to($validated['email'])
                        ->subject('Réinitialisation de mot de passe - Vente-Ntsika Platforme');
            });

            Log::info("Email envoyé avec succès à: " . $validated['email']);

            return response()->json([
                'success' => true,
                'message' => 'Un code de réinitialisation a été envoyé à votre email'
            ], 200);

        } catch (\Exception $e) {
            Log::error("Erreur envoi email: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function verifyCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6'
        ]);

        $resetCode = PasswordResetCode::where('email', $validated['email'])
            ->where('code', $validated['code'])
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$resetCode) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code valide'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|confirmed|min:8'
        ]);

        $resetCode = PasswordResetCode::where('email', $validated['email'])
            ->where('code', $validated['code'])
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$resetCode) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré'
            ], 400);
        }

        // Mettre à jour le mot de passe
        $utilisateur = Utilisateur::where('email', $validated['email'])->first();
        $utilisateur->update([
            'mot_de_passe' => Hash::make($validated['password'])
        ]);

        // Supprimer le code utilisé
        $resetCode->delete();

        // ENVOYER L'EMAIL DE CONFIRMATION
        try {
            Mail::send('emails.password-changed-confirmation', [], function ($message) use ($validated) {
                $message->to($validated['email'])
                        ->subject('Mot de passe modifié - Vente-Ntsika Platforme');
            });

            Log::info("Email de confirmation de modification de mot de passe envoyé à: " . $validated['email']);

        } catch (\Exception $e) {
            Log::error("Erreur envoi email de confirmation: " . $e->getMessage());
            // On ne retourne pas d'erreur ici car le mot de passe a bien été changé
        }

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès. Un email de confirmation a été envoyé.'
        ], 200);
    }
}
