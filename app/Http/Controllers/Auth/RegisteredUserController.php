<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use App\Models\Vendeur;
use App\Models\Commercant;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RegisteredUserController extends Controller
{
    // Étape 1: Saisie des informations personnelles + vérification email
    public function step1PersonalInfo(Request $request)
    {
        $validated = $request->validate([
            'prenomUtilisateur' => 'required|string|max:255',
            'nomUtilisateur'    => 'required|string|max:255',
            'tel'               => 'required|string|max:20',
            'type_utilisateur'  => 'required|in:vendeur,entreprise',
            'email'             => 'required|string|email|max:255|unique:utilisateurs,email',
        ]);

        try {
            // Générer un code à 6 chiffres
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Préparer les données à stocker
            $dataToStore = [
                'prenomUtilisateur' => $validated['prenomUtilisateur'],
                'nomUtilisateur' => $validated['nomUtilisateur'],
                'tel' => $validated['tel'],
                'type_utilisateur' => $validated['type_utilisateur']
            ];

            // Stocker toutes les infos + code avec expiration (15 minutes)
            // CORRECTION: Ne plus utiliser json_encode(), Laravel le fait automatiquement
            EmailVerification::updateOrCreate(
                ['email' => $validated['email']],
                [
                    'code' => $verificationCode,
                    'expires_at' => Carbon::now()->addMinutes(15),
                    'attempts' => 0,
                    'verified' => false,
                    'data' => $dataToStore // Laravel encode automatiquement si 'data' est casté en 'array'
                ]
            );

            // Envoyer l'email avec le code
            try {
                Mail::send('emails.verification', [
                    'code' => $verificationCode,
                    'prenom' => $validated['prenomUtilisateur']
                ], function ($message) use ($validated) {
                    $message->to($validated['email'])
                            ->subject('Code de vérification - Vente-Ntsika');
                });

                Log::info("Code de vérification envoyé à: " . $validated['email']);

                return response()->json([
                    'success' => true,
                    'message' => 'Code de vérification envoyé avec succès',
                    'email' => $validated['email'],
                    'type_utilisateur' => $validated['type_utilisateur']
                ], 200);

            } catch (\Exception $e) {
                Log::error("Erreur envoi email vérification: " . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi du code de vérification'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement des informations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Étape 2: Vérification du code
    public function step2VerifyCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string|size:6'
        ]);

        try {
            $verification = EmailVerification::where('email', $validated['email'])->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun code de vérification trouvé pour cet email'
                ], 400);
            }

            // Vérifier si le code a expiré
            if (Carbon::now()->gt($verification->expires_at)) {
                $verification->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Le code de vérification a expiré'
                ], 400);
            }

            // Vérifier le code
            if ($verification->code !== $validated['code']) {
                // Incrémenter le compteur de tentatives
                $verification->increment('attempts');

                if ($verification->attempts >= 3) {
                    $verification->delete();
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives échouées. Veuillez recommencer l\'inscription'
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Code de vérification incorrect',
                    'attempts_remaining' => 3 - $verification->attempts
                ], 400);
            }

            // Code correct - marquer comme vérifié
            $verification->update(['verified' => true]);

            // CORRECTION: Plus besoin de json_decode(), Laravel le fait automatiquement
            $data = $verification->data;

            // VÉRIFIER SI $data EST VALIDE
            if (!$data || !isset($data['type_utilisateur'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données d\'inscription corrompues. Veuillez recommencer.'
                ], 400);
            }

            $nextStep = $data['type_utilisateur'] === 'entreprise' ? 'step3_entreprise_info' : 'step4_password';

            return response()->json([
                'success' => true,
                'message' => 'Email vérifié avec succès',
                'email' => $validated['email'],
                'type_utilisateur' => $data['type_utilisateur'],
                'next_step' => $nextStep
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Étape 3: Informations entreprise (seulement pour type_utilisateur = "entreprise")
    public function step3EntrepriseInfo(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'nom_entreprise' => 'required|string|max:255',
            'adresse_entreprise' => 'required|string|max:500',
        ]);

        try {
            // Vérifier que l'email a été vérifié
            $verification = EmailVerification::where('email', $validated['email'])
                                            ->where('verified', true)
                                            ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email non vérifié. Veuillez compléter la vérification d\'email d\'abord.'
                ], 400);
            }

            // CORRECTION: Plus besoin de json_decode()
            $data = $verification->data;
            $data['nom_entreprise'] = $validated['nom_entreprise'];
            $data['adresse_entreprise'] = $validated['adresse_entreprise'];

            // CORRECTION: Plus besoin de json_encode()
            $verification->update(['data' => $data]);

            return response()->json([
                'success' => true,
                'message' => 'Informations entreprise enregistrées avec succès',
                'email' => $validated['email'],
                'next_step' => 'step4_password'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement des informations entreprise',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Étape 4: Mot de passe
    public function step4Password(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'mot_de_passe' => 'required|string|confirmed|min:8',
            'adresse_personnelle' => 'nullable|string|max:500',
        ]);

        try {
            // Vérifier que l'email a été vérifié
            $verification = EmailVerification::where('email', $validated['email'])
                                            ->where('verified', true)
                                            ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email non vérifié. Veuillez compléter la vérification d\'email d\'abord.'
                ], 400);
            }

            // CORRECTION: Plus besoin de json_decode()
            $data = $verification->data;
            $data['mot_de_passe'] = Hash::make($validated['mot_de_passe']);
            $data['adresse_personnelle'] = $validated['adresse_personnelle'];

            // CORRECTION: Plus besoin de json_encode()
            $verification->update(['data' => $data]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe enregistré avec succès',
                'email' => $validated['email'],
                'next_step' => 'step5_finalize'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Étape 5: Finalisation de l'inscription
    public function step5Finalize(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'logo_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            // Vérifier que l'email a été vérifié
            $verification = EmailVerification::where('email', $validated['email'])
                                            ->where('verified', true)
                                            ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email non vérifié. Veuillez compléter la vérification d\'email d\'abord.'
                ], 400);
            }

            // CORRECTION: Plus besoin de json_decode()
            $data = $verification->data;

            DB::beginTransaction();

            // 1. Création de l'utilisateur
            $utilisateur = Utilisateur::create([
                'prenomUtilisateur' => $data['prenomUtilisateur'],
                'nomUtilisateur'    => $data['nomUtilisateur'],
                'email'             => $validated['email'],
                'tel'               => $data['tel'],
                'mot_de_passe'      => $data['mot_de_passe'],
                'idRole'            => $data['type_utilisateur'] === 'entreprise' ? 2 : 1,
                'email_verified_at' => Carbon::now(),
            ]);

            // 2. Gestion du logo/image
            $logoPath = null;
            if ($request->hasFile('logo_image')) {
                $logoPath = $request->file('logo_image')->store('logos', 'public');
            }

            // 3. Création du vendeur
            $nomEntreprise = $data['type_utilisateur'] === 'entreprise'
                ? $data['nom_entreprise']
                : 'Projet Personnel de ' . $data['prenomUtilisateur'];

            $adresseEntreprise = $data['type_utilisateur'] === 'entreprise'
                ? $data['adresse_entreprise']
                : ($data['adresse_personnelle'] ?? '');

            $vendeur = Vendeur::create([
                'idUtilisateur'          => $utilisateur->idUtilisateur,
                'nom_entreprise'         => $nomEntreprise,
                'adresse_entreprise'     => $adresseEntreprise,
                'description'            => null,
                'logo_image'             => $logoPath,
                'statut_validation'      => 'valide',
                'commission_pourcentage' => 0,
            ]);

            // 4. Création du commercant
            $commercant = Commercant::create([
                'idUtilisateur'      => $utilisateur->idUtilisateur,
                'nom_entreprise'     => $nomEntreprise,
                'description'        => null,
                'adresse'            => $adresseEntreprise,
                'email'              => $validated['email'],
                'telephone'          => $data['tel'],
                'statut_validation'  => 'en_attente',
            ]);

            // Supprimer la vérification après inscription réussie
            $verification->delete();

            DB::commit();

            // ENVOYER L'EMAIL DE BIENVENUE
            try {
                Mail::send('emails.welcome', [
                    'prenom' => $data['prenomUtilisateur'],
                    'nom' => $data['nomUtilisateur'],
                    'type_utilisateur' => $data['type_utilisateur'],
                    'nom_entreprise' => $nomEntreprise
                ], function ($message) use ($validated) {
                    $message->to($validated['email'])
                            ->subject('Bienvenue sur Vente-Ntsika Platforme !');
                });

                Log::info("Email de bienvenue envoyé à: " . $validated['email']);

            } catch (\Exception $e) {
                Log::error("Erreur envoi email de bienvenue: " . $e->getMessage());
            }

            // Authentification
            Auth::login($utilisateur);

            // Création du token Sanctum
            $token = $utilisateur->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Compte créé avec succès. Bienvenue !',
                'user' => $utilisateur,
                'vendeur' => $vendeur,
                'commercant' => $commercant,
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Nettoyer le fichier uploadé en cas d'erreur
            if (isset($logoPath) && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la finalisation de l\'inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Renvoyer le code
    public function resendCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email'
        ]);

        // Récupérer les données existantes
        $verification = EmailVerification::where('email', $validated['email'])->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune inscription en cours trouvée pour cet email'
            ], 400);
        }

        // Régénérer le code
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification->update([
            'code' => $verificationCode,
            'expires_at' => Carbon::now()->addMinutes(15),
            'attempts' => 0
        ]);

        // Envoyer le nouvel email
        try {
            // CORRECTION: Plus besoin de json_decode()
            $data = $verification->data;

            Mail::send('emails.verification', [
                'code' => $verificationCode,
                'prenom' => $data['prenomUtilisateur']
            ], function ($message) use ($validated) {
                $message->to($validated['email'])
                        ->subject('Nouveau code de vérification - Vente-Ntsika');
            });

            return response()->json([
                'success' => true,
                'message' => 'Nouveau code de vérification envoyé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du nouveau code'
            ], 500);
        }
    }
}
