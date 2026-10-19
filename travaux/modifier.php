<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

$erreurs = [];

// Vérifier l'identifiant du travail
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: /ImmoGestion/travaux/index.php');
    exit;
}

// Récupérer le travail à modifier
$stmt = $pdo->prepare("
    SELECT *
    FROM travaux
    WHERE id = ?
");
$stmt->execute([$id]);
$travail = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$travail) {
    header('Location: /ImmoGestion/travaux/index.php');
    exit;
}

// Valeurs initiales
$titre = $travail['titre'];
$description = $travail['description'] ?? '';
$maison_id = (string) $travail['maison_id'];
$appartement_id = $travail['appartement_id'] !== null
    ? (string) $travail['appartement_id']
    : '';
$montant = $travail['montant'];
$date_travail = $travail['date_travail'];
$statut = $travail['statut'];

// Charger les maisons actives et la maison actuelle
$stmt = $pdo->query("
    SELECT id, nom
    FROM maisons
    WHERE actif = 1
    ORDER BY nom ASC
");
$maisons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Charger les appartements actifs et l'appartement actuel
$stmt = $pdo->prepare("
    SELECT id, maison_id, numero
    FROM appartements
    WHERE actif = 1 OR id = ?
    ORDER BY maison_id ASC, numero ASC
");
$stmt->execute([$travail['appartement_id']]);
$appartements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $maison_id = (string) ($_POST['maison_id'] ?? '');
    $appartement_id = ($_POST['appartement_id'] ?? '') !== ''
        ? (string) $_POST['appartement_id']
        : '';
    $montant = trim($_POST['montant'] ?? '');
    $date_travail = $_POST['date_travail'] ?? '';
    $statut = $_POST['statut'] ?? '';

    // Vérifier le titre
    if ($titre === '' || mb_strlen($titre) > 150) {
        $erreurs[] = 'Le titre est obligatoire et ne doit pas dépasser 150 caractères.';
    }

    // Vérifier la maison
    $maisonValide = filter_var($maison_id, FILTER_VALIDATE_INT);

    if (!$maisonValide) {
        $erreurs[] = 'Veuillez sélectionner une maison valide.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM maisons
            WHERE id = ? AND actif = 1
        ");
        $stmt->execute([$maisonValide]);

        if (!$stmt->fetch()) {
            $erreurs[] = 'La maison sélectionnée est invalide ou inactive.';
        }
    }

    // Vérifier l'appartement et son appartenance à la maison
    $appartementValide = null;

    if ($appartement_id !== '') {
        $appartementValide = filter_var(
            $appartement_id,
            FILTER_VALIDATE_INT
        );

        if (!$appartementValide) {
            $erreurs[] = 'Veuillez sélectionner un appartement valide.';
        } else {
            $stmt = $pdo->prepare("
                SELECT id
                FROM appartements
                WHERE id = ?
                  AND maison_id = ?
                  AND (actif = 1 OR id = ?)
            ");
            $stmt->execute([
                $appartementValide,
                $maison_id,
                $travail['appartement_id']
            ]);

            if (!$stmt->fetch()) {
                $erreurs[] = 'L’appartement doit appartenir à la maison sélectionnée.';
            }
        }
    }

    // Vérifier le montant
    if (
        $montant === ''
        || !is_numeric($montant)
        || !is_finite((float) $montant)
        || (float) $montant < 0
    ) {
        $erreurs[] = 'Veuillez saisir un montant valide et positif ou nul.';
    }

    // Vérifier la date
    $dateValide = false;

    if ($date_travail !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $date_travail);
        $dateValide = $date && $date->format('Y-m-d') === $date_travail;
    }

    if (!$dateValide) {
        $erreurs[] = 'Veuillez saisir une date valide.';
    }

    // Vérifier le statut
    $statutsAutorises = ['prevu', 'en_cours', 'termine'];

    if (!in_array($statut, $statutsAutorises, true)) {
        $erreurs[] = 'Le statut sélectionné est invalide.';
    }

    // Enregistrer les modifications
    if (empty($erreurs)) {
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
            (int) $maison_id,
            $appartementValide,
            $titre,
            $description !== '' ? $description : null,
            (float) $montant,
            $date_travail,
            $statut,
            $id
        ]);

        header('Location: /ImmoGestion/travaux/index.php?succes=modification');
        exit;
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

                <a href="/ImmoGestion/travaux/index.php" class="btn btn-secondary">
                    ← Retour
                </a>
            </div>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($erreurs as $erreur): ?>
                            <li><?= htmlspecialchars($erreur) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="panel">

                <form method="POST">

                    <div class="form-group">
                        <label for="titre">Titre des travaux *</label>
                        <input
                            type="text"
                            id="titre"
                            name="titre"
                            maxlength="150"
                            value="<?= htmlspecialchars($titre) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="maison_id">Maison concernée *</label>
                        <select id="maison_id" name="maison_id" required>
                            <option value="">-- Choisir une maison --</option>

                            <?php foreach ($maisons as $maison): ?>
                                <option
                                    value="<?= (int) $maison['id'] ?>"
                                    <?= $maison_id === (string) $maison['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($maison['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="appartement_id">Appartement (facultatif)</label>
                        <select id="appartement_id" name="appartement_id">
                            <option value="">-- Toute la maison --</option>

                            <?php foreach ($appartements as $appartement): ?>
                                <option
                                    value="<?= (int) $appartement['id'] ?>"
                                    data-maison="<?= (int) $appartement['maison_id'] ?>"
                                    <?= $appartement_id === (string) $appartement['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($appartement['numero']) ?>
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
                        ><?= htmlspecialchars($description) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="montant">Montant en MRU *</label>
                        <input
                            type="number"
                            id="montant"
                            name="montant"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars((string) $montant) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="date_travail">Date des travaux *</label>
                        <input
                            type="date"
                            id="date_travail"
                            name="date_travail"
                            value="<?= htmlspecialchars($date_travail) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="statut">Statut *</label>
                        <select id="statut" name="statut" required>
                            <option value="prevu" <?= $statut === 'prevu' ? 'selected' : '' ?>>
                                Prévu
                            </option>
                            <option value="en_cours" <?= $statut === 'en_cours' ? 'selected' : '' ?>>
                                En cours
                            </option>
                            <option value="termine" <?= $statut === 'termine' ? 'selected' : '' ?>>
                                Terminé
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Enregistrer les modifications
                    </button>

                    <a href="/ImmoGestion/travaux/index.php" class="btn btn-secondary">
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

