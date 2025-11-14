<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouvelle Demande Admin - Vente-Ntsika</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .content { background: white; padding: 30px; }
        .button { display: inline-block; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        .button-approve { background: #28a745; color: white; }
        .button-reject { background: #dc3545; color: white; }
        .button-details { background: #6c757d; color: white; }
        .details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .action-buttons { text-align: center; margin: 25px 0; padding: 20px; background: #f8f9fa; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Nouvelle Demande Admin</h1>
            <p>Une nouvelle demande d'administration nécessite votre validation</p>
        </div>
        
        <div class="content">
            <h2>Bonjour Super Admin,</h2>
            
            <p>Un nouveau candidat a soumis une demande pour devenir administrateur sur <strong>Vente-Ntsika</strong>.</p>
            
            <div class="details">
                <p><strong>👤 Candidat :</strong> {{ $nom_candidat }}</p>
                <p><strong>📧 Email :</strong> {{ $email_candidat }}</p>
                <p><strong>🎯 Niveau d'accès demandé :</strong> {{ ucfirst($niveau_acces_demande) }}</p>
                <p><strong>📅 Date de la demande :</strong> {{ $date_demande }}</p>
                <p><strong>⏰ ID de la demande :</strong> #{{ $id_demande ?? 'N/A' }}</p>
            </div>

            <!-- Boutons d'action directe -->
            <div class="action-buttons">
                <h3>🔍 Actions rapides :</h3>
                
                <a href="http://localhost:3000/admin/administrateurs?action=approve&demande={{ $id_demande ?? '' }}" class="button button-approve">
                    ✅ Approuver
                </a>
                
                <a href="http://localhost:3000/admin/administrateurs?action=reject&demande={{ $id_demande ?? '' }}" class="button button-reject">
                    ❌ Rejeter
                </a>
                
                <a href="http://localhost:3000/admin/administrateurs" class="button button-details">
                    📊 Voir détails
                </a>
            </div>

            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                <strong>💡 Information :</strong> 
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Le compte du candidat est actuellement <strong>inactif</strong></li>
                    <li>Il ne pourra pas se connecter tant que la demande n'est pas approuvée</li>
                    <li>Cette demande expire automatiquement après 24h</li>
                </ul>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; color: #666; font-size: 12px;">
            <p>Cet email a été envoyé automatiquement par le système Vente-Ntsika</p>
            <p>© {{ date('Y') }} Vente-Ntsika. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>