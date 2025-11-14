<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('paiements', function (Blueprint $table) {
            // Modifier l'enum pour accepter plus de méthodes
            $table->enum('methode_paiement', [
                'virement', 'mobile_money', 'carte', 'especes', 'cheque', 'mobile'
            ])->default('especes')->change();

            // Changer le statut par défaut à 'valide'
            $table->enum('statut', [
                'en_attente', 'valide', 'refuse', 'rembourse'
            ])->default('valide')->change();
        });
    }

    public function down()
    {
        Schema::table('paiements', function (Blueprint $table) {
            // Revenir aux anciennes valeurs si nécessaire
            $table->enum('methode_paiement', [
                'virement', 'mobile_money', 'carte'
            ])->default('virement')->change();

            $table->enum('statut', [
                'en_attente', 'valide', 'refuse', 'rembourse'
            ])->default('en_attente')->change();
        });
    }
};
