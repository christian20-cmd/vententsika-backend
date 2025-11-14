<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Supprimer la contrainte existante de produits vers stocks
        Schema::table('produits', function (Blueprint $table) {
            $table->dropForeign(['idStock']);
        });

        // 2. Rendre idStock nullable
        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedBigInteger('idStock')->nullable()->change();
        });

        // 3. Supprimer l'ancienne contrainte de stocks vers produits si elle existe
        try {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropForeign(['idProduit']);
            });
        } catch (\Exception $e) {
            // La contrainte n'existe peut-être pas
        }

        // 4. Recréer la contrainte de stocks vers produits avec CASCADE
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreign('idProduit')
                  ->references('idProduit')
                  ->on('produits')
                  ->onDelete('cascade');
        });

        // 5. Recréer la contrainte de produits vers stocks avec SET NULL
        Schema::table('produits', function (Blueprint $table) {
            $table->foreign('idStock')
                  ->references('idStock')
                  ->on('stocks')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropForeign(['idStock']);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['idProduit']);
        });

        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedBigInteger('idStock')->nullable(false)->change();
            $table->foreign('idStock')
                  ->references('idStock')
                  ->on('stocks')
                  ->onDelete('cascade');
        });
    }
};
