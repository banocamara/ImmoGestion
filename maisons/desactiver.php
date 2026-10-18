<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// La désactivation doit obligatoirement passer par POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

// Vérifier le jeton CSRF.
$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token)
    || !isset($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Requête invalide. Veuillez réessayer.');
}

// Vérifier l'identifiant.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant de maison invalide.');
}

// Vérifier que la maison existe.
$verification = $pdo->prepare(
    'SELECT id, nom, actif
     FROM maisons
     WHERE id = :id'
);

$verification->execute(['id' => $id]);
$maison = $verification->fetch();

if (!$maison) {
    http_response_code(404);
    exit('Maison introuvable.');
}

if ((int) $maison['actif'] === 0) {
    $_SESSION['message'] = 'Cette maison est déjà désactivée.';
} else {
    try {
        $requete = $pdo->prepare(
            "UPDATE maisons
             SET actif = 0,
                 statut = 'inactive',
                 updated_at = NOW()
             WHERE id = :id"
        );

        $requete->execute(['id' => $id]);

        $_SESSION['message'] = 'La maison a été désactivée. Son historique est conservé.';

    } catch (PDOException $e) {
        error_log('Erreur de désactivation de maison : ' . $e->getMessage());

        $_SESSION['message'] = 'Impossible de désactiver la maison.';
    }
}

header('Location: index.php');
exit;
