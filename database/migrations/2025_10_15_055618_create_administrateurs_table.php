<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('administrateurs', function (Blueprint $table) {
            $table->id('idAdministrateur');
            $table->foreignId('idUtilisateur')->constrained('utilisateurs', 'idUtilisateur')->onDelete('cascade');
            $table->enum('niveau_acces', ['super_admin', 'admin', 'moderateur'])->default('admin');
            $table->string('permissions')->nullable(); // JSON des permissions spécifiques
            $table->boolean('est_actif')->default(true);
            $table->timestamp('derniere_connexion')->nullable();
            $table->string('ip_connexion')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('administrateurs');
    }
};