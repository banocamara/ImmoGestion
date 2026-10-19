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
    $_SESSION['message'] = 'Identifiant de locataire invalide.';
    header('Location: index.php');
    exit;
}

// Vérifier que le locataire existe
$stmt = $pdo->prepare(
    'SELECT id, actif FROM locataires WHERE id = :id'
);

$stmt->execute(['id' => $id]);
$locataire = $stmt->fetch();

if (!$locataire) {
    $_SESSION['message'] = 'Locataire introuvable.';
    header('Location: index.php');
    exit;
}

// Vérifier s'il est déjà désactivé
if ((int) $locataire['actif'] === 0) {
    $_SESSION['message'] = 'Ce locataire est déjà désactivé.';
    header('Location: index.php');
    exit;
}

// Désactiver le locataire sans supprimer ses données
$stmt = $pdo->prepare(
    'UPDATE locataires SET actif = 0 WHERE id = :id'
);

$stmt->execute(['id' => $id]);

$_SESSION['message'] = 'Locataire désactivé avec succès.';

header('Location: index.php');
exit;

