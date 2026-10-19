<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

$titrePage = 'Enregistrer un paiement';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$messageErreur = '';
$montant = '';
$periodeMois = date('Y-m');
$datePaiement = date('Y-m-d\TH:i');
$modePaiement = 'especes';
$reference = '';
$commentaire = '';
$locationId = 0;

// Récupérer les locations actives
$sql = "
    SELECT
        l.id,
        l.loyer_mensuel,
        l.date_debut,
        l.date_fin,
        loc.nom AS nom_locataire,
        loc.prenom AS prenom_locataire,
        a.numero AS numero_appartement,
        m.nom AS nom_maison
    FROM locations l
    INNER JOIN locataires loc ON l.locataire_id = loc.id
    INNER JOIN appartements a ON l.appartement_id = a.id
    INNER JOIN maisons m ON a.maison_id = m.id
    WHERE l.statut = 'active'
    ORDER BY m.nom, a.numero
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$locations = $stmt->fetchAll();

// Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $messageErreur = 'Le formulaire a expiré. Rechargez la page et réessayez.';
    } else {

        $locationId = filter_input(
            INPUT_POST,
            'location_id',
            FILTER_VALIDATE_INT
        ) ?: 0;

        $montant = trim($_POST['montant'] ?? '');
        $periodeMois = trim($_POST['periode_mois'] ?? '');
        $datePaiement = trim($_POST['date_paiement'] ?? '');
        $modePaiement = $_POST['mode_paiement'] ?? 'especes';
        $reference = trim($_POST['reference'] ?? '');
        $commentaire = trim($_POST['commentaire'] ?? '');

        // Vérifier les champs
        if (
            !$locationId ||
            $montant === '' ||
            !is_numeric($montant) ||
            (float) $montant <= 0 ||
            !preg_match('/^\d{4}-\d{2}$/', $periodeMois)
        ) {
            $messageErreur = 'Veuillez remplir correctement les champs obligatoires.';
        } elseif (
            !in_array(
                $modePaiement,
                ['especes', 'virement', 'mobile_money', 'autre'],
                true
            )
        ) {
            $messageErreur = 'Le mode de paiement sélectionné est invalide.';
        } elseif (
            !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $datePaiement) ||
            !checkdate(
                (int) substr($datePaiement, 5, 2),
                (int) substr($datePaiement, 8, 2),
                (int) substr($datePaiement, 0, 4)
            ) ||
            (int) substr($datePaiement, 11, 2) > 23 ||
            (int) substr($datePaiement, 14, 2) > 59
        ) {
            $messageErreur = 'La date du paiement est invalide.';
        } elseif (strlen($reference) > 100) {
            $messageErreur = 'La référence ne doit pas dépasser 100 caractères.';
        } else {

            // Vérifier que la location existe et est active
            $stmt = $pdo->prepare("
                SELECT id
                FROM locations
                WHERE id = :id
                  AND statut = 'active'
            ");
            $stmt->execute(['id' => $locationId]);
            $location = $stmt->fetch();

            if (!$location) {
                $messageErreur = 'La location sélectionnée est introuvable ou inactive.';
            } else {

                // Convertir la période en date SQL
                $periodeSQL = $periodeMois . '-01';

                // Convertir la date et l'heure en format SQL
                $datePaiementSQL = str_replace('T', ' ', $datePaiement) . ':00';

                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO paiements (
                            location_id,
                            enregistre_par,
                            montant,
                            periode_mois,
                            date_paiement,
                            mode_paiement,
                            reference,
                            statut,
                            commentaire
                        ) VALUES (
                            :location_id,
                            :enregistre_par,
                            :montant,
                            :periode_mois,
                            :date_paiement,
                            :mode_paiement,
                            :reference,
                            'valide',
                            :commentaire
                        )
                    ");

                    $stmt->execute([
                        'location_id' => $locationId,
                        'enregistre_par' => $_SESSION['utilisateur_id'],
                        'montant' => $montant,
                        'periode_mois' => $periodeSQL,
                        'date_paiement' => $datePaiementSQL,
                        'mode_paiement' => $modePaiement,
                        'reference' => $reference !== '' ? $reference : null,
                        'commentaire' => $commentaire !== '' ? $commentaire : null
                    ]);

                    $_SESSION['message_succes'] = 'Le paiement a été enregistré avec succès.';

                    header('Location: /ImmoGestion/paiements/index.php');
                    exit;

                } catch (PDOException $e) {
                    $messageErreur = 'Une erreur est survenue lors de l’enregistrement du paiement.';
                }
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
            <h2>Enregistrer un paiement</h2>

            <a href="/ImmoGestion/paiements/index.php" class="btn btn-secondary">
                Retour à la liste
            </a>
        </div>

        <div class="page-content">

            <?php if ($messageErreur !== ''): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($messageErreur, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (empty($locations)): ?>

                <div class="alert alert-warning">
                    Aucune location active n'est disponible.
                    Vous devez d'abord créer une location active.
                </div>

            <?php else: ?>

                <div class="panel">

                    <div class="panel-header">
                        <h3>Informations du paiement</h3>
                    </div>

                    <div class="panel-body">

                        <form method="POST" action="">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                            >

                            <div class="form-group">
                                <label for="location_id">Location / Locataire *</label>

                                <select
                                    name="location_id"
                                    id="location_id"
                                    class="form-control"
                                    required
                                >
                                    <option value="">-- Sélectionner une location --</option>

                                    <?php foreach ($locations as $location): ?>
                                        <option
                                            value="<?= (int) $location['id'] ?>"
                                            data-loyer="<?= htmlspecialchars((string) $location['loyer_mensuel'], ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $locationId === (int) $location['id'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $location['nom_locataire'] . ' ' .
                                                $location['prenom_locataire'] . ' — ' .
                                                $location['nom_maison'] . ' / Appartement ' .
                                                $location['numero_appartement'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>

                                <small>Le loyer mensuel sera proposé automatiquement.</small>
                            </div>

                            <div class="form-group">
                                <label for="montant">Montant reçu (MRU) *</label>

                                <input
                                    type="number"
                                    name="montant"
                                    id="montant"
                                    class="form-control"
                                    min="0.01"
                                    step="0.01"
                                    value="<?= htmlspecialchars((string) $montant, ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="periode_mois">Mois concerné *</label>

                                <input
                                    type="month"
                                    name="periode_mois"
                                    id="periode_mois"
                                    class="form-control"
                                    value="<?= htmlspecialchars($periodeMois, ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="date_paiement">Date et heure du paiement *</label>

                                <input
                                    type="datetime-local"
                                    name="date_paiement"
                                    id="date_paiement"
                                    class="form-control"
                                    value="<?= htmlspecialchars($datePaiement, ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="mode_paiement">Mode de paiement *</label>

                                <select
                                    name="mode_paiement"
                                    id="mode_paiement"
                                    class="form-control"
                                    required
                                >
                                    <option value="especes" <?= $modePaiement === 'especes' ? 'selected' : '' ?>>
                                        Espèces
                                    </option>

                                    <option value="virement" <?= $modePaiement === 'virement' ? 'selected' : '' ?>>
                                        Virement bancaire
                                    </option>

                                    <option value="mobile_money" <?= $modePaiement === 'mobile_money' ? 'selected' : '' ?>>
                                        Bankily
                                    </option>

                                    <option value="autre" <?= $modePaiement === 'autre' ? 'selected' : '' ?>>
                                        Autre
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="reference">Référence du paiement</label>

                                <input
                                    type="text"
                                    name="reference"
                                    id="reference"
                                    class="form-control"
                                    maxlength="100"
                                    value="<?= htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Exemple : référence de transaction"
                                >
                            </div>

                            <div class="form-group">
                                <label for="commentaire">Commentaire</label>

                                <textarea
                                    name="commentaire"
                                    id="commentaire"
                                    class="form-control"
                                    rows="4"
                                ><?= htmlspecialchars($commentaire, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="form-actions">

                                <button type="submit" class="btn btn-primary">
                                    Enregistrer le paiement
                                </button>

                                <a
                                    href="/ImmoGestion/paiements/index.php"
                                    class="btn btn-secondary"
                                >
                                    Annuler
                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            <?php endif; ?>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const locationSelect = document.getElementById('location_id');
    const montantInput = document.getElementById('montant');

    if (locationSelect && montantInput) {
        locationSelect.addEventListener('change', function () {
            const option = locationSelect.options[locationSelect.selectedIndex];

            if (option && option.dataset.loyer) {
                montantInput.value = option.dataset.loyer;
            }
        });
    }
});
</script>
