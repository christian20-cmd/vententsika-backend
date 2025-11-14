<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('admin_invitations', function (Blueprint $table) {
            $table->enum('statut_approbation', ['en_attente', 'approuve', 'rejete'])->default('en_attente')->after('est_actif');
            $table->foreignId('admin_validateur')->nullable()->constrained('administrateurs', 'idAdministrateur')->after('statut_approbation');
            $table->timestamp('date_approbation')->nullable()->after('admin_validateur');
        });
    }

    public function down()
    {
        Schema::table('admin_invitations', function (Blueprint $table) {
            $table->dropColumn(['statut_approbation', 'admin_validateur', 'date_approbation']);
        });
    }
};