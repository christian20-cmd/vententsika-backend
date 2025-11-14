<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // dashboard, sales, customers, etc.
            $table->json('data');
            $table->string('date_range');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['type', 'date_range']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('analytics_cache');
    }
};
