<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VendeurLien;
use Carbon\Carbon;

class ExpirerLiensVendeurs extends Command
{
    protected $signature = 'liens:expirer';
    protected $description = 'Expire automatiquement les liens de vendeurs après 24 heures';

    public function handle()
    {
        $count = VendeurLien::where('is_active', true)
            ->where('expires_at', '<=', Carbon::now())
            ->update(['is_active' => false]);

        $this->info("{$count} liens ont été expirés automatiquement.");

        return Command::SUCCESS;
    }
}
