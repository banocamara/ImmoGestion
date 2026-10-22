<?php

require_once __DIR__ . '/../includes/auth.php';

exigerConnexion();

$erreurs = [];

$estSuperAdmin = estSuperAdmin();
$utilisateurId = (int) $_SESSION['utilisateur_id'];

// Générer le jeton CSRF si nécessaire.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifier l'identifiant du travail.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: /ImmoGestion/travaux/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Récupérer le travail et vérifier les droits d'accès
|--------------------------------------------------------------------------
*/

if ($estSuperAdmin) {
    $stmt = $pdo->prepare("
        SELECT t.*
        FROM travaux t
        WHERE t.id = ?
    ");

    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("
        SELECT t.*
        FROM travaux t
        INNER JOIN affectations_admins aa
            ON aa.maison_id = t.maison_id
        INNER JOIN maisons m
            ON m.id = t.maison_id
        WHERE t.id = ?
          AND aa.utilisateur_id = ?
          AND m.actif = 1
    ");

    $stmt->execute([$id, $utilisateurId]);
}

$travail = $stmt->fetch(PDO::FETCH_ASSOC);

// Si le travail n'existe pas ou si l'utilisateur n'y a pas accès.
if (!$travail) {
    header('Location: /ImmoGestion/travaux/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Valeurs initiales du formulaire
|--------------------------------------------------------------------------
*/

$titre = $travail['titre'];
$description = $travail['description'] ?? '';

$maison_id = (string) $travail['maison_id'];

$appartement_id = $travail['appartement_id'] !== null
    ? (string) $travail['appartement_id']
    : '';

$montant = (string) $travail['montant'];
$date_travail = $travail['date_travail'];
$statut = $travail['statut'];

/*
|--------------------------------------------------------------------------
| Charger uniquement les maisons accessibles
|--------------------------------------------------------------------------
*/

if ($estSuperAdmin) {
    $stmt = $pdo->query("
        SELECT id, nom
        FROM maisons
        WHERE actif = 1
        ORDER BY nom ASC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT m.id, m.nom
        FROM maisons m
        INNER JOIN affectations_admins aa
            ON aa.maison_id = m.id
        WHERE aa.utilisateur_id = ?
          AND m.actif = 1
        ORDER BY m.nom ASC
    ");

    $stmt->execute([$utilisateurId]);
}

$maisons = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Traitement du formulaire
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF.
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken)
        || !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $erreurs[] = 'Votre session de sécurité a expiré. Rechargez la page et réessayez.';
    }

    // Récupérer les valeurs envoyées.
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $maison_id = trim((string) ($_POST['maison_id'] ?? ''));

    $appartement_id = trim(
        (string) ($_POST['appartement_id'] ?? '')
    );

    $montant = trim((string) ($_POST['montant'] ?? ''));
    $date_travail = trim((string) ($_POST['date_travail'] ?? ''));
    $statut = trim((string) ($_POST['statut'] ?? ''));

    /*
    |--------------------------------------------------------------------------
    | Vérifier le titre et la description
    |--------------------------------------------------------------------------
    */

    if (
        $titre === ''
        || mb_strlen($titre) > 150
    ) {
        $erreurs[] = 'Le titre est obligatoire et ne doit pas dépasser 150 caractères.';
    }

    if (mb_strlen($description) > 5000) {
        $erreurs[] = 'La description ne doit pas dépasser 5000 caractères.';
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifier la maison sélectionnée
    |--------------------------------------------------------------------------
    */

    $maisonValide = filter_var(
        $maison_id,
        FILTER_VALIDATE_INT
    );

    if ($maisonValide === false || $maisonValide < 1) {

        $erreurs[] = 'Veuillez sélectionner une maison valide.';

    } else {

        if ($estSuperAdmin) {

            $stmt = $pdo->prepare("
                SELECT id
                FROM maisons
                WHERE id = ?
                  AND actif = 1
            ");

            $stmt->execute([$maisonValide]);

        } else {

            $stmt = $pdo->prepare("
                SELECT m.id
                FROM maisons m
                INNER JOIN affectations_admins aa
                    ON aa.maison_id = m.id
                WHERE m.id = ?
                  AND aa.utilisateur_id = ?
                  AND m.actif = 1
            ");

            $stmt->execute([
                $maisonValide,
                $utilisateurId
            ]);
        }

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $erreurs[] = 'Vous ne pouvez pas sélectionner cette maison ou elle est inactive.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifier l'appartement sélectionné
    |--------------------------------------------------------------------------
    */

    $appartementValide = null;

    if ($appartement_id !== '') {

        $appartementValide = filter_var(
            $appartement_id,
            FILTER_VALIDATE_INT
        );

        if (
            $appartementValide === false
            || $appartementValide < 1
        ) {

            $erreurs[] = 'Veuillez sélectionner un appartement valide.';

        } elseif (
            $maisonValide !== false
            && $maisonValide > 0
        ) {

            // Un appartement doit appartenir à la maison sélectionnée.
            // L'appartement actuel peut rester sélectionné même s'il
            // a été désactivé depuis l'enregistrement du travail.
            $stmt = $pdo->prepare("
                SELECT id
                FROM appartements
                WHERE id = ?
                  AND maison_id = ?
                  AND (actif = 1 OR id = ?)
            ");

            $stmt->execute([
                $appartementValide,
                $maisonValide,
                $travail['appartement_id'] ?? 0
            ]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $erreurs[] = 'L’appartement doit appartenir à la maison sélectionnée et être actif.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifier le montant
    |--------------------------------------------------------------------------
    */

    if (
        $montant === ''
        || !is_numeric($montant)
        || !is_finite((float) $montant)
        || (float) $montant < 0
    ) {
        $erreurs[] = 'Veuillez saisir un montant valide, positif ou nul.';
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifier la date
    |--------------------------------------------------------------------------
    */

    $dateValide = false;

    if ($date_travail !== '') {
        $date = DateTime::createFromFormat(
            '!Y-m-d',
            $date_travail
        );

        $dateValide = (
            $date !== false
            && $date->format('Y-m-d') === $date_travail
        );
    }

    if (!$dateValide) {
        $erreurs[] = 'Veuillez saisir une date valide.';
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifier le statut
    |--------------------------------------------------------------------------
    */

    $statutsAutorises = [
        'prevu',
        'en_cours',
        'termine'
    ];

    if (!in_array($statut, $statutsAutorises, true)) {
        $erreurs[] = 'Le statut sélectionné est invalide.';
    }

    /*
    |--------------------------------------------------------------------------
    | Enregistrer les modifications
    |--------------------------------------------------------------------------
    */

    if (empty($erreurs)) {

        try {
            $pdo->beginTransaction();

            /*
             * Vérifier de nouveau les droits au moment de la mise à jour.
             * Cela évite de s'appuyer uniquement sur les contrôles du
             * formulaire exécutés précédemment.
             */

            if ($estSuperAdmin) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM maisons
                    WHERE id = ?
                      AND actif = 1
                    FOR UPDATE
                ");

                $stmt->execute([$maisonValide]);

            } else {

                $stmt = $pdo->prepare("
                    SELECT m.id
                    FROM maisons m
                    INNER JOIN affectations_admins aa
                        ON aa.maison_id = m.id
                    WHERE m.id = ?
                      AND aa.utilisateur_id = ?
                      AND m.actif = 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    $maisonValide,
                    $utilisateurId
                ]);
            }

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException(
                    'Vous n’avez plus accès à la maison sélectionnée.'
                );
            }

            // Si un appartement est sélectionné, revérifier son appartenance.
            if ($appartementValide !== null) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM appartements
                    WHERE id = ?
                      AND maison_id = ?
                      AND (actif = 1 OR id = ?)
                    FOR UPDATE
                ");

                $stmt->execute([
                    $appartementValide,
                    $maisonValide,
                    $travail['appartement_id'] ?? 0
                ]);

                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new RuntimeException(
                        'L’appartement sélectionné n’appartient pas à cette maison ou n’est plus disponible.'
                    );
                }
            }

            // Mettre à jour uniquement le travail accessible.
            if ($estSuperAdmin) {

                $stmt = $pdo->prepare("
                    UPDATE travaux
                    SET
                        maison_id = ?,
                        appartement_id = ?,
                        titre = ?,
                        description = ?,
                        montant = ?,
                        date_travail = ?,
                        statut = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $maisonValide,
                    $appartementValide,
                    $titre,
                    $description !== '' ? $description : null,
                    (float) $montant,
                    $date_travail,
                    $statut,
                    $id
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE travaux
                    SET
                        maison_id = ?,
                        appartement_id = ?,
                        titre = ?,
                        description = ?,
                        montant = ?,
                        date_travail = ?,
                        statut = ?
                    WHERE id = ?
                      AND maison_id = ?
                ");

                $stmt->execute([
                    $maisonValide,
                    $appartementValide,
                    $titre,
                    $description !== '' ? $description : null,
                    (float) $montant,
                    $date_travail,
                    $statut,
                    $id,
                    (int) $travail['maison_id']
                ]);

                if ($stmt->rowCount() === 0) {

                    // rowCount() peut aussi valoir zéro si les données
                    // n'ont pas changé. On vérifie donc l'existence et
                    // l'accès au travail avant de conclure à un échec.
                    $stmt = $pdo->prepare("
                        SELECT t.id
                        FROM travaux t
                        INNER JOIN affectations_admins aa
                            ON aa.maison_id = t.maison_id
                        WHERE t.id = ?
                          AND aa.utilisateur_id = ?
                    ");

                    $stmt->execute([
                        $id,
                        $utilisateurId
                    ]);

                    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                        throw new RuntimeException(
                            'Le travail n’a pas pu être modifié.'
                        );
                    }
                }
            }

            $pdo->commit();

            // Renouveler le jeton après la modification.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header(
                'Location: /ImmoGestion/travaux/index.php?succes=modification'
            );
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erreur modification travail #' . $id . ' : ' . $e->getMessage()
            );

            $erreurs[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Une erreur est survenue lors de la modification. Veuillez réessayer.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Charger les appartements correspondant aux maisons accessibles
|--------------------------------------------------------------------------
*/

if ($estSuperAdmin) {

    $stmt = $pdo->query("
        SELECT a.id, a.maison_id, a.numero
        FROM appartements a
        INNER JOIN maisons m
            ON m.id = a.maison_id
        WHERE a.actif = 1
          AND m.actif = 1
        ORDER BY a.maison_id ASC, a.numero ASC
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT a.id, a.maison_id, a.numero
        FROM appartements a
        INNER JOIN maisons m
            ON m.id = a.maison_id
        INNER JOIN affectations_admins aa
            ON aa.maison_id = m.id
        WHERE aa.utilisateur_id = ?
          AND a.actif = 1
          AND m.actif = 1
        ORDER BY a.maison_id ASC, a.numero ASC
    ");

    $stmt->execute([$utilisateurId]);
}

$appartements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Si l'appartement actuel a été désactivé, l'afficher pour permettre
 * de conserver la valeur existante ou de choisir un autre appartement.
 */
if (
    $travail['appartement_id'] !== null
    && !in_array(
        (int) $travail['appartement_id'],
        array_map(
            'intval',
            array_column($appartements, 'id')
        ),
        true
    )
) {
    $stmt = $pdo->prepare("
        SELECT a.id, a.maison_id, a.numero
        FROM appartements a
        WHERE a.id = ?
          AND a.maison_id = ?
    ");

    $stmt->execute([
        $travail['appartement_id'],
        $travail['maison_id']
    ]);

    $appartementActuel = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appartementActuel) {
        $appartements[] = $appartementActuel;
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h1>Modifier un travail</h1>
        </div>

        <div class="page-content">

            <div class="page-header">
                <div>
                    <h2>Modification des travaux</h2>
                    <p>Modifiez les informations du travail sélectionné.</p>
                </div>

                <a
                    href="/ImmoGestion/travaux/index.php"
                    class="btn btn-secondary"
                >
                    ← Retour
                </a>
            </div>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($erreurs as $erreur): ?>
                            <li>
                                <?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="panel">

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <div class="form-group">
                        <label for="titre">Titre des travaux *</label>

                        <input
                            type="text"
                            id="titre"
                            name="titre"
                            maxlength="150"
                            value="<?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="maison_id">Maison concernée *</label>

                        <select
                            id="maison_id"
                            name="maison_id"
                            required
                        >
                            <option value="">-- Choisir une maison --</option>

                            <?php foreach ($maisons as $maison): ?>
                                <option
                                    value="<?= (int) $maison['id'] ?>"
                                    <?= $maison_id === (string) $maison['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $maison['nom'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="appartement_id">
                            Appartement (facultatif)
                        </label>

                        <select
                            id="appartement_id"
                            name="appartement_id"
                        >
                            <option value="">-- Toute la maison --</option>

                            <?php foreach ($appartements as $appartement): ?>
                                <option
                                    value="<?= (int) $appartement['id'] ?>"
                                    data-maison="<?= (int) $appartement['maison_id'] ?>"
                                    <?= $appartement_id === (string) $appartement['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $appartement['numero'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>

                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                        ><?= htmlspecialchars(
                            $description,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="montant">Montant en MRU *</label>

                        <input
                            type="number"
                            id="montant"
                            name="montant"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                (string) $montant,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="date_travail">Date des travaux *</label>

                        <input
                            type="date"
                            id="date_travail"
                            name="date_travail"
                            value="<?= htmlspecialchars(
                                $date_travail,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="statut">Statut *</label>

                        <select id="statut" name="statut" required>
                            <option
                                value="prevu"
                                <?= $statut === 'prevu' ? 'selected' : '' ?>
                            >
                                Prévu
                            </option>

                            <option
                                value="en_cours"
                                <?= $statut === 'en_cours' ? 'selected' : '' ?>
                            >
                                En cours
                            </option>

                            <option
                                value="termine"
                                <?= $statut === 'termine' ? 'selected' : '' ?>
                            >
                                Terminé
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Enregistrer les modifications
                    </button>

                    <a
                        href="/ImmoGestion/travaux/index.php"
                        class="btn btn-secondary"
                    >
                        Annuler
                    </a>

                </form>

            </div>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const maisonSelect = document.getElementById('maison_id');
    const appartementSelect = document.getElementById('appartement_id');

    function filtrerAppartements() {

        const maisonId = maisonSelect.value;

        Array.from(appartementSelect.options).forEach(function (option) {

            if (option.value === '') {
                option.hidden = false;
                return;
            }

            const correspond = option.dataset.maison === maisonId;

            option.hidden = !correspond;

            if (!correspond && option.selected) {
                appartementSelect.value = '';
            }
        });
    }

    maisonSelect.addEventListener('change', filtrerAppartements);

    filtrerAppartements();

});
</script>
