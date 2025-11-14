<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'utilisateurs';
    protected $primaryKey = 'idUtilisateur';

    protected $fillable = [
        'prenomUtilisateur',
        'nomUtilisateur',
        'email',
        'tel',
        'mot_de_passe',
        'idRole',
    ];

    protected $hidden = [
        'mot_de_passe',
        'remember_token',
    ];
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'idUtilisateur');
    }

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    // AJOUTER LA RELATION COMMERCANT
    public function commercant()
    {
        return $this->hasOne(Commercant::class, 'idUtilisateur');
    }

    public function client()
    {
        return $this->hasOne(Client::class, 'idUtilisateur');
    }

    public function vendeur()
    {
        return $this->hasOne(Vendeur::class, 'idUtilisateur');
    }

    // ⭐⭐ CORRECTION : AJOUTER LA RELATION ADMINISTRATEUR
    public function administrateur()
    {
        return $this->hasOne(Administrateur::class, 'idUtilisateur');
    }

    public function isAdmin()
    {
        return !is_null($this->administrateur);
    }
}
