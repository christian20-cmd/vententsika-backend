<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\CommandeProd;
use App\Models\Paiement; // AJOUT IMPORT MANQUANT
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Commercant;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;



class CommandeController extends Controller
{
    // ===== MÉTHODE HELPER POUR RÉCUPÉRER LE COMMERÇANT =====
    private function getCommercant()
    {
        $user = Auth::user();
        return $user->commercant;
    }

    // ===== LISTER LES COMMANDES DU COMMERÇANT =====
// Dans CommandeController.php, modifiez la méthode index
   // Dans CommandeController.php, modifiez la méthode index
    // Dans CommandeController.php, vérifiez le format des dates
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

            $commandesGroupées = Commande::with([
                'client',
                'lignesCommande.produit.medias',
                'lignesCommande.produit.stock',
                'paiements'
            ])
            ->where('idCommercant', $commercant->idCommercant)
            ->where(function($query) {
                $query->where('statut', '!=', 'annulee')
                    ->orWhereNull('statut');
            })
            ->orderBy('created_at', 'desc')
            ->get();

            // Formater les dates explicitement
            $commandesFormatees = $commandesGroupées->map(function ($commande) {
                return [
                    ...$this->formaterCommandeAvecActions($commande),
                    // Assurer le format ISO pour les dates
                    'created_at_iso' => $commande->created_at ? $commande->created_at->toISOString() : null,
                    'updated_at_iso' => $commande->updated_at ? $commande->updated_at->toISOString() : null,
                ];
            });

