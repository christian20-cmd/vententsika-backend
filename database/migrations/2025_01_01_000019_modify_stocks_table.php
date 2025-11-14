<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stocks', function (Blueprint $table) {
            // Vérifier si les colonnes n'existent pas avant de les ajouter

            if (!Schema::hasColumn('stocks', 'idProduit')) {
                $table->unsignedBigInteger('idProduit')->nullable()->after('idStock');
                $table->foreign('idProduit')->references('idProduit')->on('produits')->onDelete('cascade');
            }

            // code_stock et statut_automatique existent déjà dans la migration initiale
            // Donc on ne les ajoute pas ici
        });
    }

    public function down()
    {
        Schema::table('stocks', function (Blueprint $table) {
            if (Schema::hasColumn('stocks', 'idProduit')) {
                $table->dropForeign(['idProduit']);
                $table->dropColumn('idProduit');
            }
        });
    }
};
