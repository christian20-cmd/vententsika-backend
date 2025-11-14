<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_demandes', function (Blueprint $table) {
            $table->id('idDemande');
            $table->foreignId('idUtilisateur')->constrained('utilisateurs', 'idUtilisateur');
            $table->foreignId('idInvitation')->constrained('admin_invitations', 'idInvitation');
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente');
            $table->foreignId('admin_validateur')->nullable()->constrained('administrateurs', 'idAdministrateur');
            $table->timestamp('date_validation')->nullable();
            $table->text('raison_rejet')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_demandes');
    }
};