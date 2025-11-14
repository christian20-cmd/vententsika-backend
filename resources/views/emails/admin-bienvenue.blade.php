<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue Administrateur - Vente-Ntsika</title>
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

        .container {
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
        }

        .content {
            background: #ffffff;
            padding: 40px 30px;
        }

        .footer {
            text-align: center;
            padding: 30px;
            color: #64748b;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .info-box {
            background: #dbeafe;
            padding: 25px;
            margin: 20px 0;
            border-left: 4px solid #2563eb;
        }

        .info-box h3 {
            color: #1e40af;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .info-box p {
            margin-bottom: 8px;
            color: #1e40af;
        }

        .info-box strong {
            color: #1e3a8a;
        }

        .login-link {
            display: inline-block;
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            font-weight: 600;
            margin: 15px 0;
            border: 2px solid #2563eb;
        }

        .login-link:hover {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
        }

        .security-list {
            margin: 20px 0;
            padding-left: 20px;
            color: #374151;
        }

        .security-list li {
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .highlight {
            color: #1e40af;
            font-weight: 600;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-container">
                <img src="back\resources\assets\LogoTB.png" class="logo" alt="Logo TB">
            </div>
            <h1>Bienvenue Administrateur !</h1>
            <p>Plateforme Vente-Ntsika</p>
        </div>

        <div class="content">
            <p>Bonjour <strong class="highlight">{{ $nom_complet }}</strong>,</p>

            <p>Votre compte administrateur sur <strong class="highlight">Vente-Ntsika</strong> a été créé avec succès.</p>

            <div class="info-box">
                <h3>📋 Informations de votre compte :</h3>
                <p><strong>Email :</strong> {{ $email }}</p>
                <p><strong>Niveau d'accès :</strong> {{ $niveau_acces }}</p>
                <p><strong>Date de création :</strong> {{ $date_creation }}</p>
                @if(isset($mot_de_passe) && $mot_de_passe)
                <p><strong>Mot de passe temporaire :</strong> {{ $mot_de_passe }}</p>
                @endif
            </div>

            <p>Vous pouvez dès maintenant vous connecter à la plateforme d'administration.</p>

            <p><strong>🚀 Accéder à l'administration :</strong></p>
            <a href="{{ url('/admin') }}" class="login-link">{{ url('/admin') }}</a>

            <hr class="divider">

            <p>Pour des raisons de sécurité, nous vous recommandons de :</p>
            <ul class="security-list">
                <li>Changer votre mot de passe après la première connexion</li>
                <li>Activer l'authentification à deux facteurs si disponible</li>
                <li>Ne jamais partager vos identifiants</li>
                <li>Surveiller régulièrement les journaux d'activité</li>
            </ul>

            <p style="color: #64748b; font-size: 14px; margin-top: 20px;">
                <strong>Support :</strong> En cas de problème technique, contactez l'équipe de développement.
            </p>
        </div>

        <div class="footer">
            <p><strong>Vente-Ntsika Platform</strong></p>
            <p>© {{ date('Y') }} Vente-Ntsika Platform. Tous droits réservés.</p>
            <p style="margin-top: 15px; font-size: 12px;">
                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
            </p>
        </div>
    </div>
</body>
</html>
