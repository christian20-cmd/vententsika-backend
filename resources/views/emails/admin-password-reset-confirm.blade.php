<!DOCTYPE html>
<html>
<head>
    <title>Mot de passe réinitialisé</title>
</head>
<body>
    <h2>Mot de passe réinitialisé avec succès</h2>
    <p>Bonjour {{ $nom_complet }},</p>
    <p>Votre mot de passe administrateur a été réinitialisé avec succès.</p>
    <p><strong>Date :</strong> {{ $date_reinitialisation }}</p>
    <p><strong>Adresse IP :</strong> {{ $ip_address }}</p>
    <p>Si vous n'êtes pas à l'origine de cette modification, veuillez contacter immédiatement un super administrateur.</p>
</body>
</html>