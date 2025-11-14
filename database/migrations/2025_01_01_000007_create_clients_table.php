<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id('idClient');
            $table->string('nom_prenom_client');
            $table->string('email_client')->unique();
            $table->string('adresse_client');
            $table->string('cin_client')->unique();
            $table->string('telephone_client')->nullable(); // Ajout du téléphone
            $table->string('password_client'); // Ajout du mot de passe pour l'authentification
            $table->rememberToken(); // Pour l'authentification
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};
