<?php

require_once __DIR__ . '/../includes/auth.php';

exigerConnexion();

$titrePage = 'Gestion des paiements';

$utilisateurId = (int) $_SESSION['utilisateur_id'];
$superAdmin = estSuperAdmin();

/*
|--------------------------------------------------------------------------
| Jeton CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Messages de retour
|--------------------------------------------------------------------------
*/

$messageSucces = $_SESSION['message_succes'] ?? '';
$messageErreur = $_SESSION['message_erreur'] ?? '';

unset(
    $_SESSION['message_succes'],
    $_SESSION['message_erreur']
);

/*
|--------------------------------------------------------------------------
| Filtre des maisons accessibles
|--------------------------------------------------------------------------
*/

$filtreMaison = '';

$parametres = [];

if (!$superAdmin) {
    $filtreMaison = "
        AND EXISTS (
            SELECT 1
            FROM affectations_admins aa
            WHERE aa.maison_id = m.id
              AND aa.utilisateur_id = :utilisateur_id
        )
    ";

    $parametres['utilisateur_id'] = $utilisateurId;
}

/*
|--------------------------------------------------------------------------
| Récupérer les paiements accessibles
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.montant,
        p.periode_mois,
        p.date_paiement,
        p.mode_paiement,
        p.reference,
        p.statut,
        p.commentaire,

        l.id AS location_id,
        l.loyer_mensuel,

        loc.nom AS nom_locataire,
        loc.prenom AS prenom_locataire,

        a.numero AS numero_appartement,
        m.nom AS nom_maison,

        u.nom AS nom_utilisateur

    FROM paiements p

    INNER JOIN locations l
        ON p.location_id = l.id

    INNER JOIN locataires loc
        ON l.locataire_id = loc.id

    INNER JOIN appartements a
        ON l.appartement_id = a.id

    INNER JOIN maisons m
        ON a.maison_id = m.id

    LEFT JOIN utilisateurs u
        ON p.enregistre_par = u.id

    WHERE 1 = 1
    $filtreMaison

    ORDER BY p.date_paiement DESC, p.id DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametres);

    $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Statistiques limitées aux maisons accessibles
    |--------------------------------------------------------------------------
    */

    $sqlStats = "
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN p.statut = 'valide'
                        THEN p.montant
                        ELSE 0
                    END
                ),
                0
            ) AS total_paiements,

            SUM(
                CASE
                    WHEN p.statut = 'valide'
                    THEN 1
                    ELSE 0
                END
            ) AS nombre_valides,

            SUM(
                CASE
                    WHEN p.statut = 'annule'
                    THEN 1
                    ELSE 0
                END
            ) AS nombre_annules

        FROM paiements p

        INNER JOIN locations l
            ON p.location_id = l.id

        INNER JOIN appartements a
            ON l.appartement_id = a.id

        INNER JOIN maisons m
            ON a.maison_id = m.id

        WHERE 1 = 1
        $filtreMaison
    ";

    $stmtStats = $pdo->prepare($sqlStats);
    $stmtStats->execute($parametres);

    $statistiques = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $totalPaiements = (float) ($statistiques['total_paiements'] ?? 0);
    $nombrePaiements = (int) ($statistiques['nombre_valides'] ?? 0);
    $nombrePaiementsAnnules = (int) ($statistiques['nombre_annules'] ?? 0);

} catch (PDOException $e) {
    error_log(
        'Erreur lors du chargement des paiements : '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('Impossible de charger les paiements pour le moment.');
}

/*
|--------------------------------------------------------------------------
| Fonctions d'affichage
|--------------------------------------------------------------------------
*/

function echapperPaiementIndex($valeur): string
{
    return htmlspecialchars(
        (string) $valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formaterMontantPaiement($montant): string
{
    return number_format(
        (float) $montant,
        2,
        ',',
        ' '
    );
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h2>Gestion des paiements</h2>
        </div>

        <div class="page-content">

            <!-- Messages -->
            <?php if ($messageSucces !== ''): ?>
                <div class="alert alert-success">
                    <?= echapperPaiementIndex($messageSucces) ?>
                </div>
            <?php endif; ?>

            <?php if ($messageErreur !== ''): ?>
                <div class="alert alert-danger">
                    <?= echapperPaiementIndex($messageErreur) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-grid">

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total des paiements validés</h3>

                        <p>
                            <?= formaterMontantPaiement($totalPaiements) ?>
                            MRU
                        </p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Paiements validés</h3>

                        <p><?= $nombrePaiements ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Paiements annulés</h3>

                        <p><?= $nombrePaiementsAnnules ?></p>
                    </div>
                </div>

            </div>

            <!-- Historique -->
            <div class="panel">

                <div class="panel-header">

                    <h3>Historique des paiements</h3>

                    <a
                        href="/ImmoGestion/paiements/ajouter.php"
                        class="btn btn-primary"
                    >
                        + Enregistrer un paiement
                    </a>

                </div>

                <div class="panel-body">

                    <div class="table-responsive">

                        <table class="table">

                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Locataire</th>
                                    <th>Maison / Appartement</th>
                                    <th>Montant</th>
                                    <th>Période payée</th>
                                    <th>Date du paiement</th>
                                    <th>Mode de paiement</th>
                                    <th>Enregistré par</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (empty($paiements)): ?>

                                    <tr>
                                        <td colspan="10" class="text-center">
                                            Aucun paiement accessible pour le moment.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($paiements as $paiement): ?>

                                        <?php
                                        $datePeriode = strtotime(
                                            $paiement['periode_mois']
                                        );

                                        $dateReglement = strtotime(
                                            $paiement['date_paiement']
                                        );

                                        $modes = [
                                            'especes' => 'Espèces',
                                            'virement' => 'Virement',
                                            'mobile_money' => 'Mobile Money',
                                            'autre' => 'Autre'
                                        ];

                                        $statut = $paiement['statut'];
                                        ?>

                                        <tr>

                                            <td>
                                                <?= (int) $paiement['id'] ?>
                                            </td>

                                            <td>
                                                <?= echapperPaiementIndex(
                                                    $paiement['nom_locataire']
                                                    . ' '
                                                    . $paiement['prenom_locataire']
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= echapperPaiementIndex(
                                                    $paiement['nom_maison']
                                                ) ?>
                                                /
                                                <?= echapperPaiementIndex(
                                                    $paiement['numero_appartement']
                                                ) ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= formaterMontantPaiement(
                                                        $paiement['montant']
                                                    ) ?>
                                                    MRU
                                                </strong>
                                            </td>

                                            <td>
                                                <?= $datePeriode !== false
                                                    ? date('m/Y', $datePeriode)
                                                    : '—' ?>
                                            </td>

                                            <td>
                                                <?= $dateReglement !== false
                                                    ? date('d/m/Y H:i', $dateReglement)
                                                    : '—' ?>
                                            </td>

                                            <td>
                                                <?= echapperPaiementIndex(
                                                    $modes[$paiement['mode_paiement']]
                                                    ?? $paiement['mode_paiement']
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= echapperPaiementIndex(
                                                    $paiement['nom_utilisateur']
                                                    ?? 'Utilisateur inconnu'
                                                ) ?>
                                            </td>

                                            <td>

                                                <?php if ($statut === 'valide'): ?>

                                                    <span class="badge badge-success">
                                                        Validé
                                                    </span>

                                                <?php elseif ($statut === 'annule'): ?>

                                                    <span class="badge badge-danger">
                                                        Annulé
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-secondary">
                                                        Inconnu
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php if (
                                                    $superAdmin
                                                    && $statut === 'valide'
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="/ImmoGestion/paiements/annuler.php"
                                                        onsubmit="return confirm('Voulez-vous vraiment annuler ce paiement ? Il sera conservé dans l’historique.');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= echapperPaiementIndex(
                                                                $_SESSION['csrf_token']
                                                            ) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $paiement['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger"
                                                        >
                                                            Annuler
                                                        </button>

                                                    </form>

                                                <?php elseif ($statut === 'annule'): ?>

                                                    <span>Déjà annulé</span>

                                                <?php else: ?>

                                                    <span>—</span>

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
