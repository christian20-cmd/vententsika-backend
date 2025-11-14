<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->string('image_principale')->nullable()->after('idStock');
            $table->json('images_supplementaires')->nullable()->after('image_principale');
        });
    }

    public function down()
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['image_principale', 'images_supplementaires']);
        });
    }
};
