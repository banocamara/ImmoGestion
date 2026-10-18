<?php

// Démarrer la session si elle n'est pas encore active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est connecté.
 */
function estConnecte(): bool
{
    return isset($_SESSION['utilisateur_id']);
}

/**
 * Vérifie si l'utilisateur est super-administrateur.
 */
function estSuperAdmin(): bool
{
    return estConnecte()
        && ($_SESSION['role'] ?? '') === 'super_admin';
}

/**
 * Vérifie que l'utilisateur est connecté.
 * Sinon, le redirige vers la page de connexion.
 */
function exigerConnexion(): void
{
    if (!estConnecte()) {
        header('Location: /ImmoGestion/login.php');
        exit;
    }
}

/**
 * Vérifie que l'utilisateur est super-administrateur.
 */
function exigerSuperAdmin(): void
{
    exigerConnexion();

    if (!estSuperAdmin()) {
        http_response_code(403);
        exit('Accès refusé : vous n’avez pas les autorisations nécessaires.');
    }
}
