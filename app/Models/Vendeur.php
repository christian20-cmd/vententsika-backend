<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Vendeur extends Model
{
    use HasFactory;

    protected $table = 'vendeurs';
    protected $primaryKey = 'idVendeur';

    protected $fillable = [
        'idUtilisateur',
        'nom_entreprise',
        'adresse_entreprise',
        'description',
        'logo_image',
        'statut_validation',
        'commission_pourcentage',
    ];

    public $timestamps = true;

    // Accesseur simplifié pour l'URL du logo
    public function getLogoUrlAttribute()
    {
        if (!$this->logo_image) {
            return null;
        }

        return asset('storage/' . $this->logo_image);
    }

    protected $appends = ['logo_url'];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'idUtilisateur');
    }

    public function commercant()
    {
        return $this->hasOne(Commercant::class, 'idUtilisateur', 'idUtilisateur');
    }

    public function produits()
    {
        return $this->hasManyThrough(Produit::class, Commercant::class, 'idUtilisateur', 'idCommercant', 'idUtilisateur', 'idCommercant');
    }

    // Relation avec les liens
    public function liens()
    {
        return $this->hasMany(VendeurLien::class, 'idVendeur');
    }

    // Générer un nouveau lien
    public function genererLien()
    {
        // Désactiver tous les liens existants
        $this->liens()->update(['is_active' => false]);

        // Créer un nouveau lien
        return $this->liens()->create([
            'token' => VendeurLien::generateToken(),
            'expires_at' => now()->addHours(24),
            'is_active' => true
        ]);
    }

    // Récupérer le lien actif
    public function getLienActif()
    {
        return $this->liens()
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
    }

    // Expirer manuellement le lien
    public function expirerLien()
    {
        return $this->liens()
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

   // Dans app/Models/Vendeur.php - Remplacer cette méthode :

// Accesseur pour l'URL du profil
    public function getLienProfilAttribute()
    {
        $lien = $this->getLienActif();

        if (!$lien) {
            $lien = $this->genererLien();
        }

        // CORRECTION : Utiliser le bon nom de route défini dans api.php
        return url("/api/vendeur/profile/{$lien->token}");
    }
}
