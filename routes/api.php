<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Auth\PasswordResetCodeController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommandeProdController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\VendeurLienController;
use App\Http\Controllers\CommandeGroupéeController;
use App\Http\Controllers\Admin\AdministrateurController;
use App\Http\Controllers\Admin\VendeurAdminController;
use App\Http\Controllers\Admin\AdminPasswordResetController;
use App\Http\Controllers\CommandeDirecteController;
use App\Http\Controllers\Admin\DashboardController;


// ===== ROUTES PUBLIQUES =====

// NOUVELLES ROUTES D'INSCRIPTION EN 5 ÉTAPES
Route::post('/register/step1-personal-info', [RegisteredUserController::class, 'step1PersonalInfo']);
Route::post('/register/step2-verify-code', [RegisteredUserController::class, 'step2VerifyCode']);
Route::post('/register/step3-entreprise-info', [RegisteredUserController::class, 'step3EntrepriseInfo']);
Route::post('/register/step4-password', [RegisteredUserController::class, 'step4Password']);
Route::post('/register/step5-finalize', [RegisteredUserController::class, 'step5Finalize']);
Route::post('/register/resend-code', [RegisteredUserController::class, 'resendCode']);

// Ancienne route d'inscription
Route::post('/register', [RegisteredUserController::class, 'store']);

// ⭐⭐ CORRECTION : AJOUT DE LA ROUTE ADMIN LOGIN
Route::post('/admin/login', [AdministrateurController::class, 'login']);

Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout']);

// Mot de passe oublié
Route::post('/password/send-code', [PasswordResetCodeController::class, 'sendCode']);
Route::post('/password/verify-code', [PasswordResetCodeController::class, 'verifyCode']);
Route::post('/password/reset', [PasswordResetCodeController::class, 'resetPassword']);

// Test API
Route::get('/test', function() {
    return response()->json(['status' => 'API fonctionne']);
});

// Catégories publiques
Route::get('/categories', function () {
    return response()->json(\App\Models\Categorie::all());
});

// Vendeur - Accès public au profil
Route::get('/vendeur/profile/{token}', [VendeurLienController::class, 'accederProfil']);

// Clients - Routes publiques
Route::post('/clients/register', [ClientController::class, 'store']);
Route::post('/clients/login', [ClientController::class, 'login']);

