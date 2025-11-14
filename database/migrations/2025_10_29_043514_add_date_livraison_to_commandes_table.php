<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('commandes', function (Blueprint $table) {
            if (!Schema::hasColumn('commandes', 'date_livraison')) {
                $table->date('date_livraison')->nullable()->after('adresse_livraison');
            }
        });

        Schema::table('commande_prods', function (Blueprint $table) {
            if (!Schema::hasColumn('commande_prods', 'date_livraison')) {
                $table->date('date_livraison')->nullable()->after('adresse_livraison');
            }
        });
    }

    public function down()
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('date_livraison');
        });

        Schema::table('commande_prods', function (Blueprint $table) {
            $table->dropColumn('date_livraison');
        });
    }
};
