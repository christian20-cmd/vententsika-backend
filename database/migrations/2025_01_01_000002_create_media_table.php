<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id('idMedia');
            $table->string('chemin_fichier');
            $table->enum('type_media', ['image', 'video']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('media');
    }
};