// ===== ROUTES PROTÉGÉES (AUTH:SANCTUM) =====
Route::middleware('auth:sanctum')->group(function () {

    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ===== UPLOAD D'IMAGES ET MÉDIAS =====
    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload']);
        Route::get('/', [MediaController::class, 'index']);
        Route::delete('/{id}', [MediaController::class, 'destroy']);
    });

    // Route d'upload existante (compatibilité)
    Route::post('/upload-image', function (Request $request) {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

            // Stocker dans storage/app/public/images
            $path = $image->storeAs('public/images', $imageName);

            // Retourner l'URL accessible
            return response()->json([
                'success' => true,
                'url' => asset('storage/images/' . $imageName),
                'message' => 'Image uploadée avec succès'
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Aucune image fournie'
        ], 400);
    });

    // ===== VENDEUR =====
    Route::prefix('vendeur')->group(function () {
        Route::post('/lien/copier', [VendeurLienController::class, 'copierLien']);
        Route::post('/lien/expirer', [VendeurLienController::class, 'expirerLien']);
        Route::get('/lien/etat', [VendeurLienController::class, 'etatLien']);
        Route::get('/profile-info', [VendeurLienController::class, 'getProfileInfo']);
    });

    // ===== PRODUITS =====
    Route::prefix('produits')->group(function () {
        Route::get('/', [ProduitController::class, 'index']);
        Route::post('/', [ProduitController::class, 'store']);

        // Nouvelles routes pour gérer les médias
        Route::post('/{id}/medias', [ProduitController::class, 'ajouterMedias']);
        Route::delete('/{id}/medias/{mediaId}', [ProduitController::class, 'supprimerMedia']);
        Route::put('/{id}/medias/{mediaId}/principal', [ProduitController::class, 'definirMediaPrincipal']);

        // Routes existantes
        Route::get('/stocks-disponibles', [ProduitController::class, 'getStocksDisponibles']);
        Route::post('/{produitId}/image-principale/{mediaId}', [ProduitController::class, 'definirImagePrincipale']);
        Route::get('/{id}', [ProduitController::class, 'show']);
        Route::put('/{id}', [ProduitController::class, 'update']);
        Route::delete('/{id}', [ProduitController::class, 'destroy']);
        Route::put('/{id}/statut', [ProduitController::class, 'changerStatut']);
        Route::post('/produits/{id}/reservation', [ProduitController::class, 'mettreAJourReservation']);
        Route::get('/statut/{statut}', [ProduitController::class, 'getByStatut']);
        Route::post('/search', [ProduitController::class, 'search']);
        Route::get('/produits/avec-stocks', [ProduitController::class, 'produitsAvecStocks']);
    });

    // ===== STOCKS =====
    Route::prefix('stocks')->group(function () {
        // Routes sans paramètre
        Route::get('/statistiques', [StockController::class, 'statistiques']);
        Route::get('/alerte/seuil', [StockController::class, 'stocksAlerte']);
        Route::get('/rupture', [StockController::class, 'stocksRupture']);
        Route::get('/sans-produit', [StockController::class, 'stocksSansProduit']);
        Route::get('/non-publies', [StockController::class, 'getStocksNonPublies']);

        // Routes avec paramètre
        Route::get('/', [StockController::class, 'index']);
        Route::post('/', [StockController::class, 'store']);
        Route::get('/{id}', [StockController::class, 'show']);
        Route::put('/{id}', [StockController::class, 'update']);
        Route::delete('/{id}', [StockController::class, 'destroy']);
        Route::get('/{idStock}/infos-publication', [StockController::class, 'getInfosPourPublication']);
        Route::post('/{idStock}/publier', [StockController::class, 'publierStock']);
        Route::post('/{id}/update-quantite', [StockController::class, 'updateQuantite']);
        Route::post('/{id}/reserver', [StockController::class, 'reserverProduits']);
        Route::post('/{id}/livrer', [StockController::class, 'livrerProduits']);
        Route::post('/{id}/associer-produit', [StockController::class, 'associerProduit']);
        Route::post('/{id}/tester-alerte', [StockController::class, 'testerAlerte']);
        Route::put('/{id}/stock-entree', [StockController::class, 'updateStockEntree']);
        Route::put('/{id}/update-complete', [StockController::class, 'updateComplete']);
    });

    // ===== CLIENTS =====

    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::post('/', [ClientController::class, 'store']);
        Route::get('/statistiques', [ClientController::class, 'statistiques']);
        Route::post('/rechercher', [ClientController::class, 'rechercher']);
        Route::get('/{id}', [ClientController::class, 'show']);
        Route::put('/{id}', [ClientController::class, 'update']);
        Route::delete('/{id}', [ClientController::class, 'destroy']);
    });

    // ===== COMMANDES DIRECTES =====
    Route::prefix('commandes-directes')->group(function () {
        Route::get('/interface', [CommandeDirecteController::class, 'interfaceCreation']);
        Route::post('/creer', [CommandeDirecteController::class, 'creerCommandeDirecte']);
        Route::get('/clients/autocomplete', [CommandeDirecteController::class, 'autocompleteClients']);
        Route::get('/produits/autocomplete', [CommandeDirecteController::class, 'autocompleteProduits']);
        Route::put('/{id}/valider', [CommandeController::class, 'updateStatut']);
    });

    // ===== COMMANDES EN ATTENTE =====
    Route::prefix('commandes-attente')->group(function () {
        Route::get('/', [CommandeProdController::class, 'index']);
        Route::put('/{id}', [CommandeProdController::class, 'update']);
        Route::delete('/{id}', [CommandeProdController::class, 'destroy']);
        Route::post('/{id}/valider', [CommandeProdController::class, 'valider']);

    });

    // ===== COMMANDES GROUPÉES =====
    Route::prefix('commandes-groupees')->group(function () {
        Route::post('/valider-panier/{idClient}', [CommandeGroupéeController::class, 'validerPanierComplet']);
        Route::get('/{idCommande}', [CommandeGroupéeController::class, 'afficherCommande']);
        Route::get('/{idCommande}/details', [CommandeGroupéeController::class, 'detailsCommande']);
    });

    // ===== COMMANDES VALIDÉES =====
    Route::prefix('commandes')->group(function () {
        Route::get('/statistiques', [CommandeController::class, 'statistiques']);
        Route::get('/validees', [CommandeController::class, 'commandesValidees']);
        Route::get('/', [CommandeController::class, 'index']);
        Route::post('/', [CommandeController::class, 'store']);
        Route::get('/{id}', [CommandeController::class, 'show']);

        Route::put('/{id}', [CommandeController::class, 'update']);
        Route::delete('/{id}', [CommandeController::class, 'destroy']);

        Route::put('/{id}/statut', [CommandeController::class, 'updateStatut']);
        Route::post('/{id}/paiement', [CommandeController::class, 'enregistrerPaiement']);
        Route::get('/{id}/facture', [CommandeController::class, 'genererFacture']);
        Route::get('/{id}/facture-preview', [CommandeController::class, 'afficherFacture']);

        Route::put('/{id}/annuler', [CommandeController::class, 'annulerCommande']);
        Route::put('/{id}/restaurer', [CommandeController::class, 'restaurerCommande']);
        Route::put('/{id}/modifier-produits', [CommandeController::class, 'modifierCommandeAvecProduits']);
        Route::get('/client/{idClient}', [CommandeController::class, 'commandesParClient']);
        Route::delete('/{id}/supprimer-definitivement', [CommandeController::class, 'supprimerDefinitivement']);

        Route::get('/statut/annulees', [CommandeController::class, 'commandesAnnulees']);
    });

    // ===== LIVRAISONS =====
    Route::prefix('livraisons')->group(function () {
        Route::get('/commandes-disponibles', [LivraisonController::class, 'commandesDisponibles']);
        Route::get('/statistiques', [LivraisonController::class, 'statistiques']);
        Route::get('/', [LivraisonController::class, 'index']);
        Route::post('/', [LivraisonController::class, 'store']);
        Route::get('/{id}', [LivraisonController::class, 'show']);
        Route::put('/{id}', [LivraisonController::class, 'update']);
        Route::delete('/{id}', [LivraisonController::class, 'destroy']);

        Route::post('/{id}/expedier', [LivraisonController::class, 'marquerExpedie']);
        Route::post('/{id}/livrer', [LivraisonController::class, 'marquerLivre']);
        Route::post('/{id}/preparer', [LivraisonController::class, 'marquerPreparation']);
        Route::post('/{id}/transit', [LivraisonController::class, 'marquerTransit']);
        Route::post('/{id}/annuler', [LivraisonController::class, 'marquerAnnule']);

        Route::post('/{id}/recalculer-frais', [LivraisonController::class, 'recalculerFraisLivraison']);
        Route::get('/{id}/pdf', [LivraisonController::class, 'exportPdf']);
        Route::get('/{id}/detail-calculs', [LivraisonController::class, 'detailCalculs']);
    });

    // ===== ANALYTICS =====
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/ventes', [AnalyticsController::class, 'salesAnalytics']);
        Route::get('/clients', [AnalyticsController::class, 'customerAnalytics']);
        Route::get('/produits', [AnalyticsController::class, 'productAnalytics']);
        Route::get('/temps-reel', [AnalyticsController::class, 'realTimeAnalytics']);
        Route::post('/personnalise', [AnalyticsController::class, 'customAnalytics']);

        Route::get('/sales-histogram', [AnalyticsController::class, 'salesHistogram']);
        Route::get('/combined-histogram', [AnalyticsController::class, 'combinedSalesHistogram']);
        Route::get('/debug-products', [AnalyticsController::class, 'debugProducts']);    });

    // ===== CATÉGORIES (CRÉATION) =====
    Route::post('/categories', function (Request $request) {
        $request->validate([
            'nom_categorie' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $categorie = \App\Models\Categorie::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Catégorie créée avec succès',
            'data' => $categorie
        ], 201);
    });
});

