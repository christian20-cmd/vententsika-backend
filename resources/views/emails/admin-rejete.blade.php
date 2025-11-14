<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demande Rejetée - Vente-Ntsika</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
        .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; }
        .content { background: white; padding: 30px; }
        .reason { background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>❌ Demande Rejetée</h1>
            <p>Votre demande d'administration n'a pas été approuvée</p>
        </div>
        
        <div class="content">
            <h2>Bonjour {{ $nom_complet }},</h2>
            
            <p>Votre demande de compte administrateur sur <strong>Vente-Ntsika</strong> a été <strong>rejetée</strong>.</p>
            
            <div class="reason">
                <p><strong>Raison du rejet :</strong></p>
                <p>{{ $raison_rejet }}</p>
            </div>

            <p><strong>Date du rejet :</strong> {{ $date_rejet }}</p>

            <p>Si vous pensez qu'il s'agit d'une erreur, vous pouvez contacter l'équipe d'administration.</p>
        </div>
    </div>
</body>
</html>