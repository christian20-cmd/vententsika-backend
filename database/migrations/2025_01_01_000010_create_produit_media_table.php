<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('produit_media', function (Blueprint $table) {
            $table->id('idProduitMedia');
            $table->unsignedBigInteger('idProduit');
            $table->unsignedBigInteger('idMedia');
            $table->integer('ordre')->default(0);
            $table->boolean('is_principal')->default(false);
            $table->timestamps();

            $table->foreign('idProduit')->references('idProduit')->on('produits')->onDelete('cascade');
            $table->foreign('idMedia')->references('idMedia')->on('media')->onDelete('cascade');

            $table->index(['idProduit', 'ordre']);
            $table->unique(['idProduit', 'idMedia']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('produit_media');
    }
};
