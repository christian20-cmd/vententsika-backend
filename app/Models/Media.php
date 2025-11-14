<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';
    protected $primaryKey = 'idMedia';
    public $timestamps = true;

    protected $fillable = [
        'chemin_fichier',
        'type_media'
    ];

    public function produits()
    {
        return $this->belongsToMany(Produit::class, 'produit_media', 'idMedia', 'idProduit');
    }
}
