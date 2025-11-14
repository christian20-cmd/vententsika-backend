<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commande_prods', function (Blueprint $table) {
            $table->id('idCommandeProd');
            $table->unsignedBigInteger('idCommande')->nullable(); // ✅ Même type que idCommande dans commandes
            $table->unsignedBigInteger('idClient')->nullable();
            $table->unsignedBigInteger('idCommercant');
            $table->unsignedBigInteger('idProduit');
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('sous_total', 10, 2);
            $table->string('adresse_livraison');
            $table->date('date_livraison');
            $table->enum('statut', ['panier', 'attente_validation', 'validee', 'modification'])->default('panier');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Clés étrangères - ATTENTION: l'ordre est important!
            // D'abord créer la table commandes, puis cette table
            $table->foreign('idCommande')->references('idCommande')->on('commandes')->onDelete('cascade');
            $table->foreign('idClient')->references('idClient')->on('clients')->onDelete('set null');
            $table->foreign('idCommercant')->references('idCommercant')->on('commercants')->onDelete('cascade');
            $table->foreign('idProduit')->references('idProduit')->on('produits')->onDelete('cascade');

            // Index
            $table->index('idCommande');
            $table->index('statut');
        });
    }

    public function down()
    {
        Schema::dropIfExists('commande_prods');
    }
};
