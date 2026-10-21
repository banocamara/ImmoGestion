<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

$titrePage = 'Modifier une location';

$messageErreur = '';

// Vérifier l'identifiant de la location.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: /ImmoGestion/locations/index.php');
    exit;
}

// Créer le jeton CSRF.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer la location.
$stmt = $pdo->prepare("
    SELECT *
    FROM locations
    WHERE id = :id
");
$stmt->execute(['id' => $id]);

$location = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$location) {
    header('Location: /ImmoGestion/locations/index.php');
    exit;
}

// Récupérer le nombre de paiements enregistrés pour cette location.
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM paiements
    WHERE location_id = :location_id
");
$stmt->execute(['location_id' => $id]);

$nombrePaiements = (int) $stmt->fetchColumn();

// Récupérer les appartements actifs des maisons actives.
// On conserve aussi l'appartement actuel, même s'il est occupé.
$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.numero,
        a.loyer_mensuel,
        a.statut AS statut_appartement,
        m.nom AS nom_maison
    FROM appartements a
    INNER JOIN maisons m ON m.id = a.maison_id
    WHERE a.actif = 1
      AND m.actif = 1
      AND (
          a.statut <> 'indisponible'
          OR a.id = :appartement_actuel
      )
    ORDER BY m.nom, a.numero
");

$stmt->execute([
    'appartement_actuel' => (int) $location['appartement_id']
]);

