<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendeurLien extends Model
{
    use HasFactory;

    protected $table = 'vendeur_liens';
    protected $primaryKey = 'idLien';

    protected $fillable = [
        'idVendeur',
        'token',
        'expires_at',
        'is_active'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    public $timestamps = true;

    // Relation avec le vendeur
    public function vendeur()
    {
        return $this->belongsTo(Vendeur::class, 'idVendeur');
    }

    // Vérifier si le lien est expiré
    public function isExpired()
    {
        return !$this->is_active || $this->expires_at->isPast();
    }

    // Générer un nouveau token unique
    public static function generateToken()
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (self::where('token', $token)->exists());

        return $token;
    }
}
