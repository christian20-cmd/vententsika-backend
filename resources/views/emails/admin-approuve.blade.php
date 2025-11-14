<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compte Approuvé - Vente-Ntsika</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
        .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center; }
        .content { background: white; padding: 30px; }
        .details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Compte Approuvé</h1>
            <p>Votre compte administrateur a été validé</p>
        </div>
        
        <div class="content">
            <h2>Félicitations {{ $nom_complet }} !</h2>
            
            <p>Votre demande de compte administrateur sur <strong>Vente-Ntsika</strong> a été <strong>approuvée</strong>.</p>
            
            <div class="details">
                <p><strong>Email :</strong> {{ $email }}</p>
                <p><strong>Niveau d'accès :</strong> {{ ucfirst($niveau_acces) }}</p>
                <p><strong>Date d'activation :</strong> {{ $date_activation }}</p>
            </div>

            <p>Vous pouvez maintenant vous connecter à votre espace administrateur et commencer à utiliser toutes les fonctionnalités.</p>

            <div style="text-align: center; margin: 30px 0;">
                <a href="http://localhost:3000/admin/connexion" class="button" style="display: inline-block; background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">
                    🚀 Se connecter
                </a>
            </div>
        </div>
    </div>
</body>
</html>