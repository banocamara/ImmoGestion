<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

$erreurs = [];

$titre = '';
$description = '';
$maison_id = '';
$appartement_id = '';
$montant = '';
$date_travail = date('Y-m-d');
$statut = 'prevu';

// Récupérer les maisons actives
$stmt = $pdo->query("
    SELECT id, nom
    FROM maisons
    WHERE actif = 1
    ORDER BY nom ASC
");
$maisons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les appartements actifs
$stmt = $pdo->query("
    SELECT id, maison_id, numero
    FROM appartements
    WHERE actif = 1
    ORDER BY maison_id ASC, numero ASC
");
$appartements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $maison_id = (int) ($_POST['maison_id'] ?? 0);
    $appartement_id = ($_POST['appartement_id'] ?? '') !== ''
        ? (int) $_POST['appartement_id']
        : null;
    $montant = trim($_POST['montant'] ?? '');
    $date_travail = $_POST['date_travail'] ?? '';
    $statut = $_POST['statut'] ?? 'prevu';

    // Vérifier le titre
    if ($titre === '') {
        $erreurs[] = 'Le titre des travaux est obligatoire.';
    }

    // Vérifier la maison
    $stmt = $pdo->prepare("
        SELECT id
        FROM maisons
        WHERE id = ? AND actif = 1
    ");
    $stmt->execute([$maison_id]);

    if (!$stmt->fetch()) {
        $erreurs[] = 'Veuillez sélectionner une maison valide.';
    }

    // Vérifier l'appartement sélectionné
    if ($appartement_id !== null) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM appartements
            WHERE id = ?
              AND maison_id = ?
              AND actif = 1
        ");
        $stmt->execute([$appartement_id, $maison_id]);

        if (!$stmt->fetch()) {
            $erreurs[] = 'L’appartement sélectionné ne correspond pas à cette maison.';
        }
    }

    // Vérifier le montant
    if ($montant === '' || !is_numeric($montant) || (float) $montant < 0) {
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

    // Enregistrer les travaux
    if (empty($erreurs)) {
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
            $maison_id,
            $appartement_id,
            $_SESSION['utilisateur_id'],
            $titre,
            $description !== '' ? $description : null,
            (float) $montant,
            $date_travail,
            $statut
        ]);

        header('Location: /ImmoGestion/travaux/index.php?succes=ajout');
        exit;
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
                    <p>Renseignez les informations concernant les travaux immobiliers.</p>
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

                <form method="POST" action="">

                    <div class="form-group">
                        <label for="titre">Titre des travaux *</label>
                        <input
                            type="text"
                            id="titre"
                            name="titre"
                            maxlength="150"
                            value="<?= htmlspecialchars($titre) ?>"
                            placeholder="Ex. : Réparation de plomberie"
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
                                    <?= (string) $maison_id === (string) $maison['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($maison['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="appartement_id">
                            Appartement concerné (facultatif)
                        </label>

                        <select id="appartement_id" name="appartement_id">
                            <option value="">-- Toute la maison --</option>

                            <?php foreach ($appartements as $appartement): ?>
                                <option
                                    value="<?= (int) $appartement['id'] ?>"
                                    data-maison="<?= (int) $appartement['maison_id'] ?>"
                                    <?= (string) $appartement_id === (string) $appartement['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($appartement['numero']) ?>
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
                            placeholder="Décrivez les travaux à réaliser..."
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
                            value="<?= htmlspecialchars($montant) ?>"
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
                        Enregistrer les travaux
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

