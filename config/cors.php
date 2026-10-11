<?php

// Sans ce fichier, Laravel 12 n'envoie AUCUN header Access-Control-Allow-Origin
// sur /api/* : le navigateur bloque alors toutes les requêtes du frontend React
// (localhost:5173) vers l'API (localhost:8000), même si le backend répond 200.

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Liste d'origines autorisées, configurable via .env pour la prod
    // (ex: CORS_ALLOWED_ORIGINS=https://mon-domaine.com,https://www.mon-domaine.com)
    'allowed_origins' => array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // false car le frontend utilise un Bearer token (localStorage), pas les cookies
    // de session Sanctum. Pas besoin d'envoyer/recevoir de cookies cross-site ici.
    'supports_credentials' => false,

];
