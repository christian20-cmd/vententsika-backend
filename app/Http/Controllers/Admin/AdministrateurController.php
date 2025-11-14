<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrateur;
use App\Models\Utilisateur;
use App\Models\Vendeur;
use App\Models\Client;
use App\Models\AdminDemande;
use App\Models\AdminInvitation;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdministrateurController extends Controller
{
    // ===== INSCRIPTION ADMINISTRATEUR (Lien public) =====

    // ===== INSCRIPTION ADMINISTRATEUR (Lien public) =====
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prenomUtilisateur' => 'required|string|max:255',
            'nomUtilisateur' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:utilisateurs,email',
            'tel' => 'required|string|max:20',
            'mot_de_passe' => 'required|string|confirmed|min:8',
            'niveau_acces' => 'sometimes|in:super_admin,admin,moderateur',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // ⭐⭐ VÉRIFICATION : Vérifier si l'email existe déjà comme vendeur ou client
            $email = $request->email;

            // Vérifier si un vendeur existe avec cet email
            $vendeurExistant = Vendeur::whereHas('utilisateur', function($query) use ($email) {
                $query->where('email', $email);
            })->first();

            if ($vendeurExistant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet email est déjà utilisé par un compte vendeur. Un vendeur ne peut pas devenir administrateur.',
                    'type_compte_existant' => 'vendeur',
                    'nom_entreprise' => $vendeurExistant->nom_entreprise
                ], 422);
            }

            // Vérifier si un client existe avec cet email
            $clientExistant = Client::where('email_client', $email)->first();

            if ($clientExistant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet email est déjà utilisé par un compte client. Un client ne peut pas devenir administrateur.',
                    'type_compte_existant' => 'client',
                    'nom_client' => $clientExistant->nom_prenom_client
                ], 422);
            }

            // Vérifier si un administrateur INACTIF existe déjà avec cet email
            $adminInactifExistant = Administrateur::whereHas('utilisateur', function($query) use ($email) {
                $query->where('email', $email);
            })
            ->where('est_actif', false)
            ->first();

            if ($adminInactifExistant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une demande d\'administration est déjà en attente avec cet email. Veuillez attendre la validation de votre demande.',
                    'statut_demande' => 'en_attente'
                ], 422);
            }

            $admin = Administrateur::creerAdministrateur($request->all(), [
                'niveau_acces' => $request->niveau_acces ?? 'admin'
            ]);

            // 🔥 AJOUT: Envoyer email de bienvenue
            $this->envoyerEmailBienvenue($admin);

            // Générer le token Sanctum
            $token = $admin->utilisateur->createToken('admin-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Compte administrateur créé avec succès',
                'admin' => $admin,
                'token' => $token,
                'email_envoye' => true
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte administrateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // ===== CRÉATION MANUELLE PAR ADMIN CONNECTÉ =====
    public function createAdmin(Request $request): JsonResponse
    {
        $adminConnecte = auth()->user()->administrateur;

        if (!$adminConnecte->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seul un super administrateur peut créer des comptes admin.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'prenomUtilisateur' => 'required|string|max:255',
            'nomUtilisateur' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:utilisateurs,email',
            'tel' => 'required|string|max:20',
            'mot_de_passe' => 'required|string|min:8',
            'niveau_acces' => 'required|in:super_admin,admin,moderateur',
            'permissions' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Administrateur::creerAdministrateur($request->all(), [
                'niveau_acces' => $request->niveau_acces,
                'permissions' => $request->permissions
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compte administrateur créé avec succès',
                'admin' => $admin
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte administrateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== LISTER TOUS LES ADMINISTRATEURS =====
    // ===== LISTER TOUS LES ADMINISTRATEURS =====
public function index(): JsonResponse
{
    try {
        $adminConnecte = auth()->user()->administrateur;

        $admins = Administrateur::with('utilisateur')
            ->where('idAdministrateur', '!=', $adminConnecte->idAdministrateur) // ⭐⭐ FILTRE: exclure l'admin connecté
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($admin) {
                return [
                    'idAdministrateur' => $admin->idAdministrateur,
                    'nom_complet' => $admin->nom_complet,
                    'email' => $admin->email,
                    'telephone' => $admin->telephone,
                    'niveau_acces' => $admin->niveau_acces,
                    'est_actif' => $admin->est_actif,
                    'est_en_ligne' => $admin->derniere_connexion && $admin->derniere_connexion->gte(now()->subMinutes(15)),
                    'derniere_connexion' => $admin->derniere_connexion,
                    'date_creation' => $admin->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $admins,
            'admin_connecte' => [ // ⭐⭐ OPTIONNEL: info sur l'admin connecté
                'id' => $adminConnecte->idAdministrateur,
                'nom_complet' => $adminConnecte->nom_complet,
                'niveau_acces' => $adminConnecte->niveau_acces
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des administrateurs',
            'error' => $e->getMessage()
        ], 500);
    }
}

// ===== STATISTIQUES ADMINISTRATEURS =====
public function statistiques(): JsonResponse
{
    try {
        $adminConnecte = auth()->user()->administrateur;

        $totalAdmins = Administrateur::count();
        $adminsEnLigne = Administrateur::enLigne()->count();
        $adminsActifs = Administrateur::actifs()->count();
        $superAdmins = Administrateur::where('niveau_acces', 'super_admin')->count();
        $adminsNormaux = Administrateur::where('niveau_acces', 'admin')->count();
        $moderateurs = Administrateur::where('niveau_acces', 'moderateur')->count();

        // ⭐⭐ AJOUT: Statistiques sans l'admin connecté
        $totalAdminsSansMoi = $totalAdmins - 1;
        $adminsEnLigneSansMoi = Administrateur::enLigne()
            ->where('idAdministrateur', '!=', $adminConnecte->idAdministrateur)
            ->count();
        $adminsActifsSansMoi = Administrateur::actifs()
            ->where('idAdministrateur', '!=', $adminConnecte->idAdministrateur)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_administrateurs' => $totalAdmins,
                'total_autres_administrateurs' => $totalAdminsSansMoi, // ⭐⭐ NOUVEAU
                'administrateurs_en_ligne' => $adminsEnLigne,
                'autres_administrateurs_en_ligne' => $adminsEnLigneSansMoi, // ⭐⭐ NOUVEAU
                'administrateurs_actifs' => $adminsActifs,
                'autres_administrateurs_actifs' => $adminsActifsSansMoi, // ⭐⭐ NOUVEAU
                'administrateurs_inactifs' => $totalAdmins - $adminsActifs,
                'repartition_niveaux' => [
                    'super_admin' => $superAdmins,
                    'admin' => $adminsNormaux,
                    'moderateur' => $moderateurs,
                ],
                'admin_connecte' => [ // ⭐⭐ INFO ADMIN CONNECTÉ
                    'id' => $adminConnecte->idAdministrateur,
                    'nom_complet' => $adminConnecte->nom_complet,
                    'niveau_acces' => $adminConnecte->niveau_acces,
                    'est_actif' => $adminConnecte->est_actif,
                    'est_en_ligne' => $adminConnecte->derniere_connexion && $adminConnecte->derniere_connexion->gte(now()->subMinutes(15)),
                ]
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du calcul des statistiques',
            'error' => $e->getMessage()
        ], 500);
    }
}
    // ===== METTRE À JOUR UN ADMINISTRATEUR =====
    public function update(Request $request, $id): JsonResponse
    {
        $adminConnecte = auth()->user()->administrateur;
        $adminAModifier = Administrateur::findOrFail($id);

        // Vérification des permissions
        if (!$adminConnecte->isSuperAdmin() && $adminConnecte->idAdministrateur != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'prenomUtilisateur' => 'sometimes|string|max:255',
            'nomUtilisateur' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:utilisateurs,email,' . $adminAModifier->utilisateur->idUtilisateur . ',idUtilisateur',
            'tel' => 'sometimes|string|max:20',
            'niveau_acces' => 'sometimes|in:super_admin,admin,moderateur',
            'permissions' => 'sometimes|array',
            'est_actif' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Mettre à jour l'utilisateur
            if ($request->hasAny(['prenomUtilisateur', 'nomUtilisateur', 'email', 'tel'])) {
                $adminAModifier->utilisateur->update($request->only([
                    'prenomUtilisateur', 'nomUtilisateur', 'email', 'tel'
                ]));
            }

            // Mettre à jour l'administrateur
            $adminAModifier->update($request->only([
                'niveau_acces', 'permissions', 'est_actif'
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Administrateur mis à jour avec succès',
                'admin' => $adminAModifier->load('utilisateur')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== SUPPRIMER UN ADMINISTRATEUR =====
    // ===== SUPPRIMER UN ADMINISTRATEUR =====
public function destroy($id): JsonResponse
{
    $adminConnecte = auth()->user()->administrateur;
    $adminASupprimer = Administrateur::findOrFail($id);

    // Empêcher l'auto-suppression
    if ($adminConnecte->idAdministrateur == $id) {
        return response()->json([
            'success' => false,
            'message' => 'Vous ne pouvez pas supprimer votre propre compte'
        ], 422);
    }

    // Seul un super admin peut supprimer
    if (!$adminConnecte->isSuperAdmin()) {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Seul un super administrateur peut supprimer des comptes.'
        ], 403);
    }

    try {
        DB::beginTransaction();

        // ⭐⭐ CORRECTION : D'abord supprimer les références dans admin_invitations
        \App\Models\AdminInvitation::where('utilise_par', $id)->update(['utilise_par' => null]);
        \App\Models\AdminInvitation::where('generer_par', $id)->update(['generer_par' => null]);

        // ⭐⭐ CORRECTION : Supprimer aussi les demandes associées
        \App\Models\AdminDemande::where('admin_validateur', $id)->update(['admin_validateur' => null]);

        // Supprimer l'administrateur et l'utilisateur associé (cascade)
        $adminASupprimer->delete();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Administrateur supprimé avec succès'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la suppression',
            'error' => $e->getMessage()
        ], 500);
    }
}
    // ===== RECHERCHER UN ADMINISTRATEUR =====
    // ===== RECHERCHER UN ADMINISTRATEUR =====
    // ===== RECHERCHER UN ADMINISTRATEUR =====
public function search(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'query' => 'required|string|min:2',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Query de recherche invalide',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $adminConnecte = auth()->user()->administrateur;
        $query = $request->input('query');

        $admins = Administrateur::with('utilisateur')
            ->where('idAdministrateur', '!=', $adminConnecte->idAdministrateur) // ⭐⭐ FILTRE: exclure l'admin connecté
            ->where(function($q) use ($query) {
                $q->whereHas('utilisateur', function ($userQuery) use ($query) {
                    $userQuery->where('prenomUtilisateur', 'like', "%{$query}%")
                    ->orWhere('nomUtilisateur', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('tel', 'like', "%{$query}%");
                })
                ->orWhere('niveau_acces', 'like', "%{$query}%");
            })
            ->get()
            ->map(function ($admin) {
                return [
                    'idAdministrateur' => $admin->idAdministrateur,
                    'nom_complet' => $admin->nom_complet,
                    'email' => $admin->email,
                    'telephone' => $admin->telephone,
                    'niveau_acces' => $admin->niveau_acces,
                    'est_actif' => $admin->est_actif,
                    'est_en_ligne' => $admin->derniere_connexion && $admin->derniere_connexion->gte(now()->subMinutes(15)),
                    'derniere_connexion' => $admin->derniere_connexion,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $admins
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la recherche',
            'error' => $e->getMessage()
        ], 500);
    }
}


    // ===== ENVOYER EMAIL DE BIENVENUE =====
    private function envoyerEmailBienvenue($admin, $motDePasse = null)
    {
        try {
            $data = [
                'nom_complet' => $admin->nom_complet,
                'email' => $admin->email,
                'niveau_acces' => $admin->niveau_acces,
                'mot_de_passe' => $motDePasse, // Seulement pour la création manuelle
                'date_creation' => now()->format('d/m/Y à H:i'),
            ];

            Mail::send('emails.admin-bienvenue', $data, function ($message) use ($admin) {
                $message->to($admin->email, $admin->nom_complet)
                        ->subject('👋 Bienvenue sur Vente-Ntsika - Votre compte administrateur');
            });

            Log::info("✅ Email de bienvenue envoyé à: " . $admin->email);

            return true;

        } catch (\Exception $e) {
            Log::error("❌ Erreur envoi email bienvenue: " . $e->getMessage());
            return false;
        }
    }








    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            // Trouver l'utilisateur par email
            $utilisateur = Utilisateur::where('email', $request->email)->first();

            // Vérifier si l'utilisateur existe et est un administrateur
            if (!$utilisateur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email ou mot de passe incorrect'
                ], 401);
            }

            // Vérifier si c'est un administrateur
            $admin = Administrateur::where('idUtilisateur', $utilisateur->idUtilisateur)->first();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }

            // ⭐⭐ CORRECTION : Vérifier si l'admin est actif
            if (!$admin->est_actif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte administrateur est en attente de validation. Vous ne pouvez pas vous connecter pour le moment.'
                ], 403);
            }

            // Vérifier le mot de passe
            if (!Hash::check($request->password, $utilisateur->mot_de_passe)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email ou mot de passe incorrect'
                ], 401);
            }

            // Authentifier l'utilisateur
            Auth::login($utilisateur);

            // Mettre à jour la dernière connexion
            $utilisateur->update([
                'derniere_connexion' => now(),
                'ip_connexion' => request()->ip()
            ]);

            // Créer le token Sanctum
            $token = $utilisateur->createToken('admin-auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion administrateur réussie',
                'user' => [
                    'id' => $admin->idAdministrateur,
                    'nom' => $utilisateur->nomUtilisateur,
                    'prenom' => $utilisateur->prenomUtilisateur,
                    'email' => $utilisateur->email,
                    'role' => 'admin'
                ],
                'token' => $token
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ⭐⭐ AJOUT: Méthode de déconnexion pour les administrateurs
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion administrateur réussie'
        ], 200);
    }










    // ===== GÉNÉRER UN LIEN D'INVITATION =====
    public function generateInvitationLink(Request $request): JsonResponse
    {
        // ⭐⭐ APPROCHE ULTIME - Toutes les méthodes
        $data = null;
        $rawInput = file_get_contents('php://input');

        Log::info('=== DÉBOGAGE COMPLET ===');
        Log::info('php://input:', [$rawInput]);
        Log::info('getContent():', [$request->getContent()]);
        Log::info('json()->all():', [$request->json()->all()]);
        Log::info('all():', [$request->all()]);
        Log::info('Headers:', $request->headers->all());

        // Essayer dans l'ordre
        if (!empty($rawInput)) {
            $data = json_decode($rawInput, true);
        } elseif (!empty($request->getContent())) {
            $data = json_decode($request->getContent(), true);
        } elseif (!empty($request->json()->all())) {
            $data = $request->json()->all();
        } elseif (!empty($request->all())) {
            $data = $request->all();
        }

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de lire les données. Problème de configuration serveur.',
                'debug' => [
                    'php_input' => $rawInput,
                    'getContent' => $request->getContent(),
                    'json_all' => $request->json()->all(),
                    'all' => $request->all()
                ]
            ], 422);
        }

        // ⭐⭐ AUTHENTIFICATION MANUELLE
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token manquant'
            ], 401);
        }

        // Valider le token Sanctum manuellement
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide'
            ], 401);
        }

        // Récupérer l'utilisateur
        $utilisateur = $token->tokenable;

        if (!$utilisateur || !$utilisateur->administrateur) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non autorisé ou non administrateur'
            ], 403);
        }

        $adminConnecte = $utilisateur->administrateur;

        if (!$adminConnecte->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seul un super administrateur peut générer des liens d\'invitation.'
            ], 403);
        }


         if (isset($data['email'])) {
        $email = $data['email'];

        // Vérifier si un vendeur existe avec cet email
        $vendeurExistant = Vendeur::whereHas('utilisateur', function($query) use ($email) {
            $query->where('email', $email);
        })->first();

        if ($vendeurExistant) {
            return response()->json([
                'success' => false,
                'message' => 'Cet email est déjà utilisé par un compte vendeur. Un vendeur ne peut pas être invité comme administrateur.',
                'type_compte_existant' => 'vendeur',
                'nom_entreprise' => $vendeurExistant->nom_entreprise
            ], 422);
        }

        // Vérifier si un client existe avec cet email
        $clientExistant = Client::where('email_client', $email)->first();

        if ($clientExistant) {
            return response()->json([
                'success' => false,
                'message' => 'Cet email est déjà utilisé par un compte client. Un client ne peut pas être invité comme administrateur.',
                'type_compte_existant' => 'client',
                'nom_client' => $clientExistant->nom_prenom_client
            ], 422);
        }

        // Vérification existante pour les administrateurs actifs
        $adminExistant = Administrateur::whereHas('utilisateur', function($query) use ($data) {
            $query->where('email', $data['email']);
        })
        ->where('est_actif', true)
        ->first();

        if ($adminExistant) {
            return response()->json([
                'success' => false,
                'message' => 'Un compte administrateur actif existe déjà avec cet email',
                'email' => $data['email'],
                'admin_existant' => [
                    'nom_complet' => $adminExistant->nom_complet,
                    'niveau_acces' => $adminExistant->niveau_acces,
                    'est_actif' => $adminExistant->est_actif
                ]
            ], 422);
        }

        // Désactiver les anciennes invitations pour le même email
        AdminInvitation::where('email', $data['email'])->update(['est_actif' => false]);
    }
        // Validation
        $validator = Validator::make($data, [
            'niveau_acces' => 'required|in:super_admin,admin,moderateur',
            'email' => 'sometimes|email|unique:utilisateurs,email',        ]);

            // ⭐⭐ VÉRIFICATION SUPPLÉMENTAIRE POUR PLUS DE SÉCURITÉ

            if (isset($data['email'])) {
                // Vérifier si un compte admin ACTIF existe déjà avec cet email
                $adminExistant = Administrateur::whereHas('utilisateur', function($query) use ($data) {
                    $query->where('email', $data['email']);
                })
                ->where('est_actif', true) // ⭐⭐ SEULEMENT les comptes ACTIFS
                ->first();

                if ($adminExistant) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Un compte administrateur actif existe déjà avec cet email',
                        'email' => $data['email'],
                        'admin_existant' => [
                            'nom_complet' => $adminExistant->nom_complet,
                            'niveau_acces' => $adminExistant->niveau_acces,
                            'est_actif' => $adminExistant->est_actif
                        ]
                    ], 422);
                }

                // Désactiver les anciennes invitations pour le même email
                AdminInvitation::where('email', $data['email'])->update(['est_actif' => false]);
            }
        try {
            DB::beginTransaction();

            if (isset($data['email'])) {
                AdminInvitation::where('email', $data['email'])->update(['est_actif' => false]);
            }

            $token = Str::uuid()->toString();
            $invitation = AdminInvitation::create([
                'token' => $token,
                'email' => $data['email'] ?? null,
                'niveau_acces' => $data['niveau_acces'],
                'generer_par' => $adminConnecte->idAdministrateur,
                'expire_a' => Carbon::now()->addMinutes(10),
                'est_actif' => true,
            ]);

            $invitationUrl = "http://localhost:3000/admin/register?token={$token}";
            if (isset($data['email'])) {
                $this->envoyerEmailInvitation($data['email'], $invitationUrl, $data['niveau_acces']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => isset($data['email'])
                    ? 'Lien d\'invitation généré et envoyé par email'
                    : 'Lien d\'invitation généré avec succès',
                'data' => [
                    'token' => $token,
                    'invitation_url' => $invitationUrl,
                    'expire_a' => $invitation->expire_a,
                    'email_envoye' => isset($data['email']),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du lien d\'invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // ===== RÉGÉNÉRER UN LIEN D'INVITATION =====
    public function regenerateInvitationLink(Request $request, $token): JsonResponse
    {
        $adminConnecte = auth()->user()->administrateur;

        if (!$adminConnecte->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Trouver l'ancienne invitation
            $oldInvitation = AdminInvitation::where('token', $token)->first();

            if (!$oldInvitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lien d\'invitation non trouvé'
                ], 404);
            }

            // Désactiver l'ancienne invitation
            $oldInvitation->update(['est_actif' => false]);

            // Générer un nouveau token
            $newToken = Str::uuid()->toString();

            // Créer la nouvelle invitation
            $newInvitation = AdminInvitation::create([
                'token' => $newToken,
                'email' => $oldInvitation->email,
                'niveau_acces' => $oldInvitation->niveau_acces,
                'generer_par' => $adminConnecte->idAdministrateur,
                'expire_a' => Carbon::now()->addMinutes(10),
                'est_actif' => true,
            ]);

            // Générer la nouvelle URL
            $newInvitationUrl = url("/admin/register?token={$newToken}");

            // Renvoyer l'email si un email était associé
            if ($oldInvitation->email) {
                $this->envoyerEmailInvitation($oldInvitation->email, $newInvitationUrl, $oldInvitation->niveau_acces);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $oldInvitation->email
                    ? 'Nouveau lien généré et envoyé par email'
                    : 'Nouveau lien généré avec succès',
                'data' => [
                    'token' => $newToken,
                    'invitation_url' => $newInvitationUrl,
                    'expire_a' => $newInvitation->expire_a,
                    'email_envoye' => !empty($oldInvitation->email),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la régénération du lien',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== TESTER LA CONFIGURATION EMAIL =====
public function testEmail(Request $request): JsonResponse
{
    try {
        $email = $request->email ?? 'christiannomenjanahary4@gmail.com';

        Mail::send([], [], function ($message) use ($email) {
            $message->to($email)
                    ->subject('🎉 Test Email Configuration - Vente-Ntsika')
                    ->html('<h1>Test réussi !</h1><p>Votre configuration email fonctionne correctement.</p>');
        });

        Log::info("✅ Email de test envoyé à: " . $email);

        return response()->json([
            'success' => true,
            'message' => 'Email de test envoyé avec succès'
        ]);

    } catch (\Exception $e) {
        Log::error("❌ Erreur envoi email test: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'envoi de l\'email',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // ===== VALIDER UN TOKEN D'INVITATION =====
    public function validateInvitationToken($token): JsonResponse
    {
        try {
            $invitation = AdminInvitation::where('token', $token)
                ->where('est_actif', true)
                ->where('expire_a', '>', Carbon::now())
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lien d\'invitation invalide ou expiré',
                    'is_valid' => false
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lien d\'invitation valide',
                'data' => [
                    'is_valid' => true,
                    'email' => $invitation->email,
                    'niveau_acces' => $invitation->niveau_acces,
                    'expire_a' => $invitation->expire_a,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation du lien',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== INSCRIPTION AVEC TOKEN D'INVITATION =====
// ===== INSCRIPTION AVEC TOKEN D'INVITATION =====
// ===== INSCRIPTION AVEC TOKEN D'INVITATION =====
// ===== INSCRIPTION AVEC TOKEN D'INVITATION =====
public function registerWithToken(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'token' => 'required|string',
        'prenomUtilisateur' => 'required|string|max:255',
        'nomUtilisateur' => 'required|string|max:255',
        'email' => 'required|string|email|max:255',
        'tel' => 'required|string|max:20',
        'mot_de_passe' => 'required|string|confirmed|min:8',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        DB::beginTransaction();

        // Valider le token
        $invitation = AdminInvitation::where('token', $request->token)
            ->where('est_actif', true)
            ->where('expire_a', '>', Carbon::now())
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Lien d\'invitation invalide ou expiré'
            ], 404);
        }

        // Vérifier l'email si spécifié dans l'invitation
        if ($invitation->email && $invitation->email !== $request->email) {
            return response()->json([
                'success' => false,
                'message' => 'L\'email ne correspond pas à l\'invitation'
            ], 422);
        }

        // ⭐⭐ CORRECTION : AUTORISER l'email de l'invitation même s'il est "mémorisé"
        $email = $request->email;

        // 1. Vérifier si l'email existe déjà dans la table utilisateurs (sauf si c'est l'email invité)
        $utilisateurExistant = Utilisateur::where('email', $email)->first();

        if ($utilisateurExistant) {
            // ⭐⭐ CORRECTION : Si l'email est le même que celui de l'invitation, on autorise
            // car c'est la personne qui a été invitée qui s'inscrit

            // Vérifier si c'est un administrateur actif
            $adminActif = Administrateur::where('idUtilisateur', $utilisateurExistant->idUtilisateur)
                ->where('est_actif', true)
                ->first();

            if ($adminActif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un compte administrateur actif existe déjà avec cet email.',
                    'type_compte_existant' => 'administrateur_actif'
                ], 422);
            }

            // Vérifier si c'est un administrateur inactif
            $adminInactif = Administrateur::where('idUtilisateur', $utilisateurExistant->idUtilisateur)
                ->where('est_actif', false)
                ->first();

            if ($adminInactif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une demande d\'administration est déjà en attente avec cet email. Veuillez attendre la validation de votre demande.',
                    'statut_demande' => 'en_attente'
                ], 422);
            }

            // Vérifier si c'est un vendeur
            $vendeurExistant = Vendeur::where('idUtilisateur', $utilisateurExistant->idUtilisateur)->first();
            if ($vendeurExistant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet email est déjà utilisé par un compte vendeur. Un vendeur ne peut pas devenir administrateur.',
                    'type_compte_existant' => 'vendeur',
                    'nom_entreprise' => $vendeurExistant->nom_entreprise
                ], 422);
            }
        }

        // 2. Vérifier si un client existe avec cet email (table séparée)
        $clientExistant = Client::where('email_client', $email)->first();
        if ($clientExistant) {
            return response()->json([
                'success' => false,
                'message' => 'Cet email est déjà utilisé par un compte client. Un client ne peut pas devenir administrateur.',
                'type_compte_existant' => 'client',
                'nom_client' => $clientExistant->nom_prenom_client
            ], 422);
        }

        // ⭐⭐ CRÉATION DE L'UTILISATEUR
        $utilisateur = Utilisateur::create([
            'prenomUtilisateur' => $request->prenomUtilisateur,
            'nomUtilisateur' => $request->nomUtilisateur,
            'email' => $request->email,
            'tel' => $request->tel,
            'mot_de_passe' => Hash::make($request->mot_de_passe),
            'idRole' => 1, // Rôle admin
        ]);

        // ⭐⭐ CRÉATION ADMIN INACTIF
        $admin = Administrateur::create([
            'idUtilisateur' => $utilisateur->idUtilisateur,
            'niveau_acces' => $invitation->niveau_acces,
            'est_actif' => false, // ⭐⭐ INACTIF EN ATTENTE DE VALIDATION
        ]);

        // ⭐⭐ CRÉATION DE LA DEMANDE D'APPROBATION
        AdminDemande::create([
            'idUtilisateur' => $utilisateur->idUtilisateur,
            'idInvitation' => $invitation->idInvitation,
            'statut' => 'en_attente',
        ]);

        // Désactiver l'invitation
        $invitation->update([
            'est_actif' => false,
            'utilise_a' => Carbon::now(),
            'utilise_par' => $admin->idAdministrateur
        ]);

        // ⭐⭐ ENVOYER EMAIL AUX SUPER ADMINS POUR APPROBATION
        $this->notifierSuperAdmins($admin, $invitation);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Demande de compte administrateur soumise avec succès. En attente de validation.',
            'admin' => $admin,
            'statut' => 'en_attente',
            'email_envoye' => true
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        // ⭐⭐ AJOUT: Log détaillé pour debug
        Log::error('Erreur registerWithToken: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création du compte administrateur',
            'error' => $e->getMessage(),
            'debug' => env('APP_DEBUG') ? $e->getTraceAsString() : null
        ], 500);
    }
}
    // ===== VALIDER UNE DEMANDE ADMIN =====
public function validerDemande(Request $request, $idDemande): JsonResponse
{
    $adminValidateur = auth()->user()->administrateur;

    if (!$adminValidateur->isSuperAdmin()) {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Seul un super administrateur peut valider des demandes.'
        ], 403);
    }

    try {
        DB::beginTransaction();

        $demande = AdminDemande::with(['utilisateur', 'utilisateur.administrateur'])->findOrFail($idDemande);

        if ($demande->statut !== 'en_attente') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande a déjà été traitée'
            ], 422);
        }

        // Activer l'administrateur
        $demande->utilisateur->administrateur->update([
            'est_actif' => true
        ]);

        // Mettre à jour la demande
        $demande->update([
            'statut' => 'approuve',
            'admin_validateur' => $adminValidateur->idAdministrateur,
            'date_validation' => Carbon::now()
        ]);

        // Envoyer email de confirmation au nouvel admin
        $this->envoyerEmailApprobation($demande->utilisateur->administrateur);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Demande approuvée avec succès',
            'demande' => $demande
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la validation',
            'error' => $e->getMessage()
        ], 500);
    }
}

