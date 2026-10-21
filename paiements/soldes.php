<?php

require_once __DIR__ . '/../includes/auth.php';

exigerConnexion();

$titrePage = 'Suivi des loyers';

$utilisateurId = (int) $_SESSION['utilisateur_id'];
$estSuperAdmin = estSuperAdmin();

/*
|--------------------------------------------------------------------------
| Vérifier le mois demandé
|--------------------------------------------------------------------------
*/

$mois = $_GET['mois'] ?? date('Y-m');

if (!is_string($mois) || !preg_match('/^(\d{4})-(\d{2})$/', $mois, $matches)) {
    $mois = date('Y-m');
} else {
    $annee = (int) $matches[1];
    $numeroMois = (int) $matches[2];

    if (!checkdate($numeroMois, 1, $annee)) {
        $mois = date('Y-m');
    }
}

$dateDebutMois = $mois . '-01';
$dateFinMois = date('Y-m-t', strtotime($dateDebutMois));

/*
|--------------------------------------------------------------------------
| Récupérer les locations et leurs paiements valides
|--------------------------------------------------------------------------
|
| Pour un administrateur, seules les maisons qui lui sont
| attribuées sont visibles.
|
| Un paiement annulé n'est jamais inclus dans le calcul.
|
*/

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
        ON loc.id = l.locataire_id

    INNER JOIN appartements a
        ON a.id = l.appartement_id

    INNER JOIN maisons m
        ON m.id = a.maison_id

    WHERE l.date_debut <= :date_fin
      AND (
          l.date_fin IS NULL
          OR l.date_fin >= :date_debut
      )
      AND l.statut <> 'resiliee'
";

/*
 * Filtrer les maisons accessibles aux administrateurs.
 */
$parametres = [
    'periode_mois' => $dateDebutMois,
    'date_fin' => $dateFinMois,
    'date_debut' => $dateDebutMois
];

if (!$estSuperAdmin) {
    $sql .= "
        AND EXISTS (
            SELECT 1
            FROM affectations_admins aa
            WHERE aa.maison_id = m.id
              AND aa.utilisateur_id = :utilisateur_id
        )
    ";

    $parametres['utilisateur_id'] = $utilisateurId;
}

$sql .= "
    ORDER BY m.nom ASC, a.numero ASC, loc.nom ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametres);

    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log(
        'Erreur lors du calcul des soldes de loyers : '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('Impossible de charger le suivi des loyers pour le moment.');
}

/*
|--------------------------------------------------------------------------
| Calculer les totaux
|--------------------------------------------------------------------------
|
| Le montant restant ne peut pas être négatif.
| Si le locataire a payé plus que son loyer, le surplus reste
| visible dans le montant payé, mais le reste dû est de zéro.
|
*/

$totalLoyers = 0;
$totalPaye = 0;
$totalRestant = 0;

foreach ($locations as &$location) {

    $loyer = max(0, (float) $location['loyer_mensuel']);
    $paye = max(0, (float) $location['montant_paye']);

    $restant = max(0, $loyer - $paye);

    if ($paye >= $loyer && $loyer > 0) {
        $statutPaiement = 'Payé';
    } elseif ($paye > 0) {
        $statutPaiement = 'Partiellement payé';
    } else {
        $statutPaiement = 'Impayé';
    }

    $location['montant_restant'] = $restant;
    $location['statut_paiement'] = $statutPaiement;

    $totalLoyers += $loyer;
    $totalPaye += $paye;
    $totalRestant += $restant;
}

unset($location);

/*
|--------------------------------------------------------------------------
| Préparer l'affichage
|--------------------------------------------------------------------------
*/

function echapperSolde($valeur): string
{
    return htmlspecialchars(
        (string) $valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

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
                                value="<?= echapperSolde($mois) ?>"
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
                            <?= number_format($totalLoyers, 2, ',', ' ') ?>
                            MRU
                        </p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total payé</h3>
                        <p>
                            <?= number_format($totalPaye, 2, ',', ' ') ?>
                            MRU
                        </p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Reste à payer</h3>
                        <p>
                            <?= number_format($totalRestant, 2, ',', ' ') ?>
                            MRU
                        </p>
                    </div>
                </div>

            </div>

            <!-- Tableau des loyers -->
            <div class="panel">

                <div class="panel-header">
                    <h3>
                        Situation des loyers —
                        <?= echapperSolde(date('m/Y', strtotime($dateDebutMois))) ?>
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
                                                <?= echapperSolde(
                                                    $location['nom_locataire'] . ' '
                                                    . $location['prenom_locataire']
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= echapperSolde($location['nom_maison']) ?>
                                            </td>

                                            <td>
                                                <?= echapperSolde($location['numero_appartement']) ?>
                                            </td>

                                            <td>
                                                <?= number_format(
                                                    (float) $location['loyer_mensuel'],
                                                    2,
                                                    ',',
                                                    ' '
                                                ) ?>
                                                MRU
                                            </td>

                                            <td>
                                                <?= number_format(
                                                    (float) $location['montant_paye'],
                                                    2,
                                                    ',',
                                                    ' '
                                                ) ?>
                                                MRU
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= number_format(
                                                        (float) $location['montant_restant'],
                                                        2,
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

                            <?php if (!empty($locations)): ?>
                                <tfoot>
                                    <tr>
                                        <th colspan="3">Totaux</th>

                                        <th>
                                            <?= number_format($totalLoyers, 2, ',', ' ') ?>
                                            MRU
                                        </th>

                                        <th>
                                            <?= number_format($totalPaye, 2, ',', ' ') ?>
                                            MRU
                                        </th>

                                        <th>
                                            <?= number_format($totalRestant, 2, ',', ' ') ?>
                                            MRU
                                        </th>

                                        <th></th>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>

                        </table>

                    </div>

                </div>

            </div>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>
