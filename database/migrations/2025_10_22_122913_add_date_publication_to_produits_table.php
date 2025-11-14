<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->timestamp('date_publication')->nullable()->after('statut');
        });
    }

    public function down()
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('date_publication');
        });
    }
};
