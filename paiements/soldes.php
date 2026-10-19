<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

$titrePage = 'Suivi des loyers';

// Mois sélectionné : mois courant par défaut
$mois = $_GET['mois'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
    $mois = date('Y-m');
}

$numeroMois = (int) substr($mois, 5, 2);
$annee = (int) substr($mois, 0, 4);

if (!checkdate($numeroMois, 1, $annee)) {
    $mois = date('Y-m');
}

// Première et dernière date du mois
$dateDebutMois = $mois . '-01';
$dateFinMois = date('Y-m-t', strtotime($dateDebutMois));

// Récupérer les locations concernées par le mois choisi
$sql = "
    SELECT
        l.id AS location_id,
        l.loyer_mensuel,
        l.date_debut,
        l.date_fin,
        l.statut AS statut_location,
        loc.nom AS nom_locataire,
        loc.prenom AS prenom_locataire,
        a.numero AS numero_appartement,
        m.nom AS nom_maison,

        COALESCE((
            SELECT SUM(p.montant)
            FROM paiements p
            WHERE p.location_id = l.id
              AND p.periode_mois = :periode_mois
              AND p.statut = 'valide'
        ), 0) AS montant_paye

    FROM locations l

    INNER JOIN locataires loc
        ON l.locataire_id = loc.id

    INNER JOIN appartements a
        ON l.appartement_id = a.id

    INNER JOIN maisons m
        ON a.maison_id = m.id

    WHERE l.date_debut <= :date_fin
      AND (l.date_fin IS NULL OR l.date_fin >= :date_debut)
      AND l.statut <> 'resiliee'

    ORDER BY m.nom, a.numero
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'periode_mois' => $dateDebutMois,
    'date_fin' => $dateFinMois,
    'date_debut' => $dateDebutMois
]);

$locations = $stmt->fetchAll();

// Calculer les totaux
$totalLoyers = 0;
$totalPaye = 0;
$totalRestant = 0;

foreach ($locations as &$location) {
    $loyer = (float) $location['loyer_mensuel'];
    $paye = (float) $location['montant_paye'];

    $restant = max(0, $loyer - $paye);

    if ($paye >= $loyer) {
        $statutPaiement = 'Payé';
    } elseif ($paye > 0) {
        $statutPaiement = 'Partiellement payé';
    } else {
        $statutPaiement = 'Impayé';
    }

    $location['montant_restant'] = $restant;
    $location['statut_paiement'] = $statutPaiement;

    $totalLoyers += $loyer;
    $totalPaye += min($paye, $loyer);
    $totalRestant += $restant;
}
unset($location);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h2>Suivi des loyers</h2>
        </div>

        <div class="page-content">

            <!-- Sélection du mois -->
            <div class="panel">

                <div class="panel-header">
                    <h3>Choisir une période</h3>
                </div>

                <div class="panel-body">

                    <form method="GET" action="">

                        <div class="form-group">
                            <label for="mois">Mois à consulter</label>

                            <input
                                type="month"
                                name="mois"
                                id="mois"
                                class="form-control"
                                value="<?= htmlspecialchars($mois, ENT_QUOTES, 'UTF-8') ?>"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Afficher
                        </button>

                    </form>

                </div>

            </div>

            <!-- Statistiques -->
            <div class="stats-grid">

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total des loyers</h3>
                        <p>
                            <?= number_format($totalLoyers, 0, ',', ' ') ?>
                            MRU
                        </p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total payé</h3>
                        <p>
                            <?= number_format($totalPaye, 0, ',', ' ') ?>
                            MRU
                        </p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Reste à payer</h3>
                        <p>
                            <?= number_format($totalRestant, 0, ',', ' ') ?>
                            MRU
                        </p>
                    </div>
                </div>

            </div>

            <!-- Tableau -->
            <div class="panel">

                <div class="panel-header">
                    <h3>
                        Situation des loyers —
                        <?= htmlspecialchars(date('m/Y', strtotime($dateDebutMois)), ENT_QUOTES, 'UTF-8') ?>
                    </h3>
                </div>

                <div class="panel-body">

                    <div class="table-responsive">

                        <table class="table">

                            <thead>
                                <tr>
                                    <th>Locataire</th>
                                    <th>Maison</th>
                                    <th>Appartement</th>
                                    <th>Loyer mensuel</th>
                                    <th>Montant payé</th>
                                    <th>Reste à payer</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (empty($locations)): ?>

                                    <tr>
                                        <td colspan="7" class="text-center">
                                            Aucune location pour ce mois.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($locations as $location): ?>

                                        <tr>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $location['nom_locataire'] . ' ' .
                                                    $location['prenom_locataire'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $location['nom_maison'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $location['numero_appartement'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= number_format(
                                                    (float) $location['loyer_mensuel'],
                                                    0,
                                                    ',',
                                                    ' '
                                                ) ?>
                                                MRU
                                            </td>

                                            <td>
                                                <?= number_format(
                                                    (float) $location['montant_paye'],
                                                    0,
                                                    ',',
                                                    ' '
                                                ) ?>
                                                MRU
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= number_format(
                                                        (float) $location['montant_restant'],
                                                        0,
                                                        ',',
                                                        ' '
                                                    ) ?>
                                                   MRU
                                                </strong>
                                            </td>

                                            <td>

                                                <?php if ($location['statut_paiement'] === 'Payé'): ?>

                                                    <span class="badge badge-success">
                                                        Payé
                                                    </span>

                                                <?php elseif ($location['statut_paiement'] === 'Partiellement payé'): ?>

                                                    <span class="badge badge-warning">
                                                        Partiellement payé
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-danger">
                                                        Impayé
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>
