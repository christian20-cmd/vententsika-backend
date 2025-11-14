<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_password_reset_codes', function (Blueprint $table) {
            $table->id('idResetCode');
            $table->string('email');
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            
            $table->index('email');
            $table->index('code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_password_reset_codes');
    }
};