<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Option 1: Changer en ENUM avec toutes les méthodes
        DB::statement("ALTER TABLE paiements MODIFY COLUMN methode_paiement ENUM('virement', 'mobile_money', 'carte', 'especes') DEFAULT 'especes'");

        // Ou Option 2: Changer en VARCHAR plus long
        // Schema::table('paiements', function (Blueprint $table) {
        //     $table->string('methode_paiement', 50)->change();
        // });
    }

    public function down()
    {
        // Revenir à l'ancienne structure si nécessaire
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('methode_paiement', 20)->change();
        });
    }
};
