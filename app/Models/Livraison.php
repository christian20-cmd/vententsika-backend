<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class Livraison extends Model
{
    use HasFactory;

    protected $table = 'livraisons';
    protected $primaryKey = 'idLivraison';
    public $timestamps = true;

    protected $fillable = [
        'idCommande',
        'nom_client',
        'telephone_client',
        'adresse_livraison',
        'numero_suivi',
        'date_expedition',
        'date_livraison_prevue',
        'date_livraison_reelle',
        'status_livraison',
        'notes_livraison',
        'frais_livraison',
        'montant_total_commande'
    ];

    protected $casts = [
        'date_expedition' => 'datetime',
        'date_livraison_prevue' => 'datetime',
        'date_livraison_reelle' => 'datetime',
        'frais_livraison' => 'decimal:2',        // ← AJOUTER
        'montant_total_commande' => 'decimal:2', // ← AJOUTER
    ];

    // Relations
    public function commande()
    {
        return $this->belongsTo(Commande::class, 'idCommande');
    }

    // Validation : Une commande ne peut avoir qu'une seule livraison
    public static function boot()
    {
        parent::boot();

        static::creating(function ($livraison) {
            // Vérifier si la commande est déjà livrée
            if ($livraison->commande && $livraison->commande->statut === 'livree') {
                throw new \Exception('Impossible de créer une livraison pour une commande déjà livrée.');
            }

            // Vérifier si une livraison existe déjà pour cette commande
            $existingLivraison = self::where('idCommande', $livraison->idCommande)->first();
            if ($existingLivraison) {
                throw new \Exception('Une livraison existe déjà pour cette commande.');
            }

            // Générer un numéro de suivi unique si non fourni
            if (!$livraison->numero_suivi) {
                $livraison->numero_suivi = self::genererNumeroSuivi();
            }
        });

        static::saving(function ($livraison) {
            // Vérifier l'unicité du numéro de suivi
            if ($livraison->numero_suivi) {
                $existing = self::where('numero_suivi', $livraison->numero_suivi)
                    ->where('idLivraison', '!=', $livraison->idLivraison)
                    ->first();
                if ($existing) {
                    throw new \Exception('Ce numéro de suivi est déjà utilisé.');
                }
            }
        });
    }

    // Générer un numéro de suivi unique
    public static function genererNumeroSuivi()
    {
        do {
            $numero = 'SUIVI-' . strtoupper(substr(uniqid(), -8)) . '-' . date('Ymd');
            $exists = self::where('numero_suivi', $numero)->exists();
        } while ($exists);

        return $numero;
    }

    // Accesseurs pour les informations financières (lecture seule depuis commande)
    public function getFraisLivraisonAttribute()
    {
        return $this->commande ? $this->commande->frais_livraison : 0;
    }

    public function getMontantTotalCommandeAttribute()
    {
        return $this->commande ? $this->commande->total_commande : 0;
    }

    public function getMontantDejaPayeAttribute()
    {
        return $this->commande ? ($this->commande->total_commande - $this->commande->montant_reste_payer) : 0;
    }

    public function getMontantRestePayerAttribute()
    {
        return $this->commande ? $this->commande->montant_reste_payer : 0;
    }

    // Accesseurs pratiques
    public function getEstLivreeAttribute()
    {
        return $this->status_livraison === 'livre';
    }

    public function getEstEnRetardAttribute()
    {
        return $this->date_livraison_prevue &&
               $this->date_livraison_prevue->isPast() &&
               !$this->est_livree;
    }

    // Méthodes utilitaires
    public function marquerCommeExpedie()
    {
        $this->update([
            'status_livraison' => 'expedie',
            'date_expedition' => now()
        ]);
    }

    public function marquerCommeLivre()
    {
        DB::transaction(function () {
            // 1. Mettre à jour le statut de livraison
            $this->update([
                'status_livraison' => 'livre',
                'date_livraison_reelle' => now(),
            ]);

            // 2. Mettre à jour le statut de la commande
            $this->commande->update(['statut' => 'livree']);

            // 3. CORRECTION : Mettre à jour le stock en décrémentant quantite_reservee
            $commande = $this->commande;
            if ($commande->lignesCommande) {
                foreach ($commande->lignesCommande as $ligne) {
                    $produit = $ligne->produit;
                    if ($produit && $produit->stock) {
                        $stock = $produit->stock;
                        $quantite = $ligne->quantite;

                        // Décrémenter uniquement quantite_reservee
                        $stock->quantite_reservee -= $quantite;
                        $stock->date_derniere_maj = now();
                        $stock->save();

                        Log::info("📦 Stock mis à jour après livraison", [
                            'produit' => $produit->nom_produit,
                            'quantite_livree' => $quantite,
                            'nouveau_quantite_reservee' => $stock->quantite_reservee,
                            'nouveau_quantite_disponible' => $stock->quantite_disponible
                        ]);
                    }
                }
            }

            // 4. Envoyer l'email
            $this->envoyerEmailLivraison();
        });
    }
    public function envoyerEmailLivraison()
    {
        try {
            $client = $this->commande->client;
            $commande = $this->commande;

            Log::info("🚀 TENTATIVE D'ENVOI EMAIL RÉEL pour livraison #{$this->idLivraison}");
            Log::info("📧 Destinataire: {$client->email_client}");

            // Préparer les données pour l'email
            $data = [
                'nom_client' => $client->nom_prenom_client,
                'numero_commande' => $commande->numero_commande,
                'numero_suivi' => $this->numero_suivi,
                'date_livraison' => $this->date_livraison_reelle->format('d/m/Y à H:i'),
                'adresse_livraison' => $this->adresse_livraison,
                'montant_total' => $commande->total_commande,
                'produits' => $commande->lignesCommande->map(function($ligne) {
                    return [
                        'nom' => $ligne->produit->nom_produit,
                        'quantite' => $ligne->quantite,
                        'prix' => $ligne->prix_unitaire,
                        'sous_total' => $ligne->sous_total
                    ];
                })->toArray()
            ];

            Log::info("✅ Données email préparées", $data);

            // ENVOI RÉEL DE L'EMAIL
            Mail::send('emails.livraison', $data, function ($message) use ($client, $commande) {
                $message->to($client->email_client, $client->nom_prenom_client)
                        ->subject('✅ Votre commande ' . $commande->numero_commande . ' a été livrée ! - VenteNtsika');
            });

            Log::info("✅ EMAIL RÉEL ENVOYÉ AVEC SUCCÈS à {$client->email_client}");
            Log::info("💰 Commande: {$commande->numero_commande}, Montant: {$commande->total_commande} €");

            return true;

        } catch (\Exception $e) {
            Log::error("❌ ERREUR ENVOI EMAIL: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    // Vérifier si la livraison peut être modifiée
    public function peutEtreModifiee()
    {
        return !in_array($this->status_livraison, ['livre', 'annule']);
    }

    // Vérifier si la livraison peut être supprimée
    public function peutEtreSupprimee()
    {
        return !in_array($this->status_livraison, ['livre', 'expedie', 'en_transit']);
    }
}
