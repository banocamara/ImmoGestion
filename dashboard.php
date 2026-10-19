<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

exigerConnexion();

$nom = $_SESSION['nom'] ?? 'Utilisateur';

// Mois en cours
$debutMois = date('Y-m-01');
$finMois = date('Y-m-t');

// Initialiser les statistiques
$nombreMaisons = 0;
$nombreAppartements = 0;
$nombreAppartementsLibres = 0;
$nombreAppartementsOccupes = 0;
$nombreLocataires = 0;
$nombreUtilisateurs = 0;
$totalPaiements = 0;
$totalDepenses = 0;
$totalLoyersAttendus = 0;
$totalLoyersRestants = 0;
$derniersPaiements = [];

try {
    // Nombre de maisons actives
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM maisons WHERE actif = 1"
    );
    $nombreMaisons = (int) $stmt->fetchColumn();

    // Nombre d'appartements actifs
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM appartements WHERE actif = 1"
    );
    $nombreAppartements = (int) $stmt->fetchColumn();

    // Appartements libres
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM appartements
         WHERE actif = 1 AND statut = 'libre'"
    );
    $nombreAppartementsLibres = (int) $stmt->fetchColumn();

    // Appartements occupés
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM appartements
         WHERE actif = 1 AND statut = 'occupe'"
    );
    $nombreAppartementsOccupes = (int) $stmt->fetchColumn();

    // Nombre de locataires actifs
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM locataires
         WHERE actif = 1"
    );
    $nombreLocataires = (int) $stmt->fetchColumn();

    // Nombre d'utilisateurs : super-administrateur uniquement
    if (estSuperAdmin()) {
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE actif = 1"
        );
        $nombreUtilisateurs = (int) $stmt->fetchColumn();
    }

    /*
     * Paiements valides du mois en cours.
     * On utilise la date de paiement pour déterminer le mois d'encaissement.
     */
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(montant), 0)
         FROM paiements
         WHERE statut = 'valide'
           AND date_paiement >= :debut
           AND date_paiement <= :fin"
    );

    $stmt->execute([
        ':debut' => $debutMois,
        ':fin' => $finMois
    ]);

    $totalPaiements = (float) $stmt->fetchColumn();

    /*
     * Dépenses de travaux du mois en cours.
     * Les montants incluent tous les statuts de travaux.
     */
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(montant), 0)
         FROM travaux
         WHERE date_travail >= :debut
           AND date_travail <= :fin"
    );

    $stmt->execute([
        ':debut' => $debutMois,
        ':fin' => $finMois
    ]);

    $totalDepenses = (float) $stmt->fetchColumn();

    /*
     * Loyers attendus et restants.
     * Une location active est comptée si elle couvre au moins un jour
     * du mois en cours.
     */
    $stmt = $pdo->prepare(
        "SELECT
            l.id,
            l.loyer_mensuel,
            COALESCE(
                (
                    SELECT SUM(p.montant)
                    FROM paiements p
                    WHERE p.location_id = l.id
                      AND p.statut = 'valide'
                      AND p.periode_mois = :periode
                ),
                0
            ) AS montant_paye
         FROM locations l
         WHERE l.statut = 'active'
           AND l.date_debut <= :fin
           AND (l.date_fin IS NULL OR l.date_fin >= :debut)"
    );

    $stmt->execute([
        ':periode' => date('Y-m'),
        ':debut' => $debutMois,
        ':fin' => $finMois
    ]);

    $locationsActives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($locationsActives as $location) {
        $loyer = (float) $location['loyer_mensuel'];
        $paye = (float) $location['montant_paye'];

        $totalLoyersAttendus += $loyer;
        $totalLoyersRestants += max(0, $loyer - $paye);
    }

    /*
     * Derniers paiements enregistrés.
     */
    $stmt = $pdo->query(
        "SELECT
            p.montant,
            p.periode_mois,
            p.date_paiement,
            p.mode_paiement,
            p.statut,
            loc.nom AS locataire_nom,
            loc.prenom AS locataire_prenom,
            a.numero AS appartement_numero,
            m.nom AS maison_nom
         FROM paiements p
         INNER JOIN locations l ON p.location_id = l.id
         INNER JOIN locataires loc ON l.locataire_id = loc.id
         INNER JOIN appartements a ON l.appartement_id = a.id
         INNER JOIN maisons m ON a.maison_id = m.id
         ORDER BY p.date_paiement DESC, p.id DESC
         LIMIT 5"
    );

    $derniersPaiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erreur tableau de bord ImmoGestion : ' . $e->getMessage());

    http_response_code(500);
    exit(
        'Une erreur est survenue lors du chargement du tableau de bord. '
        . 'Consultez les journaux PHP pour plus de détails.'
    );
}