            return response()->json([
                'success' => true,
                'commandes' => $commandesFormatees,
                'total_commandes' => $commandesFormatees->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération commandes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Dans CommandeController.php, ajoutez cette méthode
    public function store(Request $request)
    {
        // ⭐ AJOUT: Log complet des données reçues
        Log::info('=== 🚀 DÉBUT CRÉATION COMMANDE ===');
        Log::info('📦 DONNÉES REÇUES DU FRONTEND:', $request->all());

        // ⭐ CORRECTION: Validation simplifiée pour date_livraison
        $request->validate([
            'idClient' => 'required|exists:clients,idClient',
            'produits' => 'required|array|min:1',
            'produits.*.idProduit' => 'required|exists:produits,idProduit',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'produits.*.prix_promotion' => 'nullable|numeric|min:0',
            'date_livraison' => 'nullable|date',
            'montant_total' => 'required|numeric|min:0',
            'statut' => 'required|in:en_attente,attente_validation,validee,en_preparation,expediee,livree,annulee',
            'adresse_livraison' => 'required|string|max:500',
            'notes' => 'nullable|string',
            'choix_paiement' => 'required|in:avance,total,non_paye',
            'montant_paye' => 'nullable|numeric|min:0',
            'methode_paiement' => 'nullable|string|max:100'
        ]);

        try {
            DB::beginTransaction();

            $commercant = $this->getCommercant();

            if (!$commercant) {
                Log::error('❌ Commerçant non trouvé');
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            // ===== ÉTAPE 1: VÉRIFICATION DES STOCKS =====
            $erreursStock = [];
            $sousTotalGlobal = 0;

            Log::info('🔍 VÉRIFICATION DES STOCKS...');
            foreach ($request->produits as $produitData) {
                $produit = Produit::with('stock')
                    ->where('idProduit', $produitData['idProduit'])
                    ->where('idCommercant', $commercant->idCommercant)
                    ->first();

                if (!$produit) {
                    $erreursStock[] = [
                        'produit' => 'Produit non trouvé: ' . $produitData['idProduit'],
                        'quantite_demandee' => $produitData['quantite'],
                        'stock_disponible' => 0
                    ];
                    continue;
                }

                $stockDisponible = $produit->stock ? $produit->stock->quantite_disponible : 0;
                $quantiteDemandee = $produitData['quantite'];

                Log::info("📊 Vérification stock produit", [
                    'produit' => $produit->nom_produit,
                    'quantite_demandee' => $quantiteDemandee,
                    'stock_disponible' => $stockDisponible,
                    'suffisant' => $stockDisponible >= $quantiteDemandee
                ]);

                if ($stockDisponible < $quantiteDemandee) {
                    $erreursStock[] = [
                        'produit' => $produit->nom_produit,
                        'quantite_demandee' => $quantiteDemandee,
                        'stock_disponible' => $stockDisponible
                    ];
                }

                // Calcul du sous-total
                $prix = $produitData['prix_promotion'] ?? $produitData['prix_unitaire'];
                $sousTotalGlobal += $prix * $quantiteDemandee;
            }

            // Si erreurs de stock, on arrête IMMÉDIATEMENT
            if (!empty($erreursStock)) {
                Log::error('❌ Erreurs stock détectées', $erreursStock);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Stocks insuffisants',
                    'erreurs_stock' => $erreursStock
                ], 400);
            }

            Log::info('✅ Tous les stocks sont suffisants');

            // ===== ÉTAPE 2: RÉSERVATION DES STOCKS =====
            Log::info('🔒 RÉSERVATION DES STOCKS...');
            foreach ($request->produits as $produitData) {
                $produit = Produit::with('stock')
                    ->where('idProduit', $produitData['idProduit'])
                    ->where('idCommercant', $commercant->idCommercant)
                    ->first();

                if ($produit && $produit->stock) {
                    $stock = $produit->stock;
                    $quantite = $produitData['quantite'];

                    // Vérifier une dernière fois avant réservation
                    if ($stock->quantite_disponible < $quantite) {
                        throw new \Exception("Stock insuffisant pour {$produit->nom_produit} après vérification. Disponible: {$stock->quantite_disponible}, Demandé: {$quantite}");
                    }

                    // ⭐ CORRECTION: Utiliser la méthode de réservation du modèle Stock
                    $stock->reserverProduits($quantite);

                    Log::info("✅ Stock réservé", [
                        'produit' => $produit->nom_produit,
                        'quantite_reservee' => $quantite,
                        'nouveau_stock_disponible' => $stock->quantite_disponible,
                        'nouveau_stock_reserve' => $stock->quantite_reservee
                    ]);
                }
            }

            // ===== ÉTAPE 3: CALCUL DES FRAIS ET TOTAUX =====
            $fraisLivraison = $sousTotalGlobal * 0.10;
            $totalCommande = $sousTotalGlobal + $fraisLivraison;

            Log::info('💰 CALCUL DES TOTAUX', [
                'sous_total' => $sousTotalGlobal,
                'frais_livraison' => $fraisLivraison,
                'total_commande' => $totalCommande
            ]);

            // ===== ÉTAPE 4: GESTION DATE LIVRAISON =====
            $dateLivraison = $request->date_livraison;

            if (empty($dateLivraison) || $dateLivraison === 'null' || $dateLivraison === 'undefined') {
                $dateLivraison = now()->addDays(3)->format('Y-m-d');
                Log::info('📅 Date livraison par défaut appliquée (cas vide):', ['date' => $dateLivraison]);
            } else {
                try {
                    $dateLivraison = Carbon::parse($dateLivraison)->format('Y-m-d');
                    Log::info('📅 Date livraison parsée:', ['date' => $dateLivraison]);
                } catch (\Exception $e) {
                    Log::warning('⚠️ Date livraison invalide, utilisation par défaut', [
                        'date_reçue' => $dateLivraison,
                        'erreur' => $e->getMessage()
                    ]);
                    $dateLivraison = now()->addDays(3)->format('Y-m-d');
                }
            }

            // ===== ÉTAPE 5: GESTION PAIEMENT =====
            $montantDejaPaye = 0;
            $montantRestePayer = $totalCommande;

            Log::info('🎯 DONNÉES PAIEMENT REÇUES:', [
                'choix_paiement' => $request->choix_paiement,
                'montant_paye_request' => $request->montant_paye,
                'total_commande' => $totalCommande
            ]);

            switch ($request->choix_paiement) {
                case 'avance':
                    $montantDejaPaye = $request->montant_paye ?? ($totalCommande * 0.5);

                    if ($montantDejaPaye <= 0) {
                        Log::error('🚨 ERREUR: Acompte avec montant 0 ou négatif');
                        throw new \Exception("Le montant de l'acompte doit être supérieur à 0");
                    }

                    if ($montantDejaPaye >= $totalCommande) {
                        Log::error('🚨 ERREUR: Acompte >= total');
                        throw new \Exception("L'acompte doit être inférieur au total de la commande");
                    }

                    $montantDejaPaye = min($montantDejaPaye, $totalCommande);
                    $montantRestePayer = $totalCommande - $montantDejaPaye;
                    break;

                case 'total':
                    $montantDejaPaye = $totalCommande;
                    $montantRestePayer = 0;
                    break;

                case 'non_paye':
                    $montantDejaPaye = 0;
                    $montantRestePayer = $totalCommande;
                    break;
            }

            // ===== ÉTAPE 6: GESTION ADRESSE LIVRAISON =====
            $adresseLivraison = $request->adresse_livraison;
            if (empty($adresseLivraison) || $adresseLivraison === 'Adresse à préciser' || $adresseLivraison === 'Adresse non spécifiée') {
                $client = \App\Models\Client::find($request->idClient);
                if ($client && !empty($client->adresse_client)) {
                    $adresseLivraison = $client->adresse_client;
                } else {
                    $adresseLivraison = 'Adresse à préciser';
                }
            }

            // ===== ÉTAPE 7: CRÉATION COMMANDE =====
            Log::info('📝 CRÉATION DE LA COMMANDE...');
            $commande = Commande::create([
                'numero_commande' => Commande::genererNumeroCommande(),
                'idClient' => $request->idClient,
                'idCommercant' => $commercant->idCommercant,
                'frais_livraison' => $fraisLivraison,
                'total_commande' => $totalCommande,
                'adresse_livraison' => $adresseLivraison,
                'date_livraison' => $dateLivraison,
                'statut' => $request->statut,
                'notes' => $request->notes,
                'montant_deja_paye' => $montantDejaPaye,
                'montant_reste_payer' => $montantRestePayer,
                'date_validation' => null,
            ]);

            Log::info('✅ Commande créée', ['id' => $commande->idCommande, 'numero' => $commande->numero_commande]);

            // ===== ÉTAPE 8: CRÉATION PAIEMENT SI MONTANT > 0 =====
            if ($montantDejaPaye > 0) {
                $paiement = Paiement::create([
                    'montant' => $montantDejaPaye,
                    'methode_paiement' => $request->methode_paiement ?? 'especes',
                    'statut' => 'valide',
                    'date_paiement' => now(),
                    'idCommande' => $commande->idCommande,
                ]);
                Log::info('💳 Paiement créé', ['montant' => $montantDejaPaye]);
            }

            // ===== ÉTAPE 9: CRÉATION LIGNES COMMANDE =====
            Log::info('📦 CRÉATION DES LIGNES DE COMMANDE...');
            foreach ($request->produits as $produitData) {
                $produit = Produit::find($produitData['idProduit']);
                $prix = $produitData['prix_promotion'] ?? $produitData['prix_unitaire'];
                $sousTotal = $prix * $produitData['quantite'];

                CommandeProd::create([
                    'idCommande' => $commande->idCommande,
                    'idClient' => $request->idClient,
                    'idCommercant' => $commercant->idCommercant,
                    'idProduit' => $produitData['idProduit'],
                    'quantite' => $produitData['quantite'],
                    'prix_unitaire' => $produitData['prix_unitaire'],
                    'prix_promotion' => $produitData['prix_promotion'] ?? null,
                    'sous_total' => $sousTotal,
                    'adresse_livraison' => $adresseLivraison,
                    'date_livraison' => $dateLivraison,
                    'statut' => $request->statut,
                    'notes' => $request->notes,
                ]);

                Log::info("✅ Ligne commande créée", [
                    'produit' => $produit->nom_produit,
                    'quantite' => $produitData['quantite'],
                    'sous_total' => $sousTotal
                ]);
            }

            DB::commit();

            // ===== ÉTAPE 10: RECHARGEMENT ET RÉPONSE =====
            $commande = $commande->fresh(['lignesCommande.produit.medias', 'client', 'paiements']);

            Log::info('=== ✅ FIN CRÉATION COMMANDE ===');

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => $commande
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erreur création commande: ' . $e->getMessage());
            Log::error('📋 Stack trace:', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne du serveur'
            ], 500);
        }
    }
        // À ajouter dans CommandeController.php
    public function destroy($id)
    {
        try {
            $commercant = $this->getCommercant();
            $commande = Commande::where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // Empêcher suppression si commande validée/livrée
            if (in_array($commande->statut, ['validee', 'expediee', 'livree'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer une commande déjà validée'
                ], 400);
            }

            DB::beginTransaction();

            // Libérer les stocks réservés
            foreach ($commande->lignesCommande as $ligne) {
                if ($ligne->produit && $ligne->produit->stock) {
                    $stock = $ligne->produit->stock;
                    $stock->quantite_disponible += $ligne->quantite;
                    $stock->quantite_reservee = max(0, $stock->quantite_reservee - $ligne->quantite);
                    $stock->save();
                }
            }

            // Supprimer les paiements
            $commande->paiements()->delete();

            // Supprimer les lignes
            $commande->lignesCommande()->delete();

            // Supprimer la commande
            $commande->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }


    // ===== RÉCUPÉRER LES COMMANDES D'UN CLIENT SPÉCIFIQUE =====
    public function commandesParClient($idClient)
    {
        try {
            $commercant = $this->getCommercant();
            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $commandes = Commande::with([
                'client',
                'lignesCommande.produit.medias',
                'lignesCommande.produit.stock',
                'paiements'
            ])
            ->where('idCommercant', $commercant->idCommercant)
            ->where('idClient', $idClient)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($commande) {
                // Ajouter les informations d'action possibles
                return $this->formaterCommandeAvecActions($commande);
            });

            return response()->json([
                'success' => true,
                'commandes' => $commandes,
                'total_commandes' => $commandes->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération commandes client: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // À ajouter dans CommandeController.php
    public function update(Request $request, $id)
    {
        $request->validate([
            'adresse_livraison' => 'sometimes|required|string|max:500',
            'notes' => 'nullable|string'
        ]);

        try {
            $commercant = $this->getCommercant();
            $commande = Commande::where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // Empêcher modification si commande validée/livrée
            if (in_array($commande->statut, ['validee', 'expediee', 'livree'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier une commande déjà validée'
                ], 400);
            }

            $commande->update($request->only(['adresse_livraison', 'notes']));

            return response()->json([
                'success' => true,
                'message' => 'Commande modifiée avec succès',
                'data' => $commande
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification'
            ], 500);
        }
    }
    // ===== FORMATER LES COMMANDES AVEC INFORMATIONS D'ACTION =====
    private function formaterCommandeAvecActions(Commande $commande)
    {
        $statut = $commande->statut;

        // Déterminer les actions possibles selon le statut
        $actionsPossibles = $this->determinerActionsParStatut($statut);

        // Description du statut pour l'affichage client
        $descriptionStatut = $this->getDescriptionStatut($statut);

        // Icône selon le statut
        $iconeStatut = $this->getIconeStatut($statut);

        return [
            // Informations de base de la commande
            'idCommande' => $commande->idCommande,
            'numero_commande' => $commande->numero_commande,
            'statut' => $statut,
            'description_statut' => $descriptionStatut,
            'icone_statut' => $iconeStatut,
            'total_commande' => $commande->total_commande,
            'frais_livraison' => $commande->frais_livraison,
            'adresse_livraison' => $commande->adresse_livraison,
            'date_creation' => $commande->created_at,
            'date_validation' => $commande->date_validation,

            // ⭐⭐ CORRECTION: AJOUT DES CHAMPS DE PAIEMENT MANQUANTS ⭐⭐
            'statut_paiement' => $commande->statut_paiement,
            'montant_deja_paye' => $commande->montant_deja_paye,
            'montant_reste_payer' => $commande->montant_reste_payer,

            // Informations d'action
            'actions_possibles' => $actionsPossibles,
            'peut_modifier' => in_array('modifier', $actionsPossibles),
            'peut_supprimer' => in_array('supprimer', $actionsPossibles),
            'peut_voir_details' => in_array('voir_details', $actionsPossibles),

            // Relations
            'lignes_commande' => $commande->lignesCommande->map(function ($ligne) {
                return [
                    'idCommandeProd' => $ligne->idCommandeProd,
                    'produit' => $ligne->produit ? [
                        'idProduit' => $ligne->produit->idProduit,
                        'nom_produit' => $ligne->produit->nom_produit,
                        'image_principale' => $ligne->produit->medias->first() ?
                            asset('storage/' . $ligne->produit->medias->first()->chemin_fichier) : null
                    ] : null,
                    'quantite' => $ligne->quantite,
                    'prix_unitaire' => $ligne->prix_unitaire,
                    'prix_promotion' => $ligne->prix_promotion,
                    'sous_total' => $ligne->sous_total
                ];
            }),

            'client' => $commande->client,
            'paiements' => $commande->paiements
        ];
    }

    // ===== DÉTERMINER LES ACTIONS POSSIBLES PAR STATUT =====
    // ===== DÉTERMINER LES ACTIONS POSSIBLES PAR STATUT =====
    private function determinerActionsParStatut($statut)
    {
        $actions = ['voir_details']; // Toujours possible de voir les détails

        switch ($statut) {
            case 'panier':
            case 'attente_validation':
            case 'modification':
                $actions[] = 'modifier';
                $actions[] = 'supprimer';
                break;

            case 'annulee':
                $actions[] = 'supprimer_definitivement';
                $actions[] = 'restaurer'; // ⭐ AJOUT: possibilité de restaurer
                break;

            case 'restauree': // ⭐ AJOUT: actions pour commandes restaurées
                $actions[] = 'modifier';
                $actions[] = 'annuler';
                break;

            case 'validee':
            case 'en_preparation':
            case 'expediee':
            case 'livree':
                // Aucune action supplémentaire
                break;
        }

        return $actions;
    }








    // ===== RESTAURER UNE COMMANDE ANNULÉE =====
    public function restaurerCommande($id)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $commande = Commande::with(['lignesCommande.produit.stock'])
                ->where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // Vérifier que la commande est bien annulée
            if ($commande->statut !== 'annulee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les commandes annulées peuvent être restaurées'
                ], 400);
            }

            DB::beginTransaction();

            // Vérifier les stocks avant restauration
            $erreursStock = [];
            foreach ($commande->lignesCommande as $ligne) {
                if ($ligne->produit && $ligne->produit->stock) {
                    $stock = $ligne->produit->stock;
                    if ($stock->quantite_disponible < $ligne->quantite) {
                        $erreursStock[] = [
                            'produit' => $ligne->produit->nom_produit,
                            'quantite_demandee' => $ligne->quantite,
                            'stock_disponible' => $stock->quantite_disponible
                        ];
                    }
                }
            }

            if (!empty($erreursStock)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stocks insuffisants pour restaurer la commande',
                    'erreurs_stock' => $erreursStock
                ], 400);
            }

