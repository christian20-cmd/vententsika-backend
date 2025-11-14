<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    use HasFactory;

    protected $table = 'produits';
    protected $primaryKey = 'idProduit';

    // 🔧 CORRECTION : Retirer idStock de fillable pour éviter sa modification accidentelle
    protected $fillable = [
        'nom_produit',
        'description',
        'prix_unitaire',
        'prix_promotion',
        'idCategorie',
        'idCommercant',
        'idStock', // ⭐ AJOUTEZ cette ligne
        'image_principale',
        'images_supplementaires',
        'statut',
        'date_publication'
    ];

    // Méthode pour vérifier et mettre à jour le statut automatiquement
    public function verifierStatut()
    {
        if (!$this->stock) {
            return;
        }

        $quantiteReelle = $this->stock->quantite_reellement_disponible;
        $seuilAlerte = $this->stock->seuil_alerte ?? 0;

        $nouveauStatut = 'actif';

        if ($quantiteReelle <= 0) {
            $nouveauStatut = 'rupture';
        } elseif ($quantiteReelle <= $seuilAlerte) {
            $nouveauStatut = 'actif'; // ou 'alerte' selon vos besoins
        }

        if ($this->statut !== $nouveauStatut) {
            $this->update(['statut' => $nouveauStatut]);
        }
    }

    // ⭐ SUPPRIMEZ ou modifiez le guarded
    protected $guarded = [
        // Retirez 'idStock' d'ici ou supprimez complètement cette propriété
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'prix_promotion' => 'decimal:2',
        'images_supplementaires' => 'array',
        'date_publication' => 'datetime'
    ];

    // Relation avec la catégorie
    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'idCategorie');
    }

    // Relation avec le commercant
    public function commercant()
    {
        return $this->belongsTo(Commercant::class, 'idCommercant');
    }

    // Relation avec le stock
    public function stock()
    {
        return $this->hasOne(Stock::class, 'idStock', 'idStock');
    }

    // Relation avec les médias via la table pivot
    public function medias()
    {
        return $this->belongsToMany(Media::class, 'produit_media', 'idProduit', 'idMedia')
                    ->withPivot('ordre', 'is_principal')
                    ->withTimestamps()
                    ->orderBy('ordre');
    }

    // Accessor pour le média principal
    public function getMediaPrincipalAttribute()
    {
        return $this->medias()->wherePivot('is_principal', true)->first();
    }

    // Accessor pour le prix final (prix promotionnel ou unitaire)
    public function getPrixFinalAttribute()
    {
        return $this->prix_promotion ?? $this->prix_unitaire;
    }

    // Scope pour les produits actifs
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    // Scope pour les produits inactifs
    public function scopeInactif($query)
    {
        return $query->where('statut', 'inactif');
    }

    // Méthode pour publier le produit
    public function publier()
    {
        $this->update([
            'statut' => 'actif',
            'date_publication' => now()
        ]);
    }

    // Méthode pour dépublier le produit
    public function depublier()
    {
        $this->update([
            'statut' => 'inactif'
        ]);
    }

    // Boot method pour les valeurs par défaut
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($produit) {
            if (empty($produit->statut)) {
                $produit->statut = 'inactif';
            }
        });
    }
}
