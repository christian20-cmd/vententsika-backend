<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Produit;
use App\Models\Categorie;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    // ===== MÉTHODE POUR VÉRIFIER L'ACCÈS =====
    private function checkStockAccess($stock)
    {
        $user = auth()->user();
        $commercant = $user->commercant;
        if (!$commercant) {
            return false;
        }
        if (!$stock->idProduit) {
            return $user->idRole === 1;
        }
        return $stock->produit && $stock->produit->idCommercant == $commercant->idCommercant;
    }

    // ===== MÉTHODE POUR DÉDUIRE LE STOCK APRÈS LIVRAISON =====
    public function deduireStockApresLivraison(Request $request, $id): JsonResponse
    {
        $request->validate([
            'quantite' => 'required|integer|min:1',
        ]);

        try {
            $stock = Stock::find($id);
            if (!$stock) {
                return response()->json(['message' => 'Stock non trouvé'], 404);
            }

            if (!$this->checkStockAccess($stock)) {
                return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
            }

            // Utiliser la méthode dédiée pour la déduction après livraison
            $stock->deduireStockApresLivraison($request->quantite);

            return response()->json([
                'message' => 'Stock déduit après livraison avec succès',
                'quantite_livree' => $request->quantite,
                'nouveau_stock' => $stock->quantite_disponible,
                'stock' => $stock->fresh(['produit.categorie'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la déduction du stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Liste tous les stocks avec les informations demandées
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $commercant = $user->commercant;
        if (!$commercant) {
            return response()->json(['message' => 'Aucun commercant trouvé pour cet utilisateur'], 404);
        }

        // Inclure aussi les stocks sans produit
        $stocks = Stock::with(['produit.categorie'])
            ->where(function($query) use ($commercant) {
                $query->whereHas('produit', function($q) use ($commercant) {
                    $q->where('idCommercant', $commercant->idCommercant);
                })->orWhereNull('idProduit');
            })
            ->get()
            ->map(function ($stock) {
                // Déterminer le statut de publication
                $statutPublication = 'sans_produit';
                if ($stock->produit) {
                    $statutPublication = $stock->produit->statut === 'actif' ? 'actif' : 'inactif';
                }

                return [
                    'idStock' => $stock->idStock,
                    'code_stock' => $stock->code_stock,
                    'nom_produit' => $stock->produit ? $stock->produit->nom_produit : 'Stock sans produit',
                    'categorie' => $stock->produit && $stock->produit->categorie
                        ? $stock->produit->categorie->nom_categorie
                        : 'Non catégorisé',
                    'prix_unitaire' => $stock->produit ? $stock->produit->prix_unitaire : 0,
                    'quantite_disponible' => $stock->quantite_disponible,
                    'quantite_reservee' => $stock->quantite_reservee,
                    'stock_entree' => $stock->stock_entree,
                    'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
                    'seuil_alerte' => $stock->seuil_alerte,
                    'statut_automatique' => $stock->statut_automatique,
                    'situation' => $stock->situation,
                    'statut_publication' => $statutPublication, // Statut basé sur l'existence et l'état du produit
                    'valeur' => $stock->valeur,
                    'date_derniere_maj' => $stock->date_derniere_maj,
                    'created_at' => $stock->created_at,
                    'updated_at' => $stock->updated_at,
                ];
            });

        return response()->json($stocks);
    }

    // Afficher un stock spécifique
        public function show($id): JsonResponse
        {
            $stock = Stock::with(['produit.categorie', 'produit.commercant.vendeur.utilisateur'])->find($id);        if (!$stock) {
            return response()->json(['message' => 'Stock non trouvé'], 404);
        }

        // VÉRIFICATION CRITIQUE : L'utilisateur a-t-il le droit d'accéder à ce stock ?
        if (!$this->checkStockAccess($stock)) {
            return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
        }

        $data = [
            'idStock' => $stock->idStock,
            'code_stock' => $stock->code_stock,
            'nom_produit' => $stock->produit ? $stock->produit->nom_produit : 'N/A',
            'categorie' => $stock->produit && $stock->produit->categorie
                ? $stock->produit->categorie->nom_categorie
                : 'N/A',
            'prix_unitaire' => $stock->produit ? $stock->produit->prix_unitaire : 0,
            'quantite_disponible' => $stock->quantite_disponible,
            'quantite_reservee' => $stock->quantite_reservee,
            'stock_entree' => $stock->stock_entree,
            'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
            'seuil_alerte' => $stock->seuil_alerte,
            'statut_automatique' => $stock->statut_automatique,
            'situation' => $stock->situation,
            'valeur' => $stock->valeur,
            'date_derniere_maj' => $stock->date_derniere_maj,
            'produit' => $stock->produit,
            //'peut_etre_vendu' => $stock->peutEtreVendu(),
        ];

        return response()->json($data);
    }

    // Créer un nouveau stock avec création automatique de catégorie si nécessaire
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nom_produit' => 'required|string|max:255',
            'categorie' => 'required|string|max:255',
            'prix_unitaire' => 'required|numeric|min:0',
            'quantite_disponible' => 'required|integer|min:0',
            'stock_entree' => 'nullable|integer|min:0',
            'quantite_reservee' => 'integer|min:0',
        ]);

        $user = auth()->user();
        $commercant = $user->commercant;
        if (!$commercant) {
            return response()->json(['message' => 'Aucun commercant trouvé pour cet utilisateur'], 404);
        }

        try {
            DB::beginTransaction();

            // 1. CRÉER OU TROUVER LA CATÉGORIE
            $categorie = Categorie::where('nom_categorie', $request->categorie)->first();
            if (!$categorie) {
                $categorie = Categorie::create([
                    'nom_categorie' => $request->categorie,
                    'description' => 'Catégorie créée automatiquement'
                ]);
            }

            // 2. CRÉER LE STOCK D'ABORD
            $stock = Stock::create([
                'quantite_disponible' => $request->quantite_disponible,
                'stock_entree' => $request->stock_entree ?? $request->quantite_disponible,
                'quantite_reservee' => $request->quantite_reservee ?? 0,
            ]);

            // 3. CRÉER LE PRODUIT AVEC idStock (statut inactif par défaut)
            $produit = Produit::create([
                'nom_produit' => $request->nom_produit,
                'description' => $request->description ?? 'Produit créé automatiquement',
                'prix_unitaire' => $request->prix_unitaire,
                'idCategorie' => $categorie->idCategorie,
                'idCommercant' => $commercant->idCommercant,
                'idStock' => $stock->idStock,
                'statut' => 'inactif', // Produit non publié par défaut
            ]);

            // 4. ASSOCIER LE STOCK AU PRODUIT
            $stock->update(['idProduit' => $produit->idProduit]);

            DB::commit();

            return response()->json([
                'message' => 'Stock et produit créés avec succès',
                'produit' => $produit,
                'stock' => [
                    'idStock' => $stock->idStock,
                    'code_stock' => $stock->code_stock,
                    'nom_produit' => $produit->nom_produit,
                    'categorie' => $categorie->nom_categorie,
                    'prix_unitaire' => $produit->prix_unitaire,
                    'quantite_disponible' => $stock->quantite_disponible,
                    'stock_entree' => $stock->stock_entree,
                    'quantite_reservee' => $stock->quantite_reservee,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Mettre à jour un stock
    public function update(Request $request, $id): JsonResponse
    {
        $stock = Stock::find($id);
        if (!$stock) {
            return response()->json(['message' => 'Stock non trouvé'], 404);
        }

        // VÉRIFICATION CRITIQUE
        if (!$this->checkStockAccess($stock)) {
            return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
        }

        $request->validate([
            'idProduit' => 'nullable|exists:produits,idProduit',
            'quantite_disponible' => 'integer|min:0',
            'stock_entree' => 'integer|min:0',
            'quantite_reservee' => 'integer|min:0',
        ]);

        $user = auth()->user();
        $commercant = $user->commercant;
        if ($request->has('idProduit') && $request->idProduit != $stock->idProduit) {
            $nouveauProduit = Produit::find($request->idProduit);
            if (!$nouveauProduit || $nouveauProduit->idCommercant != $commercant->idCommercant) {
                return response()->json(['message' => 'Produit non autorisé'], 403);
            }

            $existingStock = Stock::where('idProduit', $request->idProduit)->first();
            if ($existingStock) {
                return response()->json([
                    'message' => 'Un stock existe déjà pour ce produit',
                    'stock_existant' => $existingStock
                ], 409);
            }
        }

        $stock->update($request->only([
            'idProduit',
            'quantite_disponible',
            'stock_entree',
            'quantite_reservee',
        ]));

        return response()->json([
            'message' => 'Stock mis à jour avec succès',
            'stock' => $stock->fresh(['produit.categorie'])
        ]);
    }

    // Mettre à jour complètement un produit et son stock
    public function updateComplete(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nom_produit' => 'required|string|max:255',
            'categorie' => 'required|string|max:255',
            'prix_unitaire' => 'required|numeric|min:0',
            'quantite' => 'nullable|integer|min:0',
            'operation' => 'nullable|in:ajouter,retirer,definir'
        ]);

        $stock = Stock::with('produit')->find($id);
        if (!$stock) {
            return response()->json(['message' => 'Stock non trouvé'], 404);
        }

        // VÉRIFICATION CRITIQUE
        if (!$this->checkStockAccess($stock)) {
            return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
        }

        if (!$stock->produit) {
            return response()->json(['message' => 'Aucun produit associé à ce stock'], 400);
        }

        try {
            DB::beginTransaction();

            // 1. Mettre à jour la catégorie si nécessaire
            $categorie = Categorie::where('nom_categorie', $request->categorie)->first();
            if (!$categorie) {
                $categorie = Categorie::create([
                    'nom_categorie' => $request->categorie,
                    'description' => 'Catégorie mise à jour automatiquement'
                ]);
            }

            // 2. Mettre à jour le produit
            $stock->produit->update([
                'nom_produit' => $request->nom_produit,
                'prix_unitaire' => $request->prix_unitaire,
                'idCategorie' => $categorie->idCategorie,
            ]);

            // 3. Mettre à jour la quantité si spécifiée
            if ($request->has('quantite') && $request->has('operation')) {
                switch ($request->operation) {
                    case 'ajouter':
                        $stock->mettreAJourQuantite($request->quantite);
                        break;
                    case 'retirer':
                        $stock->mettreAJourQuantite(-$request->quantite);
                        break;
                    case 'definir':
                        $stock->quantite_disponible = $request->quantite;
                        $stock->save();
                        break;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Produit et stock mis à jour avec succès',
                'stock' => $stock->fresh(['produit.categorie'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Mettre à jour uniquement le stock_entree
    public function updateStockEntree(Request $request, $id): JsonResponse
    {
        $request->validate([
            'stock_entree' => 'required|integer|min:0',
        ]);

        $stock = Stock::find($id);
        if (!$stock) {
            return response()->json(['message' => 'Stock non trouvé'], 404);
        }

        // VÉRIFICATION CRITIQUE
        if (!$this->checkStockAccess($stock)) {
            return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
        }

        $stock->mettreAJourStockEntree($request->stock_entree);

        return response()->json([
            'message' => 'Stock entrée mis à jour avec succès',
            'stock' => $stock->fresh(['produit.categorie'])
        ]);
    }

    // Supprimer un stock
    public function destroy($id): JsonResponse
    {
        try {
            $stock = Stock::with('produit')->find($id);
            if (!$stock) {
                return response()->json(['message' => 'Stock non trouvé'], 404);
            }

            // Vérifier l'accès
            if (!$this->checkStockAccess($stock)) {
                return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
            }

            DB::beginTransaction();

            // Sauvegarder l'info du produit avant suppression
            $produitId = $stock->idProduit;
            $produitNom = $stock->produit ? $stock->produit->nom_produit : null;

            // 1. Si le stock a un produit associé, supprimer d'abord le produit
            if ($stock->produit) {
                // Détacher les médias du produit
                $stock->produit->medias()->detach();

                // Supprimer le produit
                $stock->produit->delete();
            }

            // 2. Maintenant supprimer le stock
            $stock->delete();

            DB::commit();

            return response()->json([
                'message' => $produitId
                    ? "Stock et produit '{$produitNom}' supprimés avec succès."
                    : 'Stock supprimé avec succès.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la suppression du stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Livrer des produits réservés (devenus vendus)
    // Dans StockController.php

    // Méthode pour livrer des produits avec validation renforcée
    // Méthode corrigée pour livrer des produits réservés
    public function livrerProduits(Request $request, $id): JsonResponse
    {
        $request->validate([
            'quantite' => 'required|integer|min:1',
            'idCommande' => 'nullable|exists:commandes,idCommande'
        ]);

        try {
            $stock = Stock::find($id);
            if (!$stock) {
                return response()->json(['message' => 'Stock non trouvé'], 404);
            }

            if (!$this->checkStockAccess($stock)) {
                return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
            }

            // VALIDATION : vérifier que la quantité réservée est suffisante
            if ($stock->quantite_reservee < $request->quantite) {
                return response()->json([
                    'message' => 'Quantité réservée insuffisante pour cette livraison',
                    'quantite_reservee' => $stock->quantite_reservee,
                    'quantite_demandee' => $request->quantite
                ], 400);
            }

            // 1. Diminuer la quantité réservée
            $stock->quantite_reservee -= $request->quantite;

            // 2. Diminuer le stock disponible (c'est ici le problème !)
            $stock->quantite_disponible -= $request->quantite;

            // Garantir que les quantités ne deviennent pas négatives
            if ($stock->quantite_reservee < 0) {
                $stock->quantite_reservee = 0;
            }
            if ($stock->quantite_disponible < 0) {
                $stock->quantite_disponible = 0;
            }

            $stock->date_derniere_maj = now();
            $stock->save();

            // Mettre à jour le statut automatique
            $stock->mettreAJourStatutAutomatique();

            // Garantir que la quantité réservée ne devient pas négative
            if ($stock->quantite_reservee < 0) {
                $stock->quantite_reservee = 0;
            }

            $stock->save();

            return response()->json([
                'message' => 'Produits livrés avec succès',
                'quantite_livree' => $request->quantite,
                'quantite_disponible' => $stock->quantite_disponible,
                'quantite_reservee' => $stock->quantite_reservee,
                'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
                'stock' => $stock->fresh(['produit.categorie'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Méthode pour réserver avec validation améliorée
    // Méthode améliorée pour réserver avec validation
    public function reserverProduits(Request $request, $id): JsonResponse
    {
        $request->validate([
            'quantite' => 'required|integer|min:1',
            'idCommande' => 'nullable|exists:commandes,idCommande' // Pour tracer la commande
        ]);

        try {
            $stock = Stock::find($id);
            if (!$stock) {
                return response()->json(['message' => 'Stock non trouvé'], 404);
            }

            if (!$this->checkStockAccess($stock)) {
                return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
            }

            // Utiliser la méthode du modèle pour la réservation
            $stock->reserverProduits($request->quantite);

            // Ici, vous pouvez logger la réservation ou l'associer à une commande
            // Example:
            // ReservationHistory::create([
            //     'idStock' => $stock->idStock,
            //     'idCommande' => $request->idCommande,
            //     'quantite_reservee' => $request->quantite,
            //     'date_reservation' => now()
            // ]);

            return response()->json([
                'message' => 'Produits réservés avec succès',
                'quantite_reservee' => $stock->quantite_reservee,
                'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
                'quantite_disponible' => $stock->quantite_disponible,
                'stock' => $stock->fresh(['produit.categorie'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Associer un stock à un produit
    public function associerProduit(Request $request, $id): JsonResponse
    {
        $request->validate([
            'idProduit' => 'required|exists:produits,idProduit',
        ]);

        $stock = Stock::find($id);
        if (!$stock) {
            return response()->json(['message' => 'Stock non trouvé'], 404);
        }

        // VÉRIFICATION CRITIQUE
        if (!$this->checkStockAccess($stock)) {
            return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
        }

        $user = auth()->user();
        $commercant = $user->commercant;
        $produit = Produit::find($request->idProduit);
        if (!$produit || $produit->idCommercant != $commercant->idCommercant) {
            return response()->json(['message' => 'Produit non trouvé ou non autorisé'], 403);
        }

        $existingStock = Stock::where('idProduit', $request->idProduit)->first();
        if ($existingStock && $existingStock->idStock != $id) {
            return response()->json([
                'message' => 'Un stock existe déjà pour ce produit',
                'stock_existant' => $existingStock
            ], 409);
        }

        $stock->update(['idProduit' => $request->idProduit]);

        return response()->json([
            'message' => 'Stock associé au produit avec succès',
            'stock' => $stock->fresh(['produit.categorie'])
        ]);
    }

    // Liste des stocks sans produit associé
    public function stocksSansProduit(): JsonResponse
    {
        $user = auth()->user();
        if ($user->idRole !== 1) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $stocksSansProduit = Stock::whereNull('idProduit')
            ->get()
            ->map(function ($stock) {
                return [
                    'idStock' => $stock->idStock,
                    'code_stock' => $stock->code_stock,
                    'quantite_disponible' => $stock->quantite_disponible,
                    'quantite_reservee' => $stock->quantite_reservee,
                    'stock_entree' => $stock->stock_entree,
                    'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
                    'seuil_alerte' => $stock->seuil_alerte,
                    'statut_automatique' => $stock->statut_automatique,
                    'date_derniere_maj' => $stock->date_derniere_maj,
                    'created_at' => $stock->created_at,
                ];
            });

        return response()->json($stocksSansProduit);
    }

    // Liste des stocks en alerte (quantité <= 5)
    public function stocksAlerte(): JsonResponse
    {
        try {
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant) {
                return response()->json(['message' => 'Aucun commercant trouvé'], 404);
            }

            $stocksAlerte = Stock::with(['produit.categorie', 'produit.commercant.vendeur.utilisateur'])
                ->where('quantite_disponible', '<=', 5)
                ->where('quantite_disponible', '>', 0)
                ->whereHas('produit', function($q) use ($commercant) {
                    $q->where('idCommercant', $commercant->idCommercant);
                })
                ->get()
                ->map(function ($stock) {
                    $produit = $stock->produit;
                    $categorie = $produit ? $produit->categorie : null;
                    $commercant = $produit ? $produit->commercant : null;
                    $vendeur = $commercant ? $commercant->vendeur : null;
                    $utilisateur = $vendeur ? $vendeur->utilisateur : null;

                    return [
                        'idStock' => $stock->idStock,
                        'code_stock' => $stock->code_stock,
                        'nom_produit' => $produit ? $produit->nom_produit : 'N/A',
                        'categorie' => $categorie ? $categorie->nom_categorie : 'N/A',
                        'prix_unitaire' => $produit ? $produit->prix_unitaire : 0,
                        'quantite_disponible' => $stock->quantite_disponible,
                        'stock_entree' => $stock->stock_entree,
                        'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
                        'seuil_alerte' => $stock->seuil_alerte,
                        'statut_automatique' => $stock->statut_automatique,
                        'vendeur' => $vendeur ? $vendeur->nom_entreprise : 'N/A',
                        'email_vendeur' => $utilisateur ? $utilisateur->email : 'N/A',
                    ];
                });

            return response()->json($stocksAlerte);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des stocks en alerte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Liste des stocks en rupture
    public function stocksRupture(): JsonResponse
    {
        $user = auth()->user();
        $commercant = $user->commercant;
        if (!$commercant) {
            return response()->json(['message' => 'Aucun commercant trouvé'], 404);
        }

        $stocksRupture = Stock::with(['produit.categorie', 'produit.commercant.vendeur'])
            ->where('quantite_disponible', '<=', 0)
            ->whereHas('produit', function($q) use ($commercant) {
                $q->where('idCommercant', $commercant->idCommercant);
            })
            ->get()
            ->map(function ($stock) {
                return [
                    'idStock' => $stock->idStock,
                    'code_stock' => $stock->code_stock,
                    'nom_produit' => $stock->produit ? $stock->produit->nom_produit : 'N/A',
                    'categorie' => $stock->produit && $stock->produit->categorie
                        ? $stock->produit->categorie->nom_categorie
                        : 'N/A',
                    'prix_unitaire' => $stock->produit ? $stock->produit->prix_unitaire : 0,
                    'quantite_disponible' => $stock->quantite_disponible,
                    'stock_entree' => $stock->stock_entree,
                    'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
                    'statut_automatique' => $stock->statut_automatique,
                    'vendeur' => $stock->produit && $stock->produit->commercant && $stock->produit->commercant->vendeur
                        ? $stock->produit->commercant->vendeur->nom_entreprise
                        : 'N/A',
                ];
            });

        return response()->json($stocksRupture);
    }

    // Statistiques des stocks
    public function statistiques(): JsonResponse
    {
        try {
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant) {
                return response()->json(['message' => 'Aucun commercant trouvé'], 404);
            }

            // Récupérer tous les stocks avec leurs statuts automatiques
            $stocks = Stock::whereHas('produit', function($q) use ($commercant) {
                $q->where('idCommercant', $commercant->idCommercant);
            })->get();

            $totalStocks = $stocks->count();

            // Compter par statut automatique au lieu de quantite_disponible
            $stocksEnAlerte = $stocks->where('statut_automatique', 'Faible')->count();
            $stocksEnRupture = $stocks->where('statut_automatique', 'Rupture')->count();

            $stocksSansProduit = Stock::whereNull('idProduit')
                ->whereHas('produit', function($q) use ($commercant) {
                    $q->where('idCommercant', $commercant->idCommercant);
                })
                ->count();

            $stocksNormaux = $stocks->where('statut_automatique', 'En stock')->count();

            $valeurTotale = 0;
            foreach ($stocks as $stock) {
                if ($stock->produit) {
                    $prix = $stock->produit->prix_promotion ?? $stock->produit->prix_unitaire;
                    // Utiliser la quantité réellement disponible pour la valeur
                    $quantitePourValeur = $stock->quantite_reellement_disponible > 0
                        ? $stock->quantite_reellement_disponible
                        : 0;
                    $valeurTotale += $quantitePourValeur * floatval($prix);
                }
            }

            $produitsReserves = $stocks->sum('quantite_reservee');
            $stockEntreeTotal = $stocks->sum('stock_entree');

            return response()->json([
                'total_stocks' => $totalStocks,
                'stocks_normaux' => max(0, $stocksNormaux),
                'stocks_en_alerte' => $stocksEnAlerte,
                'stocks_en_rupture' => $stocksEnRupture,
                'stocks_sans_produit' => $stocksSansProduit,
                'valeur_totale_stock' => round($valeurTotale, 2),
                'produits_reserves_non_livres' => $produitsReserves,
                'stock_entree_total' => $stockEntreeTotal,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testerAlerte($id): JsonResponse
    {
        try {
            $stock = Stock::find($id);
            if (!$stock) {
                return response()->json(['message' => 'Stock non trouvé'], 404);
            }

            // VÉRIFICATION CRITIQUE
            if (!$this->checkStockAccess($stock)) {
                return response()->json(['message' => 'Accès non autorisé à ce stock'], 403);
            }

            if (!$stock->produit) {
                return response()->json([
                    'message' => 'Ce stock n\'a pas de produit associé. Impossible d\'envoyer l\'alerte.',
                    'stock_id' => $stock->idStock,
                    'idProduit' => $stock->idProduit
                ], 400);
            }

            $stock->envoyerAlerteSeuil();

            return response()->json([
                'message' => 'Alerte envoyée avec succès',
                'stock' => [
                    'idStock' => $stock->idStock,
                    'nom_produit' => $stock->produit->nom_produit,
                    'quantite_disponible' => $stock->quantite_disponible,
                    'stock_entree' => $stock->stock_entree,
                    'derniere_alerte_envoyee' => $stock->derniere_alerte_envoyee
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'envoi de l\'alerte',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    // Récupérer les stocks non publiés (produits avec statut 'inactif' ou sans produit)
    // Dans StockController.php - Ajoutez cette méthode
    public function getStocksNonPublies(): JsonResponse
    {
        try {
            $commercant = auth()->user()->commercant;
            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            // Récupérer les stocks qui n'ont pas de produit associé OU dont le produit a le statut 'inactif'
            $stocks = Stock::with(['produit.categorie'])
                ->where(function($query) use ($commercant) {
                    $query->whereHas('produit', function($q) use ($commercant) {
                        $q->where('idCommercant', $commercant->idCommercant)
                        ->where('statut', 'inactif');
                    })->orWhereNull('idProduit');
                })
                ->where('quantite_disponible', '>', 0)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($stock) {
                    return [
                        'idStock' => $stock->idStock,
                        'code_stock' => $stock->code_stock,
                        'nom_produit' => $stock->produit ? $stock->produit->nom_produit : 'Stock sans produit',
                        'categorie' => $stock->produit && $stock->produit->categorie
                            ? $stock->produit->categorie->nom_categorie
                            : 'Non catégorisé',
                        'prix_unitaire' => $stock->produit ? $stock->produit->prix_unitaire : 0,
                        'quantite_disponible' => $stock->quantite_disponible,
                        'quantite_reservee' => $stock->quantite_reservee,
                        'stock_entree' => $stock->stock_entree,
                        'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
                        'seuil_alerte' => $stock->seuil_alerte,
                        'statut_automatique' => $stock->statut_automatique,
                        'situation' => $stock->situation,
                        'valeur' => $stock->valeur,
                        'date_derniere_maj' => $stock->date_derniere_maj,
                        'statut_publication' => $stock->produit ? 'inactif' : 'sans_produit',
                        'created_at' => $stock->created_at,
                    ];
                });

            return response()->json($stocks);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des stocks non publiés',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Publier un stock (créer ou activer le produit associé)
    // Publier un stock (créer ou activer le produit associé)
    public function publierStock(Request $request, $idStock): JsonResponse
    {
        try {
            $stock = Stock::with(['produit'])->find($idStock);
            if (!$stock) {
                return response()->json(['message' => 'Stock non trouvé'], 404);
            }

            // Vérifier l'accès
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            // Vérifier que le stock peut être publié
            if ($stock->quantite_disponible <= 0) {
                return response()->json([
                    'message' => 'Impossible de publier un stock avec une quantité disponible nulle ou négative'
                ], 400);
            }

            DB::beginTransaction();

            if ($stock->produit) {
                // Cas 1: Le stock a déjà un produit associé → activer le produit ET mettre à jour les données
                $stock->produit->update([
                    'statut' => 'actif',
                    'date_publication' => now(),
                    // METTRE À JOUR LES DONNÉES DU FORMULAIRE
                    'nom_produit' => $request->nom_produit,
                    'description' => $request->description,
                    'prix_unitaire' => $request->prix_unitaire,
                    'prix_promotion' => $request->prix_promotion,
                    'idCategorie' => $request->idCategorie,
                    'image_principale' => $request->image_principale,
                    'images_supplementaires' => $request->images_supplementaires ? json_encode($request->images_supplementaires) : null,
                ]);
                $message = 'Produit publié avec succès';
                $produit = $stock->produit;
            } else {
                // Cas 2: Le stock n'a pas de produit → créer un nouveau produit
                $request->validate([
                    'nom_produit' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'prix_unitaire' => 'required|numeric|min:0',
                    'idCategorie' => 'required|exists:categories,idCategorie',
                    'prix_promotion' => 'nullable|numeric|min:0',
                    'image_principale' => 'nullable|string',
                    'images_supplementaires' => 'nullable|array',
                ]);

                $produit = Produit::create([
                    'nom_produit' => $request->nom_produit,
                    'description' => $request->description,
                    'prix_unitaire' => $request->prix_unitaire,
                    'prix_promotion' => $request->prix_promotion,
                    'idCategorie' => $request->idCategorie,
                    'idCommercant' => $commercant->idCommercant,
                    'idStock' => $stock->idStock,
                    'image_principale' => $request->image_principale,
                    'images_supplementaires' => $request->images_supplementaires ? json_encode($request->images_supplementaires) : null,
                    'statut' => 'actif',
                    'date_publication' => now(),
                ]);

                // Associer le stock au produit
                $stock->update(['idProduit' => $produit->idProduit]);
                $message = 'Produit créé et publié avec succès';
            }

            DB::commit();

            return response()->json([
                'message' => $message,
                'produit' => $produit->load(['categorie', 'stock']),
                'stock' => $stock->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la publication du stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Récupérer les informations d'un stock pour pré-remplir le formulaire de publication
    public function getInfosPourPublication($idStock): JsonResponse
    {
        try {
            $stock = Stock::with(['produit.categorie'])->find($idStock);
            if (!$stock) {
                return response()->json(['message' => 'Stock non trouvé'], 404);
            }

            // Vérifier l'accès
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $data = [
                'stock' => [
                    'idStock' => $stock->idStock,
                    'code_stock' => $stock->code_stock,
                    'quantite_disponible' => $stock->quantite_disponible,
                    'quantite_reservee' => $stock->quantite_reservee,
                    'stock_entree' => $stock->stock_entree,
                    'quantite_reellement_disponible' => $stock->quantite_reellement_disponible,
                    'seuil_alerte' => $stock->seuil_alerte,
                    'statut_automatique' => $stock->statut_automatique,
                    'situation' => $stock->situation,
                    'valeur' => $stock->valeur,
                    'date_derniere_maj' => $stock->date_derniere_maj,
                ]
            ];

            if ($stock->produit) {
                // Si le produit existe déjà (mais est inactif)
                $data['produit'] = [
                    'idProduit' => $stock->produit->idProduit,
                    'nom_produit' => $stock->produit->nom_produit,
                    'description' => $stock->produit->description,
                    'prix_unitaire' => $stock->produit->prix_unitaire,
                    'prix_promotion' => $stock->produit->prix_promotion,
                    'idCategorie' => $stock->produit->idCategorie,
                    'categorie_nom' => $stock->produit->categorie->nom_categorie,
                    'image_principale' => $stock->produit->image_principale,
                    'images_supplementaires' => $stock->produit->images_supplementaires,
                    'statut' => $stock->produit->statut,
                ];
            } else {
                // Si pas de produit, fournir des valeurs par défaut basées sur le stock
                $data['produit'] = [
                    'nom_produit' => 'Produit basé sur stock ' . $stock->code_stock,
                    'description' => '',
                    'prix_unitaire' => 0,
                    'prix_promotion' => null,
                    'idCategorie' => null,
                    'image_principale' => null,
                    'images_supplementaires' => null,
                ];
            }

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des informations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