            // Restaurer la commande principale
            $commande->update([
                'statut' => 'attente_validation', // ou le statut précédent si vous le stockez
                'date_annulation' => null
            ]);

            // Restaurer toutes les lignes de commande
            $commande->lignesCommande()->update(['statut' => 'attente_validation']);

            // Réserver à nouveau les stocks
            foreach ($commande->lignesCommande as $ligne) {
                if ($ligne->produit && $ligne->produit->stock) {
                    $stock = $ligne->produit->stock;

                    // Réserver la quantité
                    $stock->quantite_disponible -= $ligne->quantite;
                    $stock->quantite_reservee += $ligne->quantite;

                    $stock->save();

                    Log::info("Stock réservé après restauration", [
                        'produit' => $ligne->produit->nom_produit,
                        'quantite_reservee' => $ligne->quantite,
                        'nouveau_stock_disponible' => $stock->quantite_disponible,
                        'nouveau_stock_reserve' => $stock->quantite_reservee
                    ]);
                }
            }

            DB::commit();

            // Recharger la commande avec les relations
            $commande->load(['lignesCommande.produit', 'client', 'paiements']);

            return response()->json([
                'success' => true,
                'message' => 'Commande restaurée avec succès',
                'data' => $commande
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur restauration commande: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration de la commande',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne du serveur'
            ], 500);
        }
    }