// ===== REJETER UNE DEMANDE ADMIN =====
public function rejeterDemande(Request $request, $idDemande): JsonResponse
{
    $adminValidateur = auth()->user()->administrateur;

    if (!$adminValidateur->isSuperAdmin()) {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Seul un super administrateur peut rejeter des demandes.'
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'raison_rejet' => 'required|string|max:500',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        DB::beginTransaction();

        $demande = AdminDemande::with(['utilisateur', 'utilisateur.administrateur'])->findOrFail($idDemande);

        if ($demande->statut !== 'en_attente') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande a déjà été traitée'
            ], 422);
        }

        // Mettre à jour la demande
        $demande->update([
            'statut' => 'rejete',
            'admin_validateur' => $adminValidateur->idAdministrateur,
            'date_validation' => Carbon::now(),
            'raison_rejet' => $request->raison_rejet
        ]);

        // Envoyer email de rejet au candidat
        $this->envoyerEmailRejet($demande->utilisateur->administrateur, $request->raison_rejet);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Demande rejetée avec succès',
            'demande' => $demande
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du rejet',
            'error' => $e->getMessage()
        ], 500);
    }
}

// ===== NOTIFIER LES SUPER ADMINS =====
// ===== NOTIFIER LES SUPER ADMINS =====
private function notifierSuperAdmins($admin, $invitation)
{
    try {
        $superAdmins = Administrateur::where('niveau_acces', 'super_admin')
            ->where('est_actif', true)
            ->with('utilisateur')
            ->get();

        // Récupérer l'ID de la demande
        $demande = AdminDemande::where('idUtilisateur', $admin->utilisateur->idUtilisateur)
            ->where('idInvitation', $invitation->idInvitation)
            ->first();

        foreach ($superAdmins as $superAdmin) {
            $data = [
                'nom_candidat' => $admin->nom_complet,
                'email_candidat' => $admin->email,
                'niveau_acces_demande' => $invitation->niveau_acces,
                'date_demande' => now()->format('d/m/Y à H:i'),
                'id_demande' => $demande ? $demande->idDemande : 'N/A', // ⭐⭐ AJOUT: ID de la demande
            ];

            Mail::send('emails.admin-demande-attente', $data, function ($message) use ($superAdmin) {
                $message->to($superAdmin->email, $superAdmin->nom_complet)
                        ->subject('📋 Nouvelle demande d\'administration en attente - Vente-Ntsika');
            });
        }

        Log::info("✅ Notifications envoyées aux super admins pour la demande #" . ($demande ? $demande->idDemande : 'N/A'));
        return true;

    } catch (\Exception $e) {
        Log::error("❌ Erreur notification super admins: " . $e->getMessage());
        return false;
    }
}

