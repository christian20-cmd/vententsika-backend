<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    use HasFactory;

    protected $table = 'paiements';
    protected $primaryKey = 'idPaiement';

    protected $fillable = [
        'montant',
        'methode_paiement',
        'statut',
        'date_paiement',
        'idCommande'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_paiement' => 'datetime',
    ];

    // Relations
    public function commande()
    {
        return $this->belongsTo(Commande::class, 'idCommande');
    }
}
