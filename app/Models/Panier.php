<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Panier extends Model
{
    use HasFactory;

    protected $table = 'paniers';
    protected $primaryKey = 'idPanier';

    protected $fillable = [
        'idClient',
        'idProduit',
        'idCommercant',
        'quantite',
        'prix_unitaire',
        'sous_total'
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'sous_total' => 'decimal:2',
    ];

    // Relations
    public function client()
    {
        return $this->belongsTo(Client::class, 'idClient');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'idProduit');
    }

    public function commercant()
    {
        return $this->belongsTo(Commercant::class, 'idCommercant');
    }

    // Calcul automatique du sous-total
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($panier) {
            $panier->sous_total = $panier->prix_unitaire * $panier->quantite;
        });
    }
}
