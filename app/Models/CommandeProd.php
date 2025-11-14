<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommandeProd extends Model
{
    use HasFactory;

    protected $table = 'commande_prods';
    protected $primaryKey = 'idCommandeProd';

    protected $fillable = [
        'idCommande',
        'idClient',
        'idCommercant',
        'idProduit',
        'quantite',
        'prix_unitaire',
        'sous_total',
        'adresse_livraison',
        'date_livraison',
        'statut',
        'notes'
    ];

    protected $casts = [
        'date_livraison' => 'date',
        'prix_unitaire' => 'decimal:2',
        'sous_total' => 'decimal:2',
    ];

    // Tous les statuts possibles
    const STATUTS = [
        'panier',
        'attente_validation',
        'validee',
        'modification',
        'en_preparation',
        'expediee',
        'livree',
        'annulee',
        'retournee'
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

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'idProduit');
    }

    public function commande()
    {
        return $this->belongsTo(Commande::class, 'idCommande');
    }

    // Validation d'une ligne individuelle
    public function valider($idCommande = null)
    {
        if ($idCommande) {
            $this->idCommande = $idCommande;
        }
        $this->statut = 'validee';
        $this->save();
        return $this;
    }

    // Vérifier si le statut est valide
    public function setStatutAttribute($value)
    {
        if (!in_array($value, self::STATUTS)) {
            throw new \InvalidArgumentException("Statut invalide: {$value}. Statuts valides: " . implode(', ', self::STATUTS));
        }

        // 🔒 Protection supplémentaire :
        // Si la commande parent est validée, on empêche de repasser à un autre statut
        if ($this->commande && in_array($this->commande->statut, ['validee', 'livree']) && $value !== $this->commande->statut) {
            throw new \InvalidArgumentException("Impossible de modifier une ligne d'une commande déjà validée ou livrée.");
        }

        $this->attributes['statut'] = $value;
    }

    // Calcul automatique du sous-total
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($commandeProd) {
            $commandeProd->sous_total = $commandeProd->prix_unitaire * $commandeProd->quantite;
        });
    }
}
