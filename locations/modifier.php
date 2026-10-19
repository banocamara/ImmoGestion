<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

$titrePage = 'Modifier une location';

// Vérifier l'identifiant de la location
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: /ImmoGestion/locations/index.php');
    exit;
}

// Créer le jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$messageErreur = '';

// Récupérer la location existante
$stmt = $pdo->prepare("
    SELECT *
    FROM locations
    WHERE id = :id
");
$stmt->execute(['id' => $id]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: /ImmoGestion/locations/index.php');
    exit;
}

// Récupérer les appartements et leurs maisons
$stmt = $pdo->prepare("
    SELECT
        appartements.id,
        appartements.numero,
        appartements.loyer_mensuel,
        appartements.statut AS statut_appartement,
        maisons.nom AS nom_maison
    FROM appartements
    INNER JOIN maisons ON appartements.maison_id = maisons.id
    WHERE appartements.actif = 1
      AND maisons.actif = 1
    ORDER BY maisons.nom, appartements.numero
");
$stmt->execute();
$appartements = $stmt->fetchAll();

// Récupérer les locataires actifs
$stmt = $pdo->prepare("
    SELECT id, nom, prenom
    FROM locataires
    WHERE actif = 1
    ORDER BY nom, prenom
");
$stmt->execute();
$locataires = $stmt->fetchAll();

// Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $messageErreur = 'La session du formulaire a expiré. Rechargez la page et réessayez.';
    } else {

        $appartementId = filter_input(
            INPUT_POST,
            'appartement_id',
            FILTER_VALIDATE_INT
        );

        $locataireId = filter_input(
            INPUT_POST,
            'locataire_id',
            FILTER_VALIDATE_INT
        );

        $dateDebut = trim($_POST['date_debut'] ?? '');
        $dateFin = trim($_POST['date_fin'] ?? '');
        $loyerMensuel = trim($_POST['loyer_mensuel'] ?? '');
        $jourEcheance = filter_input(
            INPUT_POST,
            'jour_echeance',
            FILTER_VALIDATE_INT
        );
        $statut = $_POST['statut'] ?? '';

        // Vérifier les champs obligatoires
        if (
            !$appartementId ||
            !$locataireId ||
            $dateDebut === '' ||
            $loyerMensuel === '' ||
            !is_numeric($loyerMensuel) ||
            (float) $loyerMensuel <= 0 ||
            !$jourEcheance ||
            $jourEcheance < 1 ||
            $jourEcheance > 31
        ) {
            $messageErreur = 'Veuillez remplir correctement tous les champs obligatoires.';
        } elseif (
            !in_array($statut, ['active', 'terminee', 'resiliee'], true)
        ) {
            $messageErreur = 'Le statut sélectionné est invalide.';
        } elseif (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) ||
            !checkdate(
                (int) substr($dateDebut, 5, 2),
                (int) substr($dateDebut, 8, 2),
                (int) substr($dateDebut, 0, 4)
            )
        ) {
            $messageErreur = 'La date de début est invalide.';
        } elseif (
            $dateFin !== '' &&
            (
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin) ||
                !checkdate(
                    (int) substr($dateFin, 5, 2),
                    (int) substr($dateFin, 8, 2),
                    (int) substr($dateFin, 0, 4)
                )
            )
        ) {
            $messageErreur = 'La date de fin est invalide.';
        } elseif ($dateFin !== '' && $dateFin < $dateDebut) {
            $messageErreur = 'La date de fin ne peut pas précéder la date de début.';
        } else {

            try {
                $pdo->beginTransaction();

                // Vérifier l'appartement et verrouiller sa ligne
                $stmt = $pdo->prepare("
                    SELECT appartements.id, appartements.statut
                    FROM appartements
                    INNER JOIN maisons
                        ON maisons.id = appartements.maison_id
                    WHERE appartements.id = :id
                      AND appartements.actif = 1
                      AND maisons.actif = 1
                    FOR UPDATE
                ");
                $stmt->execute(['id' => $appartementId]);
                $appartement = $stmt->fetch();

                if (!$appartement) {
                    throw new Exception('L’appartement sélectionné est introuvable ou inactif.');
                }

                // Vérifier que le locataire existe et est actif
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM locataires
                    WHERE id = :id AND actif = 1
                ");
                $stmt->execute(['id' => $locataireId]);

                if (!$stmt->fetch()) {
                    throw new Exception('Le locataire sélectionné est introuvable ou inactif.');
                }

                // Une seule location active par appartement
                if ($statut === 'active') {
                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM locations
                        WHERE appartement_id = :appartement_id
                          AND statut = 'active'
                          AND id <> :location_id
                        LIMIT 1
                    ");
                    $stmt->execute([
                        'appartement_id' => $appartementId,
                        'location_id' => $id
                    ]);

                    if ($stmt->fetch()) {
                        throw new Exception('Cet appartement possède déjà une autre location active.');
                    }
                }

                // Mettre à jour la location
                $stmt = $pdo->prepare("
                    UPDATE locations
                    SET
                        appartement_id = :appartement_id,
                        locataire_id = :locataire_id,
                        date_debut = :date_debut,
                        date_fin = :date_fin,
                        loyer_mensuel = :loyer_mensuel,
                        jour_echeance = :jour_echeance,
                        statut = :statut,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    'appartement_id' => $appartementId,
                    'locataire_id' => $locataireId,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin !== '' ? $dateFin : null,
                    'loyer_mensuel' => $loyerMensuel,
                    'jour_echeance' => $jourEcheance,
                    'statut' => $statut,
                    'id' => $id
                ]);

                // Recalculer le statut des appartements concernés
                $appartementsAActualiser = array_unique([
                    (int) $location['appartement_id'],
                    (int) $appartementId
                ]);

                foreach ($appartementsAActualiser as $idAppartement) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM locations
                        WHERE appartement_id = :appartement_id
                          AND statut = 'active'
                    ");
                    $stmt->execute([
                        'appartement_id' => $idAppartement
                    ]);

                    $aUneLocationActive = (int) $stmt->fetchColumn() > 0;

                    $stmt = $pdo->prepare("
                        UPDATE appartements
                        SET statut = :statut, updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'statut' => $aUneLocationActive ? 'occupe' : 'libre',
                        'id' => $idAppartement
                    ]);
                }

                $pdo->commit();

                $_SESSION['message_succes'] = 'La location a été modifiée avec succès.';
                header('Location: /ImmoGestion/locations/index.php');
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($e instanceof PDOException) {
                    $messageErreur = 'Une erreur est survenue lors de la modification. Vérifiez les données et réessayez.';
                } else {
                    $messageErreur = $e->getMessage();
                }
            }
        }

        // Conserver les valeurs saisies en cas d'erreur
        $location['appartement_id'] = $appartementId ?: $location['appartement_id'];
        $location['locataire_id'] = $locataireId ?: $location['locataire_id'];
        $location['date_debut'] = $dateDebut;
        $location['date_fin'] = $dateFin;
        $location['loyer_mensuel'] = $loyerMensuel;
        $location['jour_echeance'] = $jourEcheance ?: $location['jour_echeance'];
        $location['statut'] = $statut;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h2>Modifier une location</h2>
            <a href="/ImmoGestion/locations/index.php" class="btn btn-secondary">
                Retour à la liste
            </a>
        </div>

        <div class="page-content">

            <?php if ($messageErreur !== ''): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($messageErreur, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h3>Informations de la location</h3>
                </div>

                <div class="panel-body">

                    <form method="POST" action="">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <div class="form-group">
                            <label for="appartement_id">Appartement *</label>

                            <select
                                name="appartement_id"
                                id="appartement_id"
                                class="form-control"
                                required
                            >
                                <option value="">-- Choisir un appartement --</option>

                                <?php foreach ($appartements as $appartement): ?>
                                    <option
                                        value="<?= (int) $appartement['id'] ?>"
                                        data-loyer="<?= htmlspecialchars((string) $appartement['loyer_mensuel'], ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (int) $location['appartement_id'] === (int) $appartement['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($appartement['nom_maison'], ENT_QUOTES, 'UTF-8') ?>
                                        — Appartement
                                        <?= htmlspecialchars($appartement['numero'], ENT_QUOTES, 'UTF-8') ?>
                                        (<?= htmlspecialchars($appartement['statut_appartement'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="locataire_id">Locataire *</label>

                            <select
                                name="locataire_id"
                                id="locataire_id"
                                class="form-control"
                                required
                            >
                                <option value="">-- Choisir un locataire --</option>

                                <?php foreach ($locataires as $locataire): ?>
                                    <option
                                        value="<?= (int) $locataire['id'] ?>"
                                        <?= (int) $location['locataire_id'] === (int) $locataire['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($locataire['nom'] . ' ' . $locataire['prenom'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="date_debut">Date de début *</label>
                            <input
                                type="date"
                                name="date_debut"
                                id="date_debut"
                                class="form-control"
                                value="<?= htmlspecialchars((string) $location['date_debut'], ENT_QUOTES, 'UTF-8') ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="date_fin">Date de fin</label>
                            <input
                                type="date"
                                name="date_fin"
                                id="date_fin"
                                class="form-control"
                                value="<?= htmlspecialchars((string) ($location['date_fin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            >
                            <small>Laissez vide si la location n'a pas de date de fin prévue.</small>
                        </div>

                        <div class="form-group">
                            <label for="loyer_mensuel">Loyer mensuel *</label>
                            <input
                                type="number"
                                name="loyer_mensuel"
                                id="loyer_mensuel"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                value="<?= htmlspecialchars((string) $location['loyer_mensuel'], ENT_QUOTES, 'UTF-8') ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="jour_echeance">Jour d'échéance *</label>
                            <input
                                type="number"
                                name="jour_echeance"
                                id="jour_echeance"
                                class="form-control"
                                min="1"
                                max="31"
                                value="<?= (int) $location['jour_echeance'] ?>"
                                required
                            >
                            <small>Exemple : 5 signifie que le loyer est dû le 5 de chaque mois.</small>
                        </div>

                        <div class="form-group">
                            <label for="statut">Statut *</label>
                            <select name="statut" id="statut" class="form-control" required>
                                <option value="active" <?= $location['statut'] === 'active' ? 'selected' : '' ?>>
                                    Active
                                </option>
                                <option value="terminee" <?= $location['statut'] === 'terminee' ? 'selected' : '' ?>>
                                    Terminée
                                </option>
                                <option value="resiliee" <?= $location['statut'] === 'resiliee' ? 'selected' : '' ?>>
                                    Résiliée
                                </option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                Enregistrer les modifications
                            </button>

                            <a href="/ImmoGestion/locations/index.php" class="btn btn-secondary">
                                Annuler
                            </a>
                        </div>

                    </form>

                </div>
            </div>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appartementSelect = document.getElementById('appartement_id');
    const loyerInput = document.getElementById('loyer_mensuel');

    appartementSelect.addEventListener('change', function () {
        const option = appartementSelect.options[appartementSelect.selectedIndex];

        if (option && option.dataset.loyer) {
            loyerInput.value = option.dataset.loyer;
        }
    });
});
</script>

