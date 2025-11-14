<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Supprimer la table si elle existe
        Schema::dropIfExists('paniers');

        // Recréer la table avec la bonne structure
        Schema::create('paniers', function (Blueprint $table) {
            $table->id('idPanier');
            $table->unsignedBigInteger('idClient');
            $table->unsignedBigInteger('idProduit');
            $table->unsignedBigInteger('idCommercant');
            $table->integer('quantite')->default(1);
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('sous_total', 10, 2);
            $table->timestamps();

            // Clés étrangères
            $table->foreign('idClient')->references('idClient')->on('clients')->onDelete('cascade');
            $table->foreign('idProduit')->references('idProduit')->on('produits')->onDelete('cascade');
            $table->foreign('idCommercant')->references('idCommercant')->on('commercants')->onDelete('cascade');

            // Contrainte d'unicité
            $table->unique(['idClient', 'idProduit']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('paniers');
    }
};
