<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Vérifier si la colonne n'existe pas déjà
        if (!Schema::hasColumn('stocks', 'idProduit')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->unsignedBigInteger('idProduit')->nullable()->after('idStock');

                // Ajouter la clé étrangère seulement si la table produits existe
                if (Schema::hasTable('produits')) {
                    $table->foreign('idProduit')
                          ->references('idProduit')
                          ->on('produits')
                          ->onDelete('cascade');
                }
            });
        }
    }

    public function down()
    {
        Schema::table('stocks', function (Blueprint $table) {
            // Supprimer la clé étrangère si elle existe
            if (Schema::hasTable('produits')) {
                $table->dropForeign(['idProduit']);
            }
            $table->dropColumn('idProduit');
        });
    }
};
