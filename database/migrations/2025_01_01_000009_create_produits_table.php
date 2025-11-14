<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id('idProduit');
            $table->string('nom_produit');
            $table->text('description')->nullable();
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('prix_promotion', 10, 2)->nullable();
            $table->decimal('poids', 10, 2)->nullable();
            $table->string('dimensions')->nullable();
            $table->enum('statut', ['actif', 'inactif', 'rupture'])->default('actif');
            $table->timestamp('date_creation')->useCurrent();
            $table->timestamp('date_modification')->useCurrent();
            $table->unsignedBigInteger('idMedia')->nullable();
            $table->unsignedBigInteger('idCommercant');
            $table->unsignedBigInteger('idCategorie');
            $table->unsignedBigInteger('idStock');
            $table->timestamps();

            $table->foreign('idMedia')->references('idMedia')->on('media')->onDelete('set null');
            $table->foreign('idCommercant')->references('idCommercant')->on('commercants')->onDelete('cascade');
            $table->foreign('idCategorie')->references('idCategorie')->on('categories')->onDelete('restrict');
            $table->foreign('idStock')->references('idStock')->on('stocks')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('produits');
    }
};
