<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id('idCommande');
            $table->string('numero_commande')->unique();
            $table->unsignedBigInteger('idClient');
            $table->unsignedBigInteger('idCommercant');
            $table->decimal('frais_livraison', 10, 2)->default(0);
            $table->decimal('total_commande', 10, 2)->default(0);
            $table->decimal('montant_reste_payer', 10, 2)->default(0);
            $table->string('adresse_livraison');
            $table->enum('statut', ['attente_validation', 'validee', 'en_preparation', 'expediee', 'livree', 'annulee'])->default('attente_validation'); // ✅ AJOUTÉ et DEFAULT modifié
            $table->text('notes')->nullable();
            $table->timestamp('date_validation')->nullable();
            $table->timestamps();

            // Clés étrangères
            $table->foreign('idClient')->references('idClient')->on('clients')->onDelete('cascade');
            $table->foreign('idCommercant')->references('idCommercant')->on('commercants')->onDelete('cascade');

            // Index
            $table->index('numero_commande');
            $table->index('statut');
        });
    }

    public function down()
    {
        Schema::dropIfExists('commandes');
    }
};
