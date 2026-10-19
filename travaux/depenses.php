<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();
exigerSuperAdmin();

// Vérifier et récupérer les dates du filtre
$dateDebut = trim($_GET['date_debut'] ?? '');
$dateFin = trim($_GET['date_fin'] ?? '');

$erreurFiltre = '';

function dateValide($date)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $dateFormatee = explode('-', $date);

    return checkdate(
        (int) $dateFormatee[1],
        (int) $dateFormatee[2],
        (int) $dateFormatee[0]
    );
}

// Valider la date de début
if ($dateDebut !== '' && !dateValide($dateDebut)) {
    $erreurFiltre = 'La date de début est invalide.';
}

// Valider la date de fin
if ($dateFin !== '' && !dateValide($dateFin)) {
    $erreurFiltre = 'La date de fin est invalide.';
}

// Vérifier que la date de début ne dépasse pas la date de fin
if (
    $erreurFiltre === '' &&
    $dateDebut !== '' &&
    $dateFin !== '' &&
    $dateDebut > $dateFin
) {
    $erreurFiltre = 'La date de début doit être antérieure à la date de fin.';
}

// Préparer les conditions SQL
$conditions = [];
$parametres = [];

if ($dateDebut !== '') {
    $conditions[] = 'date_travail >= :date_debut';
    $parametres[':date_debut'] = $dateDebut;
}

if ($dateFin !== '') {
    $conditions[] = 'date_travail <= :date_fin';
    $parametres[':date_fin'] = $dateFin;
}

$conditionSQL = '';

if (!empty($conditions)) {
    $conditionSQL = ' WHERE ' . implode(' AND ', $conditions);
}

// Initialiser les résultats
$totalDepenses = 0;
$nombreTravaux = 0;
$depensesMaisons = [];
$depensesAppartements = [];