// ===== ENVOYER EMAIL D'APPROBATION =====
private function envoyerEmailApprobation($admin)
{
    try {
        $data = [
            'nom_complet' => $admin->nom_complet,
            'email' => $admin->email,
            'niveau_acces' => $admin->niveau_acces,
            'date_activation' => now()->format('d/m/Y à H:i'),
        ];

        Mail::send('emails.admin-approuve', $data, function ($message) use ($admin) {
            $message->to($admin->email, $admin->nom_complet)
                    ->subject('✅ Votre compte administrateur a été approuvé - Vente-Ntsika');
        });

        Log::info("✅ Email d'approbation envoyé à: " . $admin->email);
        return true;

    } catch (\Exception $e) {
        Log::error("❌ Erreur envoi email approbation: " . $e->getMessage());
        return false;
    }
}

// ===== ENVOYER EMAIL DE REJET =====
private function envoyerEmailRejet($admin, $raison)
{
    try {
        $data = [
            'nom_complet' => $admin->nom_complet,
            'raison_rejet' => $raison,
            'date_rejet' => now()->format('d/m/Y à H:i'),
        ];

        Mail::send('emails.admin-rejete', $data, function ($message) use ($admin) {
            $message->to($admin->email, $admin->nom_complet)
                    ->subject('❌ Votre demande d\'administration a été rejetée - Vente-Ntsika');
        });

        Log::info("✅ Email de rejet envoyé à: " . $admin->email);
        return true;

    } catch (\Exception $e) {
        Log::error("❌ Erreur envoi email rejet: " . $e->getMessage());
        return false;
    }
}

