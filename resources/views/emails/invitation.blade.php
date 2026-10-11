<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Activez votre compte {{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f7f7f7;">
    <div style="max-width:600px; margin:20px auto; padding:20px; background-color:#ffffff; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">

        <h1 style="color:#333;">Bonjour {{ $name }},</h1>

        <p style="color:#555; font-size:16px; line-height:1.5;">
            Votre compte employé <strong>{{ config('app.name') }}</strong> a été créé.
            Pour l'activer, choisissez votre mot de passe en cliquant sur le bouton ci-dessous.
        </p>

        <p style="margin:30px 0; text-align:center;">
            <a href="{{ $activationUrl }}"
               style="display:inline-block; padding:12px 24px; background-color:#007bff; color:#ffffff; text-decoration:none; border-radius:5px; font-weight:bold;">
                Créer mon mot de passe
            </a>
        </p>

        <p style="color:#d9534f; font-size:14px;">
            Ce lien est personnel, utilisable une seule fois et valable {{ $validHours }} heures.
            Ne le partagez avec personne.
        </p>

        <p style="color:#777; font-size:13px; word-break:break-all;">
            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
            {{ $activationUrl }}
        </p>

        <p style="color:#777; font-size:14px; margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
            Si vous n'êtes pas concerné par ce message, ignorez-le : aucun accès n'est possible sans ce lien.
        </p>

        <p style="color:#333; font-size:14px; margin-top:20px;">
            Cordialement,<br>
            L'équipe <strong>{{ config('app.name') }}</strong>
        </p>
    </div>
</body>
</html>
