<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Produit;
use App\Models\Commande;
use App\Models\CommandeProd;
use Illuminate\Support\Facades\Log; // AJOUT IMPORT MANQUANT
use App\Models\Stock; // AJOUT IMPORT MANQUANT
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeDirecteController extends Controller
{
    private function getCommercant()
    {
        $user = auth()->user();
        return $user->commercant;
    }

    /**
     * Afficher l'interface de création de commande
     * GET /api/commandes-directes/interface
     */
    public function interfaceCreation()
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            // Clients ayant déjà commandé chez ce commercant
            $clients = Client::all()->map(function($client) {
                return [
                    'idClient' => $client->idClient,
                    'nom_prenom_client' => $client->nom_prenom_client,
                    'telephone_client' => $client->telephone_client,
                    'email_client' => $client->email_client
                ];
            });

            // Produits disponibles avec stock
            $produits = Produit::with(['stock', 'medias', 'categorie'])
                ->where('idCommercant', $commercant->idCommercant)
                ->where('statut', 'actif')
                ->get()
                ->map(function ($produit) {
                    return [
                        'idProduit' => $produit->idProduit,
                        'nom_produit' => $produit->nom_produit,
                        'prix_unitaire' => $produit->prix_unitaire,
                        'prix_promotion' => $produit->prix_promotion,
                        'stock_disponible' => $produit->stock ? $produit->stock->quantite_disponible : 0,
                        'categorie' => $produit->categorie->nom_categorie ?? 'Non catégorisé',
                        'image_principale' => $produit->medias->first() ?
                            asset('storage/' . $produit->medias->first()->chemin_fichier) : null
                    ];
                });

            return response()->json([
                'success' => true,
                'clients' => $clients,
                'produits' => $produits,
                'commercant' => [
                    'idCommercant' => $commercant->idCommercant,
                    'nom_entreprise' => $commercant->vendeur->nom_entreprise ?? 'Non défini'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de l\'interface',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une commande directe
     * POST /api/commandes-directes/creer
     */
    public function creerCommandeDirecte(Request $request)
    {
        Log::info('Début création commande directe', $request->all());

        try {
            $request->validate([
                'idClient' => 'required|exists:clients,idClient',
                'produits' => 'required|array|min:1',
                'produits.*.idProduit' => 'required|exists:produits,idProduit',
                'produits.*.quantite' => 'required|integer|min:1',
                'adresse_livraison' => 'required|string|max:500',
                'date_livraison' => 'required|date|after:today',
                'notes' => 'nullable|string',
                'montant_paye' => 'required|numeric|min:0',
                'methode_paiement' => 'required|in:virement,mobile_money,carte,especes'
            ]);

            DB::beginTransaction();

            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $sousTotalGlobal = 0;
            $lignesCommande = [];
            $erreursStock = [];

            // VÉRIFICATION DES STOCKS
            foreach ($request->produits as $produitData) {
                $produit = Produit::with('stock')
                    ->where('idProduit', $produitData['idProduit'])
                    ->where('idCommercant', $commercant->idCommercant)
                    ->first();

                if (!$produit) {
                    throw new \Exception("Produit non trouvé ou non autorisé: " . $produitData['idProduit']);
                }

                // Vérifier le stock
                $stockDisponible = $produit->stock ? $produit->stock->quantite_disponible : 0;

                if ($stockDisponible < $produitData['quantite']) {
                    $erreursStock[] = [
                        'produit' => $produit->nom_produit,
                        'quantite_demandee' => $produitData['quantite'],
                        'stock_disponible' => $stockDisponible
                    ];
                }

                $prix = $produit->prix_promotion ?? $produit->prix_unitaire;
                $sousTotal = $prix * $produitData['quantite'];
                $sousTotalGlobal += $sousTotal;

                $lignesCommande[] = [
                    'produit' => $produit,
                    'quantite' => $produitData['quantite'],
                    'prix_unitaire' => $prix,
                    'sous_total' => $sousTotal
                ];
            }

            // Si erreurs de stock
            if (!empty($erreursStock)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stocks insuffisants',
                    'erreurs_stock' => $erreursStock
                ], 400);
            }

            // CALCUL DES TOTAUX
            $fraisLivraison = $sousTotalGlobal * 0.10;
            $totalCommande = $sousTotalGlobal + $fraisLivraison;

            if ($request->montant_paye > $totalCommande) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant payé ne peut pas dépasser le total de la commande'
                ], 400);
            }

            // CRÉATION COMMANDE (sans génération de numéro complexe)
            $commande = Commande::create([
                'numero_commande' => 'CMD-' . time() . '-' . rand(1000, 9999),
                'idClient' => $request->idClient,
                'idCommercant' => $commercant->idCommercant,
                'adresse_livraison' => $request->adresse_livraison,
                'frais_livraison' => $fraisLivraison,
                'total_commande' => $totalCommande,
                'montant_deja_paye' => $request->montant_paye,
                'montant_reste_payer' => $totalCommande - $request->montant_paye,
                'statut' => 'attente_validation',
                'notes' => $request->notes,
            ]);

            // CRÉATION LIGNES COMMANDE
            foreach ($lignesCommande as $ligne) {
                CommandeProd::create([
                    'idCommande' => $commande->idCommande,
                    'idClient' => $request->idClient,
                    'idCommercant' => $commercant->idCommercant,
                    'idProduit' => $ligne['produit']->idProduit,
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'sous_total' => $ligne['sous_total'],
                    'adresse_livraison' => $request->adresse_livraison,
                    'date_livraison' => $request->date_livraison,
                    'statut' => 'attente_validation',
                ]);
            }

            // MISE À JOUR STOCKS
           // === MISE À JOUR DES STOCKS (RÉSERVATION) ===
            // === MISE À JOUR DES STOCKS (RÉSERVATION) ===
            foreach ($lignesCommande as $ligne) {
                $produit = $ligne['produit'];
                if ($produit->stock) {
                    $stock = $produit->stock;
                    
                    // Diminuer la quantité disponible
                    $stock->quantite_disponible -= $ligne['quantite'];
                    
                    // Augmenter la quantité réservée
                    $stock->quantite_reservee += $ligne['quantite'];
                    
                    $stock->save();

                    Log::info("Stock mis à jour", [
                        'produit' => $produit->nom_produit,
                        'quantite_disponible' => $stock->quantite_disponible,
                        'quantite_reservee' => $stock->quantite_reservee
                    ]);
                }
            }

            // PAIEMENT
            if ($request->montant_paye > 0) {
                Paiement::create([
                    'montant' => $request->montant_paye,
                    'methode_paiement' => $request->methode_paiement,
                    'statut' => 'valide',
                    'date_paiement' => now(),
                    'idCommande' => $commande->idCommande,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'commande' => $commande->load(['lignesCommande.produit', 'client'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création commande directe: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recherche de clients pour autocomplétion
     * GET /api/commandes-directes/clients/autocomplete?search=dupont
     */
    public function autocompleteClients(Request $request)
    {
        try {
            $commercant = $this->getCommercant();
            $searchTerm = $request->get('search', '');

            $clients = Client::where(function($query) use ($searchTerm) {
                $query->where('nom_prenom_client', 'like', "%{$searchTerm}%")
                    ->orWhere('email_client', 'like', "%{$searchTerm}%")
                    ->orWhere('telephone_client', 'like', "%{$searchTerm}%");
            })
            ->whereHas('commandesProd', function($query) use ($commercant) {
                $query->where('idCommercant', $commercant->idCommercant);
            })
            ->limit(10)
            ->get(['idClient', 'nom_prenom_client', 'telephone_client', 'email_client']);

            return response()->json([
                'success' => true,
                'clients' => $clients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recherche de produits pour autocomplétion
     * GET /api/commandes-directes/produits/autocomplete?search=cafe
     */
    public function autocompleteProduits(Request $request)
    {
        try {
            $commercant = $this->getCommercant();
            $searchTerm = $request->get('search', '');

            $produits = Produit::with(['stock', 'categorie'])
                ->where('idCommercant', $commercant->idCommercant)
                ->where('statut', 'actif')
                ->where(function($query) use ($searchTerm) {
                    $query->where('nom_produit', 'like', "%{$searchTerm}%")
                        ->orWhereHas('categorie', function($q) use ($searchTerm) {
                            $q->where('nom_categorie', 'like', "%{$searchTerm}%");
                        });
                })
                ->limit(15)
                ->get()
                ->map(function ($produit) {
                    return [
                        'idProduit' => $produit->idProduit,
                        'nom_produit' => $produit->nom_produit,
                        'prix_unitaire' => $produit->prix_unitaire,
                        'prix_promotion' => $produit->prix_promotion,
                        'stock_disponible' => $produit->stock ? $produit->stock->quantite_disponible : 0,
                        'categorie' => $produit->categorie->nom_categorie ?? 'Non catégorisé'
                    ];
                });

            return response()->json([
                'success' => true,
                'produits' => $produits
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche produits',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
 * Modifier une commande existante
 * PUT /api/commandes-directes/{id}/modifier
 */
    public function modifierCommande(Request $request, $id)
    {
        $request->validate([
            'produits' => 'required|array|min:1',
            'produits.*.idProduit' => 'required|exists:produits,idProduit',
            'produits.*.quantite' => 'required|integer|min:1',
            'adresse_livraison' => 'sometimes|string|max:500',
            'date_livraison' => 'sometimes|date|after:today',
            'notes' => 'nullable|string',
            'montant_paye' => 'sometimes|numeric|min:0',
            'methode_paiement' => 'sometimes|in:virement,mobile_money,carte,especes'
        ]);

        try {
            DB::beginTransaction();

            $commercant = $this->getCommercant();
            $commande = Commande::where('idCommercant', $commercant->idCommercant)
                ->where('statut', 'attente_validation')
                ->findOrFail($id);

            // Supprimer les anciennes lignes
            $commande->lignesCommande()->delete();

            // Recréer avec les nouveaux produits
            $sousTotalGlobal = 0;

            foreach ($request->produits as $produitData) {
                $produit = Produit::with('stock')
                    ->where('idProduit', $produitData['idProduit'])
                    ->where('idCommercant', $commercant->idCommercant)
                    ->firstOrFail();

                $prix = $produit->prix_promotion ?? $produit->prix_unitaire;
                $sousTotal = $prix * $produitData['quantite'];
                $sousTotalGlobal += $sousTotal;

                CommandeProd::create([
                    'idCommande' => $commande->idCommande,
                    'idClient' => $commande->idClient,
                    'idCommercant' => $commercant->idCommercant,
                    'idProduit' => $produit->idProduit,
                    'quantite' => $produitData['quantite'],
                    'prix_unitaire' => $prix,
                    'sous_total' => $sousTotal,
                    'adresse_livraison' => $request->adresse_livraison ?? $commande->adresse_livraison,
                    'date_livraison' => $request->date_livraison ?? $commande->date_livraison,
                    'statut' => 'attente_validation',
                    'notes' => $request->notes ?? $commande->notes,
                ]);
            }

            // Mettre à jour la commande
            $fraisLivraison = $sousTotalGlobal * 0.10;
            $totalCommande = $sousTotalGlobal + $fraisLivraison;

            $commande->update([
                'frais_livraison' => $fraisLivraison,
                'total_commande' => $totalCommande,
                'montant_reste_payer' => $totalCommande - $request->montant_paye,
                'adresse_livraison' => $request->adresse_livraison ?? $commande->adresse_livraison,
                'notes' => $request->notes ?? $commande->notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande modifiée avec succès',
                'commande' => $commande->fresh(['lignesCommande.produit', 'client', 'paiements'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