$appartements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les locataires actifs.
// On conserve également le locataire actuel s'il a été désactivé.
$stmt = $pdo->prepare("
    SELECT id, nom, prenom, actif
    FROM locataires
    WHERE actif = 1
       OR id = :locataire_actuel
    ORDER BY nom, prenom
");

$stmt->execute([
    'locataire_actuel' => (int) $location['locataire_id']
]);

$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traiter le formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF.
    $csrfRecu = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfRecu) ||
        !hash_equals($_SESSION['csrf_token'], $csrfRecu)
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

        // Vérifier les champs obligatoires.
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

        } elseif (
            $dateFin !== '' &&
            $dateFin < $dateDebut
        ) {
            $messageErreur = 'La date de fin ne peut pas précéder la date de début.';

        } elseif (
            $statut === 'active' &&
            $dateFin !== '' &&
            $dateFin < date('Y-m-d')
        ) {
            $messageErreur = 'Une location active ne peut pas avoir une date de fin déjà passée.';

        } else {

            try {

                $pdo->beginTransaction();

                /*
                 * Relire et verrouiller la location.
                 * Cela évite de travailler sur des informations devenues obsolètes.
                 */
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM locations
                    WHERE id = :id
                    FOR UPDATE
                ");

                $stmt->execute(['id' => $id]);

                $locationActuelle = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$locationActuelle) {
                    throw new Exception('Cette location n’existe plus.');
                }

                /*
                 * Vérifier si des paiements existent.
                 * Si oui, l'appartement et le locataire ne doivent pas changer,
                 * afin de préserver la cohérence de l'historique.
                 */
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM paiements
                    WHERE location_id = :location_id
                ");

                $stmt->execute(['location_id' => $id]);

                $paiementsExistants = (int) $stmt->fetchColumn();

                $ancienAppartementId = (int) $locationActuelle['appartement_id'];
                $ancienLocataireId = (int) $locationActuelle['locataire_id'];

                if (
                    $paiementsExistants > 0 &&
                    (
                        $appartementId !== $ancienAppartementId ||
                        $locataireId !== $ancienLocataireId
                    )
                ) {
                    throw new Exception(
                        'Cette location possède déjà des paiements. Pour préserver leur historique, vous ne pouvez pas changer son appartement ni son locataire.'
                    );
                }

                /*
                 * Vérifier et verrouiller l'appartement choisi.
                 */
                $stmt = $pdo->prepare("
                    SELECT
                        a.id,
                        a.statut,
                        a.maison_id,
                        m.actif AS maison_active
                    FROM appartements a
                    INNER JOIN maisons m ON m.id = a.maison_id
                    WHERE a.id = :id
                      AND a.actif = 1
                      AND m.actif = 1
                    FOR UPDATE
                ");

                $stmt->execute(['id' => $appartementId]);

                $appartementChoisi = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$appartementChoisi) {
                    throw new Exception(
                        'L’appartement choisi ou sa maison est inactif.'
                    );
                }

                /*
                 * Ne pas permettre de choisir un appartement indisponible.
                 */
                if ($appartementChoisi['statut'] === 'indisponible') {
                    throw new Exception(
                        'Cet appartement est indisponible et ne peut pas être attribué à une location.'
                    );
                }

                /*
                 * Vérifier le locataire.
                 */
                $stmt = $pdo->prepare("
                    SELECT id, actif
                    FROM locataires
                    WHERE id = :id
                    FOR UPDATE
                ");

                $stmt->execute(['id' => $locataireId]);

                $locataireChoisi = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$locataireChoisi) {
                    throw new Exception('Le locataire sélectionné n’existe pas.');
                }

                if (
                    (int) $locataireChoisi['actif'] !== 1 &&
                    $locataireId !== $ancienLocataireId
                ) {
                    throw new Exception(
                        'Vous ne pouvez pas affecter un locataire inactif à cette location.'
                    );
                }

                /*
                 * Vérifier qu'un appartement n'a pas déjà une autre location active.
                 */
                if ($statut === 'active') {

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM locations
                        WHERE appartement_id = :appartement_id
                          AND statut = 'active'
                          AND id <> :location_id
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $stmt->execute([
                        'appartement_id' => $appartementId,
                        'location_id' => $id
                    ]);

                    if ($stmt->fetch()) {
                        throw new Exception(
                            'Cet appartement possède déjà une autre location active.'
                        );
                    }
                }

                /*
                 * Mettre à jour la location.
                 */
                $stmt = $pdo->prepare("
                    UPDATE locations
                    SET
                        appartement_id = :appartement_id,
                        locataire_id = :locataire_id,
                        date_debut = :date_debut,
                        date_fin = :date_fin,
                        loyer_mensuel = :loyer_mensuel,
                        jour_echeance = :jour_echeance,
                        statut = :statut
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

                /*
                 * Actualiser le statut des appartements concernés.
                 *
                 * Un appartement indisponible reste indisponible.
                 * Sinon, il est occupé s'il possède une location active,
                 * et libre dans le cas contraire.
                 */
                $appartementsAActualiser = array_unique([
                    $ancienAppartementId,
                    (int) $appartementId
                ]);

                foreach ($appartementsAActualiser as $idAppartement) {

                    $stmt = $pdo->prepare("
                        SELECT statut, actif
                        FROM appartements
                        WHERE id = :id
                        FOR UPDATE
                    ");

                    $stmt->execute(['id' => $idAppartement]);

                    $appartementExistant = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$appartementExistant) {
                        continue;
                    }

                    // Ne pas réactiver un appartement désactivé.
                    if ((int) $appartementExistant['actif'] !== 1) {
                        continue;
                    }

                    // Préserver le statut indisponible.
                    if ($appartementExistant['statut'] === 'indisponible') {
                        continue;
                    }

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

                    $nouveauStatut = $aUneLocationActive
                        ? 'occupe'
                        : 'libre';

                    $stmt = $pdo->prepare("
                        UPDATE appartements
                        SET statut = :statut
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        'statut' => $nouveauStatut,
                        'id' => $idAppartement
                    ]);
                }

                $pdo->commit();

                $_SESSION['message_succes'] =
                    'La location a été modifiée avec succès.';

                header('Location: /ImmoGestion/locations/index.php');
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($e instanceof PDOException) {
                    error_log(
                        'Erreur PDO dans locations/modifier.php : ' .
                        $e->getMessage()
                    );

                    $messageErreur =
                        'Une erreur technique est survenue. Vérifiez les données et réessayez.';

                } else {
                    $messageErreur = $e->getMessage();
                }
            }
        }

        // Conserver les valeurs saisies en cas d'erreur.
        $location['appartement_id'] =
            $appartementId ?: $location['appartement_id'];

        $location['locataire_id'] =
            $locataireId ?: $location['locataire_id'];

        $location['date_debut'] = $dateDebut;
        $location['date_fin'] = $dateFin;
        $location['loyer_mensuel'] = $loyerMensuel;

        $location['jour_echeance'] =
            $jourEcheance ?: $location['jour_echeance'];

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

            <a
                href="/ImmoGestion/locations/index.php"
                class="btn btn-secondary"
            >
                Retour à la liste
            </a>
        </div>

        <div class="page-content">

            <?php if ($messageErreur !== ''): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars(
                        $messageErreur,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <?php if ($nombrePaiements > 0): ?>
                <div class="alert alert-danger">
                    Cette location possède
                    <?= $nombrePaiements ?>
                    paiement(s) enregistré(s).
                    Pour préserver l'historique, son appartement et son
                    locataire ne peuvent pas être remplacés.
                    Les autres informations restent modifiables.
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
                            value="<?= htmlspecialchars(
                                $_SESSION['csrf_token'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <div class="form-group">
                            <label for="appartement_id">
                                Appartement *
                            </label>

                            <select
                                name="appartement_id"
                                id="appartement_id"
                                class="form-control"
                                required
                                <?= $nombrePaiements > 0 ? 'disabled' : '' ?>
                            >
                                <?php if ($nombrePaiements > 0): ?>
                                    <option
                                        value="<?= (int) $location['appartement_id'] ?>"
                                        selected
                                        data-loyer="<?= htmlspecialchars(
                                            (string) $location['loyer_mensuel'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                        Appartement actuel — modification interdite
                                    </option>
                                <?php else: ?>
                                    <option value="">
                                        -- Choisir un appartement --
                                    </option>

                                    <?php foreach ($appartements as $appartement): ?>
                                        <option
                                            value="<?= (int) $appartement['id'] ?>"
                                            data-loyer="<?= htmlspecialchars(
                                                (string) $appartement['loyer_mensuel'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            <?= (int) $location['appartement_id'] === (int) $appartement['id'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $appartement['nom_maison'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                            — Appartement
                                            <?= htmlspecialchars(
                                                $appartement['numero'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                            (<?= htmlspecialchars(
                                                $appartement['statut_appartement'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>

                            <?php if ($nombrePaiements > 0): ?>
                                <input
                                    type="hidden"
                                    name="appartement_id"
                                    value="<?= (int) $location['appartement_id'] ?>"
                                >
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="locataire_id">
                                Locataire *
                            </label>

                            <select
                                name="locataire_id"
                                id="locataire_id"
                                class="form-control"
                                required
                                <?= $nombrePaiements > 0 ? 'disabled' : '' ?>
                            >
                                <?php foreach ($locataires as $locataire): ?>
                                    <option
                                        value="<?= (int) $locataire['id'] ?>"
                                        <?= (int) $location['locataire_id'] === (int) $locataire['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            $locataire['nom'] . ' ' . $locataire['prenom'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                        <?= (int) $locataire['actif'] !== 1
                                            ? ' (inactif — historique)'
                                            : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($nombrePaiements > 0): ?>
                                <input
                                    type="hidden"
                                    name="locataire_id"
                                    value="<?= (int) $location['locataire_id'] ?>"
                                >
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="date_debut">
                                Date de début *
                            </label>

                            <input
                                type="date"
                                name="date_debut"
                                id="date_debut"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    (string) $location['date_debut'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="date_fin">
                                Date de fin
                            </label>

                            <input
                                type="date"
                                name="date_fin"
                                id="date_fin"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    (string) ($location['date_fin'] ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <small>
                                Laissez vide si aucune date de fin n'est prévue.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="loyer_mensuel">
                                Loyer mensuel *
                            </label>

                            <input
                                type="number"
                                name="loyer_mensuel"
                                id="loyer_mensuel"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    (string) $location['loyer_mensuel'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="jour_echeance">
                                Jour d'échéance *
                            </label>

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

                            <small>
                                Exemple : 5 signifie que le loyer est dû le 5 de chaque mois.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="statut">
                                Statut *
                            </label>

                            <select
                                name="statut"
                                id="statut"
                                class="form-control"
                                required
                            >
                                <option
                                    value="active"
                                    <?= $location['statut'] === 'active' ? 'selected' : '' ?>
                                >
                                    Active
                                </option>

                                <option
                                    value="terminee"
                                    <?= $location['statut'] === 'terminee' ? 'selected' : '' ?>
                                >
                                    Terminée
                                </option>

                                <option
                                    value="resiliee"
                                    <?= $location['statut'] === 'resiliee' ? 'selected' : '' ?>
                                >
                                    Résiliée
                                </option>
                            </select>
                        </div>

                        <div class="form-actions">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Enregistrer les modifications
                            </button>

                            <a
                                href="/ImmoGestion/locations/index.php"
                                class="btn btn-secondary"
                            >
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

    if (appartementSelect && loyerInput) {
        appartementSelect.addEventListener('change', function () {
            const option =
                appartementSelect.options[appartementSelect.selectedIndex];

            if (option && option.dataset.loyer) {
                loyerInput.value = option.dataset.loyer;
            }
        });
    }
});
</script>
