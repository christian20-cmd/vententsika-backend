<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Panier;
use App\Models\Produit;
use App\Models\Commercant;
use Illuminate\Support\Facades\DB;

class PanierController extends Controller
{
    // ===== MÉTHODE HELPER POUR RÉCUPÉRER LE COMMERÇANT =====
    private function getCommercant()
    {
        $user = auth()->user();
        return $user->commercant;
    }

    // Ajouter un produit au panier
    public function ajouterAuPanier(Request $request)
    {
        $request->validate([
            'idProduit' => 'required|exists:produits,idProduit',
            'quantite' => 'required|integer|min:1',
            'idCommercant' => 'required|exists:commercants,idCommercant',
            'idClient' => 'required|exists:clients,idClient',
        ]);

        try {
            DB::beginTransaction();

            $produit = Produit::findOrFail($request->idProduit);
            $prix = $produit->prix_promotion ?? $produit->prix_unitaire;

            // Vérifier si le produit est déjà dans le panier
            $panierExist = Panier::where('idClient', $request->idClient)
                ->where('idProduit', $request->idProduit)
                ->first();

            if ($panierExist) {
                // Mettre à jour la quantité
                $panierExist->quantite += $request->quantite;
                $panierExist->sous_total = $panierExist->quantite * $panierExist->prix_unitaire;
                $panierExist->save();
            } else {
                // Créer un nouvel item panier
                Panier::create([
                    'idClient' => $request->idClient,
                    'idProduit' => $request->idProduit,
                    'idCommercant' => $request->idCommercant,
                    'quantite' => $request->quantite,
                    'prix_unitaire' => $prix,
                    'sous_total' => $request->quantite * $prix,
                ]);
            }

            DB::commit();

            // Récupérer le panier mis à jour avec les relations
            $panier = Panier::with(['produit', 'produit.medias'])
                ->where('idClient', $request->idClient)
                ->get();

            return response()->json([
                'message' => 'Produit ajouté au panier',
                'data' => $panier,
                'total_panier' => $panier->sum('sous_total'),
                'nombre_produits' => $panier->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de l\'ajout au panier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Afficher le panier d'un client
    public function voirPanier($idClient)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $panier = Panier::with(['produit', 'produit.medias'])
                ->where('idClient', $idClient)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->get();

            return response()->json([
                'data' => $panier,
                'total_panier' => $panier->sum('sous_total'),
                'nombre_produits' => $panier->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du panier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Modifier la quantité d'un produit
    public function modifierQuantite(Request $request)
    {
        $request->validate([
            'idClient' => 'required|exists:clients,idClient',
            'idProduit' => 'required|exists:produits,idProduit',
            'quantite' => 'required|integer|min:1'
        ]);

        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $panier = Panier::where('idClient', $request->idClient)
                ->where('idProduit', $request->idProduit)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->firstOrFail();

            $panier->quantite = $request->quantite;
            $panier->sous_total = $request->quantite * $panier->prix_unitaire;
            $panier->save();

            // Récupérer le panier mis à jour
            $panierComplet = Panier::with(['produit', 'produit.medias'])
                ->where('idClient', $request->idClient)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->get();

            return response()->json([
                'message' => 'Quantité modifiée',
                'data' => $panierComplet,
                'total_panier' => $panierComplet->sum('sous_total')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Produit non trouvé dans le panier'
            ], 404);
        }
    }

    // Supprimer un produit du panier
    public function supprimerDuPanier($idClient, $idProduit)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $panier = Panier::where('idClient', $idClient)
                ->where('idProduit', $idProduit)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->firstOrFail();

            $panier->delete();

            // Récupérer le panier mis à jour
            $panierComplet = Panier::with(['produit', 'produit.medias'])
                ->where('idClient', $idClient)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->get();

            return response()->json([
                'message' => 'Produit supprimé du panier',
                'data' => $panierComplet,
                'total_panier' => $panierComplet->sum('sous_total')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Produit non trouvé dans le panier'
            ], 404);
        }
    }

    // Vider le panier d'un client (uniquement les produits du commercant connecté)
    public function viderPanier($idClient)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            Panier::where('idClient', $idClient)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->delete();

            return response()->json([
                'message' => 'Panier vidé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du panier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Récupérer le nombre d'items dans le panier
    public function nombreItemsPanier($idClient)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $nombreItems = Panier::where('idClient', $idClient)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->count();

            $totalPanier = Panier::where('idClient', $idClient)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->sum('sous_total');

            return response()->json([
                'nombre_items' => $nombreItems,
                'total_panier' => $totalPanier
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du calcul du panier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
