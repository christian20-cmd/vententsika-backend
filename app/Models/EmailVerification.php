<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    use HasFactory;

    protected $table = 'email_verifications';

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'attempts',
        'verified',
        'data' // ← AJOUTÉ : permet de stocker les données de l'inscription
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified' => 'boolean',
        'data' => 'array' // ← AJOUTÉ : Laravel gère automatiquement l'encodage/décodage JSON
    ];

    public $timestamps = true;
}
