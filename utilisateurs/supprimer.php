<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();
exigerSuperAdmin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que la requête utilise POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Vérifier le jeton CSRF
$jeton = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($jeton) ||
    !hash_equals($_SESSION['csrf_token'], $jeton)
) {
    http_response_code(403);
    exit('Requête refusée : jeton de sécurité invalide.');
}

// Vérifier l'identifiant
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php');
    exit;
}

// Empêcher la désactivation de son propre compte
if ($id === (int) $_SESSION['utilisateur_id']) {
    header('Location: index.php?erreur=propre_compte');
    exit;
}

try {
    // Vérifier que l'utilisateur existe
    $requete = $pdo->prepare(
        'SELECT id, role, actif
         FROM utilisateurs
         WHERE id = :id'
    );

    $requete->execute(['id' => $id]);
    $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

    if (!$utilisateur) {
        header('Location: index.php');
        exit;
    }

    // Empêcher la désactivation d'un super-administrateur
    if ($utilisateur['role'] === 'super_admin') {
        header('Location: index.php?erreur=super_admin');
        exit;
    }

    // Désactiver le compte sans supprimer son historique
    $requete = $pdo->prepare(
        'UPDATE utilisateurs
         SET actif = 0
         WHERE id = :id'
    );

    $requete->execute(['id' => $id]);

    header('Location: index.php?succes=desactivation');
    exit;

} catch (PDOException $e) {
    error_log('Erreur de désactivation utilisateur : ' . $e->getMessage());

    http_response_code(500);
    exit('Une erreur est survenue lors de la désactivation du compte.');
}
