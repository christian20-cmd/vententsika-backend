<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Alerte Stock - {{ $typeAlerte }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
        .content { background: white; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .alert { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; }
        .product-details { background: #f8f9fa; padding: 15px; border-radius: 5px; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Alerte Stock - {{ $typeAlerte }}</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $nomVendeur }}</strong>,</p>

            <div class="alert {{ $typeAlerte === 'RUPTURE DE STOCK' ? 'alert-danger' : 'alert-warning' }}">
                <strong>{{ $messageAlerte }}</strong>
            </div>

            <div class="product-details">
                <h3>Détails du produit:</h3>
                <ul>
                    <li><strong>Produit:</strong> {{ $nomProduit }}</li>
                    <li><strong>Stock restant:</strong> {{ $stockRestant }} unité(s)</li>
                    <li><strong>Seuil d'alerte:</strong> {{ $seuilAlerte }} unité(s)</li>
                </ul>
            </div>

            <p>Nous vous recommandons de réapprovisionner rapidement ce produit pour éviter les ruptures de stock.</p>

            <p>Cordialement,<br>Votre équipe de gestion de stock</p>
        </div>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement le {{ $dateAlerte }} par votre système de gestion de stock.</p>
            <p>© {{ date('Y') }} Votre Plateforme - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
