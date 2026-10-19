<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ImmoGestion/paiements/index.php');
    exit;
}

// Vérifier le jeton CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['message_succes'] = 'Session expirée. Rechargez la page et réessayez.';
    header('Location: /ImmoGestion/paiements/index.php');
    exit;
}

// Vérifier l'identifiant du paiement
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $_SESSION['message_succes'] = 'Identifiant de paiement invalide.';
    header('Location: /ImmoGestion/paiements/index.php');
    exit;
}

try {
    // Vérifier que le paiement existe
    $stmt = $pdo->prepare("
        SELECT id, statut
        FROM paiements
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);

    $paiement = $stmt->fetch();

    if (!$paiement) {
        $_SESSION['message_succes'] = 'Paiement introuvable.';
    } elseif ($paiement['statut'] === 'annule') {
        $_SESSION['message_succes'] = 'Ce paiement est déjà annulé.';
    } else {
        // Annuler le paiement sans supprimer son historique
        $stmt = $pdo->prepare("
            UPDATE paiements
            SET statut = 'annule'
            WHERE id = :id
              AND statut = 'valide'
        ");
        $stmt->execute(['id' => $id]);

        $_SESSION['message_succes'] = 'Le paiement a été annulé. Il reste conservé dans l’historique.';
    }
} catch (PDOException $e) {
    $_SESSION['message_succes'] = 'Une erreur est survenue pendant l’annulation du paiement.';
}

header('Location: /ImmoGestion/paiements/index.php');
exit;

