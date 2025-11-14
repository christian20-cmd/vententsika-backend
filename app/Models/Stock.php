<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Stock extends Model
{
    use HasFactory;

    protected $table = 'stocks';
    protected $primaryKey = 'idStock';

    protected $fillable = [
        'code_stock',
        'idCommercant',
        'idProduit',
        'quantite_disponible',
        'quantite_reservee',
        'stock_entree',
        'quantite_reellement_disponible',
        'seuil_alerte',
        'statut_automatique',
        'situation',
        'valeur',
        'date_derniere_maj'
    ];

    // Relation avec le produit
    public function produit()
    {
        return $this->belongsTo(Produit::class, 'idProduit');
    }

    // Relation avec le commercant
    public function commercant()
    {
        return $this->belongsTo(Commercant::class, 'idCommercant');
    }


     // ⭐⭐ AJOUTER CETTE MÉTHODE MANQUANTE ⭐⭐
    public function reserverProduits($quantite)
    {
        // Vérifier que la quantité est disponible
        $stockReellementDisponible = $this->stock_entree - $this->quantite_reservee;

        if ($stockReellementDisponible < $quantite) {
            throw new \Exception("Stock insuffisant. Disponible: {$stockReellementDisponible}, Demandé: {$quantite}");
        }

        // Mettre à jour uniquement la quantité réservée
        $this->quantite_reservee += $quantite;

        // ⭐⭐ AJOUTER CETTE LIGNE : Mettre à jour le statut automatique
        $this->mettreAJourStatutAutomatique();

        // Mettre à jour la date
        $this->date_derniere_maj = now();

        // Sauvegarder
        $this->save();

        return $this;
    }

    // ⭐⭐ OPTIONNEL: Ajouter aussi cette méthode pour la livraison ⭐⭐
    public function deduireStockApresLivraison($quantite)
    {
        // Vérifier que la quantité réservée est suffisante
        if ($this->quantite_reservee < $quantite) {
            throw new \Exception("Quantité réservée insuffisante. Réservé: {$this->quantite_reservee}, À livrer: {$quantite}");
        }

        // Dédutire de la quantité réservée
        $this->quantite_reservee -= $quantite;

        // Recalculer la quantité réellement disponible
        $this->quantite_reellement_disponible = $this->stock_entree - $this->quantite_reservee;

        // Mettre à jour la date
        $this->date_derniere_maj = now();

        // Sauvegarder
        $this->save();

        return $this;
    }


    // Dans app/Models/Stock.php - Modifiez cette méthode
    public function mettreAJourStatutAutomatique()
    {
        // Stock réellement disponible = Stock entrée - Réservé
        $stockReellementDisponible = $this->stock_entree - $this->quantite_reservee;

        // Mettre à jour la quantité réellement disponible
        $this->quantite_reellement_disponible = max(0, $stockReellementDisponible);

        // Sauvegarder l'ancien statut pour détecter les changements
        $ancienStatut = $this->statut_automatique;

        // Déterminer le nouveau statut
        if ($this->quantite_reellement_disponible <= 0) {
            $nouveauStatut = 'Rupture';
        } elseif ($this->quantite_reellement_disponible <= $this->seuil_alerte) {
            $nouveauStatut = 'Faible';
        } else {
            $nouveauStatut = 'En stock';
        }

        $this->statut_automatique = $nouveauStatut;
        $this->save();

        // ⭐⭐ ENVOYER L'ALERTE SI LE STATUT A CHANGÉ VERS "Faible" ou "Rupture" ⭐⭐
        if (($nouveauStatut === 'Faible' || $nouveauStatut === 'Rupture') && $ancienStatut !== $nouveauStatut) {
            $this->envoyerAlerteSeuil();
        }

        return $this;
    }


    // Dans app/Models/Stock.php - Ajoutez cette méthode
    public function envoyerAlerteSeuil()
    {
        try {
            // Vérifier si le produit et le commercant existent
            if (!$this->produit || !$this->produit->commercant) {
                Log::error('Impossible d\'envoyer l\'alerte: produit ou commercant manquant');
                return false;
            }

            $commercant = $this->produit->commercant;
            $vendeur = $commercant->vendeur;

            if (!$vendeur || !$vendeur->utilisateur) {
                Log::error('Impossible d\'envoyer l\'alerte: vendeur ou utilisateur manquant');
                return false;
            }

            $emailVendeur = $vendeur->utilisateur->email;
            $nomVendeur = $vendeur->utilisateur->nomUtilisateur . ' ' . $vendeur->utilisateur->prenomUtilisateur;
            $nomProduit = $this->produit->nom_produit;
            $stockRestant = $this->quantite_reellement_disponible;
            $seuilAlerte = $this->seuil_alerte;

            // Déterminer le type d'alerte
            if ($stockRestant <= 0) {
                $sujet = "🚨 RUPTURE DE STOCK - {$nomProduit}";
                $typeAlerte = "RUPTURE DE STOCK";
                $message = "Votre produit '{$nomProduit}' est en rupture de stock. Il n'y a plus d'unités disponibles.";
            } elseif ($stockRestant <= $seuilAlerte) {
                $sujet = "⚠️ ALERTE STOCK FAIBLE - {$nomProduit}";
                $typeAlerte = "STOCK FAIBLE";
                $message = "Votre produit '{$nomProduit}' a un stock faible. Il ne reste que {$stockRestant} unité(s) (seuil d'alerte: {$seuilAlerte}).";
            } else {
                // Pas d'alerte nécessaire
                return false;
            }

            // Envoyer l'email
            // Envoyer l'email
            // Envoyer l'email
            Mail::send('emails.alerte-stock', [
                'nomVendeur' => $nomVendeur,
                'nomProduit' => $nomProduit,
                'stockRestant' => $stockRestant,
                'seuilAlerte' => $seuilAlerte,
                'typeAlerte' => $typeAlerte,
                'messageAlerte' => $message, // ← Renommez la variable
                'dateAlerte' => now()->format('d/m/Y H:i'),
            ], function ($mailMessage) use ($emailVendeur, $sujet, $nomVendeur) { // ← Renommez le paramètre
                $mailMessage->to($emailVendeur)
                    ->subject($sujet);
            });

            // Logger l'envoi
            Log::info("Alerte stock envoyée à {$emailVendeur} - {$typeAlerte} pour {$nomProduit}");

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur envoi email alerte stock: ' . $e->getMessage());
            return false;
        }
    }

    // ⭐⭐ AJOUTER CETTE MÉTHODE MANQUANTE ⭐⭐
public function mettreAJourQuantite($quantite)
{
    if ($quantite >= 0) {
        // Ajouter de la quantité
        $this->quantite_disponible += $quantite;
        $this->stock_entree += $quantite;
    } else {
        // Retirer de la quantité (vérifier que c'est possible)
        $quantiteARetirer = abs($quantite);
        if ($this->quantite_disponible < $quantiteARetirer) {
            throw new \Exception("Quantité insuffisante à retirer. Disponible: {$this->quantite_disponible}, À retirer: {$quantiteARetirer}");
        }
        $this->quantite_disponible -= $quantiteARetirer;
        $this->stock_entree -= $quantiteARetirer;
    }

    // Recalculer la quantité réellement disponible
    $this->quantite_reellement_disponible = $this->stock_entree - $this->quantite_reservee;

    // Mettre à jour le statut automatique
    $this->mettreAJourStatutAutomatique();

    // Mettre à jour la date
    $this->date_derniere_maj = now();

    // Sauvegarder
    $this->save();

    return $this;
}
}
