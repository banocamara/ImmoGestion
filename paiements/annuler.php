<?php

require_once __DIR__ . '/../includes/auth.php';

exigerSuperAdmin();

/*
|--------------------------------------------------------------------------
| Autoriser uniquement les requêtes POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ImmoGestion/paiements/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Vérifier le jeton CSRF
|--------------------------------------------------------------------------
*/

$csrfTokenRecu = $_POST['csrf_token'] ?? '';
$csrfTokenSession = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($csrfTokenRecu)
    || !is_string($csrfTokenSession)
    || $csrfTokenRecu === ''
    || $csrfTokenSession === ''
    || !hash_equals($csrfTokenSession, $csrfTokenRecu)
) {
    $_SESSION['message_erreur'] =
        'Votre session a expiré. Rechargez la page et réessayez.';

    header('Location: /ImmoGestion/paiements/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Vérifier l'identifiant du paiement
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $_SESSION['message_erreur'] = 'Identifiant de paiement invalide.';

    header('Location: /ImmoGestion/paiements/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Annuler le paiement sans supprimer son historique
|--------------------------------------------------------------------------
*/

try {
    $pdo->beginTransaction();

    /*
     * Verrouiller le paiement pendant sa vérification
     * afin d'éviter deux annulations simultanées.
     */
    $requete = $pdo->prepare("
        SELECT id, statut
        FROM paiements
        WHERE id = :id
        FOR UPDATE
    ");

    $requete->execute([
        'id' => $id
    ]);

    $paiement = $requete->fetch(PDO::FETCH_ASSOC);

    if (!$paiement) {
        $pdo->commit();

        $_SESSION['message_erreur'] = 'Paiement introuvable.';

        header('Location: /ImmoGestion/paiements/index.php');
        exit;
    }

    if ($paiement['statut'] === 'annule') {
        $pdo->commit();

        $_SESSION['message_erreur'] = 'Ce paiement est déjà annulé.';

        header('Location: /ImmoGestion/paiements/index.php');
        exit;
    }

    /*
     * Vérifier que le statut correspond à une valeur
     * autorisée avant de procéder à l'annulation.
     */
    if ($paiement['statut'] !== 'valide') {
        $pdo->commit();

        $_SESSION['message_erreur'] =
            'Le statut de ce paiement ne permet pas son annulation.';

        header('Location: /ImmoGestion/paiements/index.php');
        exit;
    }

    /*
     * Modifier le statut sans supprimer le paiement.
     */
    $requete = $pdo->prepare("
        UPDATE paiements
        SET statut = 'annule'
        WHERE id = :id
          AND statut = 'valide'
    ");

    $requete->execute([
        'id' => $id
    ]);

    if ($requete->rowCount() !== 1) {
        throw new RuntimeException(
            'Le paiement n’a pas pu être annulé.'
        );
    }

    $pdo->commit();

    $_SESSION['message_succes'] =
        'Le paiement a été annulé. Il reste conservé dans l’historique.';

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erreur lors de l’annulation du paiement '
        . $id . ' : ' . $e->getMessage()
    );

    $_SESSION['message_erreur'] =
        'Une erreur est survenue pendant l’annulation du paiement. Aucun historique n’a été supprimé.';
}

/*
|--------------------------------------------------------------------------
| Retour à la liste des paiements
|--------------------------------------------------------------------------
*/

header('Location: /ImmoGestion/paiements/index.php');
exit;
