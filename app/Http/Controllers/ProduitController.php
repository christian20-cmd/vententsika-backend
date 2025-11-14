<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\Stock;
use App\Models\Categorie;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
class ProduitController extends Controller
{
    // Liste tous les produits avec leurs relations
    public function index(): JsonResponse
    {
        try {
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun commercant trouvé pour cet utilisateur'
                ], 404);
            }

            $produits = Produit::with(['categorie', 'stock', 'medias' => function($query) {
                    $query->orderBy('ordre');
                }, 'commercant.vendeur.utilisateur'])
                ->where('idCommercant', $commercant->idCommercant)
                ->whereIn('statut', ['actif', 'rupture'])
                ->get()
                ->map(function ($produit) {
                    // Calculer les quantités réelles
                        $quantiteDisponible = $produit->stock ? $produit->stock->quantite_disponible : 0;
                        $quantiteReservee = $produit->stock ? $produit->stock->quantite_reservee : 0;
                        $quantiteReellementDisponible = max(0, $quantiteDisponible - $quantiteReservee);

                        // ⭐⭐ CORRECTION : Mettre à jour automatiquement le statut si nécessaire
                        if ($quantiteReellementDisponible <= 0 && $produit->statut !== 'rupture') {
                            $produit->update(['statut' => 'rupture']);
                        } elseif ($quantiteReellementDisponible > 0 && $produit->statut === 'rupture') {
                            $produit->update(['statut' => 'actif']);
                        }
                    // Formater les médias
                    $mediasFormatted = $produit->medias->map(function($media) {
                        return [
                            'idMedia' => $media->idMedia,
                            'chemin_fichier' => $media->chemin_fichier,
                            'type_media' => $media->type_media,
                            'pivot' => [
                                'ordre' => $media->pivot->ordre,
                                'is_principal' => $media->pivot->is_principal
                            ]
                        ];
                    });

                    $mediaPrincipal = $produit->medias->where('pivot.is_principal', true)->first();

                    return [
                        'idProduit' => $produit->idProduit,
                        'nom_produit' => $produit->nom_produit,
                        'description' => $produit->description,
                        'prix_unitaire' => $produit->prix_unitaire,
                        'prix_promotion' => $produit->prix_promotion,
                        'categorie' => $produit->categorie ? $produit->categorie->nom_categorie : 'N/A',
                        'idCategorie' => $produit->idCategorie,
                        'quantite_disponible' => $quantiteDisponible,
                        'quantite_reservee' => $quantiteReservee,
                        'stock_entree' => $produit->stock ? $produit->stock->stock_entree : 0,
                        'quantite_reellement_disponible' => $quantiteReellementDisponible, // ← C'est la quantité vraiment disponible
                        'statut' => $produit->statut,
                        'medias' => $mediasFormatted,
                        'image_principale' => $mediaPrincipal ? $mediaPrincipal->chemin_fichier : $produit->image_principale,
                        'images_supplementaires' => $produit->images_supplementaires,
                        'date_publication' => $produit->date_publication,
                        'vendeur' => $produit->commercant && $produit->commercant->vendeur
                            ? $produit->commercant->vendeur->nom_entreprise
                            : 'N/A',
                        'idCommercant' => $produit->idCommercant,
                        'idStock' => $produit->idStock,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $produits
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des produits',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Dans ProduitController.php
public function produitsAvecStocks()
{
    try {
        $commercant = Auth::user()->commercant;

        if (!$commercant) {
            return response()->json([
                'success' => false,
                'message' => 'Commerçant non trouvé'
            ], 404);
        }

        $produits = Produit::with(['stock', 'medias'])
            ->where('idCommercant', $commercant->idCommercant)
            ->where('statut', 'actif')
            ->get()
            ->map(function ($produit) {
                return [
                    'idProduit' => $produit->idProduit,
                    'nom_produit' => $produit->nom_produit,
                    'prix_unitaire' => $produit->prix_unitaire,
                    'prix_promotion' => $produit->prix_promotion,
                    'stock' => $produit->stock ? [
                        'quantite_disponible' => $produit->stock->quantite_disponible,
                        'quantite_reservee' => $produit->stock->quantite_reservee
                    ] : null
                ];
            });

        return response()->json([
            'success' => true,
            'produits' => $produits
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur récupération produits avec stocks: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des produits'
        ], 500);
    }
}

    // Afficher un produit spécifique
    public function show($id): JsonResponse
    {
        try {
            $produit = Produit::with(['categorie', 'stock', 'medias' => function($query) {
                $query->orderBy('ordre');
            }, 'commercant.vendeur.utilisateur'])->find($id);

            if (!$produit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            // Vérifier que l'utilisateur a accès à ce produit
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant || $produit->idCommercant != $commercant->idCommercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à ce produit'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $produit
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du produit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Créer un nouveau produit avec sélection de stock
    // Dans la méthode store(), remplacer la section de création du média par :

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nom_produit' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prix_unitaire' => 'required|numeric|min:0',
            'prix_promotion' => 'nullable|numeric|min:0',
            'idCategorie' => 'required|exists:categories,idCategorie',
            'idStock' => 'required|exists:stocks,idStock',
            'image_principale' => 'nullable|string',
            'images_supplementaires' => 'nullable|array',
        ]);

        $user = auth()->user();
        $commercant = $user->commercant;

        if (!$commercant) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun commercant trouvé pour cet utilisateur'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Vérifier que le stock est disponible
            $stock = Stock::find($request->idStock);
            if (!$stock) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Stock non trouvé'
                ], 404);
            }

            if ($stock->idProduit) {
                $produitExistant = Produit::find($stock->idProduit);
                if ($produitExistant && $produitExistant->statut === 'actif') {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Ce stock est déjà associé à un produit actif',
                        'produit_existant' => $produitExistant
                    ], 409);
                }
            }

            // Préparer les données du produit
            $produitData = [
                'nom_produit' => $request->nom_produit,
                'description' => $request->description,
                'prix_unitaire' => $request->prix_unitaire,
                'prix_promotion' => $request->prix_promotion,
                'idCategorie' => $request->idCategorie,
                'idCommercant' => $commercant->idCommercant,
                'idStock' => $request->idStock,
                'statut' => 'actif',
                'date_publication' => now(),
                'image_principale' => $request->image_principale,
            ];

            // Gérer les images supplémentaires
            if ($request->has('images_supplementaires') && is_array($request->images_supplementaires)) {
                $produitData['images_supplementaires'] = $request->images_supplementaires;
            }

            // Créer le produit
            $produit = Produit::create($produitData);

            // Créer et associer automatiquement le média principal
            if ($request->has('image_principale') && !empty($request->image_principale)) {
                $media = Media::create([
                    'chemin_fichier' => $request->image_principale,
                    'type_media' => 'image'
                ]);

                $produit->medias()->attach($media->idMedia, [
                    'ordre' => 0,
                    'is_principal' => true
                ]);
            }

            // Créer et associer les médias supplémentaires
            if ($request->has('images_supplementaires') && is_array($request->images_supplementaires)) {
                foreach ($request->images_supplementaires as $index => $imageUrl) {
                    $media = Media::create([
                        'chemin_fichier' => $imageUrl,
                        'type_media' => 'image'
                    ]);

                    $produit->medias()->attach($media->idMedia, [
                        'ordre' => $index + 1,
                        'is_principal' => false
                    ]);
                }
            }

            // Associer le stock au produit
            $stock->update(['idProduit' => $produit->idProduit]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produit créé et publié avec succès',
                'data' => $produit->load(['categorie', 'stock', 'medias'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création produit: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du produit',
                'error' => $e->getMessage()
            ], 500);
        }
    }







        // Méthode pour mettre à jour les réservations de stock
    public function mettreAJourReservation(Request $request, $id): JsonResponse
    {
        try {
            $produit = Produit::with('stock')->find($id);
            if (!$produit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            // Vérifier les permissions
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant || $produit->idCommercant != $commercant->idCommercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $request->validate([
                'quantite_reservee' => 'required|integer|min:0',
                'operation' => 'required|in:ajouter,retirer,mettre_a_jour'
            ]);

            DB::beginTransaction();

            $stock = $produit->stock;
            if (!$stock) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Stock non trouvé pour ce produit'
                ], 404);
            }

            $nouvelleQuantiteReservee = $stock->quantite_reservee;

            switch ($request->operation) {
                case 'ajouter':
                    $nouvelleQuantiteReservee += $request->quantite_reservee;
                    break;
                case 'retirer':
                    $nouvelleQuantiteReservee = max(0, $nouvelleQuantiteReservee - $request->quantite_reservee);
                    break;
                case 'mettre_a_jour':
                    $nouvelleQuantiteReservee = $request->quantite_reservee;
                    break;
            }

            // Vérifier que la quantité réservée ne dépasse pas la quantité disponible
            if ($nouvelleQuantiteReservee > $stock->quantite_disponible) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Quantité réservée supérieure au stock disponible',
                    'stock_disponible' => $stock->quantite_disponible,
                    'quantite_reservee_demandee' => $nouvelleQuantiteReservee
                ], 400);
            }

            // Mettre à jour le stock
            $stock->update([
                'quantite_reservee' => $nouvelleQuantiteReservee,
                'quantite_reellement_disponible' => $stock->quantite_disponible - $nouvelleQuantiteReservee,
                'date_derniere_maj' => now()
            ]);

            // Mettre à jour le statut du produit si nécessaire
            $nouveauStatut = $this->determinerStatutProduit($stock);
            if ($produit->statut !== $nouveauStatut) {
                $produit->update(['statut' => $nouveauStatut]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Réservation mise à jour avec succès',
                'data' => [
                    'produit' => $produit->fresh(['categorie', 'stock']),
                    'stock_actuel' => $stock->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Méthode pour déterminer automatiquement le statut du produit
    private function determinerStatutProduit(Stock $stock): string
    {
        if ($stock->quantite_reellement_disponible <= 0) {
            return 'rupture';
        } elseif ($stock->quantite_reellement_disponible <= $stock->seuil_alerte) {
            return 'actif'; // ou 'alerte' si vous voulez un statut spécifique
        } else {
            return 'actif';
        }
    }

    // Dans la méthode update(), corriger la gestion des médias :

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $produit = Produit::find($id);
            if (!$produit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            // Vérifier que l'utilisateur a accès à ce produit
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant || $produit->idCommercant != $commercant->idCommercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à ce produit'
                ], 403);
            }

            $request->validate([
                'nom_produit' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'prix_unitaire' => 'sometimes|required|numeric|min:0',
                'prix_promotion' => 'nullable|numeric|min:0',
                'idCategorie' => 'sometimes|required|exists:categories,idCategorie',
                'image_principale' => 'nullable|string',
                'images_supplementaires' => 'nullable|array',
                'statut' => 'sometimes|in:actif,inactif',
            ]);

            DB::beginTransaction();

            $updateData = $request->only([
                'nom_produit', 'description', 'prix_unitaire', 'prix_promotion',
                'idCategorie', 'statut'
            ]);

            // Gérer l'image principale
            if ($request->has('image_principale') && !empty($request->image_principale)) {
                $updateData['image_principale'] = $request->image_principale;

                // Supprimer l'ancien média principal
                $ancienMediaPrincipal = $produit->medias()->wherePivot('is_principal', true)->first();
                if ($ancienMediaPrincipal) {
                    $produit->medias()->detach($ancienMediaPrincipal->idMedia);
                }

                // Créer le nouveau média principal
                $media = Media::create([
                    'chemin_fichier' => $request->image_principale,
                    'type_media' => 'image'
                ]);

                $produit->medias()->attach($media->idMedia, [
                    'ordre' => 0,
                    'is_principal' => true
                ]);
            }

            // Gérer les images supplémentaires
            if ($request->has('images_supplementaires') && is_array($request->images_supplementaires)) {
                $updateData['images_supplementaires'] = $request->images_supplementaires;

                // Supprimer les anciens médias supplémentaires
                $produitsMediasSecondaires = $produit->medias()->wherePivot('is_principal', false)->get();
                foreach ($produitsMediasSecondaires as $media) {
                    $produit->medias()->detach($media->idMedia);
                }

                // Créer les nouveaux médias supplémentaires
                foreach ($request->images_supplementaires as $index => $imageUrl) {
                    $media = Media::create([
                        'chemin_fichier' => $imageUrl,
                        'type_media' => 'image'
                    ]);

                    $produit->medias()->attach($media->idMedia, [
                        'ordre' => $index + 1,
                        'is_principal' => false
                    ]);
                }
            }

            $produit->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produit mis à jour avec succès',
                'data' => $produit->load(['categorie', 'stock', 'medias'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur mise à jour produit: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du produit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer un produit
    public function destroy($id): JsonResponse
{
    try {
        $produit = Produit::with('stock')->find($id);
        if (!$produit) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Vérifier que l'utilisateur a accès à ce produit
        $user = auth()->user();
        $commercant = $user->commercant;
        if (!$commercant || $produit->idCommercant != $commercant->idCommercant) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à ce produit'
            ], 403);
        }

        DB::beginTransaction();

        // Au lieu de supprimer, on change le statut pour "cacher" le produit
        $produit->update([
            'statut' => 'inactif' // ou 'cache' si vous voulez créer un nouveau statut
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Produit caché avec succès. Il n\'apparaîtra plus dans le catalogue mais le stock est conservé.'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du masquage du produit',
            'error' => $e->getMessage()
        ], 500);
    }
}
    // Changer le statut d'un produit
    public function changerStatut(Request $request, $id): JsonResponse
    {
        $request->validate([
            'statut' => 'required|in:actif,inactif'
        ]);

        try {
            $produit = Produit::find($id);
            if (!$produit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            // Vérifier que l'utilisateur a accès à ce produit
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant || $produit->idCommercant != $commercant->idCommercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à ce produit'
                ], 403);
            }

            $produit->update([
                'statut' => $request->statut,
                'date_publication' => $request->statut === 'actif' ? now() : $produit->date_publication
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statut du produit mis à jour avec succès',
                'data' => $produit->load(['categorie', 'stock'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Récupérer les produits par statut
    public function getByStatut($statut): JsonResponse
    {
        if (!in_array($statut, ['actif', 'inactif'])) {
            return response()->json([
                'success' => false,
                'message' => 'Statut invalide'
            ], 400);
        }

        try {
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun commercant trouvé pour cet utilisateur'
                ], 404);
            }

            $produits = Produit::with(['categorie', 'stock', 'medias'])
                ->where('idCommercant', $commercant->idCommercant)
                ->where('statut', $statut)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $produits
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des produits',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Rechercher des produits
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        try {
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun commercant trouvé pour cet utilisateur'
                ], 404);
            }

            $produits = Produit::with(['categorie', 'stock', 'medias'])
                ->where('idCommercant', $commercant->idCommercant)
                ->where(function($query) use ($request) {
                    $query->where('nom_produit', 'like', '%' . $request->query . '%')
                          ->orWhere('description', 'like', '%' . $request->query . '%');
                })
                ->get();

            return response()->json([
                'success' => true,
                'data' => $produits
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Récupérer les stocks disponibles
    public function getStocksDisponibles(): JsonResponse
    {
        Log::info('Méthode getStocksDisponibles appelée');

        try {
            $commercant = auth()->user()->commercant;
            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $stocks = Stock::with(['produit'])
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

            return response()->json([
                'success' => true,
                'data' => $stocks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des stocks disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Méthodes pour gérer les médias des produits

    public function ajouterMedias(Request $request, $id): JsonResponse
    {
        try {
            $produit = Produit::find($id);
            if (!$produit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            // Vérifier les permissions
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant || $produit->idCommercant != $commercant->idCommercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $request->validate([
                'medias' => 'required|array',
                'medias.*.idMedia' => 'required|exists:media,idMedia',
                'medias.*.ordre' => 'nullable|integer|min:0',
                'medias.*.is_principal' => 'nullable|boolean'
            ]);

            foreach ($request->medias as $media) {
                $produit->medias()->attach($media['idMedia'], [
                    'ordre' => $media['ordre'] ?? 0,
                    'is_principal' => $media['is_principal'] ?? false
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Médias ajoutés avec succès',
                'data' => $produit->load('medias')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout des médias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function supprimerMedia($id, $mediaId): JsonResponse
    {
        try {
            $produit = Produit::find($id);
            if (!$produit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            // Vérifier les permissions
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant || $produit->idCommercant != $commercant->idCommercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $produit->medias()->detach($mediaId);

            return response()->json([
                'success' => true,
                'message' => 'Média supprimé du produit avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function definirMediaPrincipal($id, $mediaId): JsonResponse
    {
        try {
            $produit = Produit::find($id);
            if (!$produit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            // Vérifier les permissions
            $user = auth()->user();
            $commercant = $user->commercant;
            if (!$commercant || $produit->idCommercant != $commercant->idCommercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            // Réinitialiser tous les médias principaux
            $produit->medias()->updateExistingPivot($produit->medias->pluck('idMedia'), ['is_principal' => false]);

            // Définir le nouveau média principal
            $produit->medias()->updateExistingPivot($mediaId, ['is_principal' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Média principal défini avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la définition du média principal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
