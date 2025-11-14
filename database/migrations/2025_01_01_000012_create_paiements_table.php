<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id('idPaiement');
            $table->decimal('montant', 10, 2);
            $table->enum('methode_paiement', ['virement', 'mobile_money', 'carte']);
            $table->enum('statut', ['en_attente', 'valide', 'refuse', 'rembourse'])->default('en_attente');
            $table->timestamp('date_paiement')->useCurrent();
            $table->string('preuve_paiement')->nullable();
            $table->foreignId('idCommande')->constrained('commandes', 'idCommande');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('paiements');
    }
};