// ===== LISTER LES DEMANDES EN ATTENTE =====
public function listDemandesEnAttente(): JsonResponse
{
    $adminConnecte = auth()->user()->administrateur;

    if (!$adminConnecte->isSuperAdmin()) {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé'
        ], 403);
    }

    try {
        // ⭐⭐ CORRECTION: Utiliser le statut numérique 0 pour "en_attente"
        $demandes = AdminDemande::with([
            'utilisateur',
            'invitation',
            'invitation.generateur'
        ])
        ->where('statut', 0) // ⭐⭐ CHANGEMENT: utiliser 0 au lieu de 'en_attente'
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($demande) {
            return [
                'idDemande' => $demande->idDemande,
                'candidat' => [
                    'nom_complet' => $demande->utilisateur->prenomUtilisateur . ' ' . $demande->utilisateur->nomUtilisateur,
                    'email' => $demande->utilisateur->email,
                    'telephone' => $demande->utilisateur->tel,
                ],
                'invitation' => $demande->invitation ? [
                    'niveau_acces' => $demande->invitation->niveau_acces,
                    'generer_par' => $demande->invitation->generateur->nom_complet ?? 'N/A',
                    'date_invitation' => $demande->invitation->created_at,
                ] : null,
                'date_demande' => $demande->created_at,
                'statut' => $demande->statut, // ⭐⭐ AJOUT: pour debug
            ];
        });

        // ⭐⭐ AJOUT: Log pour debug
        Log::info('Demandes en attente trouvées:', [
            'count' => $demandes->count(),
            'demandes' => $demandes->toArray()
        ]);

        return response()->json([
            'success' => true,
            'data' => $demandes
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur listDemandesEnAttente: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des demandes',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // ===== LISTER LES INVITATIONS ACTIVES =====
    public function listActiveInvitations(): JsonResponse
    {
        $adminConnecte = auth()->user()->administrateur;

        if (!$adminConnecte->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            $invitations = AdminInvitation::with('generateur')
                ->where('est_actif', true)
                ->where('expire_a', '>', Carbon::now())
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invitation) {
                    return [
                        'token' => $invitation->token,
                        'email' => $invitation->email,
                        'niveau_acces' => $invitation->niveau_acces,
                        'generer_par' => $invitation->generateur->nom_complet,
                        'expire_a' => $invitation->expire_a,
                        'created_at' => $invitation->created_at,
                        'temps_restant' => Carbon::now()->diffInMinutes($invitation->expire_a, false),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $invitations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des invitations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== ENVOYER EMAIL D'INVITATION =====
    private function envoyerEmailInvitation($email, $invitationUrl, $niveauAcces)
    {
        try {
            $data = [
                'invitation_url' => $invitationUrl,
                'niveau_acces' => $niveauAcces,
                'expiration_minutes' => 10,
            ];

            Mail::send('emails.admin-invitation', $data, function ($message) use ($email) {
                $message->to($email)
                        ->subject('🎉 Invitation à rejoindre l\'administration - Vente-Ntsika');
            });

            Log::info("✅ Email d'invitation envoyé à: " . $email);
            return true;

        } catch (\Exception $e) {
            Log::error("❌ Erreur envoi email invitation: " . $e->getMessage());
            return false;
        }
    }


    // ===== MOT DE PASSE OUBLIÉ - DEMANDE =====
public function forgotPassword(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:utilisateurs,email',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Email non trouvé ou invalide'
        ], 422);
    }

    try {
        $email = $request->email;
        
        // Vérifier que c'est bien un administrateur
        $utilisateur = Utilisateur::where('email', $email)->first();
        $admin = Administrateur::where('idUtilisateur', $utilisateur->idUtilisateur)->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte administrateur trouvé avec cet email'
            ], 404);
        }

        // Vérifier si l'admin est actif
        if (!$admin->est_actif) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte administrateur est en attente de validation. Vous ne pouvez pas réinitialiser votre mot de passe pour le moment.'
            ], 403);
        }

        // Générer le token de réinitialisation
        $token = Str::random(60);
        
        // Sauvegarder le token dans la table password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => Carbon::now()
            ]
        );

        // Envoyer l'email de réinitialisation
        $this->envoyerEmailReinitialisation($admin, $token);

        return response()->json([
            'success' => true,
            'message' => 'Email de réinitialisation envoyé avec succès',
            'reset_token' => $token // ⚠️ En production, ne renvoyez pas le token
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la demande de réinitialisation',
            'error' => $e->getMessage()
        ], 500);
    }
}

