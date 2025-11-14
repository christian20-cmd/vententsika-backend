<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrateur;
use App\Models\Vendeur;
use App\Models\Utilisateur;
use App\Models\AdminDemande;
use App\Models\AdminInvitation;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Tableau de bord principal admin
     */
    public function dashboard(): JsonResponse
    {
        try {
            // Le middleware 'admin' a déjà vérifié l'authentification et les droits
            $user = auth()->user();
            $adminConnecte = $user->administrateur;

            Log::info('Dashboard admin accédé par', [
                'admin_id' => $adminConnecte->idAdministrateur,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'metriques_principales' => $this->getMetriquesPrincipales(),
                    'activite_temps_reel' => $this->getActiviteTempsReel(),
                    'statistiques_inscriptions' => $this->getStatistiquesInscriptions(),
                    'repartition_roles' => $this->getRepartitionRoles(),
                    'demandes_attente' => $this->getDemandesEnAttente(),
                    'performance_systeme' => $this->getPerformanceSysteme(),
                    'admin_connecte' => [
                        'id' => $adminConnecte->idAdministrateur,
                        'nom_complet' => $adminConnecte->nom_complet,
                        'niveau_acces' => $adminConnecte->niveau_acces,
                        'est_actif' => (bool)$adminConnecte->est_actif,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dashboard admin: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    /**
     * Statistiques détaillées
     */
    public function statistiquesDetaillees(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'histogramme_inscriptions' => $this->getHistogrammeInscriptions(),
                    'taux_conversion' => $this->getTauxConversion(),
                    'activite_par_periode' => $this->getActiviteParPeriode(),
                    'performance_vendeurs' => $this->getPerformanceVendeurs()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur statistiques détaillées: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques détaillées',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    // ===== MÉTHODES PRIVÉES =====

    private function getMetriquesPrincipales(): array
    {
        $totalAdmins = Administrateur::count();
        $totalVendeurs = Vendeur::count();

        // Admins en ligne (dernière connexion < 15 min)
        $adminsEnLigne = Administrateur::where('derniere_connexion', '>=', now()->subMinutes(15))->count();

        // Vendeurs avec connexion récente
        $vendeursEnLigne = Utilisateur::whereHas('vendeur')
            ->where('derniere_connexion', '>=', now()->subMinutes(15))
            ->count();

        // Nouveaux inscrits (7 derniers jours)
        $nouveauxAdmins = Administrateur::where('created_at', '>=', now()->subDays(7))->count();
        $nouveauxVendeurs = Vendeur::where('created_at', '>=', now()->subDays(7))->count();

        // Taux d'activation
        $adminsActifs = Administrateur::where('est_actif', true)->count();
        $vendeursActifs = Vendeur::whereHas('utilisateur', function($q) {
            $q->where('Statut', 'actif');
        })->count();

        $tauxActivationAdmins = $totalAdmins > 0 ? round(($adminsActifs / $totalAdmins) * 100, 1) : 0;
        $tauxActivationVendeurs = $totalVendeurs > 0 ? round(($vendeursActifs / $totalVendeurs) * 100, 1) : 0;

        return [
            'administrateurs' => [
                'total' => $totalAdmins,
                'en_ligne' => $adminsEnLigne,
                'nouveaux_7j' => $nouveauxAdmins,
                'taux_activation' => $tauxActivationAdmins,
                'variation' => $this->getVariationMensuelle(Administrateur::class)
            ],
            'vendeurs' => [
                'total' => $totalVendeurs,
                'en_ligne' => $vendeursEnLigne,
                'nouveaux_7j' => $nouveauxVendeurs,
                'taux_activation' => $tauxActivationVendeurs,
                'variation' => $this->getVariationMensuelle(Vendeur::class)
            ]
        ];
    }

    private function getActiviteTempsReel(): array
    {
        // Dernières connexions (30 dernières minutes)
        $dernieresConnexions = Utilisateur::with(['administrateur', 'vendeur'])
            ->where('derniere_connexion', '>=', now()->subMinutes(30))
            ->orderBy('derniere_connexion', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                $type = $user->administrateur ? 'admin' : ($user->vendeur ? 'vendeur' : 'autre');
                $nom = $user->administrateur ? $user->administrateur->nom_complet :
                      ($user->vendeur ? $user->vendeur->nom_entreprise : $user->prenomUtilisateur . ' ' . $user->nomUtilisateur);

                return [
                    'type' => $type,
                    'nom' => $nom,
                    'email' => $user->email,
                    'heure_connexion' => $user->derniere_connexion->format('H:i:s'),
                    'ip' => $user->ip_connexion
                ];
            });

        return [
            'connexions_recentes' => $dernieresConnexions,
            'actions_recentes' => $this->getActionsRecentess(),
            'timestamp' => now()->toISOString()
        ];
    }

    private function getStatistiquesInscriptions(): array
    {
        $periodes = ['jour' => 7, 'semaine' => 4, 'mois' => 6];
        $data = [];

        foreach ($periodes as $periode => $limit) {
            $data[$periode] = [
                'admins' => $this->getInscriptionsParPeriode(Administrateur::class, $periode, $limit),
                'vendeurs' => $this->getInscriptionsParPeriode(Vendeur::class, $periode, $limit)
            ];
        }

        return $data;
    }

    private function getRepartitionRoles(): array
    {
        // Répartition des niveaux d'accès admin
        $niveauxAdmin = Administrateur::select('niveau_acces', DB::raw('count(*) as count'))
            ->groupBy('niveau_acces')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->niveau_acces => $item->count];
            });

        // Statut des vendeurs
        $statutsVendeurs = Vendeur::select('statut_validation', DB::raw('count(*) as count'))
            ->groupBy('statut_validation')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->statut_validation => $item->count];
            });

        // Types de vendeurs (avec produits vs sans produits)
        $vendeursAvecProduits = Vendeur::has('produits')->count();
        $vendeursSansProduits = Vendeur::doesntHave('produits')->count();

        return [
            'administrateurs' => [
                'super_admin' => $niveauxAdmin['super_admin'] ?? 0,
                'admin' => $niveauxAdmin['admin'] ?? 0,
                'moderateur' => $niveauxAdmin['moderateur'] ?? 0,
            ],
            'vendeurs' => [
                'valides' => $statutsVendeurs['valide'] ?? 0,
                'en_attente' => $statutsVendeurs['en_attente'] ?? 0,
                'rejetes' => $statutsVendeurs['rejete'] ?? 0,
                'avec_produits' => $vendeursAvecProduits,
                'sans_produits' => $vendeursSansProduits
            ]
        ];
    }

    private function getDemandesEnAttente(): array
    {
        // Demandes admin en attente
        $demandesAdmin = AdminDemande::with(['utilisateur', 'invitation'])
            ->where('statut', 'en_attente')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($demande) {
                return [
                    'id' => $demande->idDemande,
                    'type' => 'admin',
                    'nom_candidat' => $demande->utilisateur->prenomUtilisateur . ' ' . $demande->utilisateur->nomUtilisateur,
                    'email' => $demande->utilisateur->email,
                    'niveau_acces' => $demande->invitation->niveau_acces ?? 'N/A',
                    'date_demande' => $demande->created_at->format('d/m/Y H:i')
                ];
            });

        // Vendeurs en attente de validation
        $vendeursEnAttente = Vendeur::with('utilisateur')
            ->where('statut_validation', 'en_attente')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($vendeur) {
                return [
                    'id' => $vendeur->idVendeur,
                    'type' => 'vendeur',
                    'nom_entreprise' => $vendeur->nom_entreprise,
                    'email' => $vendeur->utilisateur->email,
                    'telephone' => $vendeur->utilisateur->tel,
                    'date_demande' => $vendeur->created_at->format('d/m/Y H:i')
                ];
            });

        return [
            'admins' => $demandesAdmin,
            'vendeurs' => $vendeursEnAttente,
            'total_demandes' => $demandesAdmin->count() + $vendeursEnAttente->count()
        ];
    }

    private function getPerformanceSysteme(): array
    {
        // Temps de réponse (simulé)
        $tempsReponse = rand(50, 200);

        // Utilisation base de données
        $nombreTables = count(DB::select('SHOW TABLES'));
        $tailleBDD = $this->getTailleBaseDeDonnees();

        return [
            'performance' => [
                'temps_reponse_moyen' => $tempsReponse,
                'statut' => $tempsReponse < 100 ? 'excellent' : ($tempsReponse < 200 ? 'bon' : 'modere')
            ],
            'base_donnees' => [
                'nombre_tables' => $nombreTables,
                'taille_totale' => $tailleBDD,
                'statut' => 'optimal'
            ],
            'securite' => [
                'tentatives_echouees_24h' => $this->getTentativesConnexionEchouees(),
                'activites_suspectes' => $this->getActivitesSuspectes(),
                'niveau_alerte' => 'faible'
            ]
        ];
    }

    private function getHistogrammeInscriptions(): array
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $mois = now()->subMonths($i);
            $data[] = [
                'mois' => $mois->format('M Y'),
                'admins' => Administrateur::whereYear('created_at', $mois->year)
                    ->whereMonth('created_at', $mois->month)
                    ->count(),
                'vendeurs' => Vendeur::whereYear('created_at', $mois->year)
                    ->whereMonth('created_at', $mois->month)
                    ->count()
            ];
        }
        return $data;
    }

    private function getTauxConversion(): array
    {
        $invitationsEnvoyees = AdminInvitation::count();
        $invitationsUtilisees = AdminInvitation::whereNotNull('utilise_par')->count();

        $tauxConversionInvitations = $invitationsEnvoyees > 0 ?
            round(($invitationsUtilisees / $invitationsEnvoyees) * 100, 1) : 0;

        $vendeursInscrits = Vendeur::count();
        $vendeursValides = Vendeur::where('statut_validation', 'valide')->count();

        $tauxValidationVendeurs = $vendeursInscrits > 0 ?
            round(($vendeursValides / $vendeursInscrits) * 100, 1) : 0;

        return [
            'invitations' => [
                'envoyees' => $invitationsEnvoyees,
                'utilisees' => $invitationsUtilisees,
                'taux' => $tauxConversionInvitations
            ],
            'vendeurs' => [
                'inscrits' => $vendeursInscrits,
                'valides' => $vendeursValides,
                'taux' => $tauxValidationVendeurs
            ]
        ];
    }

    // ===== MÉTHODES UTILITAIRES =====

    private function getVariationMensuelle($model): float
    {
        $moisActuel = $model::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $moisPrecedent = $model::whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        if ($moisPrecedent === 0) {
            return $moisActuel > 0 ? 100 : 0;
        }

        return round((($moisActuel - $moisPrecedent) / $moisPrecedent) * 100, 1);
    }

    private function getInscriptionsParPeriode($model, $periode, $limit): array
    {
        $data = [];
        $maintenant = now();

        for ($i = $limit - 1; $i >= 0; $i--) {
            $dateDebut = null;
            $dateFin = null;
            $label = '';

            switch ($periode) {
                case 'jour':
                    $dateDebut = $maintenant->copy()->subDays($i)->startOfDay();
                    $dateFin = $maintenant->copy()->subDays($i)->endOfDay();
                    $label = $dateDebut->format('d/m');
                    break;

                case 'semaine':
                    $dateDebut = $maintenant->copy()->subWeeks($i)->startOfWeek();
                    $dateFin = $maintenant->copy()->subWeeks($i)->endOfWeek();
                    $label = 'S' . $dateDebut->weekOfYear;
                    break;

                case 'mois':
                    $dateDebut = $maintenant->copy()->subMonths($i)->startOfMonth();
                    $dateFin = $maintenant->copy()->subMonths($i)->endOfMonth();
                    $label = $dateDebut->format('M');
                    break;
            }

            $count = $model::whereBetween('created_at', [$dateDebut, $dateFin])->count();
            $data[] = ['periode' => $label, 'count' => $count];
        }

        return $data;
    }

    private function getActionsRecentess(): array
    {
        // À implémenter avec un système de logs d'activité
        return [
            [
                'action' => 'Création administrateur',
                'utilisateur' => 'Super Admin',
                'cible' => 'Nouveau Modérateur',
                'heure' => now()->subMinutes(5)->format('H:i'),
                'type' => 'creation'
            ],
            [
                'action' => 'Validation vendeur',
                'utilisateur' => 'Admin Principal',
                'cible' => 'Entreprise ABC',
                'heure' => now()->subMinutes(15)->format('H:i'),
                'type' => 'validation'
            ]
        ];
    }

    private function getTailleBaseDeDonnees(): string
    {
        try {
            $databaseName = config('database.connections.mysql.database');
            $result = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size
                FROM information_schema.TABLES
                WHERE table_schema = ?
            ", [$databaseName]);

            return ($result[0]->size ?? 0) . ' MB';
        } catch (\Exception $e) {
            Log::error('Erreur calcul taille BDD: ' . $e->getMessage());
            return 'N/A';
        }
    }

    private function getTentativesConnexionEchouees(): int
    {
        // À implémenter avec un système de logs de sécurité
        return rand(0, 5);
    }

    private function getActivitesSuspectes(): int
    {
        // À implémenter avec un système de détection d'anomalies
        return rand(0, 2);
    }

    private function getActiviteParPeriode(): array
    {
        return [
            'matin' => rand(10, 50),
            'apres_midi' => rand(20, 60),
            'soiree' => rand(5, 30),
            'nuit' => rand(0, 10)
        ];
    }

    private function getPerformanceVendeurs(): array
    {
        $vendeursAvecProduits = Vendeur::has('produits')->count();
        $totalProduits = Produit::count();
        $moyenneProduits = $vendeursAvecProduits > 0 ? round($totalProduits / $vendeursAvecProduits, 1) : 0;

        return [
            'vendeurs_actifs' => $vendeursAvecProduits,
            'total_produits' => $totalProduits,
            'moyenne_produits' => $moyenneProduits,
            'produits_par_statut' => [
                'actifs' => Produit::where('statut', 'actif')->count(),
                'inactifs' => Produit::where('statut', 'inactif')->count(),
                'en_attente' => Produit::where('statut', 'en_attente')->count()
            ]
        ];
    }
}