// ===== DESCRIPTION DU STATUT POUR AFFICHAGE CLIENT =====
    private function getDescriptionStatut($statut)
    {
        $descriptions = [
            'panier' => 'Commande en cours de composition',
            'attente_validation' => 'Commande en attente de validation du vendeur',
            'modification' => 'Commande en cours de modification',
            'validee' => 'Commande validée par le vendeur – en préparation',
            'en_preparation' => 'Commande en cours de préparation',
            'expediee' => 'Commande expédiée – en cours de livraison',
            'livree' => 'Commande livrée avec succès',
            'annulee' => 'Commande annulée',
            'restauree' => 'Commande restaurée' // ⭐ AJOUT
        ];

        return $descriptions[$statut] ?? 'Statut inconnu';
    }

    // ===== ICÔNE ASSOCIÉE AU STATUT =====
    private function getIconeStatut($statut)
    {
        $icones = [
            'panier' => '🛒',
            'attente_validation' => '🟡',
            'modification' => '✏️',
            'validee' => '🟢',
            'en_preparation' => '📦',
            'expediee' => '🚚',
            'livree' => '✅',
            'annulee' => '🔴',
            'restauree' => '🔄' // ⭐ AJOUT
        ];

        return $icones[$statut] ?? '❓';
    }
    // ===== MODIFIER UNE COMMANDE (PRODUITS) =====
    // ===== MODIFIER UNE COMMANDE (PRODUITS) =====
    public function modifierCommandeAvecProduits(Request $request, $id)
    {
        $request->validate([
            'produits' => 'required|array|min:1',
            'produits.*.idProduit' => 'required|exists:produits,idProduit',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_unitaire' => 'required|numeric|min:0',
            'produits.*.prix_promotion' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $commercant = $this->getCommercant();
            $commande = Commande::with(['lignesCommande.produit.stock'])
                ->where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // Vérifier que la commande est modifiable
            if (!in_array($commande->statut, ['panier', 'attente_validation', 'modification'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier une commande déjà validée'
                ], 400);
            }

            // Libérer les stocks réservés des anciennes lignes
            foreach ($commande->lignesCommande as $ligne) {
                if ($ligne->produit && $ligne->produit->stock) {
                    $stock = $ligne->produit->stock;
                    $stock->quantite_disponible += $ligne->quantite;
                    $stock->quantite_reservee = max(0, $stock->quantite_reservee - $ligne->quantite);
                    $stock->save();
                }
            }

            // Supprimer les anciennes lignes
            $commande->lignesCommande()->delete();

            $sousTotalGlobal = 0;
            $erreursStock = [];

            // Vérifier les nouveaux stocks
            foreach ($request->produits as $produitData) {
                $produit = Produit::with('stock')
                    ->where('idProduit', $produitData['idProduit'])
                    ->where('idCommercant', $commercant->idCommercant)
                    ->first();

                if (!$produit) {
                    throw new \Exception("Produit non trouvé: " . $produitData['idProduit']);
                }

                $stockDisponible = $produit->stock ? $produit->stock->quantite_disponible : 0;
                if ($stockDisponible < $produitData['quantite']) {
                    $erreursStock[] = [
                        'produit' => $produit->nom_produit,
                        'quantite_demandee' => $produitData['quantite'],
                        'stock_disponible' => $stockDisponible
                    ];
                }

                $prix = $produitData['prix_promotion'] ?? $produitData['prix_unitaire'];
                $sousTotalGlobal += $prix * $produitData['quantite'];
            }

            if (!empty($erreursStock)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Stocks insuffisants pour la modification',
                    'erreurs_stock' => $erreursStock
                ], 400);
            }

            // Recréer les lignes avec les nouveaux produits
            foreach ($request->produits as $produitData) {
                $produit = Produit::with('stock')->find($produitData['idProduit']);
                $prix = $produitData['prix_promotion'] ?? $produitData['prix_unitaire'];
                $sousTotal = $prix * $produitData['quantite'];

                CommandeProd::create([
                    'idCommande' => $commande->idCommande,
                    'idClient' => $commande->idClient,
                    'idCommercant' => $commercant->idCommercant,
                    'idProduit' => $produitData['idProduit'],
                    'quantite' => $produitData['quantite'],
                    'prix_unitaire' => $produitData['prix_unitaire'],
                    'prix_promotion' => $produitData['prix_promotion'] ?? null,
                    'sous_total' => $sousTotal,
                    'adresse_livraison' => $commande->adresse_livraison,
                    'date_livraison' => now()->addDays(3), // ⭐ CORRECTION AJOUTÉE
                    'statut' => 'modification',
                ]);

                // Réserver les nouveaux stocks
                if ($produit && $produit->stock) {
                    $stock = $produit->stock;
                    $stock->quantite_disponible -= $produitData['quantite'];
                    $stock->quantite_reservee += $produitData['quantite'];
                    $stock->save();
                }
            }

            // Recalculer les totaux
            $fraisLivraison = $sousTotalGlobal * 0.10;
            $totalCommande = $sousTotalGlobal + $fraisLivraison;

            $commande->update([
                'frais_livraison' => $fraisLivraison,
                'total_commande' => $totalCommande,
                'montant_reste_payer' => $totalCommande - $commande->montant_deja_paye,

            ]);

            DB::commit();

            $commande->load(['lignesCommande.produit.medias', 'client', 'paiements']);

            return response()->json([
                'success' => true,
                'message' => 'Commande modifiée avec succès',
                'data' => $commande
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur modification commande: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de la commande',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }



    // ===== COMMANDES ANNULÉES =====
// ===== COMMANDES ANNULÉES =====
    public function commandesAnnulees()
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $commandesAnnulees = Commande::with([
                'client',
                'lignesCommande.produit.medias',
                'lignesCommande.produit.stock',
                'paiements'
            ])
                ->where('idCommercant', $commercant->idCommercant)
                ->where('statut', 'annulee')
                ->orderBy('updated_at', 'desc') // ⭐ CORRECTION: utiliser updated_at au lieu de date_annulation
                ->get()
                ->map(function ($commande) {
                    return $this->formaterCommandeAvecActions($commande);
                });

            return response()->json([
                'success' => true,
                'commandes' => $commandesAnnulees,
                'total_commandes' => $commandesAnnulees->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération commandes annulées: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes annulées',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // À AJOUTER dans CommandeController.php
    // ===== ANNULER UNE COMMANDE =====
    // ===== ANNULER UNE COMMANDE =====
    public function annulerCommande($id)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $commande = Commande::with(['lignesCommande.produit.stock'])
                ->where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // ⭐ AJOUT: Vérifier si la commande a été restaurée
            if ($commande->statut === 'restauree') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'annuler une commande déjà restaurée'
                ], 400);
            }

            // Vérifications métier existantes
            if ($commande->statut === 'livree') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'annuler une commande déjà livrée'
                ], 400);
            }

            if ($commande->statut === 'annulee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande est déjà annulée'
                ], 400);
            }

            DB::beginTransaction();

            // Annuler la commande principale
            $commande->update([
                'statut' => 'annulee',
                'date_annulation' => now()
            ]);

            // Annuler toutes les lignes de commande
            $commande->lignesCommande()->update(['statut' => 'annulee']);

            // Libérer les stocks réservés si la commande était validée/en préparation
            if (in_array($commande->statut, ['validee', 'en_preparation', 'expediee'])) {
                foreach ($commande->lignesCommande as $ligne) {
                    if ($ligne->produit && $ligne->produit->stock) {
                        $stock = $ligne->produit->stock;

                        // Libérer la quantité réservée
                        $stock->quantite_disponible += $ligne->quantite;
                        $stock->quantite_reservee = max(0, $stock->quantite_reservee - $ligne->quantite);

                        $stock->save();

                        Log::info("Stock libéré après annulation", [
                            'produit' => $ligne->produit->nom_produit,
                            'quantite_liberee' => $ligne->quantite,
                            'nouveau_stock_disponible' => $stock->quantite_disponible,
                            'nouveau_stock_reserve' => $stock->quantite_reservee
                        ]);
                    }
                }
            }

            DB::commit();

            // Recharger la commande avec les relations
            $commande->load(['lignesCommande.produit', 'client', 'paiements']);

            return response()->json([
                'success' => true,
                'message' => 'Commande annulée avec succès',
                'data' => $commande
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur annulation commande: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de la commande',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne du serveur'
            ], 500);
        }
    }
    // ===== SUPPRIMER DÉFINITIVEMENT UNE COMMANDE ANNULÉE =====
    public function supprimerDefinitivement($id)
    {
        try {
            $commercant = $this->getCommercant();
            $commande = Commande::where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            // Vérifier que la commande est bien annulée
            if ($commande->statut !== 'annulee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les commandes annulées peuvent être supprimées définitivement'
                ], 400);
            }

            DB::beginTransaction();

            // Supprimer les paiements
            $commande->paiements()->delete();

            // Supprimer les lignes de commande
            $commande->lignesCommande()->delete();

            // Supprimer la commande
            $commande->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande supprimée définitivement avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur suppression définitive commande: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression définitive',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== AFFICHER UNE COMMANDE SPÉCIFIQUE =====
    public function show($id)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $commande = Commande::with(['client', 'lignesCommande.produit.medias', 'lignesCommande.commercant', 'paiements'])
                ->where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $commande
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }
    }

    // ===== METTRE À JOUR LE STATUT (inclut validation manuelle) =====
    public function updateStatut(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:panier,attente_validation,modification,validee,en_preparation,expediee,livree,annulee'
        ]);

        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $commande = Commande::where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            $nouveauStatut = $request->statut;

            // === LOGIQUE SPÉCIALE: transitions contrôlées ===
            // On n'autorise pas la modification si la commande est déjà livrée
            if ($commande->statut === 'livree') {
                return response()->json([
                    'message' => 'Impossible de modifier le statut d\'une commande déjà livrée'
                ], 400);
            }

            // Si on essaye de valider (manuellement) -> faire des vérifications (stock / paiement si besoin)
            if ($nouveauStatut === 'validee') {

                // Vérifier que la commande est actuellement en attente_validation (ou modification)
                if (!in_array($commande->statut, ['attente_validation', 'modification', 'panier'])) {
                    return response()->json([
                        'message' => "La commande doit être en 'attente_validation' ou 'modification' pour être validée",
                        'current_statut' => $commande->statut
                    ], 400);
                }

                // Vérifier les stocks avant validation (définitive)
                $commande->load(['lignesCommande.produit.stock']);
                foreach ($commande->lignesCommande as $ligne) {
                    if ($ligne->produit && $ligne->produit->stock) {
                        $stock = $ligne->produit->stock;
                        if ($stock->quantite_disponible < $ligne->quantite) {
                            return response()->json([
                                'message' => 'Stock insuffisant pour valider la commande',
                                'produit' => $ligne->produit->nom_produit,
                                'quantite_demandee' => $ligne->quantite,
                                'stock_disponible' => $stock->quantite_disponible
                            ], 400);
                        }
                    }
                }

                // On marque la date_validation, mais on ne déduit le stock ici (reste à la livraison)
                $commande->update([
                    'statut' => 'validee',
                    'date_validation' => now()
                ]);

                // Mettre à jour aussi les lignes
                foreach ($commande->lignesCommande as $ligne) {
                    // si une ligne était en 'attente_validation' ou 'modification', on la passe à 'validee'
                    if (in_array($ligne->statut, ['attente_validation', 'modification'])) {
                        $ligne->update(['statut' => 'validee']);
                    }
                }

                return response()->json([
                    'message' => 'Commande validée manuellement par le vendeur',
                    'data' => $commande->fresh(['lignesCommande.produit', 'paiements'])
                ]);
            }

            // === livree traitée par la fonction traiterLivraison (conserve la logique existante) ===
            if ($nouveauStatut === 'livree') {
                return $this->traiterLivraison($commande);
            }

            // Pour les autres statuts autorisés : mise à jour normale (avec protections)
            // Interdire le passage direct à 'validee' via cette voie si la commande est en 'livree' (géré ci-dessus)
            $commande->update(['statut' => $nouveauStatut]);

            return response()->json([
                'message' => 'Statut de la commande mis à jour avec succès',
                'data' => $commande
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Traiter la livraison et mettre à jour les stocks
     */
    private function traiterLivraison(Commande $commande)
    {
        // Vérifier que la commande est validée
        if ($commande->statut !== 'validee') {
            return response()->json([
                'message' => 'Seules les commandes validées peuvent être livrées'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Charger les lignes avec les stocks
            $commande->load(['lignesCommande.produit.stock']);

            foreach ($commande->lignesCommande as $ligne) {
                if ($ligne->produit && $ligne->produit->stock) {
                    $stock = $ligne->produit->stock;

                    // Vérifier le stock une dernière fois
                    if ($stock->quantite_disponible < $ligne->quantite) {
                        throw new \Exception(
                            "Stock insuffisant pour: {$ligne->produit->nom_produit}. " .
                            "Disponible: {$stock->quantite_disponible}, Demandé: {$ligne->quantite}"
                        );
                    }

                    // DÉDUCTION DU STOCK AU MOMENT DE LA LIVRAISON
                    $stock->quantite_disponible -= $ligne->quantite;
                    $stock->save();

                    // Marquer la ligne comme livrée
                    $ligne->update(['statut' => 'livree']);
                }
            }

            // Marquer la commande comme livrée
            $commande->update(['statut' => 'livree']);

            DB::commit();

            return response()->json([
                'message' => 'Commande livrée et stocks mis à jour avec succès',
                'commande' => $commande->fresh(['lignesCommande.produit'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la livraison: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== STATISTIQUES =====
    public function statistiques()
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $idCommercant = $commercant->idCommercant;

            // ===== COMMANDES GROUPÉES =====
            $commandesGroupees = Commande::where('idCommercant', $idCommercant)->count();
            $commandesGroupeesValidees = Commande::where('idCommercant', $idCommercant)
                ->where('statut', 'validee')->count();
            $commandesGroupeesLivrees = Commande::where('idCommercant', $idCommercant)
                ->where('statut', 'livree')->count();
            $caGroupees = Commande::where('idCommercant', $idCommercant)
                ->where('statut', 'livree')->sum('total_commande');

            return response()->json([
                'success' => true,
                'total_commandes' => $commandesGroupees,
                'commandes_validees' => $commandesGroupeesValidees,
                'commandes_livrees' => $commandesGroupeesLivrees,
                'chiffre_affaires' => round($caGroupees, 2),
                'details' => [
                    'commandes_groupees' => [
                        'total' => $commandesGroupees,
                        'validees' => $commandesGroupeesValidees,
                        'livrees' => $commandesGroupeesLivrees,
                        'ca' => round($caGroupees, 2)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== GÉNÉRER LA FACTURE =====
// ===== GÉNÉRER LA FACTURE =====
    public function genererFacture($id)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $commande = Commande::with([
                'client',
                'commercant.vendeur',
                'lignesCommande.produit',
                'paiements'
            ])
            ->where('idCommercant', $commercant->idCommercant)
            ->findOrFail($id);

            // Vérifier que la vue existe
            if (!view()->exists('factures.commande')) {
                Log::error('Vue facture manquante: factures.commande');
                return response()->json([
                    'message' => 'Template de facture non disponible'
                ], 500);
            }

            $pdf = Pdf::loadView('factures.commande', compact('commande'));

            return $pdf->download("facture-{$commande->numero_commande}.pdf");

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Commande non trouvée'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Erreur génération facture: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la génération de la facture',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }
    // ===== AFFICHER LA FACTURE =====
    public function afficherFacture($id)
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            $commande = Commande::with(['client', 'commercant', 'lignesCommande.produit'])
                ->where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            $pdf = Pdf::loadView('factures.commande', compact('commande'));

            return $pdf->stream("facture-{$commande->numero_commande}.pdf");

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'affichage de la facture',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== COMMANDES VALIDÉES =====
    public function commandesValidees()
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            // Commandes groupées validées
            $commandesGroupées = Commande::with(['client', 'lignesCommande.produit.medias', 'lignesCommande.commercant'])
                ->where('idCommercant', $commercant->idCommercant)
                ->where('statut', 'validee')
                ->orderBy('date_validation', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'commandes' => $commandesGroupées,
                'total_commandes' => $commandesGroupées->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes validées',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== ENREGISTRER UN PAIEMENT =====
    // Dans CommandeController.php - méthode enregistrerPaiement
    public function enregistrerPaiement(Request $request, $id)
    {
        // ⭐ AJOUT: Log des données reçues
        Log::info('=== 💰 DÉBUT ENREGISTREMENT PAIEMENT ===');
        Log::info('📦 DONNÉES PAIEMENT REÇUES:', $request->all());
        Log::info('🎯 ID COMMANDE:', ['id' => $id]);

        $request->validate([
            'montant' => 'required|numeric|min:0',
            'methode_paiement' => 'required|string|max:100',
        ]);

        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                Log::error('❌ Commerçant non trouvé pour le paiement');
                return response()->json([
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            Log::info('🔍 RECHERCHE COMMANDE:', [
                'idCommande' => $id,
                'idCommercant' => $commercant->idCommercant
            ]);

            $commande = Commande::where('idCommercant', $commercant->idCommercant)
                ->findOrFail($id);

            Log::info('✅ COMMANDE TROUVÉE:', [
                'id' => $commande->idCommande,
                'numero' => $commande->numero_commande,
                'total_commande' => $commande->total_commande,
                'montant_deja_paye' => $commande->montant_deja_paye,
                'montant_reste_payer' => $commande->montant_reste_payer
            ]);

            $nouveauMontantPaye = ($commande->paiements()->where('statut', 'valide')->sum('montant')) + $request->montant;

            Log::info('💰 CALCUL MONTANT:', [
                'montant_actuel_paye' => $commande->paiements()->where('statut', 'valide')->sum('montant'),
                'nouveau_montant' => $request->montant,
                'nouveau_total_paye' => $nouveauMontantPaye,
                'total_commande' => $commande->total_commande
            ]);

            if ($nouveauMontantPaye > $commande->total_commande) {
                Log::error('🚨 ERREUR: Montant payé dépasse le total', [
                    'nouveau_montant_paye' => $nouveauMontantPaye,
                    'total_commande' => $commande->total_commande
                ]);
                return response()->json([
                    'message' => 'Le montant payé ne peut pas dépasser le total de la commande'
                ], 400);
            }

            // ⭐ CORRECTION: Créer un nouveau paiement
            $paiement = Paiement::create([
                'montant' => $request->montant,
                'methode_paiement' => $request->methode_paiement,
                'statut' => 'valide',
                'date_paiement' => now(),
                'idCommande' => $commande->idCommande,
            ]);

            Log::info('💸 PAIEMENT CRÉÉ:', [
                'id_paiement' => $paiement->idPaiement,
                'montant' => $paiement->montant,
                'methode' => $paiement->methode_paiement
            ]);

            // ⭐ CORRECTION: Mettre à jour les champs montants (stockés)
            $montantDejaPaye = $commande->paiements()->where('statut', 'valide')->sum('montant');
            $commande->update([
                'montant_deja_paye' => $montantDejaPaye,
                'montant_reste_payer' => max(0, $commande->total_commande - $montantDejaPaye),
            ]);

            Log::info('📊 MISE À JOUR COMMANDE:', [
                'nouveau_montant_deja_paye' => $montantDejaPaye,
                'nouveau_montant_reste_payer' => $commande->montant_reste_payer,
                'statut_paiement_calculé' => $commande->statut_paiement
            ]);

            Log::info('=== ✅ FIN ENREGISTREMENT PAIEMENT ===');

            return response()->json([
                'message' => 'Paiement enregistré avec succès',
                'data' => [
                    'montant_paye' => $request->montant,
                    'total_deja_paye' => $montantDejaPaye,
                    'reste_a_payer' => $commande->montant_reste_payer,
                    'statut_paiement' => $commande->statut_paiement,
                    'commande_statut' => $commande->statut
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ Commande non trouvée pour le paiement:', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Commande non trouvée'
            ], 404);
        } catch (\Exception $e) {
            Log::error('❌ Erreur enregistrement paiement: ' . $e->getMessage());
            Log::error('📋 Stack trace:', ['exception' => $e]);

            return response()->json([
                'message' => 'Erreur lors de l\'enregistrement du paiement',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne du serveur'
            ], 500);
        }
    }
}