// ===== RÉINITIALISER LE MOT DE PASSE =====
public function resetPassword(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'token' => 'required|string',
        'email' => 'required|email',
        'password' => 'required|string|confirmed|min:8',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        DB::beginTransaction();

        $email = $request->email;
        $token = $request->token;
        $password = $request->password;

        // Récupérer le token stocké
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Token de réinitialisation invalide'
            ], 422);
        }

        // Vérifier si le token a expiré (15 minutes)
        if (Carbon::parse($passwordReset->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Le token de réinitialisation a expiré'
            ], 422);
        }

        // Vérifier le token
        if (!Hash::check($token, $passwordReset->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token de réinitialisation invalide'
            ], 422);
        }

        // Trouver l'utilisateur et vérifier que c'est un admin
        $utilisateur = Utilisateur::where('email', $email)->first();
        $admin = Administrateur::where('idUtilisateur', $utilisateur->idUtilisateur)->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte administrateur trouvé'
            ], 404);
        }

        // Mettre à jour le mot de passe
        $utilisateur->update([
            'mot_de_passe' => Hash::make($password)
        ]);

        // Supprimer le token utilisé
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Envoyer email de confirmation
        $this->envoyerEmailConfirmationReinitialisation($admin);

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

// ===== VALIDER LE TOKEN DE RÉINITIALISATION =====
public function validateResetToken(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'token' => 'required|string',
        'email' => 'required|email',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Données invalides'
        ], 422);
    }

    try {
        $email = $request->email;
        $token = $request->token;

        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide',
                'is_valid' => false
            ], 422);
        }

        // Vérifier l'expiration
        if (Carbon::parse($passwordReset->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Token expiré',
                'is_valid' => false
            ], 422);
        }

        // Vérifier le token
        $isValid = Hash::check($token, $passwordReset->token);

        return response()->json([
            'success' => true,
            'message' => $isValid ? 'Token valide' : 'Token invalide',
            'is_valid' => $isValid
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la validation du token',
            'error' => $e->getMessage()
        ], 500);
    }
}

