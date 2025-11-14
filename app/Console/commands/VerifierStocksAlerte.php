<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Stock;
use Illuminate\Support\Facades\Log;

class VerifierStocksAlerte extends Command
{
    protected $signature = 'stocks:verifier-alertes';
    protected $description = 'Vérifier les stocks en alerte et envoyer des notifications';

    public function handle()
    {
        $stocksAlerte = Stock::with(['produit.commercant.vendeur.utilisateur'])
            ->where('quantite_disponible', '<=', 5)
            ->where('quantite_disponible', '>', 0)
            ->get();

        $count = 0;

        foreach ($stocksAlerte as $stock) {
            if ($stock->peutEnvoyerAlerte()) {
                $stock->envoyerAlerteSeuil();
                $count++;
            }
        }

        $this->info("{$count} alertes de stock envoyées.");
        Log::info("Commande stocks:verifier-alertes exécutée - {$count} alertes envoyées");

        return Command::SUCCESS;
    }
}
