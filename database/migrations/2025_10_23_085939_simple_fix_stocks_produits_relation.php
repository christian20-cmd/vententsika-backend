<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Juste s'assurer que idStock est nullable dans produits
        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedBigInteger('idStock')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedBigInteger('idStock')->nullable(false)->change();
        });
    }
};
