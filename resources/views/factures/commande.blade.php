<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture {{ $commande->numero_commande }}</title>
    <style>
        /* Styles de base compatibles PDF */
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 20px;
        }

        .info-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .table th {
            background-color: #1e40af;
            color: white;
            padding: 10px;
            text-align: left;
            border: 1px solid #1e40af;
        }

        .table td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        .total-section {
            margin-top: 30px;
            text-align: right;
            padding: 15px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        .section-title {
            color: #1e40af;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #1e40af;
            padding-bottom: 5px;
        }

        .company-info {
            background-color: #1e40af;
            color: white;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- En-tête de la facture -->
    <div class="company-info">
        <h1 style="margin: 0; font-size: 24px;">{{ $commercant->vendeur->nom_entreprise ?? 'VOTRE ENTREPRISE' }}</h1>
        <p style="margin: 5px 0; font-size: 14px;">
            {{ $commercant->vendeur->adresse_entreprise ?? 'Adresse non définie' }} |
            {{ $commercant->vendeur->telephone_entreprise ?? 'Tél: Non défini' }} |
            {{ $commercant->vendeur->email_entreprise ?? 'Email: Non défini' }}
        </p>
    </div>

    <div class="header">
        <h2 style="color: #1e40af; margin: 0;">FACTURE</h2>
        <h3 style="margin: 10px 0;">N° {{ $commande->numero_commande }}</h3>
        <p style="margin: 5px 0;">
            Date: {{ $commande->created_at->format('d/m/Y') }} |
            Statut: {{ ucfirst($commande->statut) }}
        </p>
    </div>

    <!-- Informations client et vendeur -->
    <div style="display: flex; justify-content: space-between; gap: 20px; margin-bottom: 30px;">
        <div class="info-section" style="flex: 1;">
            <div class="section-title">INFORMATIONS CLIENT</div>
            <p style="margin: 8px 0;"><strong>Nom :</strong> {{ $commande->client->nom_prenom_client ?? 'Non spécifié' }}</p>
            <p style="margin: 8px 0;"><strong>Email :</strong> {{ $commande->client->email_client ?? 'Non spécifié' }}</p>
            <p style="margin: 8px 0;"><strong>Téléphone :</strong> {{ $commande->client->telephone_client ?? 'Non spécifié' }}</p>
            <p style="margin: 8px 0;"><strong>Adresse livraison :</strong><br>
            {{ $commande->adresse_livraison ?? 'Non spécifiée' }}</p>
        </div>

        <div class="info-section" style="flex: 1;">
            <div class="section-title">INFORMATIONS COMMANDE</div>
            <p style="margin: 8px 0;"><strong>Date de commande :</strong> {{ $commande->created_at->format('d/m/Y H:i') }}</p>
            @if($commande->date_validation)
            <p style="margin: 8px 0;"><strong>Date de validation :</strong> {{ $commande->date_validation->format('d/m/Y H:i') }}</p>
            @endif
            <p style="margin: 8px 0;"><strong>Statut :</strong>
                <span style="background-color:
                    @if($commande->statut === 'validee') #10b981
                    @elseif($commande->statut === 'livree') #059669
                    @elseif($commande->statut === 'attente_validation') #f59e0b
                    @elseif($commande->statut === 'annulee') #ef4444
                    @else #6b7280
                    @endif;
                    color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px;">
                    {{ strtoupper($commande->statut) }}
                </span>
            </p>
        </div>
    </div>

    <!-- Détails des produits -->
    <div class="section-title">DÉTAIL DE LA COMMANDE</div>
    <table class="table">
        <thead>
            <tr>
                <th style="width: 40%;">Produit</th>
                <th style="width: 15%; text-align: center;">Quantité</th>
                <th style="width: 20%; text-align: right;">Prix unitaire</th>
                <th style="width: 25%; text-align: right;">Sous-total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sousTotalGlobal = 0;
            @endphp

            @forelse($commande->lignesCommande as $index => $ligne)
            @php
                $sousTotalGlobal += $ligne->sous_total;
            @endphp
            <tr>
                <td>{{ $ligne->produit->nom_produit ?? 'Produit non spécifié' }}</td>
                <td style="text-align: center;">{{ $ligne->quantite }}</td>
                <td style="text-align: right;">{{ number_format($ligne->prix_unitaire, 2, ',', ' ') }} €</td>
                <td style="text-align: right;">{{ number_format($ligne->sous_total, 2, ',', ' ') }} €</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align: center; padding: 20px;">Aucun produit dans cette commande</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Totaux et paiements -->
    <div class="total-section">
        <div style="display: inline-block; text-align: left;">
            <p style="margin: 5px 0; font-size: 14px;">
                <strong>Sous-total produits :</strong>
                <span style="float: right; margin-left: 50px;">{{ number_format($sousTotalGlobal, 2, ',', ' ') }} €</span>
            </p>

            <p style="margin: 5px 0; font-size: 14px;">
                <strong>Frais de livraison :</strong>
                <span style="float: right; margin-left: 50px;">{{ number_format($commande->frais_livraison ?? 0, 2, ',', ' ') }} €</span>
            </p>

            <p style="margin: 15px 0; font-size: 16px; border-top: 1px solid #ddd; padding-top: 10px;">
                <strong>TOTAL COMMANDE :</strong>
                <span style="float: right; margin-left: 50px; color: #1e40af;">{{ number_format($commande->total_commande ?? 0, 2, ',', ' ') }} €</span>
            </p>

            <!-- Informations paiement -->
            @if($commande->paiements && $commande->paiements->count() > 0)
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ddd;">
                <div class="section-title" style="text-align: left;">INFORMATIONS DE PAIEMENT</div>
                @foreach($commande->paiements as $paiement)
                <p style="margin: 3px 0; font-size: 12px;">
                    {{ ucfirst($paiement->methode_paiement) }} :
                    {{ number_format($paiement->montant, 2, ',', ' ') }} €
                    ({{ $paiement->date_paiement->format('d/m/Y') }})
                    - <em>{{ $paiement->statut }}</em>
                </p>
                @endforeach

                <p style="margin: 8px 0; font-size: 13px;">
                    <strong>Total payé :</strong>
                    <span style="float: right;">{{ number_format($commande->montant_deja_paye ?? 0, 2, ',', ' ') }} €</span>
                </p>

                <p style="margin: 8px 0; font-size: 13px;">
                    <strong>Reste à payer :</strong>
                    <span style="float: right; color: {{ ($commande->montant_reste_payer ?? 0) > 0 ? '#dc2626' : '#059669' }};">
                        {{ number_format($commande->montant_reste_payer ?? 0, 2, ',', ' ') }} €
                    </span>
                </p>
            </div>
            @endif
        </div>
    </div>

    <!-- Pied de page -->
    <div class="footer">
        <p><strong>{{ $commercant->vendeur->nom_entreprise ?? 'Votre entreprise' }}</strong></p>
        <p>{{ $commercant->vendeur->adresse_entreprise ?? 'Adresse entreprise' }} |
           {{ $commercant->vendeur->telephone_entreprise ?? 'Téléphone' }} |
           {{ $commercant->vendeur->email_entreprise ?? 'Email' }}</p>
        <p style="margin-top: 10px;">Merci pour votre confiance !</p>
        <p>Facture générée le {{ now()->format('d/m/Y à H:i') }}</p>
    </div>
</body>
</html>
