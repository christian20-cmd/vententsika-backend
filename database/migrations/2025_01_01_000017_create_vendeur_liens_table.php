<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vendeur_liens', function (Blueprint $table) {
            $table->id('idLien');
            $table->foreignId('idVendeur')->constrained('vendeurs', 'idVendeur');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vendeur_liens');
    }
};
