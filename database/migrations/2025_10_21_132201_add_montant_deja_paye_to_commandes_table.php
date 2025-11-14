<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la colonne n'existe pas déjà
        if (!Schema::hasColumn('commandes', 'montant_deja_paye')) {
            Schema::table('commandes', function (Blueprint $table) {
                $table->decimal('montant_deja_paye', 10, 2)->default(0)->after('total_commande');
            });

            // Mettre à jour les commandes existantes
            DB::table('commandes')->update([
                'montant_deja_paye' => DB::raw('total_commande - montant_reste_payer')
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('montant_deja_paye');
        });
    }
};
