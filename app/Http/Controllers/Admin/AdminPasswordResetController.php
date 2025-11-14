<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrateur;
use App\Models\AdminPasswordResetCode;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminPasswordResetController extends Controller
{
    // ===== DEMANDE DE RÉINITIALISATION =====
    public function demandeReset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        try {
            Log::info("📧 Demande reset pour: " . $request->email);

            // Vérifier si l'email existe comme administrateur
            $admin = Administrateur::whereHas('utilisateur', function($query) use ($request) {
                $query->where('email', $request->email);
            })->first();

            if (!$admin) {
                Log::warning("❌ Admin non trouvé: " . $request->email);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun compte administrateur trouvé avec cet email'
                ], 404);
            }

            Log::info("✅ Admin trouvé: " . $admin->idAdministrateur);

            // TEMPORAIRE: Commenter le nettoyage pour tester
            // AdminPasswordResetCode::cleanExpiredCodes($request->email);

            // Générer un code à 6 chiffres
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Log::info("🔑 Code généré: " . $code);

            // Créer le code de reset
            $resetCode = AdminPasswordResetCode::create([
                'email' => $request->email,
                'code' => $code,
                'expires_at' => Carbon::now()->addMinutes(15),
            ]);

            Log::info("✅ Code sauvegardé en base");

            // TEMPORAIRE: Retourner le code directement
            $this->envoyerCodeReset($admin, $code);

            return response()->json([
                'success' => true,
                'message' => 'Code de réinitialisation envoyé avec succès',
                'expires_in' => 15
            ]);

        } catch (\Exception $e) {
            Log::error("💥 Erreur complète: " . $e->getMessage());
            Log::error("💥 Stack trace: " . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de réinitialisation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== VÉRIFIER LE CODE =====
    public function verifierCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6'
        ]);

        try {
            $resetCode = AdminPasswordResetCode::where('email', $request->email)
                ->where('code', $request->code)
                ->where('expires_at', '>', now())
                ->where('is_used', false)
                ->first();

            if (!$resetCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code invalide ou expiré'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Code valide',
                'can_reset' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== RÉINITIALISER LE MOT DE PASSE =====
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed'
        ]);

        DB::beginTransaction();
        try {
            // Vérifier le code
            $resetCode = AdminPasswordResetCode::where('email', $request->email)
                ->where('code', $request->code)
                ->where('expires_at', '>', now())
                ->where('is_used', false)
                ->first();

            if (!$resetCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code invalide ou expiré'
                ], 400);
            }

            // Trouver l'admin et l'utilisateur
            $admin = Administrateur::whereHas('utilisateur', function($query) use ($request) {
                $query->where('email', $request->email);
            })->first();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrateur non trouvé'
                ], 404);
            }

            // Mettre à jour le mot de passe
            $admin->utilisateur->update([
                'mot_de_passe' => Hash::make($request->password)
            ]);

            // Marquer le code comme utilisé
            $resetCode->markAsUsed();

            // Envoyer email de confirmation
            $this->envoyerConfirmationReset($admin);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== ENVOYER LE CODE PAR EMAIL =====
       // ===== ENVOYER LE CODE PAR EMAIL =====
    private function envoyerCodeReset($admin, $code)
    {
        try {
            $nom_complet = $admin->utilisateur->prenomUtilisateur . ' ' . $admin->utilisateur->nomUtilisateur;
            $email = $admin->utilisateur->email;

            $data = [
                'nom_complet' => $nom_complet,
                'code' => $code,
                'expiration' => 15, // minutes
            ];

            Mail::send('emails.admin-reset-code', $data, function ($message) use ($email, $nom_complet) {
                $message->to($email, $nom_complet)
                        ->subject('🔐 Code de réinitialisation - Vente-Ntsika Admin');
            });

            Log::info("✅ Code reset envoyé à: " . $email);
            return true;

        } catch (\Exception $e) {
            Log::error("❌ Erreur envoi code reset: " . $e->getMessage());
            return false;
        }
    }

    // ===== ENVOYER CONFIRMATION RÉINITIALISATION =====
    private function envoyerConfirmationReset($admin)
    {
        try {
            $nom_complet = $admin->utilisateur->prenomUtilisateur . ' ' . $admin->utilisateur->nomUtilisateur;
            $email = $admin->utilisateur->email;

            $data = [
                'nom_complet' => $nom_complet,
                'date_reinitialisation' => now()->format('d/m/Y à H:i'),
            ];

            Mail::send('emails.admin-password-changed', $data, function ($message) use ($email, $nom_complet) {
                $message->to($email, $nom_complet)
                        ->subject('✅ Mot de passe modifié - Vente-Ntsika Admin');
            });

            Log::info("✅ Confirmation reset envoyée à: " . $email);
            return true;

        } catch (\Exception $e) {
            Log::error("❌ Erreur envoi confirmation reset: " . $e->getMessage());
            return false;
        }
    }
}