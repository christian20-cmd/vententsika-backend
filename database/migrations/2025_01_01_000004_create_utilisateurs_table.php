<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id('idUtilisateur');
            $table->string('email')->unique();
            $table->string('mot_de_passe');
            $table->string('nomUtilisateur');
            $table->string('prenomUtilisateur');
            $table->string('tel')->nullable();
            $table->timestamp('date_inscription')->useCurrent();
            $table->enum('Statut', ['actif', 'inactif'])->default('actif');
            $table->foreignId('idRole')->constrained('roles', 'idRole');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('utilisateurs');
    }
};
