<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// Autoriser uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Vérifier le jeton CSRF
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    http_response_code(403);
    exit('Erreur de sécurité : formulaire invalide. Rechargez la page et réessayez.');
}

// Vérifier l'identifiant
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    $_SESSION['message'] = 'Identifiant d’appartement invalide.';
    header('Location: index.php');
    exit;
}

// Vérifier que l'appartement existe
$stmt = $pdo->prepare(
    'SELECT id, actif FROM appartements WHERE id = :id'
);

$stmt->execute(['id' => $id]);
$appartement = $stmt->fetch();

if (!$appartement) {
    $_SESSION['message'] = 'Appartement introuvable.';
    header('Location: index.php');
    exit;
}

// Ne rien modifier s'il est déjà désactivé
if ((int) $appartement['actif'] === 0) {
    $_SESSION['message'] = 'Cet appartement est déjà désactivé.';
    header('Location: index.php');
    exit;
}

// Désactiver sans supprimer l'enregistrement
$stmt = $pdo->prepare(
    'UPDATE appartements SET actif = 0 WHERE id = :id'
);

$stmt->execute(['id' => $id]);

$_SESSION['message'] = 'Appartement désactivé avec succès.';

header('Location: index.php');
exit;

