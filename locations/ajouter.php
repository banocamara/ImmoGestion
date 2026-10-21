<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// Générer un jeton CSRF
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

// Récupérer les appartements libres et actifs
$stmt = $pdo->query("
    SELECT
        a.id,
        a.numero,
        a.loyer_mensuel,
        m.nom AS nom_maison
    FROM appartements a
    INNER JOIN maisons m ON m.id = a.maison_id
    WHERE a.actif = 1
      AND m.actif = 1
      AND a.statut = 'libre'
      AND NOT EXISTS (
          SELECT 1
          FROM locations l
          WHERE l.appartement_id = a.id
            AND l.statut = 'active'
      )
    ORDER BY m.nom, a.numero
");

$appartements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les locataires actifs
$stmt = $pdo->query("
    SELECT id, nom, prenom
    FROM locataires
    WHERE actif = 1
    ORDER BY nom, prenom
");

$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $erreurs[] = 'Le formulaire a expiré. Rechargez la page et réessayez.';
    }

    // Récupérer les valeurs
    $appartementBrut = $_POST['appartement_id'] ?? '';
    $locataireBrut = $_POST['locataire_id'] ?? '';
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    $loyer_mensuel = trim((string) ($_POST['loyer_mensuel'] ?? ''));
    $jourBrut = $_POST['jour_echeance'] ?? '';

    // Vérifier les types des données
    if (!is_string($appartementBrut) && !is_int($appartementBrut)) {
        $appartementBrut = '';
    }

    if (!is_string($locataireBrut) && !is_int($locataireBrut)) {
        $locataireBrut = '';
    }

    if (!is_string($date_debut)) {
        $date_debut = '';
    }

    if (!is_string($date_fin)) {
        $date_fin = '';
    }

    if (!is_string($jourBrut) && !is_int($jourBrut)) {
        $jourBrut = '';
    }

    $appartement_id = filter_var(
        $appartementBrut,
        FILTER_VALIDATE_INT
    );

    $locataire_id = filter_var(
        $locataireBrut,
        FILTER_VALIDATE_INT
    );

    $jour_echeance = filter_var(
        $jourBrut,
        FILTER_VALIDATE_INT
    );

    // Vérifier l'appartement
    if ($appartement_id === false || $appartement_id <= 0) {
        $erreurs[] = 'Veuillez sélectionner un appartement valide.';
    }

    // Vérifier le locataire
    if ($locataire_id === false || $locataire_id <= 0) {
        $erreurs[] = 'Veuillez sélectionner un locataire valide.';
    }

    // Vérifier la date de début
    $dateDebutValide = false;

    if ($date_debut === '') {
        $erreurs[] = 'La date de début est obligatoire.';
    } else {
        $dateDebutObjet = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date_debut
        );

        $dateDebutValide = $dateDebutObjet !== false
            && $dateDebutObjet->format('Y-m-d') === $date_debut;

        if (!$dateDebutValide) {
            $erreurs[] = 'La date de début est invalide.';
        }
    }

    // Vérifier la date de fin
    if ($date_fin !== '') {
        $dateFinObjet = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date_fin
        );

        if (
            !$dateFinObjet ||
            $dateFinObjet->format('Y-m-d') !== $date_fin
        ) {
            $erreurs[] = 'La date de fin est invalide.';
        } elseif (
            $dateDebutValide &&
            $dateFinObjet <= $dateDebutObjet
        ) {
            $erreurs[] = 'La date de fin doit être postérieure à la date de début.';
        }
    }

    // Vérifier le loyer
    if (
        $loyer_mensuel === '' ||
        !is_numeric($loyer_mensuel) ||
        !is_finite((float) $loyer_mensuel) ||
        (float) $loyer_mensuel <= 0 ||
        (float) $loyer_mensuel > 9999999999.99
    ) {
        $erreurs[] = 'Le loyer doit être un montant positif valide.';
    }

    // Vérifier le jour d'échéance
    if (
        $jour_echeance === false ||
        $jour_echeance < 1 ||
        $jour_echeance > 31
    ) {
        $erreurs[] = 'Le jour d’échéance doit être compris entre 1 et 31.';
    }

    // Enregistrer la location si toutes les validations sont correctes
    if (empty($erreurs)) {
        try {
            $pdo->beginTransaction();

            /*
             * Verrouiller l'appartement pendant la transaction.
             * Cela réduit le risque de créer deux locations simultanées.
             */
            $stmt = $pdo->prepare("
                SELECT a.id, a.loyer_mensuel
                FROM appartements a
                INNER JOIN maisons m ON m.id = a.maison_id
                WHERE a.id = :id
                  AND a.actif = 1
                  AND m.actif = 1
                  AND a.statut = 'libre'
                FOR UPDATE
            ");

            $stmt->execute(['id' => $appartement_id]);
            $appartement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appartement) {
                throw new RuntimeException(
                    'Cet appartement est indisponible ou désactivé.'
                );
            }

            // Vérifier qu'aucune location active n'existe déjà
            $stmt = $pdo->prepare("
                SELECT id
                FROM locations
                WHERE appartement_id = :appartement_id
                  AND statut = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                'appartement_id' => $appartement_id
            ]);

            if ($stmt->fetch()) {
                throw new RuntimeException(
                    'Cet appartement possède déjà une location active.'
                );
            }

            // Vérifier que le locataire est toujours actif
            $stmt = $pdo->prepare("
                SELECT id
                FROM locataires
                WHERE id = :id
                  AND actif = 1
                LIMIT 1
            ");

            $stmt->execute(['id' => $locataire_id]);

            if (!$stmt->fetch()) {
                throw new RuntimeException(
                    'Ce locataire est introuvable ou désactivé.'
                );
            }

            // Créer la location
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
                  AND actif = 1
            ");

            $stmt->execute(['id' => $appartement_id]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Impossible de mettre à jour le statut de l’appartement.'
                );
            }

            $pdo->commit();

            $_SESSION['message'] = 'Location enregistrée avec succès.';

            // Renouveler le jeton après une opération réussie
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: index.php');
            exit;

        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erreurs[] = $e->getMessage();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erreur création location : ' . $e->getMessage()
            );

            $erreurs[] = 'Une erreur est survenue lors de l’enregistrement. Réessayez.';
        }
    }

    // Recharger les listes après le traitement
    $stmt = $pdo->query("
        SELECT
            a.id,
            a.numero,
            a.loyer_mensuel,
            m.nom AS nom_maison
        FROM appartements a
        INNER JOIN maisons m ON m.id = a.maison_id
        WHERE a.actif = 1
          AND m.actif = 1
          AND a.statut = 'libre'
          AND NOT EXISTS (
              SELECT 1
              FROM locations l
              WHERE l.appartement_id = a.id
                AND l.statut = 'active'
          )
        ORDER BY m.nom, a.numero
    ");

    $appartements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT id, nom, prenom
        FROM locataires
        WHERE actif = 1
        ORDER BY nom, prenom
    ");

    $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

            <?php if (empty($appartements)): ?>
                <div class="alert">
                    Aucun appartement libre et actif n'est disponible.
                    Vérifiez les statuts des appartements et des maisons.
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
                        value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <div class="form-group">
                        <label for="appartement_id">Appartement *</label>

                        <select
                            id="appartement_id"
                            name="appartement_id"
                            required
                        >
                            <option value="">-- Choisir un appartement --</option>

                            <?php foreach ($appartements as $appartement): ?>
                                <option
                                    value="<?= (int) $appartement['id'] ?>"
                                    data-loyer="<?= htmlspecialchars(
                                        (string) $appartement['loyer_mensuel'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    <?= (string) $appartement_id ===
                                        (string) $appartement['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $appartement['nom_maison'] .
                                        ' - Appartement ' .
                                        $appartement['numero'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="locataire_id">Locataire *</label>

                        <select
                            id="locataire_id"
                            name="locataire_id"
                            required
                        >
                            <option value="">-- Choisir un locataire --</option>

                            <?php foreach ($locataires as $locataire): ?>
                                <option
                                    value="<?= (int) $locataire['id'] ?>"
                                    <?= (string) $locataire_id ===
                                        (string) $locataire['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $locataire['nom'] . ' ' .
                                        $locataire['prenom'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="loyer_mensuel">Loyer mensuel (MRU) *</label>

                        <input
                            type="number"
                            id="loyer_mensuel"
                            name="loyer_mensuel"
                            min="0.01"
                            max="9999999999.99"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                (string) $loyer_mensuel,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="jour_echeance">Jour d'échéance *</label>

                        <select
                            id="jour_echeance"
                            name="jour_echeance"
                            required
                        >
                            <?php for ($jour = 1; $jour <= 31; $jour++): ?>
                                <option
                                    value="<?= $jour ?>"
                                    <?= (string) $jour_echeance ===
                                        (string) $jour
                                        ? 'selected'
                                        : '' ?>
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
                            value="<?= htmlspecialchars(
                                $date_debut,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="date_fin">Date de fin (facultative)</label>

                        <input
                            type="date"
                            id="date_fin"
                            name="date_fin"
                            value="<?= htmlspecialchars(
                                $date_fin,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        <?= empty($appartements) || empty($locataires)
                            ? 'disabled'
                            : '' ?>
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
