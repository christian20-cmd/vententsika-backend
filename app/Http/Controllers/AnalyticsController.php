<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Livraison;
use App\Models\Commercant;
use App\Models\Categorie;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    // Tableau de bord principal avec toutes les métriques
    public function dashboard(): JsonResponse
    {
        try {
            $dateRange = request('date_range', '30days');
            $startDate = $this->getStartDate($dateRange);
            $endDate = now();

            $analytics = [
                'periode' => [
                    'date_debut' => $startDate->format('Y-m-d'),
                    'date_fin' => $endDate->format('Y-m-d'),
                    'periode_type' => $dateRange
                ],
                'metriques_principales' => $this->getMainMetrics($startDate, $endDate),
                'ventes_tendances' => $this->getSalesTrends($startDate, $endDate),
                'produits_performants' => $this->getTopProducts($startDate, $endDate),
                'categories_performantes' => $this->getTopCategories($startDate, $endDate),
                'clients_actifs' => $this->getActiveClients($startDate, $endDate),
                'statistiques_livraisons' => $this->getDeliveryStats($startDate, $endDate),
                'alertes_stock' => $this->getStockAlerts(),
                'statistiques_stock_detaillees' => $this->getDetailedStockStats(), // ← NOUVEAU
                'revenus_par_commercant' => $this->getRevenueByMerchant($startDate, $endDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    // Méthode de débogage pour les produits
    public function debugProducts(): JsonResponse
    {
        try {
            $startDate = now()->subDays(30);
            $endDate = now();
            $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

            $debug = [
                'commandes_avec_produits' => DB::table('commandes')
                    ->join('commande_prods', 'commandes.idCommande', '=', 'commande_prods.idCommande')
                    ->whereBetween('commandes.created_at', [$startDate, $endDate])
                    ->whereIn('commandes.statut', $validStatuses)
                    ->count(),
                'produits_dans_commandes' => DB::table('commande_prods')
                    ->join('commandes', 'commande_prods.idCommande', '=', 'commandes.idCommande')
                    ->whereBetween('commandes.created_at', [$startDate, $endDate])
                    ->whereIn('commandes.statut', $validStatuses)
                    ->select('commande_prods.idProduit', 'commande_prods.quantite', 'commande_prods.sous_total')
                    ->get(),
                'test_top_products' => DB::table('commande_prods')
                    ->join('commandes', 'commande_prods.idCommande', '=', 'commandes.idCommande')
                    ->join('produits', 'commande_prods.idProduit', '=', 'produits.idProduit')
                    ->whereBetween('commandes.created_at', [$startDate, $endDate])
                    ->whereIn('commandes.statut', $validStatuses)
                    ->select(
                        'commande_prods.idProduit',
                        'produits.nom_produit',
                        DB::raw('SUM(commande_prods.quantite) as quantite_vendue'),
                        DB::raw('SUM(commande_prods.sous_total) as revenu_total')
                    )
                    ->groupBy('commande_prods.idProduit', 'produits.nom_produit')
                    ->get(),
                'test_top_categories' => DB::table('commande_prods')
                    ->join('commandes', 'commande_prods.idCommande', '=', 'commandes.idCommande')
                    ->join('produits', 'commande_prods.idProduit', '=', 'produits.idProduit')
                    ->join('categories', 'produits.idCategorie', '=', 'categories.idCategorie')
                    ->whereBetween('commandes.created_at', [$startDate, $endDate])
                    ->whereIn('commandes.statut', $validStatuses)
                    ->select(
                        'categories.nom_categorie',
                        DB::raw('SUM(commande_prods.quantite) as quantite_vendue'),
                        DB::raw('SUM(commande_prods.sous_total) as revenu_total')
                    )
                    ->groupBy('categories.idCategorie', 'categories.nom_categorie')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'debug' => $debug
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Analytics détaillées des ventes
    // Analytics détaillées des ventes
    public function salesAnalytics(): JsonResponse
    {
        try {
            $dateRange = request('date_range', '30days');
            $startDate = $this->getStartDate($dateRange);
            $endDate = now();

            $salesData = [
                'resume_ventes' => $this->getSalesSummary($startDate, $endDate),
                'ventes_quotidiennes' => $this->getDailySales($startDate, $endDate),
                'ventes_par_categorie' => $this->getSalesByCategory($startDate, $endDate),
                'ventes_par_commercant' => $this->getSalesByMerchant($startDate, $endDate),
                'panier_moyen' => $this->getAverageCart($startDate, $endDate),
                'taux_conversion' => $this->getConversionRate($startDate, $endDate),
                // AJOUT: Intégration de l'histogramme
                'histogramme_ventes' => $this->getHistogramData($startDate, $endDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $salesData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse des ventes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Nouvelle méthode pour récupérer spécifiquement les données d'histogramme
        // Ajouter cette méthode pour avoir les deux vues en même temps
    public function combinedSalesHistogram(): JsonResponse
    {
        try {
            $dateRange = request('date_range', '30days');
            $startDate = $this->getStartDate($dateRange);
            $endDate = now();

            $dailyData = $this->getDailyHistogramData($startDate, $endDate);
            $monthlyData = $this->getMonthlyHistogramData($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'daily' => $dailyData,
                    'monthly' => $monthlyData
                ],
                'period' => $dateRange,
                'stats' => [
                    'total_days' => $dailyData['period_days'] ?? 0,
                    'total_months' => $monthlyData['period_months'] ?? 0,
                    'recommendation' => $this->getViewRecommendation($startDate, $endDate)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des histogrammes combinés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Analytics clients
    public function customerAnalytics(): JsonResponse
    {
        try {
            $dateRange = request('date_range', '30days');
            $startDate = $this->getStartDate($dateRange);
            $endDate = now();

            $customerData = [
                'resume_clients' => $this->getCustomerSummary($startDate, $endDate),
                'nouveaux_clients' => $this->getNewCustomers($startDate, $endDate),
                'clients_fideles' => $this->getLoyalCustomers($startDate, $endDate),
                'segmentation_clients' => $this->getCustomerSegmentation($startDate, $endDate),
                'taux_retention' => $this->getRetentionRate($startDate, $endDate),
                'valeur_duree_vie_client' => $this->getCustomerLifetimeValue($startDate, $endDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $customerData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse des clients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Analytics produits
    public function productAnalytics(): JsonResponse
    {
        try {
            $dateRange = request('date_range', '30days');
            $startDate = $this->getStartDate($dateRange);
            $endDate = now();

            $productData = [
                'produits_plus_vendus' => $this->getBestSellingProducts($startDate, $endDate),
                'produits_moins_vendus' => $this->getWorstSellingProducts($startDate, $endDate),
                'analyse_stock' => $this->getStockAnalysis(),
                'analyse_stock_detaillees' => $this->getDetailedStockStats(), // ← NOUVEAU
                'rotation_stock' => $this->getStockTurnover($startDate, $endDate),
                'marge_produits' => $this->getProductMargins($startDate, $endDate),
                'alertes_prioritaires' => $this->getPriorityStockAlerts() // ← NOUVEAU (optionnel)
            ];

            return response()->json([
                'success' => true,
                'data' => $productData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'analyse des produits",
                'error' => $e->getMessage()
            ], 500);
        }
    }


        // Méthode optionnelle pour les alertes prioritaires
    private function getPriorityStockAlerts(): array
    {
        return Stock::with('produit')
            ->whereIn('statut_automatique', ['Rupture', 'Faible'])
            ->orderByRaw("CASE WHEN statut_automatique = 'Rupture' THEN 1 ELSE 2 END")
            ->orderBy('quantite_reellement_disponible')
            ->limit(10)
            ->get()
            ->map(function($stock) {
                $urgence = $stock->statut_automatique === 'Rupture' ? 'critique' : 'elevee';
                $action = $stock->statut_automatique === 'Rupture' ? 'Réapprovisionnement urgent' : 'Surveillance renforcée';

                return [
                    'produit' => $stock->produit ? $stock->produit->nom_produit : 'N/A',
                    'statut' => $stock->statut_automatique,
                    'quantite' => $stock->quantite_reellement_disponible,
                    'seuil_alerte' => $stock->seuil_alerte,
                    'urgence' => $urgence,
                    'action_recommandee' => $action,
                    'depuis' => $stock->date_derniere_maj ? Carbon::parse($stock->date_derniere_maj)->diffForHumans() : 'N/A'
                ];
            })
            ->toArray();
    }

    // Analytics en temps réel
    public function realTimeAnalytics(): JsonResponse
    {
        try {
            $realTimeData = [
                'commandes_en_temps_reel' => $this->getRealTimeOrders(),
                'visites_en_temps_reel' => $this->getRealTimeVisits(),
                'conversions_en_temps_reel' => $this->getRealTimeConversions(),
                'alertes_immediates' => $this->getImmediateAlerts()
            ];

            return response()->json([
                'success' => true,
                'data' => $realTimeData,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données temps réel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== MÉTHODES PRIVÉES ==========

    // Métriques principales
    private function getMainMetrics($startDate, $endDate): array
    {
        // Élargir les statuts valides pour inclure plus de commandes
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        // Utiliser date_validation si disponible, sinon created_at
        $totalRevenue = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', $validStatuses)
            ->sum('total_commande');

        $totalOrders = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', $validStatuses)
            ->count();

        $activeClients = Client::whereHas('commandes', function($query) use ($startDate, $endDate, $validStatuses) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('statut', $validStatuses);
        })->count();

        $productsSold = DB::table('commande_prods')
            ->join('commandes', 'commande_prods.idCommande', '=', 'commandes.idCommande')
            ->whereBetween('commandes.created_at', [$startDate, $endDate])
            ->whereIn('commandes.statut', $validStatuses)
            ->sum('commande_prods.quantite');

        return [
            'chiffre_affaires' => [
                'valeur' => round($totalRevenue, 2),
                'croissance' => 0,
                'tendance' => $totalRevenue > 0 ? 'positive' : 'stable'
            ],
            'commandes_total' => [
                'valeur' => $totalOrders,
                'croissance' => 0,
                'tendance' => $totalOrders > 0 ? 'positive' : 'stable'
            ],
            'clients_actifs' => [
                'valeur' => $activeClients,
                'croissance' => 0,
                'tendance' => $activeClients > 0 ? 'positive' : 'stable'
            ],
            'produits_vendus' => [
                'valeur' => $productsSold ?? 0,
                'croissance' => 0,
                'tendance' => $productsSold > 0 ? 'positive' : 'stable'
            ]
        ];
    }
    // Tendances des ventes
    private function getSalesTrends($startDate, $endDate): array
    {
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        $salesTrends = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', $validStatuses)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_commande) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $tendance = 'stable';
        if ($salesTrends->count() > 1) {
            $firstRevenue = $salesTrends->first()->revenue ?? 0;
            $lastRevenue = $salesTrends->last()->revenue ?? 0;
            $tendance = $lastRevenue > $firstRevenue ? 'positive' : ($lastRevenue < $firstRevenue ? 'negative' : 'stable');
        }

        return [
            'donnees_quotidiennes' => $salesTrends,
            'tendance_generale' => $tendance
        ];
    }
    // Produits les plus performants
    private function getTopProducts($startDate, $endDate): array
    {
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        return DB::table('commande_prods')
            ->join('commandes', 'commande_prods.idCommande', '=', 'commandes.idCommande')
            ->join('produits', 'commande_prods.idProduit', '=', 'produits.idProduit')
            ->whereBetween('commandes.created_at', [$startDate, $endDate])
            ->whereIn('commandes.statut', $validStatuses)
            ->select(
                'commande_prods.idProduit',
                'produits.nom_produit',
                DB::raw('SUM(commande_prods.quantite) as quantite_vendue'),
                DB::raw('SUM(commande_prods.sous_total) as revenu_total')
            )
            ->groupBy('commande_prods.idProduit', 'produits.nom_produit')
            ->orderByDesc('revenu_total')
            ->limit(5)
            ->get()
            ->map(function($item) {
                return [
                    'produit' => $item->nom_produit,
                    'quantite_vendue' => $item->quantite_vendue,
                    'revenu_total' => round($item->revenu_total, 2)
                ];
            })
            ->toArray();
    }

    private function getTopCategories($startDate, $endDate): array
    {
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        return DB::table('commande_prods')
            ->join('commandes', 'commande_prods.idCommande', '=', 'commandes.idCommande')
            ->join('produits', 'commande_prods.idProduit', '=', 'produits.idProduit')
            ->join('categories', 'produits.idCategorie', '=', 'categories.idCategorie')
            ->whereBetween('commandes.created_at', [$startDate, $endDate])
            ->whereIn('commandes.statut', $validStatuses)
            ->select(
                'categories.nom_categorie',
                DB::raw('SUM(commande_prods.quantite) as quantite_vendue'),
                DB::raw('SUM(commande_prods.sous_total) as revenu_total')
            )
            ->groupBy('categories.idCategorie', 'categories.nom_categorie')
            ->orderByDesc('revenu_total')
            ->get()
            ->toArray();
    }
    // Clients actifs
    private function getActiveClients($startDate, $endDate): array
    {
        return Client::whereHas('commandes', function($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('statut', ['livree', 'validee']);
        })
        ->withCount(['commandes' => function($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('statut', ['livree', 'validee']);
        }])
        ->withSum(['commandes' => function($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('statut', ['livree', 'validee']);
        }], 'total_commande')
        ->orderByDesc('commandes_sum_total_commande')
        ->limit(5)
        ->get()
        ->map(function($client) {
            return [
                'nom' => $client->nom_prenom_client,
                'email' => $client->email_client,
                'nombre_commandes' => $client->commandes_count,
                'montant_total' => round($client->commandes_sum_total_commande, 2)
            ];
        })
        ->toArray();
    }
    // Statistiques livraisons
    private function getDeliveryStats($startDate, $endDate): array
    {
        $deliveryStats = Livraison::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total_livraisons'),
                DB::raw('SUM(CASE WHEN status_livraison = "livre" THEN 1 ELSE 0 END) as livrees'),
                DB::raw('SUM(CASE WHEN status_livraison = "en_attente" THEN 1 ELSE 0 END) as en_attente'),
                DB::raw('SUM(CASE WHEN status_livraison = "en_preparation" THEN 1 ELSE 0 END) as en_preparation')
            )
            ->first();

        $tauxLivraison = $deliveryStats->total_livraisons > 0 ?
            round(($deliveryStats->livrees / $deliveryStats->total_livraisons) * 100, 2) : 0;

        return [
            'taux_livraison' => $tauxLivraison,
            'total_livraisons' => $deliveryStats->total_livraisons,
            'repartition_statuts' => [
                'livrees' => $deliveryStats->livrees,
                'en_attente' => $deliveryStats->en_attente,
                'en_preparation' => $deliveryStats->en_preparation
            ]
        ];
    }

    // Alertes stock
    // CORRECTION pour getStockAlerts()
    // CORRECTION pour getStockAlerts() - Inclure rupture ET faible
    private function getStockAlerts(): array
    {
        $stocksFaibles = Stock::with('produit')
            ->where('statut_automatique', 'Faible')  // ← Utiliser le statut automatique
            ->orderBy('quantite_reellement_disponible')
            ->limit(5)
            ->get()
            ->map(function($stock) {
                return [
                    'produit' => $stock->produit ? $stock->produit->nom_produit : 'N/A',
                    'quantite_restante' => $stock->quantite_reellement_disponible,
                    'statut' => 'faible',
                    'seuil_alerte' => $stock->seuil_alerte,
                    'type' => 'stock_faible'
                ];
            })
            ->toArray();

        $stocksRupture = Stock::with('produit')
            ->where('statut_automatique', 'Rupture')  // ← Utiliser le statut automatique
            ->orderBy('quantite_reellement_disponible')
            ->limit(5)
            ->get()
            ->map(function($stock) {
                return [
                    'produit' => $stock->produit ? $stock->produit->nom_produit : 'N/A',
                    'quantite_restante' => $stock->quantite_reellement_disponible,
                    'statut' => 'rupture',
                    'type' => 'rupture_stock'
                ];
            })
            ->toArray();

        // Combiner les deux types d'alertes
        return array_merge($stocksRupture, $stocksFaibles);
    }

    // Revenus par commerçant
    private function getRevenueByMerchant($startDate, $endDate): array
    {
        return Commande::whereBetween('date_validation', [$startDate, $endDate])
            ->where('statut', 'livree')
            ->join('commercants', 'commandes.idCommercant', '=', 'commercants.idCommercant')
            ->select(
                'commercants.nom_entreprise',
                DB::raw('SUM(commandes.total_commande) as revenu_total')
            )
            ->groupBy('commercants.idCommercant', 'commercants.nom_entreprise')
            ->orderByDesc('revenu_total')
            ->limit(5)
            ->get()
            ->toArray();
    }

    // ========== MÉTHODES POUR SALES ANALYTICS ==========

    private function getSalesSummary($startDate, $endDate): array
    {
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        $summary = DB::table('commandes')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', $validStatuses)
            ->select(
                DB::raw('SUM(total_commande) as chiffre_affaires'),
                DB::raw('COUNT(*) as total_commandes'),
                DB::raw('AVG(total_commande) as panier_moyen')
            )
            ->first();

        $productsSold = DB::table('commande_prods')
            ->join('commandes', 'commande_prods.idCommande', '=', 'commandes.idCommande')
            ->whereBetween('commandes.created_at', [$startDate, $endDate])
            ->whereIn('commandes.statut', $validStatuses)
            ->sum('commande_prods.quantite');

        return [
            'chiffre_affaires' => $summary->chiffre_affaires ?? 0,
            'total_commandes' => $summary->total_commandes ?? 0,
            'panier_moyen' => $summary->panier_moyen ?? 0,
            'produits_vendus' => $productsSold ?? 0
        ];
    }

    private function getDailySales($startDate, $endDate): array
    {
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        return Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', $validStatuses)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_commande) as chiffre_affaires'),
                DB::raw('COUNT(*) as commandes')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getSalesByCategory($startDate, $endDate): array
    {
        return $this->getTopCategories($startDate, $endDate);
    }

    private function getSalesByMerchant($startDate, $endDate): array
    {
        return $this->getRevenueByMerchant($startDate, $endDate);
    }

    private function getAverageCart($startDate, $endDate): float
    {
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        $avg = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', $validStatuses)
            ->avg('total_commande');

        return round($avg ?? 0, 2);
    }

    private function getConversionRate($startDate, $endDate): float
    {
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        $totalCommandes = Commande::whereBetween('created_at', [$startDate, $endDate])->count();
        $commandesConverties = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', $validStatuses)
            ->count();

        return $totalCommandes > 0 ? round(($commandesConverties / $totalCommandes) * 100, 2) : 0;
    }


    // Méthode de débogage pour les produits


    // ========== MÉTHODES POUR CUSTOMER ANALYTICS ==========

    private function getCustomerSummary($startDate, $endDate): array
    {
        return [
            'total_clients' => Client::count(),
            'nouveaux_clients' => Client::whereBetween('created_at', [$startDate, $endDate])->count(),
            'clients_actifs' => Client::whereHas('commandes', function($query) use ($startDate, $endDate) {
                $query->whereBetween('date_validation', [$startDate, $endDate]);
            })->count()
        ];
    }

    private function getNewCustomers($startDate, $endDate): array
    {
        return Client::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($client) {
                return [
                    'nom' => $client->nom_prenom_client,
                    'email' => $client->email_client,
                    'date_inscription' => $client->created_at->format('Y-m-d')
                ];
            })
            ->toArray();
    }

    private function getLoyalCustomers($startDate, $endDate): array
    {
        return $this->getActiveClients($startDate, $endDate);
    }

    private function getCustomerSegmentation($startDate, $endDate): array
    {
        return [
            'clients_occasionnels' => Client::whereHas('commandes', function($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                    ->whereIn('statut', ['livree', 'validee']);
            }, '=', 1)->count(),
            'clients_reguliers' => Client::whereHas('commandes', function($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                    ->whereIn('statut', ['livree', 'validee']);
            }, '>=', 2)->count(),
            'clients_fideles' => Client::whereHas('commandes', function($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                    ->whereIn('statut', ['livree', 'validee']);
            }, '>=', 5)->count()
        ];
    }

    private function getRetentionRate($startDate, $endDate): float
    {
        // Simplifié pour l'instant
        return 75.5;
    }

    private function getCustomerLifetimeValue($startDate, $endDate): float
    {
        $totalRevenue = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', ['livree', 'validee'])
            ->sum('total_commande');

        $totalClients = Client::whereHas('commandes', function($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('statut', ['livree', 'validee']);
        })->count();

        return $totalClients > 0 ? round($totalRevenue / $totalClients, 2) : 0;
    }

    // ========== MÉTHODES POUR PRODUCT ANALYTICS ==========

    private function getBestSellingProducts($startDate, $endDate): array
    {
        return $this->getTopProducts($startDate, $endDate);
    }

    private function getWorstSellingProducts($startDate, $endDate): array
    {
        return DB::table('commande_prods')
            ->join('commandes', 'commande_prods.idCommande', '=', 'commandes.idCommande')
            ->join('produits', 'commande_prods.idProduit', '=', 'produits.idProduit')
            ->whereBetween('commandes.date_validation', [$startDate, $endDate])
            ->where('commandes.statut', 'livree')
            ->select(
                'commande_prods.idProduit',
                'produits.nom_produit',
                DB::raw('SUM(commande_prods.quantite) as quantite_vendue')
            )
            ->groupBy('commande_prods.idProduit', 'produits.nom_produit')
            ->orderBy('quantite_vendue')
            ->limit(5)
            ->get()
            ->map(function($item) {
                return [
                    'produit' => $item->nom_produit,
                    'quantite_vendue' => $item->quantite_vendue
                ];
            })
            ->toArray();
    }
    private function getStockAnalysis(): array
    {
        $totalStocks = Stock::count();
        $stocksFaibles = Stock::where('statut_automatique', 'Faible')->count();
        $stocksRupture = Stock::where('statut_automatique', 'Rupture')->count();
        $stocksNormaux = Stock::where('statut_automatique', 'En stock')->count();

        return [
            'total_stocks' => $totalStocks,
            'stocks_faibles' => $stocksFaibles,
            'stocks_rupture' => $stocksRupture,
            'stocks_normaux' => $stocksNormaux,
            'pourcentage_alertes' => $totalStocks > 0 ? round(($stocksFaibles + $stocksRupture) / $totalStocks * 100, 2) : 0
        ];
    }

    private function getStockTurnover($startDate, $endDate): float
    {
        // Simplifié pour l'instant
        return 2.5;
    }

    private function getProductMargins($startDate, $endDate): array
    {
        return [
            'marge_moyenne' => 35.5,
            'produit_plus_rentable' => 'Produit A',
            'produit_moins_rentable' => 'Produit B'
        ];
    }

    // ========== MÉTHODES POUR REAL-TIME ANALYTICS ==========

    private function getRealTimeOrders(): array
    {
        return [
            'commandes_aujourdhui' => Commande::whereDate('date_validation', today())->count(),
            'commandes_encours' => Commande::whereIn('statut', ['en_preparation', 'expediee'])->count()
        ];
    }

    private function getRealTimeVisits(): array
    {
        return [
            'visites_aujourdhui' => 0,
            'utilisateurs_actifs' => 0
        ];
    }

    private function getRealTimeConversions(): array
    {
        return [
            'taux_conversion_ajd' => 0,
            'commandes_converties' => Commande::whereDate('date_validation', today())->count()
        ];
    }

    private function getImmediateAlerts(): array
    {
        return [
            'stocks_faibles' => Stock::where('statut_automatique', 'Faible')->count(),
            'stocks_rupture' => Stock::where('statut_automatique', 'Rupture')->count(),
            'commandes_en_retard' => Livraison::where('status_livraison', 'en_retard')->count(),
            'total_alertes_stock' => Stock::whereIn('statut_automatique', ['Faible', 'Rupture'])->count()
        ];
    }

    // ========== MÉTHODES UTILITAIRES ==========

    private function getStartDate(string $dateRange): Carbon
    {
        return match($dateRange) {
            'today' => now()->startOfDay(),
            '7days' => now()->subDays(7)->startOfDay(),
            '30days' => now()->subDays(30)->startOfDay(),
            '90days' => now()->subDays(90)->startOfDay(),
            '6months' => now()->subMonths(6)->startOfMonth(), // NOUVEAU
            '1year' => now()->subYear()->startOfMonth(),      // NOUVEAU
            '2years' => now()->subYears(2)->startOfMonth(),
            default => now()->subDays(30)->startOfDay()
        };
    }

    // Nouvelle méthode pour des statistiques détaillées des stocks
    private function getDetailedStockStats(): array
    {
        $stocks = Stock::with('produit')->get();

        $valeurStocksFaibles = 0;
        $valeurStocksRupture = 0;
        $produitsEnAlerte = [];

        foreach ($stocks as $stock) {
            if ($stock->produit && in_array($stock->statut_automatique, ['Faible', 'Rupture'])) {
                $prix = $stock->produit->prix_promotion ?? $stock->produit->prix_unitaire;
                $valeur = $stock->quantite_reellement_disponible * floatval($prix);

                if ($stock->statut_automatique === 'Faible') {
                    $valeurStocksFaibles += $valeur;
                } else {
                    $valeurStocksRupture += $valeur;
                }

                $produitsEnAlerte[] = [
                    'produit' => $stock->produit->nom_produit,
                    'statut' => $stock->statut_automatique,
                    'quantite' => $stock->quantite_reellement_disponible,
                    'seuil_alerte' => $stock->seuil_alerte,
                    'valeur' => $valeur,
                    'urgence' => $stock->statut_automatique === 'Rupture' ? 'haute' : 'moyenne'
                ];
            }
        }

        // Trier par urgence et quantité
        usort($produitsEnAlerte, function($a, $b) {
            if ($a['urgence'] === $b['urgence']) {
                return $a['quantite'] <=> $b['quantite'];
            }
            return $a['urgence'] === 'haute' ? -1 : 1;
        });

        return [
            'valeur_stocks_faibles' => round($valeurStocksFaibles, 2),
            'valeur_stocks_rupture' => round($valeurStocksRupture, 2),
            'produits_en_alerte' => array_slice($produitsEnAlerte, 0, 10), // Top 10 plus urgents
            'total_produits_alerte' => count($produitsEnAlerte),
            'impact_financier_total' => round($valeurStocksFaibles + $valeurStocksRupture, 2)
        ];
    }


    // Ajouter cette méthode dans AnalyticsController
    // CORRECTION de getHistogramData()
        // Remplacer la méthode getHistogramData() existante par celle-ci :

    // Ajouter cette méthode utilitaire pour les noms de mois en français
    private function getFrenchMonthName($month): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        return $months[$month] ?? 'Mois ' . $month;
    }









        // Remplacer la méthode salesHistogram() existante
    public function salesHistogram(): JsonResponse
    {
        try {
            $dateRange = request('date_range', '30days');
            $viewType = request('view_type', 'auto'); // 'daily', 'monthly', 'auto'

            $startDate = $this->getStartDate($dateRange);
            $endDate = now();

            // Détection automatique du meilleur type de vue
            if ($viewType === 'auto') {
                $viewType = $this->detectBestViewType($startDate, $endDate);
            }

            $histogramData = $this->getHistogramData($startDate, $endDate, $viewType);

            return response()->json([
                'success' => true,
                'data' => $histogramData,
                'view_type' => $viewType,
                'period' => $dateRange,
                'recommendation' => $this->getViewRecommendation($startDate, $endDate)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'histogramme',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Nouvelle méthode pour détecter le meilleur type de vue
    private function detectBestViewType($startDate, $endDate): string
    {
        $daysDiff = $startDate->diffInDays($endDate);
        $monthsDiff = $startDate->diffInMonths($endDate);

        // Règles de détection automatique
        if ($daysDiff <= 60) {
            return 'daily'; // Moins de 2 mois → vue quotidienne
        } elseif ($monthsDiff <= 24) {
            return 'monthly'; // 2 à 24 mois → vue mensuelle
        } else {
            return 'monthly'; // Plus de 2 ans → vue mensuelle agrégée
        }
    }

    // Remplacer la méthode getHistogramData() existante
    private function getHistogramData($startDate, $endDate, $viewType = 'auto'): array
    {
        if ($viewType === 'monthly') {
            return $this->getMonthlyHistogramData($startDate, $endDate);
        } else {
            return $this->getDailyHistogramData($startDate, $endDate);
        }
    }

    // Nouvelle méthode pour les données quotidiennes
    private function getDailyHistogramData($startDate, $endDate): array
    {
        // Générer toutes les dates de la période pour éviter les trous
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            $endDate->addDay()
        );

        $allDates = [];
        foreach ($period as $date) {
            $allDates[$date->format('Y-m-d')] = [
                'date' => $date->format('Y-m-d'),
                'chiffre_affaires' => 0,
                'commandes' => 0
            ];
        }

        // Récupérer les données réelles quotidiennes avec les bons statuts
        $validStatuses = ['livree', 'validee', 'en_preparation', 'expediee'];

        $dailySales = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', $validStatuses)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_commande) as chiffre_affaires'),
                DB::raw('COUNT(*) as commandes')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Fusionner les données réelles avec toutes les dates
        $mergedData = [];
        foreach ($allDates as $date => $defaultData) {
            if (isset($dailySales[$date])) {
                $mergedData[] = $dailySales[$date];
            } else {
                $mergedData[] = $defaultData;
            }
        }

        // Préparer les données pour l'histogramme
        $labels = [];
        $caData = [];
        $ordersData = [];

        foreach ($mergedData as $day) {
            $date = Carbon::parse($day['date']);
            $labels[] = $date->format('d/m'); // Format "28/10"
            $caData[] = round($day['chiffre_affaires'], 2);
            $ordersData[] = $day['commandes'];
        }

        return [
            'ventes_quotidiennes' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Chiffre d\'Affaires (€)',
                        'data' => $caData,
                        'backgroundColor' => '#3B82F6',
                        'borderColor' => '#1D4ED8',
                        'borderWidth' => 1
                    ]
                ]
            ],
            'commandes_quotidiennes' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Nombre de Commandes',
                        'data' => $ordersData,
                        'backgroundColor' => '#10B981',
                        'borderColor' => '#047857',
                        'borderWidth' => 1
                    ]
                ]
            ],
            'type' => 'daily',
            'period_days' => count($labels)
        ];
    }
    // Nouvelle méthode pour les données mensuelles (garder l'existante mais renommer)
    private function getMonthlyHistogramData($startDate, $endDate): array
    {
        // Récupérer les données mensuelles
        $monthlySales = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', ['livree', 'validee'])
            ->select(
                DB::raw('YEAR(created_at) as annee'),
                DB::raw('MONTH(created_at) as mois'),
                DB::raw('SUM(total_commande) as chiffre_affaires'),
                DB::raw('COUNT(*) as commandes')
            )
            ->groupBy('annee', 'mois')
            ->orderBy('annee')
            ->orderBy('mois')
            ->get();

        // Générer tous les mois de la période pour éviter les trous
        $allMonths = [];
        $current = Carbon::parse($startDate)->startOfMonth();
        $end = Carbon::parse($endDate)->endOfMonth();

        while ($current <= $end) {
            $key = $current->format('Y-m');
            $allMonths[$key] = [
                'annee' => $current->year,
                'mois' => $current->month,
                'chiffre_affaires' => 0,
                'commandes' => 0,
                'nom_mois' => $this->getFrenchMonthName($current->month)
            ];
            $current->addMonth();
        }

        // Fusionner les données réelles avec tous les mois
        foreach ($monthlySales as $sale) {
            $key = $sale->annee . '-' . str_pad($sale->mois, 2, '0', STR_PAD_LEFT);
            if (isset($allMonths[$key])) {
                $allMonths[$key]['chiffre_affaires'] = $sale->chiffre_affaires;
                $allMonths[$key]['commandes'] = $sale->commandes;
            }
        }

        // Préparer les données pour l'histogramme
        $labels = [];
        $caData = [];
        $ordersData = [];

        foreach ($allMonths as $monthData) {
            $labels[] = $monthData['nom_mois'] . ' ' . $monthData['annee'];
            $caData[] = round($monthData['chiffre_affaires'], 2);
            $ordersData[] = $monthData['commandes'];
        }

        return [
            'ventes_mensuelles' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Chiffre d\'Affaires (€)',
                        'data' => $caData,
                        'backgroundColor' => '#3B82F6',
                        'borderColor' => '#1D4ED8',
                        'borderWidth' => 1
                    ]
                ]
            ],
            'commandes_mensuelles' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Nombre de Commandes',
                        'data' => $ordersData,
                        'backgroundColor' => '#10B981',
                        'borderColor' => '#047857',
                        'borderWidth' => 1
                    ]
                ]
            ],
            'type' => 'monthly',
            'period_months' => count($labels)
        ];
    }

    // Nouvelle méthode pour les recommandations
    private function getViewRecommendation($startDate, $endDate): array
    {
        $daysDiff = $startDate->diffInDays($endDate);
        $dataPoints = Commande::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('statut', ['livree', 'validee'])
            ->count();

        $recommendation = [
            'best_view' => $daysDiff <= 60 ? 'daily' : 'monthly',
            'reason' => '',
            'data_points' => $dataPoints,
            'period_days' => $daysDiff
        ];

        if ($daysDiff <= 7) {
            $recommendation['reason'] = 'Période très courte - Vue quotidienne recommandée pour voir chaque jour';
        } elseif ($daysDiff <= 60) {
            $recommendation['reason'] = 'Période courte - Vue quotidienne pour analyse détaillée';
        } elseif ($daysDiff <= 365) {
            $recommendation['reason'] = 'Période moyenne - Vue mensuelle pour tendances générales';
        } else {
            $recommendation['reason'] = 'Période longue - Vue mensuelle pour analyse stratégique';
        }

        return $recommendation;
    }
}
