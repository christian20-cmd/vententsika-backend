<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livraison Complète</title>
    <style>
        /* Variables de couleurs - Palette bleue */
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #dbeafe;
            --primary-very-light: #eff6ff;
            --secondary: #2563eb;
            --success: #16a34a;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --white: #ffffff;
        }

        /* Styles de base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: var(--primary-very-light);
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: var(--white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        /* En-tête */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 30px;
            text-align: center;
            position: relative;
            border-bottom: 4px solid var(--secondary);
        }

        .logo-container {
            margin-bottom: 20px;
        }

        .logo {
            max-width: 150px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        .confetti {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: block;
        }

        .header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* Contenu principal */
        .content {
            padding: 30px;
        }

        .greeting {
            margin-bottom: 25px;
            font-size: 1.1rem;
        }

        .greeting strong {
            color: var(--primary);
        }

        /* Cartes d'information */
        .info-card {
            background: var(--light);
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .success-card {
            background: var(--primary-very-light);
            border-left-color: var(--success);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-icon {
            font-size: 1.5rem;
            margin-right: 12px;
            color: var(--primary);
        }

        .success-card .card-icon {
            color: var(--success);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }

        .details-list {
            list-style: none;
        }

        .details-list li {
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
        }

        .details-list li:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: var(--gray);
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark);
        }

        .highlight {
            color: var(--primary);
            font-weight: 700;
        }

        .success-highlight {
            color: var(--success);
            font-weight: 700;
        }

        /* Section de confirmation */
        .confirmation {
            background: var(--primary-light);
            padding: 20px;
            text-align: center;
            margin: 25px 0;
            border: 1px solid var(--primary);
        }

        .confirmation p {
            margin-bottom: 15px;
            font-size: 1.05rem;
        }

        /* Pied de page */
        .footer {
            text-align: center;
            padding: 25px;
            color: var(--gray);
            background: var(--light);
            border-top: 1px solid var(--gray-light);
        }

        .signature {
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .divider {
            height: 1px;
            background: var(--gray-light);
            margin: 20px 0;
        }

        .footer-note {
            font-size: 0.85rem;
            color: var(--gray);
        }
        .footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .footer-brand {
            font-size: 18px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 10px;
        }

        .footer-text {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        /* Badge de statut */
        .status-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(22, 163, 74, 0.1);
            color: var(--success);
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 10px;
            border: 1px solid rgba(22, 163, 74, 0.3);
        }

        .status-badge::before {
            content: "✓";
            display: inline-block;
            margin-right: 8px;
            font-weight: bold;
        }

        /* Responsive */
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .content {
                padding: 20px;
            }

            .header {
                padding: 25px 20px;
            }

            .header h2 {
                font-size: 1.5rem;
            }

            .info-card {
                padding: 20px;
            }

            .details-list li {
                flex-direction: column;
            }

            .detail-value {
                margin-top: 5px;
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
            <div class="confetti">🎉</div>
            <h2>Votre commande a été livrée !</h2>
            <p>Mission accomplie avec succès</p>
            <div class="status-badge">Livraison confirmée</div>
        </div>

        <div class="content">
            <div class="greeting">
                <p>Bonjour <strong>{{ $client->nom_prenom_client }}</strong>,</p>
                <p>Nous sommes ravis de vous informer que votre commande <strong class="highlight">{{ $commande->numero_commande }}</strong> a été livrée avec succès.</p>
            </div>

            <div class="info-card">
                <div class="card-header">
                    <div class="card-icon">📦</div>
                    <h3 class="card-title">Détails de la livraison</h3>
                </div>
                <ul class="details-list">
                    <li>
                        <span class="detail-label">Numéro de suivi :</span>
                        <span class="detail-value">{{ $livraison->numero_suivi }}</span>
                    </li>
                    <li>
                        <span class="detail-label">Date de livraison :</span>
                        <span class="detail-value">{{ $livraison->date_livraison_reelle->format('d/m/Y H:i') }}</span>
                    </li>
                    <li>
                        <span class="detail-label">Adresse :</span>
                        <span class="detail-value">{{ $livraison->adresse_livraison }}</span>
                    </li>
                </ul>
            </div>

            <div class="info-card success-card">
                <div class="card-header">
                    <div class="card-icon">💰</div>
                    <h3 class="card-title">Récapitulatif financier</h3>
                </div>
                <ul class="details-list">
                    <li>
                        <span class="detail-label">Montant commande :</span>
                        <span class="detail-value">{{ number_format($commande->total_commande - $commande->frais_livraison, 2) }} €</span>
                    </li>
                    <li>
                        <span class="detail-label">Frais de livraison (10%) :</span>
                        <span class="detail-value">{{ number_format($commande->frais_livraison, 2) }} €</span>
                    </li>
                    <li>
                        <span class="detail-label">Total payé :</span>
                        <span class="detail-value success-highlight">{{ number_format($commande->total_commande, 2) }} €</span>
                    </li>
                </ul>
            </div>

            <div class="confirmation">
                <p>Votre facture détaillée est jointe à cet email.</p>
                <p>Nous espérons que vous serez satisfait de votre achat !</p>
            </div>

            <div class="footer">
                <div class="footer-brand">Vente-Ntsika Platforme</div>
                <p class="footer-text">
                    Votre solution de gestion commerciale de confiance<br>
                    © 2025 Vente-Ntsika Platforme. Tous droits réservés.
                </p>
                <p class="footer-text" style="margin-top: 15px; font-size: 12px;">
                    Cet email a été envoyé automatiquement. Merci de ne pas y répondre directement.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
