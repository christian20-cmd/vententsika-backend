<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('stocks', 'derniere_alerte_envoyee')) {
                $table->timestamp('derniere_alerte_envoyee')->nullable()->after('date_derniere_maj');
            }
        });
    }

    public function down()
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('derniere_alerte_envoyee');
        });
    }
};