if ($erreurFiltre === '') {

    // 1. Calculer le montant total des dépenses
    $sqlTotal = "
        SELECT
            COUNT(*) AS nombre_travaux,
            COALESCE(SUM(montant), 0) AS total_depenses
        FROM travaux
        $conditionSQL
    ";

    $stmt = $pdo->prepare($sqlTotal);
    $stmt->execute($parametres);

    $resultatTotal = $stmt->fetch(PDO::FETCH_ASSOC);

    $nombreTravaux = (int) $resultatTotal['nombre_travaux'];
    $totalDepenses = (float) $resultatTotal['total_depenses'];


    // 2. Calculer les dépenses de chaque maison
    $conditionsMaison = [];
    $parametresMaison = [];

    if ($dateDebut !== '') {
        $conditionsMaison[] = 't.date_travail >= :date_debut';
        $parametresMaison[':date_debut'] = $dateDebut;
    }

    if ($dateFin !== '') {
        $conditionsMaison[] = 't.date_travail <= :date_fin';
        $parametresMaison[':date_fin'] = $dateFin;
    }

    $conditionMaisonSQL = '';

    if (!empty($conditionsMaison)) {
        $conditionMaisonSQL = ' AND ' . implode(' AND ', $conditionsMaison);
    }

    $sqlMaisons = "
        SELECT
            m.id,
            m.nom AS maison_nom,
            COUNT(t.id) AS nombre_travaux,
            COALESCE(SUM(t.montant), 0) AS total_depenses
        FROM maisons m
        LEFT JOIN travaux t
            ON t.maison_id = m.id
            $conditionMaisonSQL
        WHERE m.actif = 1
        GROUP BY m.id, m.nom
        ORDER BY total_depenses DESC, m.nom ASC
    ";

    $stmt = $pdo->prepare($sqlMaisons);
    $stmt->execute($parametresMaison);

    $depensesMaisons = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // 3. Calculer les dépenses de chaque appartement
    $conditionsAppartement = [];
    $parametresAppartement = [];

    if ($dateDebut !== '') {
        $conditionsAppartement[] = 't.date_travail >= :date_debut';
        $parametresAppartement[':date_debut'] = $dateDebut;
    }

    if ($dateFin !== '') {
        $conditionsAppartement[] = 't.date_travail <= :date_fin';
        $parametresAppartement[':date_fin'] = $dateFin;
    }

    $conditionAppartementSQL = '';

    if (!empty($conditionsAppartement)) {
        $conditionAppartementSQL = ' AND ' . implode(' AND ', $conditionsAppartement);
    }

    $sqlAppartements = "
        SELECT
            a.id,
            a.numero AS appartement_numero,
            m.nom AS maison_nom,
            COUNT(t.id) AS nombre_travaux,
            COALESCE(SUM(t.montant), 0) AS total_depenses
        FROM appartements a
        INNER JOIN maisons m
            ON m.id = a.maison_id
        LEFT JOIN travaux t
            ON t.appartement_id = a.id
            AND t.maison_id = a.maison_id
            $conditionAppartementSQL
        WHERE a.actif = 1
            AND m.actif = 1
        GROUP BY a.id, a.numero, m.nom
        ORDER BY m.nom ASC, total_depenses DESC, a.numero ASC
    ";

    $stmt = $pdo->prepare($sqlAppartements);
    $stmt->execute($parametresAppartement);

    $depensesAppartements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Formater les montants en MRU
function formaterDepense($montant)
{
    return number_format((float) $montant, 0, ',', ' ') . ' MRU';
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h1>Récapitulatif des dépenses</h1>
        </div>

        <div class="page-content">

            <div class="panel">

                <h2>Filtrer les dépenses</h2>

                <form method="GET" action="">

                    <div class="form-group">
                        <label for="date_debut">Date de début</label>

                        <input
                            type="date"
                            id="date_debut"
                            name="date_debut"
                            value="<?= htmlspecialchars($dateDebut) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="date_fin">Date de fin</label>

                        <input
                            type="date"
                            id="date_fin"
                            name="date_fin"
                            value="<?= htmlspecialchars($dateFin) ?>"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Filtrer
                    </button>

                    <a href="depenses.php" class="btn btn-secondary">
                        Réinitialiser
                    </a>

                </form>

                <?php if ($erreurFiltre !== ''): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($erreurFiltre) ?>
                    </div>
                <?php endif; ?>

            </div>


            <?php if ($erreurFiltre === ''): ?>

                <!-- Statistiques générales -->

                <div class="stats-grid">

                    <div class="stat-card">
                        <h3>Dépenses totales</h3>

                        <p>
                            <?= formaterDepense($totalDepenses) ?>
                        </p>
                    </div>

                    <div class="stat-card">
                        <h3>Nombre de travaux</h3>

                        <p>
                            <?= $nombreTravaux ?>
                        </p>
                    </div>

                    <div class="stat-card">
                        <h3>Maisons concernées</h3>

                        <p>
                            <?= count($depensesMaisons) ?>
                        </p>
                    </div>

                    <div class="stat-card">
                        <h3>Appartements concernés</h3>

                        <p>
                            <?= count($depensesAppartements) ?>
                        </p>
                    </div>

                </div>


                <!-- Dépenses par maison -->

                <div class="panel">

                    <h2>Dépenses par maison</h2>

                    <div class="table-responsive">

                        <table class="table">

                            <thead>
                                <tr>
                                    <th>Maison</th>
                                    <th>Nombre de travaux</th>
                                    <th>Total des dépenses</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (empty($depensesMaisons)): ?>

                                    <tr>
                                        <td colspan="3">
                                            Aucune maison trouvée.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($depensesMaisons as $maison): ?>

                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($maison['maison_nom']) ?>
                                            </td>

                                            <td>
                                                <?= (int) $maison['nombre_travaux'] ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= formaterDepense($maison['total_depenses']) ?>
                                                </strong>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>


                <!-- Dépenses par appartement -->

                <div class="panel">

                    <h2>Dépenses par appartement</h2>

                    <div class="table-responsive">

                        <table class="table">

                            <thead>
                                <tr>
                                    <th>Maison</th>
                                    <th>Appartement</th>
                                    <th>Nombre de travaux</th>
                                    <th>Total des dépenses</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (empty($depensesAppartements)): ?>

                                    <tr>
                                        <td colspan="4">
                                            Aucun appartement trouvé.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($depensesAppartements as $appartement): ?>

                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($appartement['maison_nom']) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($appartement['appartement_numero']) ?>
                                            </td>

                                            <td>
                                                <?= (int) $appartement['nombre_travaux'] ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= formaterDepense($appartement['total_depenses']) ?>
                                                </strong>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            <?php endif; ?>

            <div style="margin-top: 20px;">
                <a href="index.php" class="btn btn-secondary">
                    Retour aux travaux
                </a>
            </div>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>