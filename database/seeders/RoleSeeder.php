<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        DB::table('roles')->insert([
            ['idRole' => 1, 'libeleRole' => 'vendeur', 'created_at' => now(), 'updated_at' => now()],
            ['idRole' => 2, 'libeleRole' => 'entreprise', 'created_at' => now(), 'updated_at' => now()],
            ['idRole' => 3, 'libeleRole' => 'admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