// ===== ROUTES ADMIN =====
// Routes publiques ADMIN
Route::prefix('admin')->group(function () {
    // Routes d'authentification publiques
    Route::post('/register', [AdministrateurController::class, 'register']);
    Route::post('/login', [AdministrateurController::class, 'login']);

    // Routes d'invitation publiques
    Route::get('/invitations/validate/{token}', [AdministrateurController::class, 'validateInvitationToken']);
    Route::post('/register-with-token', [AdministrateurController::class, 'registerWithToken']);

    // Routes de mot de passe oublié
    Route::post('/password/reset', [AdminPasswordResetController::class, 'demandeReset']);
    Route::post('/password/verify', [AdminPasswordResetController::class, 'verifierCode']);
    Route::post('/password/change', [AdminPasswordResetController::class, 'resetPassword']);
        Route::post('/password/forgot', [AdministrateurController::class, 'forgotPassword']);
    Route::post('/password/forgot', [AdministrateurController::class, 'forgotPassword']);
    Route::post('/password/reset', [AdministrateurController::class, 'resetPassword']);
    Route::post('/password/validate-token', [AdministrateurController::class, 'validateResetToken']);
});



// Routes protégées (admin)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('admin')->group(function () {
        // Gestion des administrateurs
        Route::get('/administrateurs', [AdministrateurController::class, 'index']);
        Route::get('/administrateurs/statistiques', [AdministrateurController::class, 'statistiques']);
        Route::post('/administrateurs/create', [AdministrateurController::class, 'createAdmin']);
        Route::put('/administrateurs/{id}', [AdministrateurController::class, 'update']);
        Route::delete('/administrateurs/{id}', [AdministrateurController::class, 'destroy']);
        Route::get('/administrateurs/search', [AdministrateurController::class, 'search']);

        // Gestion des invitations (protégées)
        Route::post('/invitations/generate', [AdministrateurController::class, 'generateInvitationLink']);
        Route::get('/invitations/active', [AdministrateurController::class, 'listActiveInvitations']);
        Route::post('/invitations/regenerate/{token}', [AdministrateurController::class, 'regenerateInvitationLink']);

        // ⭐⭐ AJOUT: Route de test email (protégée)
        Route::post('/test-email', [AdministrateurController::class, 'testEmail']);

        // Gestion des vendeurs
        Route::get('/vendeurs', [VendeurAdminController::class, 'index']);
        Route::get('/vendeurs/statistiques', [VendeurAdminController::class, 'statistiques']);
        Route::get('/vendeurs/search', [VendeurAdminController::class, 'search']);
        Route::get('/vendeurs/{id}', [VendeurAdminController::class, 'show']);
        Route::put('/vendeurs/{id}/statut', [VendeurAdminController::class, 'updateStatut']);
        Route::delete('/vendeurs/{id}', [VendeurAdminController::class, 'destroy']);
        Route::get('/demandes-en-attente', [AdministrateurController::class, 'listDemandesEnAttente']);
        Route::post('/demandes/{idDemande}/valider', [AdministrateurController::class, 'validerDemande']);
        Route::post('/demandes/{idDemande}/rejeter', [AdministrateurController::class, 'rejeterDemande']);

                // Dashboard
        // Par ceci avec middleware explicite :
        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'dashboard']);
            Route::get('/dashboard/statistiques-detaillees', [DashboardController::class, 'statistiquesDetaillees']);
        });
        Route::get('/dashboard/statistiques-detaillees', [DashboardController::class, 'statistiquesDetaillees']);

        // Déconnexion
        Route::post('/logout', [AdministrateurController::class, 'logout']);
    });
});