// ===== ENVOYER EMAIL DE RÉINITIALISATION =====
private function envoyerEmailReinitialisation($admin, $token)
{
    try {
        $data = [
            'nom_complet' => $admin->nom_complet,
            'reset_url' => "http://localhost:3000/admin/reset-password?token={$token}&email=" . urlencode($admin->email),
            'expiration_minutes' => 15,
        ];

        Mail::send('emails.admin-reset-password', $data, function ($message) use ($admin) {
            $message->to($admin->email, $admin->nom_complet)
                    ->subject('🔐 Réinitialisation de votre mot de passe administrateur - Vente-Ntsika');
        });

        Log::info("✅ Email de réinitialisation envoyé à: " . $admin->email);
        return true;

    } catch (\Exception $e) {
        Log::error("❌ Erreur envoi email réinitialisation: " . $e->getMessage());
        return false;
    }
}

// ===== ENVOYER EMAIL DE CONFIRMATION =====
private function envoyerEmailConfirmationReinitialisation($admin)
{
    try {
        $data = [
            'nom_complet' => $admin->nom_complet,
            'date_reinitialisation' => now()->format('d/m/Y à H:i'),
            'ip_address' => request()->ip(),
        ];

        Mail::send('emails.admin-password-reset-confirm', $data, function ($message) use ($admin) {
            $message->to($admin->email, $admin->nom_complet)
                    ->subject('✅ Mot de passe réinitialisé avec succès - Vente-Ntsika');
        });

        Log::info("✅ Email de confirmation de réinitialisation envoyé à: " . $admin->email);
        return true;

    } catch (\Exception $e) {
        Log::error("❌ Erreur envoi email confirmation: " . $e->getMessage());
        return false;
    }
}

}
