<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Client extends Authenticatable
{
    protected $table = 'clients';
    protected $primaryKey = 'idClient';

    public $timestamps = true;

    protected $fillable = [
        'nom_prenom_client',
        'email_client',
        'adresse_client',
        'cin_client',
        'telephone_client',
        // 'password_client', // RETIRÉ des fillable
    ];

    protected $hidden = [
        // 'password_client', // RETIRÉ des hidden
        'remember_token',
    ];

    // Relations existantes
    public function commandes()
    {
        return $this->hasMany(Commande::class, 'idClient');
    }

    public function derniereCommande()
    {
        return $this->hasOne(Commande::class, 'idClient')->latest();
    }

    public function livraisons()
    {
        return $this->hasManyThrough(Livraison::class, Commande::class, 'idClient', 'idCommande');
    }

    // AJOUTEZ CETTE RELATION :
    public function commandesProd()
    {
        return $this->hasMany(CommandeProd::class, 'idClient');
    }

    // Méthode d'authentification - MODIFIÉE
    public function getAuthPassword()
    {
        return null; // Retourne null car pas de mot de passe
    }

    // Méthode pour créer un client automatiquement lors d'une commande - MODIFIÉE
    public static function creerAutomatiquement($data)
    {
        return self::create([
            'nom_prenom_client' => $data['nom_prenom_client'],
            'email_client' => $data['email_client'],
            'adresse_client' => $data['adresse_client'] ?? null,
            'cin_client' => $data['cin_client'] ?? null,
            'telephone_client' => $data['telephone_client'] ?? null,
            // 'password_client' => bcrypt($data['password_client'] ?? 'temp123'), // RETIRÉ
        ]);
    }
}
