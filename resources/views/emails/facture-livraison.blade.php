<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture {{ $commande->numero_commande }}</title>
    <style>
        /* Variables de couleurs */
        :root {
            --primary: #0b27a5;
            --primary-dark: #020c38;
            --secondary: #061746;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #2ecc71;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 8px;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        /* Styles de base */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: #f5f7ff;
            padding: 20px;
        }

        .invoice-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;

            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* En-tête */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff9a9e 0%, #fad0c4 99%, #fad0c4 100%);
        }

        .logo-container {
            margin-bottom: 15px;
        }

        .logo {
            max-width: 180px;
            filter: brightness(0) invert(1);
        }

        .invoice-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }

        .invoice-number {
            font-size: 1.8rem;
            font-weight: 500;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .status-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 20px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9rem;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .status-livre {
            color: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .status-livre::before {
            content: "";
            display: inline-block;
            width: 10px;
            height: 10px;

            background-color: var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 153, 73, 0.7);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(46, 204, 113, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0);
            }
        }

        /* Sections */
        .section {
            padding: 25px 30px;
            border-bottom: 1px solid var(--gray-light);
        }

        .section:last-of-type {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gray-light);
            display: flex;
            align-items: center;
        }

        .section-title::before {
            content: "";
            display: inline-block;
            width: 6px;
            height: 20px;
            background: var(--primary);
            margin-right: 10px;

        }

        /* Grille d'informations */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-card {
            background: var(--light);
            padding: 15px;

            border-left: 4px solid var(--primary);
        }

        .info-card h4 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card p {
            font-weight: 500;
            font-size: 1.05rem;
        }

        /* Tableaux */
        table {
            width: 100%;
            border-collapse: collapse;

            overflow: hidden;
            box-shadow: 0 0 0 1px var(--gray-light);
        }

        thead {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
        }

        th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--gray-light);
        }

        tbody tr {
            transition: background-color 0.2s;
        }

        tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Total */
        .total-table {
            width: 60%;
            margin-left: auto;
        }

        .total-table td {
            border: none;
            padding: 10px 15px;
        }

        .total-table tr:last-child {
            background-color: var(--light);
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary);
        }

        /* Pied de page */
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

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .section {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .total-table {
                width: 100%;
            }

            .invoice-title {
                font-size: 2rem;
            }

            .invoice-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="logo-container">
                <img src="back\resources\assets\LogoTB.png" class="logo" alt="Logo TB">
            </div>
            <h1 class="invoice-title">FACTURE</h1>
            <h2 class="invoice-number">{{ $commande->numero_commande }}</h2>
            <div class="status-badge">
                <span class="status-livre">COMMANDE LIVRÉE</span>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Informations Client</h3>
            <div class="info-grid">
                <div class="info-card">
                    <h4>Nom complet</h4>
                    <p>{{ $client->nom_prenom_client }}</p>
                </div>
                <div class="info-card">
                    <h4>Email</h4>
                    <p>{{ $client->email_client }}</p>
                </div>
                <div class="info-card">
                    <h4>Téléphone</h4>
                    <p>{{ $client->telephone_client }}</p>
                </div>
                <div class="info-card">
                    <h4>Adresse de livraison</h4>
                    <p>{{ $livraison->adresse_livraison }}</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Détails de la livraison</h3>
            <div class="info-grid">
                <div class="info-card">
                    <h4>Numéro de suivi</h4>
                    <p>{{ $livraison->numero_suivi }}</p>
                </div>
                <div class="info-card">
                    <h4>Date de livraison</h4>
                    <p>{{ $livraison->date_livraison_reelle->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Produits commandés</h3>
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Quantité</th>
                        <th>Prix unitaire</th>
                        <th>Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($commande->lignesCommande as $ligne)
                    <tr>
                        <td>{{ $ligne->produit->nom_produit }}</td>
                        <td>{{ $ligne->quantite }}</td>
                        <td>{{ number_format($ligne->prix_unitaire, 2) }} €</td>
                        <td>{{ number_format($ligne->sous_total, 2) }} €</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section">
            <h3 class="section-title">Total de la commande</h3>
            <table class="total-table">
                <tr>
                    <td>Sous-total produits:</td>
                    <td>{{ number_format($commande->total_commande - $commande->frais_livraison, 2) }} €</td>
                </tr>
                <tr>
                    <td>Frais de livraison (10%):</td>
                    <td>{{ number_format($commande->frais_livraison, 2) }} €</td>
                </tr>
                <tr>
                    <td>TOTAL:</td>
                    <td>{{ number_format($commande->total_commande, 2) }} €</td>
                </tr>
            </table>
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
</body>
</html>
