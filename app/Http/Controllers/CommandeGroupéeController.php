<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\CommandeProd;
use App\Models\Panier;
use App\Models\Produit;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommandeGroupéeController extends Controller
{
    /**
     * Rediriger vers le nouveau système
     */
    public function __construct()
    {
        // Toutes les méthodes de ce contrôleur sont obsolètes
    }

    /**
     * Méthode désactivée - Rediriger vers le nouveau système
     */
    public function validerPanierComplet(Request $request, $idClient)
    {
        return response()->json([
            'success' => false,
            'message' => 'Cette fonctionnalité est obsolète. Utilisez le système de commandes directes.',
            'new_endpoint' => '/api/commandes-directes/creer',
            'instructions' => 'Utilisez POST /api/commandes-directes/creer avec les produits sélectionnés directement'
        ], 410);
    }

    /**
     * Méthode désactivée
     */
    public function ajouterPaiement(Request $request, $idCommande)
    {
        return response()->json([
            'success' => false,
            'message' => 'Méthode obsolète. Utilisez le système de paiement des commandes directes.',
            'new_endpoint' => '/api/commandes/' . $idCommande . '/paiement'
        ], 410);
    }

    /**
     * Méthode désactivée
     */
    public function afficherCommande($idCommande)
    {
        return response()->json([
            'success' => false,
            'message' => 'Méthode obsolète. Utilisez le système de consultation des commandes.',
            'new_endpoint' => '/api/commandes/' . $idCommande
        ], 410);
    }

    /**
     * Méthode désactivée
     */
    public function detailsCommande($idCommande)
    {
        return response()->json([
            'success' => false,
            'message' => 'Méthode obsolète. Utilisez le système de consultation des commandes.',
            'new_endpoint' => '/api/commandes/' . $idCommande
        ], 410);
    }
}
