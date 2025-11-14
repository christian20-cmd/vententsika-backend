<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation Administrateur - Vente-Ntsika</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Invitation Administrateur</h1>
            <p>Vous avez été invité à rejoindre l'équipe d'administration</p>
        </div>
        
        <div class="content">
            <h2>Bonjour !</h2>
            
            <p>Vous avez été invité à devenir administrateur sur <strong>Vente-Ntsika</strong>.</p>
            
            <div class="details">
                <p><strong>Niveau d'accès :</strong> {{ ucfirst($niveau_acces) }}</p>
                <p><strong>Expire dans :</strong> {{ $expiration_minutes }} minutes</p>
            </div>

            <p>Pour activer votre compte administrateur, cliquez sur le bouton ci-dessous :</p>
            
            <div style="text-align: center;">
                <a href="{{ $invitation_url }}" class="button">
                    🚀 Activer mon compte administrateur
                </a>
            </div>

            <p>Ou copiez-collez ce lien dans votre navigateur :</p>
            <p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px;">
                {{ $invitation_url }}
            </p>

            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                <strong>⚠️ Important :</strong> Ce lien est valable pendant {{ $expiration_minutes }} minutes seulement.
                Ne partagez pas ce lien avec d'autres personnes.
            </div>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} Vente-Ntsika. Tous droits réservés.</p>
            <p>Si vous n'avez pas demandé cette invitation, vous pouvez ignorer cet email.</p>
        </div>
    </div>
</body>
</html>