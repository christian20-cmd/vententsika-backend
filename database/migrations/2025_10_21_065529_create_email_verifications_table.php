<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailVerificationsTable extends Migration
{
    public function up()
    {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->integer('attempts')->default(0);
            $table->boolean('verified')->default(false);
            $table->timestamps();
            $table->json('data')->nullable(); // Pour stocker les données des étapes
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_verifications');
    }
}
