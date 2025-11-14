<?php

namespace App\Http\Controllers;

use App\Models\Vendeur;
use App\Models\VendeurLien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendeurLienController extends Controller
{
    // Copier le lien (génère un nouveau lien)
    // Dans VendeurLienController.php - Remplacer la méthode copierLien()
    public function copierLien(Request $request)
    {
        $vendeur = Vendeur::where('idUtilisateur', Auth::id())->firstOrFail();

        // TOUJOURS générer un nouveau lien à chaque copie
        $nouveauLien = $vendeur->genererLien();

        return response()->json([
            'success' => true,
            'lien' => $vendeur->lien_profil,
            'message' => 'Lien copié avec succès. Il expirera dans 24 heures.'
        ]);
    }

    // Expirer manuellement le lien
    public function expirerLien()
    {
        $vendeur = Vendeur::where('idUtilisateur', Auth::id())->firstOrFail();

        $vendeur->expirerLien();

        return response()->json([
            'success' => true,
            'message' => 'Lien expiré avec succès.'
        ]);
    }

    // Dans App\Http\Controllers\VendeurLienController
    public function getProfileInfo(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Charger la relation vendeur avec l'utilisateur
            $vendeur = Vendeur::with('utilisateur')
                ->where('idUtilisateur', $user->idUtilisateur)
                ->first();

            if (!$vendeur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil vendeur non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'vendeur' => [
                    'idVendeur' => $vendeur->idVendeur,
                    'nom_entreprise' => $vendeur->nom_entreprise,
                    'logo_url' => $vendeur->logo_url,
                    'description' => $vendeur->description,
                    'adresse_entreprise' => $vendeur->adresse_entreprise,
                    'statut_validation' => $vendeur->statut_validation,
                ],
                'utilisateur' => [
                    'prenom' => $vendeur->utilisateur->prenomUtilisateur,
                    'nom' => $vendeur->utilisateur->nomUtilisateur,
                    'email' => $vendeur->utilisateur->email,
                    'telephone' => $vendeur->utilisateur->tel,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Accéder au profil via le lien
    // Dans app/Http/Controllers/VendeurLienController.php - Modifiez cette méthode :

// Accéder au profil via le lien (route publique)
    public function accederProfil($token)
    {
        $lien = VendeurLien::with([
            'vendeur.utilisateur',
            'vendeur.produits',
            'vendeur.produits.media',
            'vendeur.produits.categorie'
        ])
            ->where('token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$lien) {
            return response()->json([
                'success' => false,
                'message' => 'Lien invalide ou expiré'
            ], 404);
        }

        $vendeur = $lien->vendeur;

        return response()->json([
            'success' => true,
            'vendeur' => [
                'id' => $vendeur->idVendeur,
                'nom_entreprise' => $vendeur->nom_entreprise,
                'description' => $vendeur->description,
                'logo_url' => $vendeur->logo_url,
                'adresse_entreprise' => $vendeur->adresse_entreprise,
                'utilisateur' => [
                    'nom' => $vendeur->utilisateur->nomUtilisateur,
                    'prenom' => $vendeur->utilisateur->prenomUtilisateur,
                    'email' => $vendeur->utilisateur->email,
                    'telephone' => $vendeur->utilisateur->tel,
                ]
            ],
            'produits' => $vendeur->produits->map(function($produit) {
                return [
                    'id' => $produit->idProduit,
                    'nom' => $produit->nom,
                    'description' => $produit->description,
                    'prix' => $produit->prix,
                    'categorie' => $produit->categorie->nom_categorie ?? null,
                    'images' => $produit->media->map(function($media) {
                        return [
                            'url' => $media->getUrl(),
                            'is_main' => $media->is_main
                        ];
                    })
                ];
            })
        ]);
    }

    // Vérifier l'état du lien actuel
    public function etatLien()
    {
        $vendeur = Vendeur::where('idUtilisateur', Auth::id())->firstOrFail();

        $lienActif = $vendeur->getLienActif();

        return response()->json([
            'has_active_link' => !is_null($lienActif),
            'link' => $lienActif ? $vendeur->lien_profil : null,
            'expires_at' => $lienActif ? $lienActif->expires_at->diffForHumans() : null,
            'is_expired' => $lienActif ? $lienActif->isExpired() : true
        ]);
    }
}
