<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreurs = [];

$appartement_id = '';
$locataire_id = '';
$date_debut = date('Y-m-d');
$date_fin = '';
$loyer_mensuel = '';
$jour_echeance = '5';

// Récupérer les appartements actifs
$stmt = $pdo->query("
    SELECT
        appartements.id,
        appartements.numero,
        appartements.loyer_mensuel,
        maisons.nom AS nom_maison
    FROM appartements
    INNER JOIN maisons ON appartements.maison_id = maisons.id
    WHERE appartements.actif = 1
      AND maisons.actif = 1
      AND appartements.statut = 'libre'
    ORDER BY maisons.nom, appartements.numero
");
$appartements = $stmt->fetchAll();

// Récupérer les locataires actifs
$stmt = $pdo->query("
    SELECT id, nom, prenom
    FROM locataires
    WHERE actif = 1
    ORDER BY nom, prenom
");
$locataires = $stmt->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $erreurs[] = 'Le formulaire a expiré. Rechargez la page et réessayez.';
    }

    $appartement_id = $_POST['appartement_id'] ?? '';
    $locataire_id = $_POST['locataire_id'] ?? '';
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    $loyer_mensuel = trim($_POST['loyer_mensuel'] ?? '');
    $jour_echeance = $_POST['jour_echeance'] ?? '5';

    $appartement_id = filter_var($appartement_id, FILTER_VALIDATE_INT);
    $locataire_id = filter_var($locataire_id, FILTER_VALIDATE_INT);
    $jour_echeance = filter_var($jour_echeance, FILTER_VALIDATE_INT);

    if (!$appartement_id || $appartement_id <= 0) {
        $erreurs[] = 'Veuillez sélectionner un appartement.';
    }

    if (!$locataire_id || $locataire_id <= 0) {
        $erreurs[] = 'Veuillez sélectionner un locataire.';
    }

    $dateDebutValide = DateTime::createFromFormat('!Y-m-d', $date_debut);

    if (!$dateDebutValide || $dateDebutValide->format('Y-m-d') !== $date_debut) {
        $erreurs[] = 'La date de début est invalide.';
    }

    if ($date_fin !== '') {
        $dateFinValide = DateTime::createFromFormat('!Y-m-d', $date_fin);

        if (
            !$dateFinValide ||
            $dateFinValide->format('Y-m-d') !== $date_fin
        ) {
            $erreurs[] = 'La date de fin est invalide.';
        } elseif (
            isset($dateDebutValide) &&
            $dateFinValide <= $dateDebutValide
        ) {
            $erreurs[] = 'La date de fin doit être postérieure à la date de début.';
        }
    }

    if (
        $loyer_mensuel === '' ||
        !is_numeric($loyer_mensuel) ||
        (float) $loyer_mensuel <= 0
    ) {
        $erreurs[] = 'Le loyer mensuel doit être un montant positif.';
    }

    if (!$jour_echeance || $jour_echeance < 1 || $jour_echeance > 31) {
        $erreurs[] = 'Le jour d’échéance doit être compris entre 1 et 31.';
    }

    // Vérifier que l'appartement est toujours disponible
    if ($appartement_id && $appartement_id > 0) {
        $stmt = $pdo->prepare("
            SELECT appartements.id
            FROM appartements
            INNER JOIN maisons ON maisons.id = appartements.maison_id
            WHERE appartements.id = :id
              AND appartements.actif = 1
              AND maisons.actif = 1
              AND appartements.statut = 'libre'
        ");
        $stmt->execute(['id' => $appartement_id]);

        if (!$stmt->fetch()) {
            $erreurs[] = 'Cet appartement n’est plus disponible.';
        }
    }

    // Vérifier que le locataire est toujours actif
    if ($locataire_id && $locataire_id > 0) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM locataires
            WHERE id = :id AND actif = 1
        ");
        $stmt->execute(['id' => $locataire_id]);

        if (!$stmt->fetch()) {
            $erreurs[] = 'Ce locataire est introuvable ou désactivé.';
        }
    }

    // Vérifier qu'il n'existe pas déjà une location active pour l'appartement
    if ($appartement_id && $appartement_id > 0) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM locations
            WHERE appartement_id = :appartement_id
              AND statut = 'active'
            LIMIT 1
        ");
        $stmt->execute(['appartement_id' => $appartement_id]);

        if ($stmt->fetch()) {
            $erreurs[] = 'Cet appartement possède déjà une location active.';
        }
    }

    // Enregistrer la location
    if (empty($erreurs)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO locations (
                    appartement_id,
                    locataire_id,
                    date_debut,
                    date_fin,
                    loyer_mensuel,
                    jour_echeance,
                    statut
                ) VALUES (
                    :appartement_id,
                    :locataire_id,
                    :date_debut,
                    :date_fin,
                    :loyer_mensuel,
                    :jour_echeance,
                    'active'
                )
            ");

            $stmt->execute([
                'appartement_id' => $appartement_id,
                'locataire_id' => $locataire_id,
                'date_debut' => $date_debut,
                'date_fin' => $date_fin !== '' ? $date_fin : null,
                'loyer_mensuel' => $loyer_mensuel,
                'jour_echeance' => $jour_echeance
            ]);

            // Marquer l'appartement comme occupé
            $stmt = $pdo->prepare("
                UPDATE appartements
                SET statut = 'occupe'
                WHERE id = :id
            ");
            $stmt->execute(['id' => $appartement_id]);

            $pdo->commit();

            $_SESSION['message'] = 'Location enregistrée avec succès.';
            header('Location: index.php');
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $erreurs[] = 'Une erreur est survenue lors de l’enregistrement. Vérifiez les données et réessayez.';
        }
    }
}

