<!DOCTYPE html>
<html>
<head>
    <title>Réinitialisation de mot de passe</title>
</head>
<body>
    <h2>Réinitialisation de votre mot de passe administrateur</h2>
    <p>Bonjour {{ $nom_complet }},</p>
    <p>Vous avez demandé la réinitialisation de votre mot de passe administrateur.</p>
    <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
    <a href="{{ $reset_url }}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        Réinitialiser mon mot de passe
    </a>
    <p>Ce lien expirera dans {{ $expiration_minutes }} minutes.</p>
    <p>Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.</p>
</body>
</html>