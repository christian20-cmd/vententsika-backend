<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class LivraisonCompleteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $livraison;
    public $pdf;

    public function __construct($livraison, $pdf)
    {
        $this->livraison = $livraison;
        $this->pdf = $pdf;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre commande a été livrée - ' . $this->livraison->commande->numero_commande,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.livraison-complete',
            with: [
                'livraison' => $this->livraison,
                'client' => $this->livraison->commande->client,
                'commande' => $this->livraison->commande,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            $this->pdf->output(), 'facture-' . $this->livraison->commande->numero_commande . '.pdf',
        ];
    }
}
