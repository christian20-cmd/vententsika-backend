<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->id('idInvitation');
            $table->string('token')->unique();
            $table->string('email')->nullable();
            $table->enum('niveau_acces', ['super_admin', 'admin', 'moderateur'])->default('admin');
            $table->foreignId('generer_par')->constrained('administrateurs', 'idAdministrateur');
            $table->timestamp('expire_a');
            $table->boolean('est_actif')->default(true);
            $table->timestamp('utilise_a')->nullable();
            $table->foreignId('utilise_par')->nullable()->constrained('administrateurs', 'idAdministrateur');
            $table->timestamps();

            $table->index(['token', 'est_actif']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_invitations');
    }
};