$titrePage = 'Nouvelle location';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="main-content">

    <header class="topbar">
        <h1>Nouvelle location</h1>
    </header>

    <section class="page-content">

        <div class="page-heading">
            <h2>Créer un contrat de location</h2>
            <p>Associez un locataire à un appartement disponible.</p>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div class="alert">
                <ul>
                    <?php foreach ($erreurs as $erreur): ?>
                        <li>
                            <?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($appartements)): ?>
            <div class="alert">
                Aucun appartement libre et actif n'est disponible.
                Ajoutez un appartement ou vérifiez son statut.
            </div>
        <?php endif; ?>

        <?php if (empty($locataires)): ?>
            <div class="alert">
                Aucun locataire actif n'est disponible.
                Enregistrez d'abord un locataire.
            </div>
        <?php endif; ?>

        <div class="panel">

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-group">
                    <label for="appartement_id">Appartement *</label>

                    <select id="appartement_id" name="appartement_id" required>
                        <option value="">-- Choisir un appartement --</option>

                        <?php foreach ($appartements as $appartement): ?>
                            <option
                                value="<?= (int) $appartement['id'] ?>"
                                data-loyer="<?= htmlspecialchars((string) $appartement['loyer_mensuel'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= (string) $appartement_id === (string) $appartement['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $appartement['nom_maison'] . ' - Appartement ' . $appartement['numero'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="locataire_id">Locataire *</label>

                    <select id="locataire_id" name="locataire_id" required>
                        <option value="">-- Choisir un locataire --</option>

                        <?php foreach ($locataires as $locataire): ?>
                            <option
                                value="<?= (int) $locataire['id'] ?>"
                                <?= (string) $locataire_id === (string) $locataire['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $locataire['nom'] . ' ' . $locataire['prenom'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="loyer_mensuel">Loyer mensuel *</label>

                    <input
                        type="number"
                        id="loyer_mensuel"
                        name="loyer_mensuel"
                        min="0.01"
                        step="0.01"
                        value="<?= htmlspecialchars((string) $loyer_mensuel, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="jour_echeance">Jour d'échéance *</label>

                    <select id="jour_echeance" name="jour_echeance" required>
                        <?php for ($jour = 1; $jour <= 31; $jour++): ?>
                            <option
                                value="<?= $jour ?>"
                                <?= (string) $jour_echeance === (string) $jour ? 'selected' : '' ?>
                            >
                                Le <?= $jour ?> du mois
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_debut">Date de début *</label>

                    <input
                        type="date"
                        id="date_debut"
                        name="date_debut"
                        value="<?= htmlspecialchars($date_debut, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="date_fin">Date de fin (facultative)</label>

                    <input
                        type="date"
                        id="date_fin"
                        name="date_fin"
                        value="<?= htmlspecialchars($date_fin, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                    <?= empty($appartements) || empty($locataires) ? 'disabled' : '' ?>
                >
                    Enregistrer la location
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Annuler
                </a>

            </form>

        </div>

    </section>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</main>

</div>

<script>
document.getElementById('appartement_id').addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    const loyer = option.getAttribute('data-loyer');

    if (loyer !== null && loyer !== '') {
        document.getElementById('loyer_mensuel').value = loyer;
    }
});
</script>
