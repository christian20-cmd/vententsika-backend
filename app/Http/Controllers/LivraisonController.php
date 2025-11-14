<?php

namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\Commande;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class LivraisonController extends Controller
{
    // ===== MÉTHODE HELPER POUR RÉCUPÉRER LE COMMERÇANT =====
    private function getCommercant()
    {
        $user = auth()->user();
        return $user->commercant;
    }






    // ===== MARQUER COMME EN PRÉPARATION =====
    public function marquerPreparation($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $livraison = Livraison::with(['commande.lignesCommande.produit.stock'])
                ->whereHas('commande', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->findOrFail($id);

            // Vérifier si la livraison peut être mise en préparation
            if ($livraison->status_livraison !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les livraisons en attente peuvent être mises en préparation. Statut actuel: ' . $livraison->status_livraison
                ], 422);
            }

            // Mettre à jour le statut
            $livraison->update([
                'status_livraison' => 'en_preparation'
            ]);

            // Mettre à jour le statut de la commande
            $livraison->commande->update(['statut' => 'en_preparation']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison marquée comme en préparation',
                'data' => $livraison->fresh(['commande.client', 'commande.lignesCommande.produit'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Erreur mise en préparation: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise en préparation: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== MARQUER COMME EN TRANSIT =====
public function marquerTransit($id): JsonResponse
{
    DB::beginTransaction();

    try {
        $commercant = $this->getCommercant();

        if (!$commercant) {
            return response()->json(['message' => 'Commerçant non trouvé'], 404);
        }

        $livraison = Livraison::with(['commande'])
            ->whereHas('commande', function($query) use ($commercant) {
                $query->where('idCommercant', $commercant->idCommercant);
            })
            ->findOrFail($id);

        // Vérifier si la livraison peut être mise en transit
        if (!in_array($livraison->status_livraison, ['en_preparation', 'expedie'])) {
            return response()->json([
                'success' => false,
                'message' => 'Statut non autorisé pour le transit. Statut actuel: ' . $livraison->status_livraison
            ], 422);
        }

        $livraison->update([
            'status_livraison' => 'en_transit'
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Livraison marquée comme en transit',
            'data' => $livraison->fresh(['commande.client', 'commande.lignesCommande.produit'])
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("❌ Erreur mise en transit: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise en transit: ' . $e->getMessage()
        ], 500);
    }
}

// ===== MARQUER COMME ANNULÉE =====
    public function marquerAnnule($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $livraison = Livraison::with(['commande.lignesCommande.produit.stock'])
                ->whereHas('commande', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->findOrFail($id);

            // Vérifier si la livraison peut être annulée
            if ($livraison->status_livraison === 'livre') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'annuler une livraison déjà livrée'
                ], 422);
            }

            // Si des produits étaient réservés, les libérer
            if (in_array($livraison->status_livraison, ['en_preparation', 'expedie', 'en_transit'])) {
                foreach ($livraison->commande->lignesCommande as $ligne) {
                    if ($ligne->produit && $ligne->produit->stock) {
                        $stock = $ligne->produit->stock;
                        $quantite = $ligne->quantite;

                        if ($stock->quantite_reservee >= $quantite) {
                            $stock->quantite_reservee -= $quantite;
                            $stock->date_derniere_maj = now();
                            $stock->save();
                        }
                    }
                }
            }

            // Mettre à jour les statuts
            $livraison->update([
                'status_livraison' => 'annule'
            ]);

            $livraison->commande->update(['statut' => 'annulee']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison annulée avec succès',
                'data' => $livraison->fresh(['commande.client', 'commande.lignesCommande.produit'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Erreur annulation: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== MARQUER COMME LIVRÉ (CORRIGÉ POUR LE STOCK) =====
    // ===== MARQUER COMME LIVRÉ (VERSION CORRIGÉE) =====
// ===== MARQUER COMME LIVRÉ (VERSION CORRIGÉE) =====
    public function marquerLivre($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $livraison = Livraison::with(['commande.lignesCommande.produit.stock'])
                ->whereHas('commande', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->findOrFail($id);

            $commande = $livraison->commande;

            // === VÉRIFICATION NOUVELLE : PAIEMENT COMPLET ===
            if ($commande->montant_reste_payer > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de marquer comme livrée : la commande n\'est pas entièrement payée. ' .
                                'Montant restant à payer : ' . $commande->montant_reste_payer . ' €'
                ], 422);
            }

            if ($livraison->status_livraison === 'livre') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette livraison est déjà marquée comme livrée.'
                ], 422);
            }

            // === GESTION DU STOCK ===
            foreach ($commande->lignesCommande as $ligne) {
                if ($ligne->produit && $ligne->produit->stock) {
                    $stock = $ligne->produit->stock;
                    $quantite = $ligne->quantite;

                    if ($stock->quantite_reservee < $quantite) {
                        throw new \Exception(
                            "Quantité réservée insuffisante pour {$ligne->produit->nom_produit}. " .
                            "Réservée: {$stock->quantite_reservee}, Demandée: {$quantite}"
                        );
                    }

                    $stock->quantite_reservee -= $quantite;
                    $stock->date_derniere_maj = now();
                    $stock->save();
                }
            }

            // Mettre à jour la livraison et la commande
            $livraison->update([
                'status_livraison' => 'livre',
                'date_livraison_reelle' => now(),
            ]);

            $commande->update(['statut' => 'livree']);

            // Envoi d'email
            $emailEnvoye = $livraison->envoyerEmailLivraison();

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Livraison marquée comme livrée avec succès',
                'data' => $livraison->fresh(['commande.client', 'commande.lignesCommande.produit.stock'])
            ];

            if ($emailEnvoye) {
                $response['email'] = 'Email de confirmation envoyé au client';
                Log::info("✅ Email envoyé avec succès pour la livraison #{$livraison->idLivraison}");
            } else {
                $response['email'] = 'Email non envoyé (voir logs pour détails)';
                Log::warning("⚠️ Email non envoyé pour la livraison #{$livraison->idLivraison}");
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Erreur livraison: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== CONSERVER LES AUTRES MÉTHODES EXISTANTES =====

    public function index(): JsonResponse
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $livraisons = Livraison::with(['commande.client', 'commande.lignesCommande.produit', 'commande.paiements'])
                ->whereHas('commande', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livraison) {
                    return [
                        'idLivraison' => $livraison->idLivraison,
                        'numero_suivi' => $livraison->numero_suivi,
                        'status_livraison' => $livraison->status_livraison,
                        'date_livraison_prevue' => $livraison->date_livraison_prevue,
                        'date_livraison_reelle' => $livraison->date_livraison_reelle,
                        'notes_livraison' => $livraison->notes_livraison,
                        'adresse_livraison' => $livraison->adresse_livraison, // ← UTILISER l'adresse de la livraison

                        // Informations de la commande (via relation)
                        'commande' => [
                            'idCommande' => $livraison->commande->idCommande,
                            'numero_commande' => $livraison->commande->numero_commande,
                            'total_commande' => $livraison->commande->total_commande,
                            'frais_livraison' => $livraison->commande->frais_livraison,
                            'montant_deja_paye' => $livraison->commande->montant_deja_paye,
                            'montant_reste_payer' => $livraison->commande->montant_reste_payer,
                            // 'adresse_livraison' => $livraison->commande->adresse_livraison, // ← RETIRER cette ligne
                            'client' => [
                                'nom_prenom_client' => $livraison->commande->client->nom_prenom_client,
                                'email_client' => $livraison->commande->client->email_client,
                                'telephone_client' => $livraison->commande->client->telephone_client
                            ],
                            'lignes_commande' => $livraison->commande->lignesCommande,
                            'paiements' => $livraison->commande->paiements
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $livraisons
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function show($id): JsonResponse
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $livraison = Livraison::with(['commande.client', 'commande.lignesCommande.produit.stock'])
                ->whereHas('commande', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $livraison
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison non trouvée'
            ], 404);
        }
    }

    // Créer une nouvelle livraison - AVEC VÉRIFICATION DU COMMERÇANT
    // ===== CRÉER UNE NOUVELLE LIVRAISON - VERSION CORRIGÉE =====
 // ===== CRÉER UNE NOUVELLE LIVRAISON - VERSION SIMPLIFIÉE =====
    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'idCommande' => 'required|exists:commandes,idCommande',
                'date_livraison_prevue' => 'required|date|after:today',
                'notes_livraison' => 'nullable|string',
                'numero_suivi' => 'nullable|string|max:100|unique:livraisons,numero_suivi',
            ]);

            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            // Récupérer la commande DU COMMERÇANT CONNECTÉ
            $commande = Commande::with(['client', 'lignesCommande.produit'])
                ->where('idCommercant', $commercant->idCommercant)
                ->findOrFail($request->idCommande);

            // Vérifier si la commande est déjà livrée
            if ($commande->statut === 'livree') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de créer une livraison pour une commande déjà livrée.'
                ], 422);
            }

            // Vérifier si une livraison existe déjà pour cette commande
            $existingLivraison = Livraison::where('idCommande', $request->idCommande)->first();
            if ($existingLivraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une livraison existe déjà pour cette commande.'
                ], 409);
            }

            // Vérifier que la commande est validée
            if ($commande->statut !== 'validee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les commandes validées peuvent être livrées. Statut actuel: ' . $commande->statut
                ], 422);
            }

            // Générer un numéro de suivi si non fourni
            $numeroSuivi = $request->numero_suivi ?? Livraison::genererNumeroSuivi();

            // Créer la livraison avec seulement les informations essentielles
            $livraison = Livraison::create([
                'idCommande' => $request->idCommande,
                'nom_client' => $commande->client->nom_prenom_client,
                'telephone_client' => $commande->client->telephone_client,
                'adresse_livraison' => $request->adresse_livraison ?? $commande->adresse_livraison,
                'date_livraison_prevue' => $request->date_livraison_prevue ?? now()->addDays(3)->format('Y-m-d'),                'numero_suivi' => $numeroSuivi,
                'status_livraison' => 'en_attente',
                'notes_livraison' => $request->notes_livraison,
                // On ne stocke pas les informations financières ici
            ]);

            // Mettre à jour le statut de la commande
            $commande->update([
                'statut' => 'en_preparation'
            ]);

            DB::commit();

            // Recharger la livraison avec toutes les relations
            $livraison->load(['commande.client', 'commande.lignesCommande.produit', 'commande.paiements']);

            return response()->json([
                'success' => true,
                'message' => 'Livraison créée avec succès',
                'numero_suivi' => $numeroSuivi,
                'data' => [
                    'idLivraison' => $livraison->idLivraison,
                    'numero_suivi' => $livraison->numero_suivi,
                    'status_livraison' => $livraison->status_livraison,
                    'date_livraison_prevue' => $livraison->date_livraison_prevue,
                    'notes_livraison' => $livraison->notes_livraison,

                    // Informations de la commande (via relation)
                    'commande' => [
                        'idCommande' => $commande->idCommande,
                        'numero_commande' => $commande->numero_commande,
                        'statut' => $commande->statut,
                        'total_commande' => $commande->total_commande,
                        'frais_livraison' => $commande->frais_livraison,
                        'montant_deja_paye' => $commande->montant_deja_paye,
                        'montant_reste_payer' => $commande->montant_reste_payer,
                        'adresse_livraison' => $commande->adresse_livraison,
                        'client' => [
                            'nom_prenom_client' => $commande->client->nom_prenom_client,
                            'email_client' => $commande->client->email_client,
                            'telephone_client' => $commande->client->telephone_client
                        ],
                        'lignes_commande' => $commande->lignesCommande->map(function($ligne) {
                            return [
                                'produit' => $ligne->produit->nom_produit,
                                'quantite' => $ligne->quantite,
                                'prix_unitaire' => $ligne->prix_unitaire,
                                'sous_total' => $ligne->sous_total
                            ];
                        }),
                        'paiements' => $commande->paiements
                    ]
                ]
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée ou non autorisée'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création livraison: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne du serveur'
            ], 500);
        }
    }
    // Mettre à jour une livraison - AVEC VALIDATIONS
    public function update(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'adresse_livraison' => 'sometimes|string|max:255',
                'date_livraison_prevue' => 'sometimes|date|after:today',
                'numero_suivi' => 'nullable|string|max:100|unique:livraisons,numero_suivi,' . $id . ',idLivraison',
                'status_livraison' => 'sometimes|in:en_attente,en_preparation,expedie,en_transit,livre,retourne,annule',
                'notes_livraison' => 'nullable|string',
                'frais_livraison' => 'sometimes|numeric|min:0',
            ]);

            $livraison = Livraison::findOrFail($id);

            // Vérifier si la livraison peut être modifiée
            if (!$livraison->peutEtreModifiee()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette livraison ne peut plus être modifiée (statut: ' . $livraison->status_livraison . ')'
                ], 422);
            }

            $livraison->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison mise à jour avec succès',
                'data' => $livraison->fresh(['commande.client', 'commande.lignesCommande.produit'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer une livraison - AVEC VALIDATION
    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $livraison = Livraison::findOrFail($id);

            // Vérifier si la livraison peut être supprimée
            if (!$livraison->peutEtreSupprimee()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette livraison ne peut pas être supprimée (statut: ' . $livraison->status_livraison . ')'
                ], 422);
            }

            $livraison->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Recalculer les frais de livraison
    public function recalculerFraisLivraison($id): JsonResponse
    {
        try {
            $livraison = Livraison::with('commande')->findOrFail($id);

            // Recalculer les frais de livraison (10%)
            $nouveauxFrais = $livraison->commande->total_commande * 0.10;

            return response()->json([
                'success' => true,
                'message' => 'Frais de livraison recalculés avec succès',
                'data' => [
                    'ancien_frais_livraison' => $livraison->frais_livraison, // Via accesseur
                    'nouveau_frais_livraison' => $nouveauxFrais,
                    'montant_total_commande' => $livraison->commande->total_commande,
                    'pourcentage_applique' => '10%',
                    'difference' => $nouveauxFrais - $livraison->frais_livraison,
                    'note' => 'Les frais sont calculés dynamiquement via les accesseurs'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du recalcul des frais de livraison',
                'error' => $e->getMessage()
            ], 500);
        }
}

    // Marquer comme expédié
    public function marquerExpedie($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $livraison = Livraison::findOrFail($id);
            $livraison->marquerCommeExpedie();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison marquée comme expédiée',
                'data' => $livraison->fresh(['commande.client', 'commande.lignesCommande.produit'])
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

    // Marquer comme livré (avec mise à jour automatique du stock)


    // Export PDF d'une livraison
    public function exportPdf($id)
    {
        try {
            $livraison = Livraison::with(['commande.client', 'commande.lignesCommande.produit'])
                ->findOrFail($id);

            $pdf = Pdf::loadView('livraisons.pdf', compact('livraison'));

            return $pdf->download('livraison-' . $livraison->idLivraison . '.pdf');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Rechercher les commandes disponibles pour livraison
    public function commandesDisponibles(): JsonResponse
    {
        try {
            // Commandes validées qui n'ont pas encore de livraison
            $commandes = Commande::with(['client', 'lignesCommande.produit'])
                ->whereNotIn('idCommande', function($query) {
                    $query->select('idCommande')->from('livraisons');
                })
                ->where('statut', 'validee')
                ->get()
                ->map(function($commande) {
                    // Calculer les frais de livraison estimés (10%)
                    $fraisLivraisonEstimes = $commande->total_commande * 0.10;
                    $totalAvecLivraison = $commande->total_commande + $fraisLivraisonEstimes;

                    return [
                        'idCommande' => $commande->idCommande,
                        'numero_commande' => 'CMD-' . str_pad($commande->idCommande, 6, '0', STR_PAD_LEFT),
                        'client' => $commande->client->nom_prenom_client ?? 'Client inconnu',
                        'telephone' => $commande->client->telephone_client ?? 'Non renseigné',
                        'produits' => $commande->lignesCommande->map(function($ligne) {
                            return [
                                'nom_produit' => $ligne->produit->nom_produit ?? 'Produit inconnu',
                                'quantite' => $ligne->quantite,
                                'prix_unitaire' => $ligne->prix_unitaire,
                            ];
                        }),
                        'total_commande' => $commande->total_commande,
                        'frais_livraison_estimes' => round($fraisLivraisonEstimes, 2),
                        'total_avec_livraison' => round($totalAvecLivraison, 2),
                        'adresse_livraison' => $commande->adresse_livraison,
                        'date_livraison' => $commande->date_livraison,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $commandes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Statistiques des livraisons
    // Dans la méthode statistiques(), remplacez cette partie :
    public function statistiques(): JsonResponse
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $livraisons = Livraison::with(['commande'])
                ->whereHas('commande', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->get();

            // Calculer les totaux MANUELLEMENT via les accesseurs
            $totalFraisLivraison = 0;
            $totalMontantCommandes = 0;

            foreach ($livraisons as $livraison) {
                $totalFraisLivraison += $livraison->frais_livraison;
                $totalMontantCommandes += $livraison->montant_total_commande;
            }

            // Compter par statut
            $totalLivraisons = $livraisons->count();
            $livraisonsEnAttente = $livraisons->where('status_livraison', 'en_attente')->count();
            $livraisonsEnPreparation = $livraisons->where('status_livraison', 'en_preparation')->count();
            $livraisonsExpediees = $livraisons->where('status_livraison', 'expedie')->count();
            $livraisonsEnTransit = $livraisons->where('status_livraison', 'en_transit')->count();
            $livraisonsLivrees = $livraisons->where('status_livraison', 'livre')->count();
            $livraisonsRetournees = $livraisons->where('status_livraison', 'retourne')->count();
            $livraisonsAnnulees = $livraisons->where('status_livraison', 'annule')->count();

            // Calcul du pourcentage
            $pourcentageReelFrais = $totalMontantCommandes > 0 ?
                round(($totalFraisLivraison / $totalMontantCommandes) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_livraisons' => $totalLivraisons,
                    'statuts' => [
                        'en_attente' => $livraisonsEnAttente,
                        'en_preparation' => $livraisonsEnPreparation,
                        'expediees' => $livraisonsExpediees,
                        'en_transit' => $livraisonsEnTransit,
                        'livrees' => $livraisonsLivrees,
                        'retournees' => $livraisonsRetournees,
                        'annulees' => $livraisonsAnnulees,
                    ],
                    'financier' => [
                        'total_frais_livraison' => round($totalFraisLivraison, 2),
                        'total_montant_commandes' => round($totalMontantCommandes, 2),
                        'pourcentage_frais_livraison_reel' => $pourcentageReelFrais,
                        'pourcentage_frais_livraison_theorique' => '10%',
                        'ecart_pourcentage' => round($pourcentageReelFrais - 10, 2)
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

    // Méthode pour obtenir le détail des calculs d'une livraison
    public function detailCalculs($id): JsonResponse
    {
        try {
            $livraison = Livraison::with(['commande', 'commande.lignesCommande.produit'])
                ->findOrFail($id);

            $detailCalculs = [
                'montant_total_commande' => $livraison->montant_total_commande,
                'frais_livraison_appliques' => $livraison->frais_livraison,
                'pourcentage_calcule' => '10%',
                'calcul_detaille' => [
                    'base_calcul' => $livraison->montant_total_commande,
                    'pourcentage' => 0.10,
                    'frais_calcules' => $livraison->montant_total_commande * 0.10,
                    'total_avec_livraison' => $livraison->montant_total_commande + $livraison->frais_livraison
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $detailCalculs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des détails de calcul',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
