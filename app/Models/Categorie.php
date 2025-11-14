<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'idCategorie';
    public $timestamps = true;

    protected $fillable = [
        'nom_categorie',
        'description'
    ];

    public function produits()
    {
        return $this->hasMany(Produit::class, 'idCategorie');
    }
}
