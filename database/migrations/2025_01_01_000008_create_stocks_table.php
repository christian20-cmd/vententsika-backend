<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id('idStock');
            $table->string('code_stock')->unique()->nullable();
            $table->integer('quantite_disponible')->default(0);
            $table->integer('stock_entree')->default(0); // NOUVEAU : Stock initial fixe
            $table->integer('quantite_reservee')->default(0);
            $table->integer('seuil_alerte')->default(0);
            $table->enum('statut_automatique', ['En stock', 'Faible', 'Rupture'])->default('En stock');
            $table->timestamp('date_derniere_maj')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stocks');
    }
};
