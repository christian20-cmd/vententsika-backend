<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livraison Confirmée - VenteNtsika</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Trebuchet MS', 'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Arial, sans-serif;
            line-height: 1.6;
            color: #1e293b;
            background-color: #eff6ff;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .header {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 4px solid #2563eb;
        }

        .logo-container {
            margin-bottom: 20px;
        }

        .logo {
            max-width: 180px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        .content {
            background: #ffffff;
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message {
            color: #374151;
            font-size: 15px;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .info-card {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
            padding: 25px;
            margin: 25px 0;
        }

        .info-card h3 {
            color: #1e40af;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .info-card ul {
            list-style: none;
            color: #1e40af;
        }

        .info-card li {
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px solid rgba(37, 99, 235, 0.2);
        }

        .info-card li:last-child {
            border-bottom: none;
        }

        .info-card strong {
            color: #1e3a8a;
        }

        .products-section {
            margin: 30px 0;
        }

        .products-section h3 {
            color: #1e40af;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
        }

        .product {
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .product:last-child {
            border-bottom: none;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .product-details {
            color: #64748b;
            font-size: 14px;
        }

        .product-total {
            font-weight: 600;
            color: #1e40af;
            font-size: 16px;
        }

        .tip-box {
            background: #f0f4ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 25px 0;
        }

        .tip-box p {
            color: #1e40af;
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }

        .footer {
            text-align: center;
            padding: 30px;
            color: #64748b;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .footer-brand {
            font-size: 18px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 10px;
        }

        .footer-text {
            font-size: 13px;
            line-height: 1.6;
        }

        .highlight {
            color: #1e40af;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            background: rgba(37, 99, 235, 0.1);
            color: #1e40af;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 12px;
            margin-top: 10px;
            border: 1px solid rgba(37, 99, 235, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }

            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .content {
                padding: 30px 20px;
            }

            .footer {
                padding: 20px;
            }

            .logo {
                max-width: 150px;
            }

            .product {
                flex-direction: column;
                align-items: flex-start;
            }

            .product-total {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo-container">
                <img src="back\resources\assets\LogoTB.png" class="logo" alt="Logo TB">
            </div>
            <h1>Livraison Confirmée !</h1>
            <p>Votre commande a été livrée avec succès</p>
            <div class="status-badge">Livraison terminée</div>
        </div>

        <div class="content">
            <p class="greeting">Bonjour <strong class="highlight">{{ $nom_client }}</strong>,</p>

            <p class="message">
                Votre commande <strong class="highlight">{{ $numero_commande }}</strong> a été livrée avec succès !
            </p>

            <div class="info-card">
                <h3>📦 Détails de la livraison</h3>
                <ul>
                    <li><strong>Numéro de suivi :</strong> {{ $numero_suivi }}</li>
                    <li><strong>Date de livraison :</strong> {{ $date_livraison }}</li>
                    <li><strong>Adresse :</strong> {{ $adresse_livraison }}</li>
                    <li><strong>Montant total :</strong> {{ number_format($montant_total, 2, ',', ' ') }} €</li>
                </ul>
            </div>

            <div class="products-section">
                <h3>🛍️ Produits livrés</h3>
                @foreach($produits as $produit)
                <div class="product">
                    <div class="product-info">
                        <div class="product-name">{{ $produit['nom'] }}</div>
                        <div class="product-details">
                            Quantité : {{ $produit['quantite'] }} × {{ number_format($produit['prix'], 2, ',', ' ') }} €
                        </div>
                    </div>
                    <div class="product-total">
                        {{ number_format($produit['sous_total'], 2, ',', ' ') }} €
                    </div>
                </div>
                @endforeach
            </div>

            <div class="tip-box">
                <p>
                    <strong>💡 Conseil :</strong> Vérifiez l'état de vos produits à réception.
                    En cas de problème, contactez notre service client dans les 48 heures.
                </p>
            </div>
        </div>

        <div class="footer">
            <div class="footer-brand">VenteNtsika</div>
            <p class="footer-text">
                Merci pour votre confiance !<br>
                <strong>L'équipe VenteNtsika</strong>
            </p>
            <p class="footer-text" style="margin-top: 15px; font-size: 12px;">
                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
            </p>
        </div>
    </div>
</body>
</html>
