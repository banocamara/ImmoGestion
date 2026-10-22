<?php

require_once __DIR__ . '/../includes/auth.php';

exigerConnexion();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$estSuperAdmin = estSuperAdmin();
$utilisateurId = (int) $_SESSION['utilisateur_id'];

$erreurs = [];

$titre = '';
$description = '';
$maison_id = '';
$appartement_id = '';
$montant = '';
$date_travail = date('Y-m-d');
$statut = 'prevu';

// Générer le jeton CSRF.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les maisons accessibles.
if ($estSuperAdmin) {

    $stmt = $pdo->query("
        SELECT id, nom
        FROM maisons
        WHERE actif = 1
        ORDER BY nom ASC
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT DISTINCT m.id, m.nom
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

// Récupérer les appartements actifs des maisons accessibles.
if ($estSuperAdmin) {

    $stmt = $pdo->query("
        SELECT a.id, a.maison_id, a.numero
        FROM appartements a
        INNER JOIN maisons m ON m.id = a.maison_id
        WHERE a.actif = 1
          AND m.actif = 1
        ORDER BY m.nom ASC, a.numero ASC
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT DISTINCT a.id, a.maison_id, a.numero
        FROM appartements a
        INNER JOIN maisons m ON m.id = a.maison_id
        INNER JOIN affectations_admins aa
            ON aa.maison_id = m.id
        WHERE aa.utilisateur_id = ?
          AND a.actif = 1
          AND m.actif = 1
        ORDER BY m.nom ASC, a.numero ASC
    ");

    $stmt->execute([$utilisateurId]);
}

$appartements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF.
    $jeton = $_POST['csrf_token'] ?? '';

    if (
        !is_string($jeton) ||
        !hash_equals($_SESSION['csrf_token'], $jeton)
    ) {
        $erreurs[] =
            'La vérification de sécurité a échoué. Rechargez la page.';
    }

    // Récupérer les données en vérifiant leur type.
    $titreRecu = $_POST['titre'] ?? '';
    $descriptionRecue = $_POST['description'] ?? '';
    $maisonRecu = $_POST['maison_id'] ?? '';
    $appartementRecu = $_POST['appartement_id'] ?? '';
    $montantRecu = $_POST['montant'] ?? '';
    $dateRecue = $_POST['date_travail'] ?? '';
    $statutRecu = $_POST['statut'] ?? '';

    if (
        !is_string($titreRecu) ||
        !is_string($descriptionRecue) ||
        !is_string($maisonRecu) ||
        !is_string($appartementRecu) ||
        !is_string($montantRecu) ||
        !is_string($dateRecue) ||
        !is_string($statutRecu)
    ) {
        $erreurs[] = 'Les données envoyées sont invalides.';

    } else {

        $titre = trim($titreRecu);
        $description = trim($descriptionRecue);
        $maison_id = $maisonRecu;
        $appartement_id = $appartementRecu;
        $montant = trim($montantRecu);
        $date_travail = $dateRecue;
        $statut = $statutRecu;

        // Vérifier le titre.
        if ($titre === '') {
            $erreurs[] = 'Le titre des travaux est obligatoire.';
        } elseif (mb_strlen($titre, 'UTF-8') > 150) {
            $erreurs[] =
                'Le titre ne doit pas dépasser 150 caractères.';
        }

        // Vérifier la longueur de la description.
        if (mb_strlen($description, 'UTF-8') > 5000) {
            $erreurs[] =
                'La description ne doit pas dépasser 5000 caractères.';
        }

        // Vérifier l'identifiant de la maison.
        $maisonIdValide = filter_var(
            $maison_id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($maisonIdValide === false) {
            $erreurs[] = 'Veuillez sélectionner une maison valide.';
        }

        // Vérifier l'identifiant de l'appartement.
        $appartementIdValide = null;

        if ($appartement_id !== '') {

            $appartementIdValide = filter_var(
                $appartement_id,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($appartementIdValide === false) {
                $erreurs[] = 'L’appartement sélectionné est invalide.';
            }
        }

        // Vérifier le montant.
        if (
            $montant === '' ||
            !is_numeric($montant) ||
            !is_finite((float) $montant) ||
            (float) $montant < 0
        ) {
            $erreurs[] =
                'Veuillez saisir un montant valide, positif ou nul.';

        } elseif ((float) $montant > 999999999999.99) {
            $erreurs[] = 'Le montant saisi est trop élevé.';
        }

        // Vérifier la date.
        $date = DateTime::createFromFormat('!Y-m-d', $date_travail);

        $dateValide = $date
            && $date->format('Y-m-d') === $date_travail;

        if (!$dateValide) {
            $erreurs[] = 'Veuillez saisir une date valide.';
        }

        // Vérifier le statut.
        $statutsAutorises = ['prevu', 'en_cours', 'termine'];

        if (!in_array($statut, $statutsAutorises, true)) {
            $erreurs[] = 'Le statut sélectionné est invalide.';
        }

        // Vérifier la maison et les droits de l'utilisateur.
        if ($maisonIdValide !== false) {

            if ($estSuperAdmin) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM maisons
                    WHERE id = ?
                      AND actif = 1
                    LIMIT 1
                ");

                $stmt->execute([$maisonIdValide]);

            } else {

                $stmt = $pdo->prepare("
                    SELECT m.id
                    FROM maisons m
                    INNER JOIN affectations_admins aa
                        ON aa.maison_id = m.id
                    WHERE m.id = ?
                      AND aa.utilisateur_id = ?
                      AND m.actif = 1
                    LIMIT 1
                ");

                $stmt->execute([
                    $maisonIdValide,
                    $utilisateurId
                ]);
            }

            if (!$stmt->fetchColumn()) {
                $erreurs[] =
                    'Cette maison est inactive ou ne vous est pas attribuée.';
            }
        }

        // Vérifier l'appartement et son appartenance à la maison.
        if (
            $appartementIdValide !== null &&
            $appartementIdValide !== false &&
            $maisonIdValide !== false
        ) {

            $stmt = $pdo->prepare("
                SELECT a.id
                FROM appartements a
                INNER JOIN maisons m ON m.id = a.maison_id
                WHERE a.id = ?
                  AND a.maison_id = ?
                  AND a.actif = 1
                  AND m.actif = 1
            ");

            $stmt->execute([
                $appartementIdValide,
                $maisonIdValide
            ]);

            if (!$stmt->fetchColumn()) {
                $erreurs[] =
                    'L’appartement ne correspond pas à cette maison '
                    . 'ou est inactif.';
            }
        }
    }

    // Enregistrer les travaux si aucune erreur.
    if (empty($erreurs)) {

        try {

            $pdo->beginTransaction();

            /*
             * Revérifier la maison pendant la transaction.
             * Cette vérification évite de se fier uniquement
             * aux données affichées dans le formulaire.
             */
            if ($estSuperAdmin) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM maisons
                    WHERE id = ?
                      AND actif = 1
                    FOR UPDATE
                ");

                $stmt->execute([$maisonIdValide]);

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
                    $maisonIdValide,
                    $utilisateurId
                ]);
            }

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Cette maison n’est plus accessible ou est inactive.'
                );
            }

            // Revérifier l'appartement si nécessaire.
            if ($appartementIdValide !== null) {

                $stmt = $pdo->prepare("
                    SELECT a.id
                    FROM appartements a
                    INNER JOIN maisons m ON m.id = a.maison_id
                    WHERE a.id = ?
                      AND a.maison_id = ?
                      AND a.actif = 1
                      AND m.actif = 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    $appartementIdValide,
                    $maisonIdValide
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'L’appartement sélectionné est devenu inaccessible.'
                    );
                }
            }

            // Insérer le travail.
            $stmt = $pdo->prepare("
                INSERT INTO travaux (
                    maison_id,
                    appartement_id,
                    enregistre_par,
                    titre,
                    description,
                    montant,
                    date_travail,
                    statut
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $maisonIdValide,
                $appartementIdValide,
                $utilisateurId,
                $titre,
                $description !== '' ? $description : null,
                (float) $montant,
                $date_travail,
                $statut
            ]);

            $pdo->commit();

            // Renouveler le jeton CSRF.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header(
                'Location: /ImmoGestion/travaux/index.php?succes=ajout'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erreur ajout travaux : ' . $e->getMessage()
            );

            if ($e instanceof RuntimeException) {
                $erreurs[] = $e->getMessage();
            } else {
                $erreurs[] =
                    'Une erreur est survenue pendant l’enregistrement. '
                    . 'Veuillez réessayer.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h1>Ajouter un travail</h1>
        </div>

        <div class="page-content">

            <div class="page-header">

                <div>
                    <h2>Enregistrer des travaux</h2>

                    <p>
                        <?php if ($estSuperAdmin): ?>
                            Enregistrez des travaux pour toutes les maisons actives.
                        <?php else: ?>
                            Enregistrez des travaux uniquement pour vos maisons attribuées.
                        <?php endif; ?>
                    </p>
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
                                <?= htmlspecialchars(
                                    $erreur,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            <?php endif; ?>

            <?php if (empty($maisons)): ?>

                <div class="alert alert-danger">
                    Aucune maison active ne vous est attribuée.
                    Vous ne pouvez donc pas enregistrer de travaux.
                    Contactez le super-administrateur si nécessaire.
                </div>

            <?php else: ?>

                <div class="panel">

                    <form method="POST" action="">

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
                                value="<?= htmlspecialchars(
                                    $titre,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="Ex. : Réparation de plomberie"
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

                                <option value="">
                                    -- Choisir une maison --
                                </option>

                                <?php foreach ($maisons as $maison): ?>

                                    <option
                                        value="<?= (int) $maison['id'] ?>"
                                        <?= (string) $maison_id === (string) $maison['id']
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
                                Appartement concerné (facultatif)
                            </label>

                            <select
                                id="appartement_id"
                                name="appartement_id"
                            >

                                <option value="">
                                    -- Toute la maison --
                                </option>

                                <?php foreach ($appartements as $appartement): ?>

                                    <option
                                        value="<?= (int) $appartement['id'] ?>"
                                        data-maison="<?= (int) $appartement['maison_id'] ?>"
                                        <?= (string) $appartement_id === (string) $appartement['id']
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

                            <small>
                                Si les travaux concernent toute la maison,
                                laissez ce champ vide.
                            </small>

                        </div>

                        <div class="form-group">

                            <label for="description">Description</label>

                            <textarea
                                id="description"
                                name="description"
                                rows="4"
                                maxlength="5000"
                                placeholder="Décrivez les travaux à réaliser..."
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
                                    $montant,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="Ex. : 25000"
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

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Enregistrer les travaux
                        </button>

                        <a
                            href="/ImmoGestion/travaux/index.php"
                            class="btn btn-secondary"
                        >
                            Annuler
                        </a>

                    </form>

                </div>

            <?php endif; ?>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const maisonSelect = document.getElementById('maison_id');
    const appartementSelect = document.getElementById('appartement_id');

    if (!maisonSelect || !appartementSelect) {
        return;
    }

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
