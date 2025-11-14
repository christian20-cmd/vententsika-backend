<?php

namespace App\Http\Controllers;

use App\Models\CommandeProd;
use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CommandeProdController extends Controller
{
    private function getCommercant()
    {
        $user = Auth::user();
        $commercant = \App\Models\Commercant::where('idUtilisateur', $user->idUtilisateur)->first();
        return $commercant;
    }

    // Afficher les commandes individuelles (obsolètes dans le nouveau système)
    public function index()
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            // Commandes individuelles validées (sans commande groupée)
            $commandesIndividuelles = CommandeProd::with(['client', 'produit.medias', 'commercant'])
                ->where('idCommercant', $commercant->idCommercant)
                ->where('statut', 'validee')
                ->whereNull('idCommande')
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Note: Le système de commandes individuelles est remplacé par les commandes directes groupées',
                'commandes_individuelles' => $commandesIndividuelles,
                'nouveau_systeme' => '/api/commandes-directes/creer'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Désactiver l'ancienne méthode de création
    public function store(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Méthode obsolète. Utilisez /api/commandes-directes/creer pour créer des commandes.',
            'new_endpoint' => '/api/commandes-directes/creer'
        ], 410);
    }

    // Mettre à jour une commande produit existante
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantite' => 'sometimes|integer|min:1',
            'adresse_livraison' => 'sometimes|string|max:255',
            'date_livraison' => 'sometimes|date|after:today',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $commercant = $this->getCommercant();

            $commandeProd = CommandeProd::where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // === RESTRICTION : empêcher modification si la ligne ou la commande parent est validée ou livrée ===
            if (in_array($commandeProd->statut, ['validee', 'livree'])) {
                return response()->json([
                    'message' => 'Impossible de modifier une ligne de commande déjà validée ou livrée'
                ], 400);
            }

            if ($commandeProd->idCommande) {
                $commandeParent = Commande::find($commandeProd->idCommande);
                if ($commandeParent && in_array($commandeParent->statut, ['validee', 'livree'])) {
                    return response()->json([
                        'message' => 'Impossible de modifier cette ligne : la commande parent est déjà validée ou livrée'
                    ], 400);
                }
            }

            // Recalculer le sous-total si la quantité change
            if ($request->has('quantite')) {
                $sousTotal = $commandeProd->prix_unitaire * $request->quantite;
                $request->merge(['sous_total' => $sousTotal]);
            }

            $commandeProd->update($request->all());

            // Si la commande parent existe, recalculer ses totaux stockés
            if (isset($commandeParent) && $commandeParent) {
                $total = $commandeParent->lignesCommande()->sum('sous_total');
                $fraisLivraison = $commandeParent->frais_livraison ?? ($total * 0.10);
                $commandeParent->update([
                    'total_commande' => $total + $fraisLivraison,
                    'montant_deja_paye' => $commandeParent->paiements()->where('statut', 'valide')->sum('montant'),
                    'montant_reste_payer' => max(0, ($total + $fraisLivraison) - $commandeParent->paiements()->where('statut', 'valide')->sum('montant'))
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Commande mise à jour avec succès',
                'data' => $commandeProd
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Valider une commande produit individuelle (reste autorisé si besoin)
    public function valider($id)
    {
        try {
            DB::beginTransaction();

            $commercant = $this->getCommercant();

            $commandeProd = CommandeProd::where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // Vérifier stock avant validation
            if ($commandeProd->produit && $commandeProd->produit->stock) {
                $stock = $commandeProd->produit->stock;
                if ($stock->quantite_disponible < $commandeProd->quantite) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuffisant pour valider cette commande",
                        'produit' => $commandeProd->produit->nom_produit,
                        'quantite_demandee' => $commandeProd->quantite,
                        'stock_disponible' => $stock->quantite_disponible
                    ], 400);
                }
            }

            // Si la ligne appartient à une commande groupée déjà validée -> empêcher la re-validation indépendante
            if ($commandeProd->idCommande) {
                $commandeParent = Commande::find($commandeProd->idCommande);
                if ($commandeParent && $commandeParent->statut === 'validee') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Impossible de valider une ligne uniquement : la commande groupée est déjà validée'
                    ], 400);
                }
            }

            $commandeProd->update(['statut' => 'validee']);

            DB::commit();

            return response()->json([
                'message' => 'Commande validée avec succès',
                'data' => $commandeProd
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la validation de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer une commande produit
    public function destroy($id)
    {
        try {
            $commercant = $this->getCommercant();

            $commandeProd = CommandeProd::where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // Vérifier que la commande n'est pas déjà livrée
            if ($commandeProd->statut === 'livree') {
                return response()->json([
                    'message' => 'Impossible de supprimer une commande déjà livrée'
                ], 400);
            }

            // Si la ligne appartient à une commande groupée validée, empêcher la suppression
            if ($commandeProd->idCommande) {
                $commandeParent = Commande::find($commandeProd->idCommande);
                if ($commandeParent && $commandeParent->statut === 'validee') {
                    return response()->json([
                        'message' => 'Impossible de supprimer une ligne : la commande parent est déjà validée'
                    ], 400);
                }
            }

            $commandeProd->delete();

            return response()->json([
                'message' => 'Commande supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== MÉTHODES OBSOLÈTES - DÉSACTIVÉES =====

    public function creerDepuisPanier(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Méthode obsolète. Le système panier est remplacé par les commandes directes.',
            'new_endpoint' => '/api/commandes-directes/creer'
        ], 410);
    }

    public function validerPanierComplet($idClient)
    {
        return response()->json([
            'success' => false,
            'message' => 'Méthode obsolète. Utilisez /api/commandes-directes/creer pour créer des commandes complètes.',
            'new_endpoint' => '/api/commandes-directes/creer'
        ], 410);
    }
}
