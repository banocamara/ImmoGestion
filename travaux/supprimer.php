<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();
exigerSuperAdmin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Accepter uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ImmoGestion/travaux/index.php');
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
    header('Location: /ImmoGestion/travaux/index.php?erreur=id');
    exit;
}

// Vérifier que le travail existe
$stmt = $pdo->prepare('SELECT id FROM travaux WHERE id = ?');
$stmt->execute([$id]);

if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    header('Location: /ImmoGestion/travaux/index.php?erreur=introuvable');
    exit;
}

// Supprimer le travail
$stmt = $pdo->prepare('DELETE FROM travaux WHERE id = ?');
$stmt->execute([$id]);

header('Location: /ImmoGestion/travaux/index.php?succes=suppression');
exit;

