<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bon de Livraison - {{ $livraison->idLivraison }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .section { margin-bottom: 15px; }
        .section-title { background-color: #f5f5f5; padding: 5px; font-weight: bold; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 5px; border: 1px solid #ddd; }
        .info-table .label { font-weight: bold; background-color: #f9f9f9; width: 30%; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        .signature { margin-top: 50px; border-top: 1px solid #333; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BON DE LIVRAISON</h1>
        <p>N° DL-{{ str_pad($livraison->idLivraison, 6, '0', STR_PAD_LEFT) }}</p>
    </div>

    <div class="section">
        <div class="section-title">Informations de la Livraison</div>
        <table class="info-table">
            <tr>
                <td class="label">Numéro de Livraison</td>
                <td>DL-{{ str_pad($livraison->idLivraison, 6, '0', STR_PAD_LEFT) }}</td>
            </tr>
            <tr>
                <td class="label">Numéro de Commande</td>
                <td>CMD-{{ str_pad($livraison->idCommande, 6, '0', STR_PAD_LEFT) }}</td>
            </tr>
            <tr>
                <td class="label">Statut</td>
                <td>{{ ucfirst(str_replace('_', ' ', $livraison->status_livraison)) }}</td>
            </tr>
            <tr>
                <td class="label">Date de création</td>
                <td>{{ $livraison->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Informations du Client</div>
        <table class="info-table">
            <tr>
                <td class="label">Nom du client</td>
                <td>{{ $livraison->nom_client }}</td>
            </tr>
            <tr>
                <td class="label">Téléphone</td>
                <td>{{ $livraison->telephone_client }}</td>
            </tr>
            <tr>
                <td class="label">Adresse de livraison</td>
                <td>{{ $livraison->adresse_livraison }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Informations Financières</div>
        <table class="info-table">
            <tr>
                <td class="label">Montant total commande</td>
                <td>{{ number_format($livraison->montant_total_commande, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td class="label">Frais de livraison</td>
                <td>{{ number_format($livraison->frais_livraison, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td class="label">Montant déjà payé</td>
                <td>{{ number_format($livraison->montant_deja_paye, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td class="label">Reste à payer</td>
                <td>{{ number_format($livraison->montant_reste_payer, 2, ',', ' ') }} €</td>
            </tr>
        </table>
    </div>

    @if($livraison->commande && $livraison->commande->produit)
    <div class="section">
        <div class="section-title">Détails du Produit</div>
        <table class="info-table">
            <tr>
                <td class="label">Produit</td>
                <td>{{ $livraison->commande->produit->nom_produit }}</td>
            </tr>
            <tr>
                <td class="label">Quantité</td>
                <td>{{ $livraison->commande->quantite }}</td>
            </tr>
            <tr>
                <td class="label">Prix unitaire</td>
                <td>{{ number_format($livraison->commande->prix_unitaire, 2, ',', ' ') }} €</td>
            </tr>
        </table>
    </div>
    @endif

    @if($livraison->numero_suivi)
    <div class="section">
        <div class="section-title">Suivi</div>
        <table class="info-table">
            <tr>
                <td class="label">Numéro de suivi</td>
                <td>{{ $livraison->numero_suivi }}</td>
            </tr>
            @if($livraison->date_expedition)
            <tr>
                <td class="label">Date d'expédition</td>
                <td>{{ $livraison->date_expedition->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
            @if($livraison->date_livraison_prevue)
            <tr>
                <td class="label">Date de livraison prévue</td>
                <td>{{ $livraison->date_livraison_prevue->format('d/m/Y') }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    @if($livraison->notes)
    <div class="section">
        <div class="section-title">Notes</div>
        <p>{{ $livraison->notes }}</p>
    </div>
    @endif

    <div class="signature">
        <table width="100%">
            <tr>
                <td width="50%" align="center">
                    <p>Signature du livreur</p>
                    <p style="margin-top: 40px;">________________________________</p>
                </td>
                <td width="50%" align="center">
                    <p>Signature du client</p>
                    <p style="margin-top: 40px;">________________________________</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Document généré le {{ now()->format('d/m/Y à H:i') }} | Système de Gestion des Livraisons</p>
    </div>
</body>
</html>
