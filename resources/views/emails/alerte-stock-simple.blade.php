<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alerte Stock - {{ $typeAlerte ?? 'Alerte' }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            border: 1px solid #e0e0e0;
        }
        .alert-box {
            background: {{ ($typeAlerte ?? '') === 'RUPTURE DE STOCK' ? '#ffebee' : '#fff3e0' }};
            border-left: 4px solid {{ ($typeAlerte ?? '') === 'RUPTURE DE STOCK' ? '#f44336' : '#ff9800' }};
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .product-info {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 12px;
        }
        .statut-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
            background: {{ ($typeAlerte ?? '') === 'RUPTURE DE STOCK' ? '#f44336' : '#ff9800' }};
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📦 Alerte Stock</h1>
        <p>Votre système de gestion de stock</p>
    </div>

    <div class="content">
        <h2>Bonjour {{ $nomVendeur ?? 'Vendeur' }},</h2>

        <div class="alert-box">
            <h3 style="margin-top: 0; color: {{ ($typeAlerte ?? '') === 'RUPTURE DE STOCK' ? '#d32f2f' : '#f57c00' }};">
                {{ $typeAlerte === 'RUPTURE DE STOCK' ? '🚨 RUPTURE DE STOCK' : '⚠️ STOCK FAIBLE' }}
            </h3>
            <p style="font-size: 16px; margin-bottom: 0;"><strong>{{ $message ?? 'Alerte de stock' }}</strong></p>
        </div>

        <div class="product-info">
            <h3 style="color: #333; margin-top: 0;">Détails du produit</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Produit:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{ $nomProduit ?? 'Produit non spécifié' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Stock restant:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;">
                        <span class="statut-badge">{{ $stockRestant ?? 0 }} unité(s)</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Seuil d'alerte:</strong></td>
                    <td style="padding: 8px 0;">{{ $seuilAlerte ?? 0 }} unité(s)</td>
                </tr>
            </table>
        </div>

        <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #2e7d32;">📋 Recommandation</h4>
            <p style="margin-bottom: 0;">
                @if(($typeAlerte ?? '') === 'RUPTURE DE STOCK')
                    Nous vous recommandons de <strong>réapprovisionner ce produit de toute urgence</strong> pour éviter les ventes manquées.
                @else
                    Nous vous recommandons de <strong>planifier un réapprovisionnement prochain</strong> pour maintenir votre niveau de stock.
                @endif
            </p>
        </div>

        <p style="text-align: center;">
            <a href="{{ url('/stocks') }}" style="background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                📊 Voir mes stocks
            </a>
        </p>
    </div>

    <div class="footer">
        <p>Cet email a été envoyé automatiquement le <strong>{{ $dateAlerte ?? now()->format('d/m/Y H:i') }}</strong> par votre système de gestion de stock.</p>
        <p>© {{ date('Y') }} Votre Plateforme - Tous droits réservés</p>
        <p style="font-size: 10px; color: #999;">
            Si vous pensez avoir reçu cet email par erreur, veuillez nous contacter.
        </p>
    </div>
</body>
</html>
