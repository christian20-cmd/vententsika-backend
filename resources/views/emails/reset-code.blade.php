<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe - VenteNtsika</title>
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
            letter-spacing: 1px;
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

        .code-container {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            border-left: 4px solid #2563eb;
        }

        .code-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .code {
            background-color: rgba(255, 255, 255, 0.95);
            color: #1e40af;
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            padding: 20px 30px;
            display: inline-block;
            font-family: 'Courier New', monospace;
            border: 2px solid #2563eb;
        }

        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px 20px;
            margin: 25px 0;
        }

        .warning-box p {
            color: #92400e;
            font-size: 14px;
            margin: 0;
        }

        .warning-box strong {
            display: block;
            margin-bottom: 5px;
            font-size: 15px;
        }

        .info-text {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
            margin-top: 20px;
        }

        .divider {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 30px 0;
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

        .footer-link {
            color: #1e40af;
            text-decoration: none;
            font-weight: 500;
        }

        .security-notice {
            background-color: #dbeafe;
            border: 1px solid #93c5fd;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid #2563eb;
        }

        .security-notice p {
            color: #1e40af;
            font-size: 13px;
            margin: 0;
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

            .code {
                font-size: 28px;
                letter-spacing: 5px;
                padding: 15px 20px;
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
                <img src="back\resources\assets\LogoTB.png" class="logo" alt="Logo TB">
            </div>
            <h1>VenteNtsika</h1>
            <p>Plateforme de gestion commerciale</p>
        </div>

        <!-- Contenu principal -->
        <div class="content">
            <p class="greeting">Bonjour,</p>

            <p class="message">
                Nous avons reçu une demande de réinitialisation du mot de passe associé à votre compte VenteNtsika.
                Pour continuer, veuillez utiliser le code de vérification ci-dessous :
            </p>

            <!-- Code de vérification -->
            <div class="code-container">
                <div class="code-label">Votre code de vérification</div>
                <div class="code">{{ $code }}</div>
            </div>

            <!-- Avertissement -->
            <div class="warning-box">
                <strong>⏰ Important</strong>
                <p>Ce code de vérification expire dans <strong>10 minutes</strong> pour des raisons de sécurité.</p>
            </div>

            <p class="info-text">
                Saisissez ce code dans l'application VenteNtsika pour définir votre nouveau mot de passe.
            </p>

            <hr class="divider">

            <!-- Notice de sécurité -->
            <div class="security-notice">
                <p>
                    <strong>🛡️ Vous n'avez pas demandé cette réinitialisation ?</strong><br>
                    Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.
                    Votre mot de passe actuel reste sécurisé et inchangé.
                </p>
            </div>
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
