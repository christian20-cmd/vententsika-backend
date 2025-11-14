<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendeur;
use App\Models\Utilisateur;
use App\Models\Commercant;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendeurAdminController extends Controller
{
    // ===== LISTER TOUS LES VENDEURS =====
    // ===== LISTER TOUS LES VENDEURS =====
public function index(Request $request): JsonResponse
{
    try {
        $query = Vendeur::with(['utilisateur', 'commercant']);

        // Filtre par statut de validation
        if ($request->has('statut_validation')) {
            $query->where('statut_validation', $request->statut_validation);
        }

        // Filtre par statut d'activité
        if ($request->has('est_actif')) {
            $estActif = $request->boolean('est_actif');
            $query->whereHas('utilisateur', function($q) use ($estActif) {
                $q->where('Statut', $estActif ? 'actif' : 'inactif');
            });
        }

        $vendeurs = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($vendeur) {
                // Générer l'URL complète pour le logo
                $logoUrl = null;
                if ($vendeur->logo_image) {
                    // Si c'est déjà une URL complète
                    if (filter_var($vendeur->logo_image, FILTER_VALIDATE_URL)) {
                        $logoUrl = $vendeur->logo_image;
                    }
                    // Si c'est un chemin relatif
                    else {
                        $logoUrl = asset('storage/' . $vendeur->logo_image);
                    }
                }

                return [
                    'idVendeur' => $vendeur->idVendeur,
                    'idUtilisateur' => $vendeur->idUtilisateur,
                    'nom_entreprise' => $vendeur->nom_entreprise,
                    'description' => $vendeur->description,
                    'logo_image' => $logoUrl, // URL complète
                    'logo_path' => $vendeur->logo_image, // Chemin original pour debug
                    'adresse_entreprise' => $vendeur->adresse_entreprise,
                    'statut_validation' => $vendeur->statut_validation,
                    'commission_pourcentage' => $vendeur->commission_pourcentage,
                    'utilisateur' => [
                        'nom_complet' => $vendeur->utilisateur->prenomUtilisateur . ' ' . $vendeur->utilisateur->nomUtilisateur,
                        'email' => $vendeur->utilisateur->email,
                        'telephone' => $vendeur->utilisateur->tel,
                        'statut' => $vendeur->utilisateur->Statut,
                        'date_inscription' => $vendeur->utilisateur->date_inscription,
                    ],
                    'commercant' => $vendeur->commercant ? [
                        'idCommercant' => $vendeur->commercant->idCommercant,
                        'email_commercant' => $vendeur->commercant->email,
                        'telephone_commercant' => $vendeur->commercant->telephone,
                        'statut_validation' => $vendeur->commercant->statut_validation,
                    ] : null,
                    'est_en_ligne' => $this->estEnLigne($vendeur->utilisateur),
                    'nombre_produits' => $vendeur->produits()->count(),
                    'date_creation' => $vendeur->created_at,
                ];
            });

        // Debug: Afficher les URLs générées
        Log::info('URLs logos générées:', $vendeurs->pluck('logo_image')->toArray());

        return response()->json([
            'success' => true,
            'data' => $vendeurs,
            'total' => $vendeurs->count()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des vendeurs',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // ===== STATISTIQUES DES VENDEURS =====
    public function statistiques(): JsonResponse
    {
        try {
            $totalVendeurs = Vendeur::count();
            $vendeursActifs = Vendeur::whereHas('utilisateur', function($q) {
                $q->where('Statut', 'actif');
            })->count();

            // TEMPORAIREMENT : Retirer la vérification en ligne
            $vendeursEnLigne = 0; // Temporaire jusqu'à ce que la colonne soit ajoutée

            $vendeursValides = Vendeur::where('statut_validation', 'valide')->count();
            $vendeursEnAttente = Vendeur::where('statut_validation', 'en_attente')->count();
            $vendeursRejetes = Vendeur::where('statut_validation', 'rejete')->count();

            $vendeursAvecProduits = Vendeur::has('produits')->count();
            $totalProduits = Produit::count();
            $moyenneProduitsParVendeur = $totalVendeurs > 0 ? round($totalProduits / $totalVendeurs, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_vendeurs' => $totalVendeurs,
                    'vendeurs_actifs' => $vendeursActifs,
                    'vendeurs_inactifs' => $totalVendeurs - $vendeursActifs,
                    'vendeurs_en_ligne' => $vendeursEnLigne,
                    'vendeurs_hors_ligne' => $totalVendeurs - $vendeursEnLigne,
                    'statut_validation' => [
                        'valides' => $vendeursValides,
                        'en_attente' => $vendeursEnAttente,
                        'rejetes' => $vendeursRejetes,
                    ],
                    'produits' => [
                        'total_produits' => $totalProduits,
                        'vendeurs_avec_produits' => $vendeursAvecProduits,
                        'moyenne_produits_par_vendeur' => $moyenneProduitsParVendeur,
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

    // ===== VOIR LES DÉTAILS D'UN VENDEUR =====
    public function show($id): JsonResponse
    {
        try {
            $vendeur = Vendeur::with([
                'utilisateur',
                'commercant',
                'produits.categorie',
                'produits.stock',
                'produits.medias'
            ])->findOrFail($id);

            $data = [
                'idVendeur' => $vendeur->idVendeur,
                'nom_entreprise' => $vendeur->nom_entreprise,
                'description' => $vendeur->description,
                'logo_image' => $vendeur->logo_image,
                'logo_path' => $vendeur->logo_image,
                'adresse_entreprise' => $vendeur->adresse_entreprise,
                'statut_validation' => $vendeur->statut_validation,
                'commission_pourcentage' => $vendeur->commission_pourcentage,
                'utilisateur' => [
                    'idUtilisateur' => $vendeur->utilisateur->idUtilisateur,
                    'nom_complet' => $vendeur->utilisateur->prenomUtilisateur . ' ' . $vendeur->utilisateur->nomUtilisateur,
                    'email' => $vendeur->utilisateur->email,
                    'telephone' => $vendeur->utilisateur->tel,
                    'statut' => $vendeur->utilisateur->Statut,
                    'date_inscription' => $vendeur->utilisateur->date_inscription,
                    'derniere_connexion' => $vendeur->utilisateur->derniere_connexion,
                ],
                'commercant' => $vendeur->commercant,
                'est_en_ligne' => $this->estEnLigne($vendeur->utilisateur),
                'produits' => [
                    'total' => $vendeur->produits->count(),
                    'liste' => $vendeur->produits->take(10)->map(function($produit) {
                        return [
                            'idProduit' => $produit->idProduit,
                            'nom_produit' => $produit->nom_produit,
                            'prix_unitaire' => $produit->prix_unitaire,
                            'statut' => $produit->statut,
                            'categorie' => $produit->categorie->nom_categorie ?? 'N/A',
                            'quantite_disponible' => $produit->stock->quantite_disponible ?? 0,
                        ];
                    }),
                ],
                'performance' => [
                    'produits_actifs' => $vendeur->produits()->where('statut', 'actif')->count(),
                    'produits_inactifs' => $vendeur->produits()->where('statut', 'inactif')->count(),
                    'total_vues' => 0, // À implémenter si vous avez un système de vues
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vendeur non trouvé'
            ], 404);
        }
    }

    // ===== METTRE À JOUR LE STATUT DE VALIDATION =====
    public function updateStatut(Request $request, $id): JsonResponse
    {
        $request->validate([
            'statut_validation' => 'required|in:valide,en_attente,rejete',
            'raison' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $vendeur = Vendeur::findOrFail($id);
            $ancienStatut = $vendeur->statut_validation;

            $vendeur->update([
                'statut_validation' => $request->statut_validation
            ]);

            // Mettre à jour aussi le statut du commercant
            if ($vendeur->commercant) {
                $vendeur->commercant->update([
                    'statut_validation' => $request->statut_validation
                ]);
            }

            // Loguer l'action
            // TODO: Ajouter un système de logs d'activité admin

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Statut de validation mis à jour avec succès',
                'data' => [
                    'ancien_statut' => $ancienStatut,
                    'nouveau_statut' => $request->statut_validation,
                    'vendeur' => $vendeur->load('utilisateur')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== SUPPRIMER UN VENDEUR =====
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $vendeur = Vendeur::with(['produits', 'commercant'])->findOrFail($id);

            // Vérifier si le vendeur a des produits
            if ($vendeur->produits->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce vendeur car il a des produits associés'
                ], 422);
            }

            // Supprimer le commercant associé s'il existe
            if ($vendeur->commercant) {
                $vendeur->commercant->delete();
            }

            // Supprimer le vendeur
            $vendeur->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vendeur supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du vendeur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== RECHERCHER DES VENDEURS =====// ===== RECHERCHER DES VENDEURS =====
        // ===== RECHERCHER DES VENDEURS =====
    // ===== RECHERCHER DES VENDEURS =====
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('query', '');

            Log::info("Recherche vendeurs avec query: " . $query);

            // Version simple pour debug
            $vendeurs = Vendeur::with('utilisateur')
                ->where('nom_entreprise', 'LIKE', "%{$query}%")
                ->get();

            Log::info("Nombre de résultats: " . $vendeurs->count());

            return response()->json([
                'success' => true,
                'data' => $vendeurs,
                'debug' => [
                    'query' => $query,
                    'count' => $vendeurs->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur recherche: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== MÉTHODE UTILITAIRE POUR VÉRIFIER SI EN LIGNE =====
    private function estEnLigne($utilisateur)
    {
        return $utilisateur->derniere_connexion &&
               $utilisateur->derniere_connexion->gte(now()->subMinutes(15));
    }
}
