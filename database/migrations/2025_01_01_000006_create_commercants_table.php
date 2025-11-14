<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commercants', function (Blueprint $table) {
            $table->id('idCommercant');
            $table->string('nom_entreprise');
            $table->text('description')->nullable();
            $table->text('adresse');
            $table->string('email');
            $table->string('telephone');
            $table->enum('statut_validation', ['en_attente', 'valide', 'refuse'])->default('en_attente');
            $table->foreignId('idUtilisateur')->constrained('utilisateurs', 'idUtilisateur');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('commercants');
    }
};
