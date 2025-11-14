<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commande extends Model
{
    use HasFactory;

    protected $table = 'commandes';
    protected $primaryKey = 'idCommande';
    protected $appends = ['statut_paiement'];

    protected $fillable = [
        'numero_commande',
        'idClient',
        'idCommercant',
        'frais_livraison',
        'total_commande',
        'adresse_livraison',
        'statut',
        'notes',
        'montant_deja_paye',
        'montant_reste_payer',
        'date_validation',
        'date_livraison' // ← AJOUTER CETTE LIGNE
    ];

    protected $casts = [
        'frais_livraison' => 'decimal:2',
        'total_commande' => 'decimal:2',
        'date_validation' => 'datetime',
    ];

    // Relations
    public function client()
    {
        return $this->belongsTo(Client::class, 'idClient');
    }

    public function commercant()
    {
        return $this->belongsTo(Commercant::class, 'idCommercant');
    }

    public function lignesCommande()
    {
        return $this->hasMany(CommandeProd::class, 'idCommande');
    }

    public function livraison()
    {
        return $this->hasOne(Livraison::class, 'idCommande');
    }

    // Une commande peut avoir plusieurs paiements
    public function paiements()
    {
        return $this->hasMany(Paiement::class, 'idCommande');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'idProduit');
    }

    // Générer le numéro de commande
    public static function genererNumeroCommande()
    {
        $prefix = 'CMD';
        $date = now()->format('Ymd');

        $lastCommand = self::where('numero_commande', 'like', $prefix . $date . '%')
            ->orderBy('idCommande', 'desc')
            ->first();

        if ($lastCommand) {
            $lastSequence = intval(substr($lastCommand->numero_commande, -4));
            $sequence = $lastSequence + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Calculer le total de la commande
    public function calculerTotal()
    {
        $sousTotal = $this->lignesCommande->sum('sous_total');
        $this->total_commande = $sousTotal + $this->frais_livraison;
        return $this->total_commande;
    }

    // Accesseurs pour les paiements (calculés dynamiquement)
    public function getMontantDejaPayeAttribute()
    {
        // Somme des paiements valides (source de vérité)
        return $this->paiements()->where('statut', 'valide')->sum('montant');
    }

    public function getMontantRestePayerAttribute()
    {
        return max(0, $this->total_commande - $this->montant_deja_paye);
    }

    public function getStatutPaiementAttribute()
    {
        $reste = $this->montant_reste_payer;
        if ($reste <= 0) {
            return 'paye';
        } elseif ($this->montant_deja_paye > 0) {
            return 'acompte';
        } else {
            return 'impaye';
        }
    }

    // Méthode pour ajouter un paiement (utilitaire)
    public function ajouterPaiement($montant, $methodePaiement)
    {
        $paiement = \App\Models\Paiement::create([
            'montant' => $montant,
            'methode_paiement' => $methodePaiement,
            'statut' => 'valide',
            'date_paiement' => now(),
            'idCommande' => $this->idCommande,
        ]);

        // Mettre à jour montants stockés
        $montantDejaPaye = $this->paiements()->where('statut', 'valide')->sum('montant');
        $this->update([
            'montant_deja_paye' => $montantDejaPaye,
            'montant_reste_payer' => max(0, $this->total_commande - $montantDejaPaye)
        ]);

        return $paiement;
    }


}
