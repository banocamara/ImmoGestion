<?php

require_once __DIR__ . '/includes/auth.php';

// La déconnexion doit se faire par une requête POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token)
    || !isset($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Requête invalide. Veuillez réessayer.');
}

// Effacer les données de session.
$_SESSION = [];

// Supprimer le cookie de session, si les cookies sont utilisés.
if (ini_get('session.use_cookies')) {
    $parametres = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $parametres['path'],
            'domain' => $parametres['domain'],
            'secure' => $parametres['secure'],
            'httponly' => $parametres['httponly'],
            'samesite' => $parametres['samesite'] ?? 'Lax'
        ]
    );
}

session_destroy();

header('Location: login.php');
exit;
