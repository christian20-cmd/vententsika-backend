<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe modifié - Vente-Ntsika Platforme</title>
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
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message {
            color: #374151;
            font-size: 15px;
            margin-bottom: 30px;
            line-height: 1.8;
        }

        .success-box {
            background-color: #dbeafe;
            border-left: 4px solid #2563eb;
            padding: 20px;
            margin: 25px 0;
        }

        .success-box strong {
            color: #1e40af;
            font-size: 16px;
            display: block;
            margin-bottom: 8px;
        }

        .success-box p {
            color: #1e40af;
            font-size: 14px;
            margin: 0;
        }

        .warning-box {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            padding: 20px;
            margin: 30px 0;
        }

        .warning-box strong {
            color: #92400e;
            font-size: 15px;
            display: block;
            margin-bottom: 10px;
        }

        .warning-box p {
            color: #92400e;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .warning-list {
            color: #92400e;
            font-size: 14px;
            margin-left: 20px;
            line-height: 1.6;
        }

        .warning-list li {
            margin-bottom: 5px;
        }

        .info-text {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            margin-top: 20px;
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

        .footer-small {
            color: #64748b;
            font-size: 12px;
            margin-top: 15px;
        }

        .divider {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 30px 0;
        }

        .security-icon {
            font-size: 24px;
            margin-right: 10px;
            vertical-align: middle;
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
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- En-tête -->
        <div class="header">
            <div class="logo-container">
                <img src="back/resources/assets/LogoTB.png" class="logo" alt="Logo TB">
            </div>
            <h1>Vente-Ntsika Platforme</h1>
            <p>Confirmation de modification de mot de passe</p>
        </div>

        <!-- Contenu principal -->
        <div class="content">
            <p class="greeting">Bonjour,</p>

            <p class="message">
                Votre mot de passe a été modifié avec succès sur la plateforme <strong style="color: #1e40af;">Vente-Ntsika Platforme</strong>.
            </p>

            <!-- Boîte de succès -->
            <div class="success-box">
                <strong>🛡️ Modification réussie</strong>
                <p>
                    Votre mot de passe a été changé le <strong>{{ now()->format('d/m/Y à H:i') }}</strong>.
                </p>
            </div>

            <!-- Notice de sécurité -->
            <div class="warning-box">
                <strong>⚠️ Vous n'êtes pas à l'origine de cette modification ?</strong>
                <p>
                    Si vous n'avez pas modifié votre mot de passe, veuillez immédiatement :
                </p>
                <ul class="warning-list">
                    <li>Réinitialiser votre mot de passe</li>
                    <li>Contacter l'administrateur</li>
                    <li>Vérifier l'activité de votre compte</li>
                </ul>
            </div>

            <p class="info-text">
                Si vous rencontrez des problèmes pour accéder à votre compte, n'hésitez pas à nous contacter.
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