// Formater les montants en MRU
function formaterMontantDashboard($montant)
{
    return number_format((float) $montant, 0, ',', ' ') . ' MRU';
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tableau de bord - ImmoGestion</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="app-layout">

    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="main-content">

        <header class="topbar">

            <div style="display: flex; align-items: center; gap: 12px;">

                <button
                    type="button"
                    class="menu-toggle"
                    id="menuToggle"
                    aria-label="Ouvrir le menu"
                >
                    ☰
                </button>

                <h1>Tableau de bord</h1>

            </div>

            <div class="user-info">
                Bonjour,
                <strong>
                    <?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>

        </header>

        <section class="page-content">

            <div class="page-heading">

                <h2>Vue d'ensemble</h2>

                <p>
                    Bienvenue dans votre espace de gestion immobilière.
                </p>

                <p>
                    <strong>Mois :</strong>
                    <?= htmlspecialchars(date('m/Y'), ENT_QUOTES, 'UTF-8') ?>
                </p>

            </div>


            <!-- Statistiques générales -->

            <div class="stats-grid">

                <article class="stat-card">
                    <div class="stat-icon">🏠</div>
                    <p>Maisons actives</p>
                    <h3><?= $nombreMaisons ?></h3>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">🏢</div>
                    <p>Appartements actifs</p>
                    <h3><?= $nombreAppartements ?></h3>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">🟢</div>
                    <p>Appartements libres</p>
                    <h3><?= $nombreAppartementsLibres ?></h3>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">🔑</div>
                    <p>Appartements occupés</p>
                    <h3><?= $nombreAppartementsOccupes ?></h3>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">👥</div>
                    <p>Locataires actifs</p>
                    <h3><?= $nombreLocataires ?></h3>
                </article>

                <?php if (estSuperAdmin()): ?>

                    <article class="stat-card">
                        <div class="stat-icon">⚙️</div>
                        <p>Utilisateurs actifs</p>
                        <h3><?= $nombreUtilisateurs ?></h3>
                    </article>

                <?php endif; ?>

            </div>


            <!-- Statistiques financières -->

            <h2 style="margin-top: 30px;">Finances du mois</h2>

            <div class="stats-grid">

                <article class="stat-card">
                    <div class="stat-icon">💰</div>
                    <p>Loyers encaissés</p>
                    <h3><?= formaterMontantDashboard($totalPaiements) ?></h3>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">📋</div>
                    <p>Loyers attendus</p>
                    <h3><?= formaterMontantDashboard($totalLoyersAttendus) ?></h3>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <p>Loyers restant à payer</p>
                    <h3><?= formaterMontantDashboard($totalLoyersRestants) ?></h3>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">🧾</div>
                    <p>Dépenses de travaux</p>
                    <h3><?= formaterMontantDashboard($totalDepenses) ?></h3>
                </article>

            </div>


            <!-- Derniers paiements -->

            <section class="panel" style="margin-top: 30px;">

                <h3>📑 Derniers paiements enregistrés</h3>

                <div class="table-responsive">

                    <table class="table">

                        <thead>
                            <tr>
                                <th>Locataire</th>
                                <th>Maison</th>
                                <th>Appartement</th>
                                <th>Période</th>
                                <th>Date de paiement</th>
                                <th>Montant</th>
                                <th>Mode</th>
                                <th>Statut</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($derniersPaiements)): ?>

                                <tr>
                                    <td colspan="8">
                                        Aucun paiement enregistré.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($derniersPaiements as $paiement): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars(
                                                trim(
                                                    $paiement['locataire_nom']
                                                    . ' '
                                                    . $paiement['locataire_prenom']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $paiement['maison_nom'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $paiement['appartement_numero'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $paiement['periode_mois'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $paiement['date_paiement'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= formaterMontantDashboard($paiement['montant']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $paiement['mode_paiement'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>

                                            <?php if ($paiement['statut'] === 'valide'): ?>

                                                <span class="badge badge-success">
                                                    Validé
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-secondary">
                                                    Annulé
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <div style="margin-top: 15px;">

                    <a href="paiements/index.php" class="btn btn-primary">
                        💰 Voir tous les paiements
                    </a>

                </div>

            </section>


            <!-- Présentation -->

            <section class="panel" style="margin-top: 25px;">

                <h3>Bienvenue sur ImmoGestion</h3>

                <p>
                    Votre application centralise la gestion des maisons,
                    des appartements, des locataires, des paiements et des travaux.
                </p>

                <p>
                    <strong>Votre rôle :</strong>
                    <?= htmlspecialchars(
                        $_SESSION['role'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

            </section>


            <!-- Déconnexion -->

            <form action="logout.php" method="POST" style="margin-top: 20px;">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <button type="submit" class="btn">
                    🚪 Se déconnecter
                </button>

            </form>

        </section>

        <?php require_once __DIR__ . '/includes/footer.php'; ?>

    </main>

</div>

<script src="assets/js/script.js"></script>

</body>
</html>

