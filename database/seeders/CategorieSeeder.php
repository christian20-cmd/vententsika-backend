<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorieSeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['nom_categorie' => 'Électronique', 'description' => 'Appareils électroniques'],
            ['nom_categorie' => 'Vêtements', 'description' => 'Vêtements et accessoires'],
            ['nom_categorie' => 'Alimentation', 'description' => 'Produits alimentaires'],
            ['nom_categorie' => 'Maison', 'description' => 'Articles pour la maison'],
            ['nom_categorie' => 'Sport', 'description' => 'Équipements sportifs'],
            ['nom_categorie' => 'Beauté', 'description' => 'Produits de beauté'],
        ];

        foreach ($categories as $categorie) {
            DB::table('categories')->insert([
                'nom_categorie' => $categorie['nom_categorie'],
                'description' => $categorie['description'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
