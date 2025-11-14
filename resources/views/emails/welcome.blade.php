<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue sur Vente-Ntsika Platforme</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #eff6ff;
            padding: 20px;
            line-height: 1.6;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .header {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
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
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 20px;
            color: #1e293b;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message {
            color: #374151;
            font-size: 15px;
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .welcome-box {
            background-color: #dbeafe;
            border-left: 4px solid #2563eb;
            padding: 20px;
            margin: 25px 0;
        }

        .welcome-box strong {
            color: #1e40af;
            font-size: 16px;
            display: block;
            margin-bottom: 10px;
        }

        .welcome-box p {
            color: #1e40af;
            font-size: 14px;
            margin: 0;
        }

        .features {
            background-color: #f0f4ff;
            padding: 20px;
            margin: 25px 0;
            border-left: 4px solid #3b82f6;
        }

        .features h3 {
            color: #1e40af;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .features ul {
            color: #374151;
            font-size: 14px;
            margin-left: 20px;
            line-height: 1.6;
        }

        .features li {
            margin-bottom: 8px;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            font-weight: 600;
            margin: 20px 0;
            border: 2px solid #2563eb;
        }

        .cta-button:hover {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
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

        .divider {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 25px 0;
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

            .cta-button {
                display: block;
                text-align: center;
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- En-tête -->
        <div class="header">
            <div class="logo-container">
                <!-- REMPLACEZ CETTE URL PAR L'URL ABSOLUE DE VOTRE LOGO -->
                <img src="http://localhost:3000/back/resources/assets/LogoTB.png" class="logo" alt="Logo TB">
            </div>
            <h1>Bienvenue sur Vente-Ntsika Platforme !</h1>
            <p>Votre solution de gestion commerciale</p>
        </div>

        <!-- Contenu principal -->
        <div class="content">
            <p class="greeting">Bonjour {{ $prenom }} {{ $nom }},</p>

            <p class="message">
                Félicitations ! Votre compte a été créé avec succès sur <strong style="color: #1e40af;">Vente-Ntsika Platforme</strong>.
                Nous sommes ravis de vous compter parmi nos utilisateurs.
            </p>

            <!-- Boîte de bienvenue -->
            <div class="welcome-box">
                <strong>📋 Détails de votre compte</strong>
                <p>
                    <strong>Type de compte :</strong> {{ $type_utilisateur === 'entreprise' ? 'Entreprise' : 'Vendeur Individuel' }}<br>
                    <strong>Entreprise :</strong> {{ $nom_entreprise }}<br>
                    <strong>Date d'inscription :</strong> {{ now()->format('d/m/Y') }}
                </p>
            </div>

            <!-- Fonctionnalités -->
            <div class="features">
                <h3>🚀 Ce que vous pouvez faire maintenant :</h3>
                <ul>
                    <li>Gérer votre inventaire et stocks</li>
                    <li>Créer et suivre vos commandes</li>
                    <li>Gérer vos clients et livraisons</li>
                    <li>Consulter vos statistiques de vente</li>
                    <li>Personnaliser votre boutique en ligne</li>
                </ul>
            </div>

            <p class="message">
                <strong>Prochaine étape :</strong> Complétez votre profil et commencez à ajouter vos produits pour
                démarrer vos ventes sur notre plateforme.
            </p>

            <a href="{{ url('/login') }}" class="cta-button">Accéder à mon compte</a>

            <hr class="divider">

            <p class="message" style="font-size: 14px; color: #64748b;">
                <strong>Besoin d'aide ?</strong> Notre équipe de support est disponible pour vous accompagner
                dans la prise en main de la plateforme.
            </p>
        </div>

        <!-- Pied de page -->
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
