<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LigneCommande extends Model
{
    use HasFactory;

    protected $table = 'lignes_commande';
    protected $primaryKey = 'idLigne';

    protected $fillable = [
        'idCommande',
        'idProduit',
        'idCommercant',
        'quantite',
        'prix_unitaire',
        'sous_total_ligne'
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'sous_total_ligne' => 'decimal:2',
    ];

    // Relations
    public function commande()
    {
        return $this->belongsTo(Commande::class, 'idCommande');
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

        static::saving(function ($ligne) {
            $ligne->sous_total_ligne = $ligne->prix_unitaire * $ligne->quantite;
        });
    }
}
