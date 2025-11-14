<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->timestamp('derniere_connexion')->nullable()->after('idRole');
            $table->string('ip_connexion')->nullable()->after('derniere_connexion');
        });
    }

    public function down()
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->dropColumn(['derniere_connexion', 'ip_connexion']);
        });
    }
};