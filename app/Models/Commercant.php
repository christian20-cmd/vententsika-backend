<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commercant extends Model
{
    protected $table = 'commercants';
    protected $primaryKey = 'idCommercant';
    public $timestamps = true;

    protected $fillable = [
        'nom_entreprise',
        'description',
        'adresse',
        'email',
        'telephone',
        'statut_validation',
        'idUtilisateur'
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'idUtilisateur');
    }

    public function produits()
    {
        return $this->hasMany(Produit::class, 'idCommercant');
    }

    // NOUVELLE RELATION : Commercant a un Vendeur via idUtilisateur
    public function vendeur()
    {
        return $this->hasOne(Vendeur::class, 'idUtilisateur', 'idUtilisateur');
    }
}
