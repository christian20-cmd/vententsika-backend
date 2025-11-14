<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Commande;
use Illuminate\Support\Facades\DB;

class GestionStockService
{
    // Réserver le stock pour une commande
    public function reserverStockPourCommande($idCommande, $lignesCommande)
    {
        DB::beginTransaction();

        try {
            foreach ($lignesCommande as $ligne) {
                $stock = Stock::find($ligne['idStock']);

                if (!$stock) {
                    throw new \Exception("Stock non trouvé pour ID: " . $ligne['idStock']);
                }

                // Réserver la quantité
                $stock->reserverProduits($ligne['quantite']);

                // Ici, vous pouvez créer un enregistrement de réservation
                // Reservation::create([
                //     'idCommande' => $idCommande,
                //     'idStock' => $stock->idStock,
                //     'quantite' => $ligne['quantite'],
                //     'statut' => 'reserve'
                // ]);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // Livrer le stock réservé
    public function livrerStockReserve($idCommande)
    {
        DB::beginTransaction();

        try {
            // Récupérer les réservations de la commande
            // $reservations = Reservation::where('idCommande', $idCommande)->get();

            // Pour l'instant, simuler avec les lignes de commande
            $commande = Commande::with('lignesCommande')->find($idCommande);

            foreach ($commande->lignesCommande as $ligne) {
                $stock = Stock::find($ligne['idStock']);

                if ($stock) {
                    // Livrer la quantité réservée
                    $stock->quantite_reservee -= $ligne['quantite'];
                    if ($stock->quantite_reservee < 0) {
                        $stock->quantite_reservee = 0;
                    }
                    $stock->save();

                    // Marquer la réservation comme livrée
                    // $reservation->update(['statut' => 'livre']);
                }
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
