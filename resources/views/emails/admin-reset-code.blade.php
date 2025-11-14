<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de Réinitialisation - Vente-Ntsika Admin</title>
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

        .code-container {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            border-left: 4px solid #2563eb;
        }

        .code-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
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
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 25px 0;
        }

        .warning-box p {
            color: #92400e;
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }

        .info-box {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
            padding: 20px;
            margin: 25px 0;
        }

        .info-box p {
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

        .security-badge {
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

            .code {
                font-size: 28px;
                letter-spacing: 5px;
                padding: 15px 20px;
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
            <h1>Réinitialisation de mot de passe</h1>
            <p>Administration Vente-Ntsika</p>
            <div class="security-badge">Code de sécurité</div>
        </div>

        <div class="content">
            <p class="greeting">Bonjour <strong class="highlight">{{ $nom_complet }}</strong>,</p>

            <p class="message">
                Vous avez demandé la réinitialisation de votre mot de passe administrateur.
                Pour continuer, veuillez utiliser le code de vérification ci-dessous :
            </p>

            <div class="code-container">
                <div class="code-label">Votre code de vérification</div>
                <div class="code">{{ $code }}</div>
            </div>

            <div class="info-box">
                <p>
                    <strong>⏰ Important :</strong> Ce code de sécurité expirera dans
                    <strong>{{ $expiration }} minutes</strong> pour des raisons de sécurité.
                </p>
            </div>

            <div class="warning-box">
                <p>
                    <strong>⚠️ Sécurité du compte :</strong> Si vous n'avez pas demandé cette réinitialisation,
                    veuillez ignorer cet email et vérifier la sécurité de votre compte administrateur.
                </p>
            </div>

            <p class="message">
                Pour toute assistance technique, contactez immédiatement l'équipe de support.
            </p>
        </div>

        <div class="footer">
            <div class="footer-brand">Vente-Ntsika Platform</div>
            <p class="footer-text">
                Votre solution de gestion commerciale sécurisée<br>
                © {{ date('Y') }} Vente-Ntsika Platform. Tous droits réservés.
            </p>
            <p class="footer-text" style="margin-top: 15px; font-size: 12px;">
                Cet email a été envoyé automatiquement pour des raisons de sécurité.<br>
                Merci de ne pas y répondre directement.
            </p>
        </div>
    </div>
</body>
</html>
