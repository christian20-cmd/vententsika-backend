<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('livraisons', function (Blueprint $table) {
            $table->id('idLivraison');
            $table->unsignedBigInteger('idCommande');

            // Informations client (pour référence rapide)
            $table->string('nom_client');
            $table->string('telephone_client');

            // Informations spécifiques à la livraison
            $table->string('adresse_livraison'); // Peut être différente de l'adresse client
            $table->string('numero_suivi')->unique()->nullable();
            $table->timestamp('date_expedition')->nullable();
            $table->timestamp('date_livraison_prevue')->nullable(); // Date prévue pour cette livraison
            $table->timestamp('date_livraison_reelle')->nullable();

            // Statut de livraison
            $table->enum('status_livraison', [
                'en_attente',
                'en_preparation',
                'expedie',
                'en_transit',
                'livre',
                'retourne',
                'annule'
            ])->default('en_attente');

            $table->text('notes_livraison')->nullable(); // Notes spécifiques à la livraison
            $table->timestamps();

            // Clé étrangère
            $table->foreign('idCommande')->references('idCommande')->on('commandes')->onDelete('cascade');

            // Index
            $table->index('numero_suivi');
            $table->index('status_livraison');
            $table->index('idCommande');
        });
    }

    public function down()
    {
        Schema::dropIfExists('livraisons');
    }
};
