<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Modifier la colonne enum pour ajouter les nouveaux statuts
        DB::statement("ALTER TABLE commande_prods MODIFY COLUMN statut ENUM('panier', 'attente_validation', 'validee', 'modification', 'en_preparation', 'expediee', 'livree', 'annulee', 'retournee') DEFAULT 'panier'");
    }

    public function down()
    {
        // Revenir à l'ancien enum si nécessaire
        DB::statement("ALTER TABLE commande_prods MODIFY COLUMN statut ENUM('panier', 'attente_validation', 'validee', 'modification') DEFAULT 'panier'");
    }
};
