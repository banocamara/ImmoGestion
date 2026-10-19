<?php

// Démarrer la session si elle n'est pas déjà active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger la connexion à la base de données en dehors des fonctions.
// Cela rend $pdo disponible dans la portée globale.
require_once __DIR__ . '/../config/database.php';


/**
 * Vérifie si un utilisateur est connecté.
 */
function estConnecte(): bool
{
    return isset($_SESSION['utilisateur_id'])
        && is_numeric($_SESSION['utilisateur_id'])
        && (int) $_SESSION['utilisateur_id'] > 0;
}


/**
 * Vérifie si l'utilisateur connecté est super-administrateur.
 */
function estSuperAdmin(): bool
{
    return estConnecte()
        && isset($_SESSION['role'])
        && $_SESSION['role'] === 'super_admin';
}


/**
 * Vérifie que l'utilisateur est connecté
 * et que son compte existe toujours et reste actif.
 */
function exigerConnexion(): void
{
    // Indiquer que nous utilisons la variable globale $pdo.
    global $pdo;

    // Si personne n'est connecté, rediriger vers la connexion.
    if (!estConnecte()) {
        header('Location: /ImmoGestion/auth/login.php');
        exit;
    }

    try {
        // Vérifier l'existence et le statut du compte.
        $requete = $pdo->prepare(
            'SELECT id, nom, email, role, actif
             FROM utilisateurs
             WHERE id = :id
             LIMIT 1'
        );

        $requete->execute([
            'id' => (int) $_SESSION['utilisateur_id']
        ]);

        $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

        // Bloquer les comptes inexistants ou désactivés.
        if (
            !$utilisateur ||
            (int) $utilisateur['actif'] !== 1
        ) {
            // Supprimer les données de session.
            $_SESSION = [];

            // Supprimer le cookie de session.
            if (ini_get('session.use_cookies')) {
                $parametres = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $parametres['path'],
                    $parametres['domain'],
                    $parametres['secure'],
                    $parametres['httponly']
                );
            }

            // Détruire la session.
            session_destroy();

            // Rediriger vers la connexion.
            header(
                'Location: /ImmoGestion/auth/login.php?erreur=compte_inactif'
            );
            exit;
        }

        // Actualiser les informations de l'utilisateur.
        $_SESSION['nom'] = $utilisateur['nom'];
        $_SESSION['email'] = $utilisateur['email'];
        $_SESSION['role'] = $utilisateur['role'];

    } catch (PDOException $e) {
        error_log(
            'Erreur de vérification du compte : ' . $e->getMessage()
        );

        http_response_code(500);

        exit(
            'Impossible de vérifier votre compte pour le moment.'
        );
    }
}


/**
 * Réserve une page au super-administrateur.
 */
function exigerSuperAdmin(): void
{
    exigerConnexion();

    if (!estSuperAdmin()) {
        http_response_code(403);

        exit(
            'Accès refusé : cette page est réservée au super-administrateur.'
        );
    }
}

