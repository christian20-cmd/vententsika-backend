<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\CommandeProd;
use Illuminate\Http\Request;
use App\Models\Panier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    // ===== MÉTHODE HELPER POUR RÉCUPÉRER LE COMMERÇANT =====
    private function getCommercant()
    {
        $user = auth()->user();
        return $user->commercant;
    }

    // Liste tous les clients AVEC FILTRAGE PAR VENDEUR
    // Remplacer la méthode index() existante par celle-ci :
    public function index(): JsonResponse
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json([
                    'message' => 'Commerçant non trouvé'
                ], 404);
            }

            // 🔥 MODIFICATION : Récupérer TOUS les clients sans filtre
            $clients = Client::all();

            // Formater les données
            $clientsFormatted = $clients->map(function($client) use ($commercant) {
                // Récupérer les paniers du client pour ce vendeur
                $paniers = Panier::where('idClient', $client->idClient)
                    ->whereHas('produit', function($query) use ($commercant) {
                        $query->where('idCommercant', $commercant->idCommercant);
                    })
                    ->with('produit')
                    ->get();

                // Récupérer les commandes du client pour ce vendeur
                $commandes = CommandeProd::where('idClient', $client->idClient)
                    ->whereHas('produit', function($query) use ($commercant) {
                        $query->where('idCommercant', $commercant->idCommercant);
                    })
                    ->with(['produit', 'commande'])
                    ->get();

                return [
                    'idClient' => $client->idClient,
                    'nom_prenom_client' => $client->nom_prenom_client,
                    'email_client' => $client->email_client,
                    'adresse_client' => $client->adresse_client,
                    'cin_client' => $client->cin_client,
                    'telephone_client' => $client->telephone_client,
                    'paniers_count' => $paniers->count(),
                    'commandes_count' => $commandes->count(),
                    'total_commandes' => $commandes->sum('sous_total'),
                    'derniere_commande' => $commandes->sortByDesc('created_at')->first()?->created_at,
                    'produits_panier' => $paniers->map(function($panier) {
                        return [
                            'produit' => $panier->produit->nom_produit,
                            'quantite' => $panier->quantite,
                            'sous_total' => $panier->sous_total
                        ];
                    }),
                    'produits_commandes' => $commandes->map(function($commande) {
                        return [
                            'produit' => $commande->produit->nom_produit,
                            'quantite' => $commande->quantite,
                            'sous_total' => $commande->sous_total,
                            'statut' => $commande->statut,
                            'date_commande' => $commande->created_at
                        ];
                    })
                ];
            });

            return response()->json($clientsFormatted);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des clients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Afficher un client spécifique AVEC FILTRAGE
    public function show($id): JsonResponse
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            // 🔥 CORRECTION : Supprimer le filtre qui bloque l'accès
            // Ancien code problématique :
            /*
            $hasCommandes = CommandeProd::where('idClient', $id)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->exists();

            if (!$hasCommandes) {
                return response()->json(['message' => 'Client non trouvé'], 404);
            }
            */

            // Nouveau code : permettre l'accès à tous les clients
            $client = Client::find($id);

            if (!$client) {
                return response()->json(['message' => 'Client non trouvé'], 404);
            }

            return response()->json($client);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du client',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Créer un nouveau client (manuellement par le vendeur)
        public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nom_prenom_client' => 'required|string|max:255',
            'email_client' => 'required|string|email|max:255|unique:clients',
            'adresse_client' => 'required|string|max:500',
            'cin_client' => 'required|string|max:20|unique:clients',
            'telephone_client' => 'required|string|max:20',
            // 'password_client' => 'required|string|min:8', // RETIRÉ
        ]);

        $client = Client::create([
            'nom_prenom_client' => $request->nom_prenom_client,
            'email_client' => $request->email_client,
            'adresse_client' => $request->adresse_client,
            'cin_client' => $request->cin_client,
            'telephone_client' => $request->telephone_client,
            // 'password_client' => Hash::make($request->password_client), // RETIRÉ
        ]);

        return response()->json([
            'message' => 'Client créé avec succès',
            'client' => $client
        ], 201);
    }
    // Mettre à jour un client AVEC FILTRAGE
    // Dans ClientController.php - Modifiez la méthode update()
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            // 🔥 SUPPRIMER CE FILTRE ou le modifier
            // Ancien code qui bloque la modification :
            /*
            $hasCommandes = CommandeProd::where('idClient', $id)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->exists();

            if (!$hasCommandes) {
                return response()->json(['message' => 'Client non trouvé'], 404);
            }
            */

            // Nouveau code - permettre la modification de tous les clients
            $client = Client::find($id);

            if (!$client) {
                return response()->json(['message' => 'Client non trouvé'], 404);
            }

            $request->validate([
                'nom_prenom_client' => 'string|max:255',
                'email_client' => 'string|email|max:255|unique:clients,email_client,' . $id . ',idClient',
                'adresse_client' => 'string|max:500',
                'cin_client' => 'string|max:20|unique:clients,cin_client,' . $id . ',idClient',
                'telephone_client' => 'string|max:20',
                'password_client' => 'nullable|string|min:8',
            ]);

            $data = $request->only([
                'nom_prenom_client',
                'email_client',
                'adresse_client',
                'cin_client',
                'telephone_client',
            ]);

            if ($request->has('password_client') && $request->password_client) {
                $data['password_client'] = Hash::make($request->password_client);
            }

            $client->update($data);

            return response()->json([
                'message' => 'Client mis à jour avec succès',
                'client' => $client
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du client',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Dans ClientController.php - Remplacer la méthode statistiques() par ceci :
    public function statistiques(): JsonResponse
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            // 🔥 CORRECTION : Compter TOUS les clients
            $totalClients = Client::count();

            // Clients avec commandes chez ce vendeur
            $clientsAvecCommandes = CommandeProd::whereHas('produit', function($query) use ($commercant) {
                $query->where('idCommercant', $commercant->idCommercant);
            })
            ->distinct()
            ->count('idClient');

            // Clients avec paniers chez ce vendeur
            $clientsAvecPaniers = Panier::whereHas('produit', function($query) use ($commercant) {
                $query->where('idCommercant', $commercant->idCommercant);
            })
            ->distinct()
            ->count('idClient');

            // Clients actifs (avec panier ou commande dans les 30 derniers jours)
            $clientsActifs30Jours = Panier::whereHas('produit', function($query) use ($commercant) {
                $query->where('idCommercant', $commercant->idCommercant);
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct()
            ->count('idClient');

            // Taux de conversion (clients avec commandes / total clients)
            $tauxConversion = $totalClients > 0 ? ($clientsAvecCommandes / $totalClients) * 100 : 0;

            return response()->json([
                'total_clients' => $totalClients,
                'clients_avec_commandes' => $clientsAvecCommandes,
                'clients_avec_paniers' => $clientsAvecPaniers,
                'clients_actifs_30_jours' => $clientsActifs30Jours,
                'taux_conversion' => round($tauxConversion, 2),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Supprimer un client AVEC FILTRAGE
    // Dans ClientController.php - Modifiez la méthode destroy()


    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                DB::rollBack();
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $client = Client::find($id);

            if (!$client) {
                DB::rollBack();
                return response()->json(['message' => 'Client non trouvé'], 404);
            }

            // 🔥 VÉRIFICATIONS SIMPLES SEULEMENT
            $hasCommandes = Commande::where('idClient', $id)->exists();
            $hasCommandesProd = CommandeProd::where('idClient', $id)->exists();
            $hasPaniers = Panier::where('idClient', $id)->exists();

            if ($hasCommandes || $hasCommandesProd || $hasPaniers) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Impossible de supprimer ce client car il a des commandes ou paniers associés',
                    'details' => [
                        'commandes' => $hasCommandes,
                        'commandes_prod' => $hasCommandesProd,
                        'paniers' => $hasPaniers
                    ]
                ], 422);
            }

            // Si aucune donnée associée, supprimer le client
            $client->delete();

            DB::commit();

            return response()->json([
                'message' => 'Client supprimé avec succès',
                'deleted_id' => (int)$id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur suppression client ID ' . $id, [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la suppression du client',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }
    // Rechercher un client par email ou CIN AVEC FILTRAGE
    public function rechercher(Request $request): JsonResponse
    {
        try {
            $commercant = $this->getCommercant();

            if (!$commercant) {
                return response()->json(['message' => 'Commerçant non trouvé'], 404);
            }

            $request->validate([
                'email_client' => 'required_without:cin_client|email',
                'cin_client' => 'required_without:email_client|string'
            ]);

            // Trouver le client d'abord
            $client = Client::where(function($query) use ($request) {
                $query->where('email_client', $request->email_client)
                      ->orWhere('cin_client', $request->cin_client);
            })->first();

            if (!$client) {
                return response()->json(['message' => 'Client non trouvé'], 404);
            }

            // Vérifier qu'il a commandé chez ce vendeur
            $hasCommandes = CommandeProd::where('idClient', $client->idClient)
                ->whereHas('produit', function($query) use ($commercant) {
                    $query->where('idCommercant', $commercant->idCommercant);
                })
                ->exists();

            if (!$hasCommandes) {
                return response()->json(['message' => 'Client non trouvé'], 404);
            }

            return response()->json($client);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la recherche du client',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Login client (inchangé - public)
   // Soit supprimer complètement la méthode login
// Soit la modifier pour une authentification sans mot de passe
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email_client' => 'required|email',
            // 'password_client' => 'required|string', // RETIRÉ
        ]);

        $client = Client::where('email_client', $request->email_client)->first();

        if (!$client) {
            return response()->json(['message' => 'Client non trouvé'], 404);
        }

        // Générer un token Sanctum (optionnel)
        $token = $client->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'client' => $client,
            'token' => $token
        ]);
    }
}
