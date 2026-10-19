<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

$titrePage = 'Gestion des paiements';

// Créer le jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer le message de confirmation
$messageSucces = $_SESSION['message_succes'] ?? '';
unset($_SESSION['message_succes']);

// Récupérer tous les paiements
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
    INNER JOIN locations l ON p.location_id = l.id
    INNER JOIN locataires loc ON l.locataire_id = loc.id
    INNER JOIN appartements a ON l.appartement_id = a.id
    INNER JOIN maisons m ON a.maison_id = m.id
    INNER JOIN utilisateurs u ON p.enregistre_par = u.id
    ORDER BY p.date_paiement DESC, p.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$paiements = $stmt->fetchAll();

// Calculer le total des paiements validés
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(montant), 0)
    FROM paiements
    WHERE statut = 'valide'
");
$stmt->execute();
$totalPaiements = (float) $stmt->fetchColumn();

// Compter les paiements validés
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM paiements
    WHERE statut = 'valide'
");
$stmt->execute();
$nombrePaiements = (int) $stmt->fetchColumn();

// Compter les paiements annulés
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM paiements
    WHERE statut = 'annule'
");
$stmt->execute();
$nombrePaiementsAnnules = (int) $stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h2>Gestion des paiements</h2>
        </div>

        <div class="page-content">

            <?php if ($messageSucces !== ''): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars(
                        $messageSucces,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-grid">

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total des paiements validés</h3>
                        <p>
                            <?= number_format($totalPaiements, 0, ',', ' ') ?>
                            FCFA
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

            <!-- Tableau des paiements -->
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
                                            Aucun paiement enregistré pour le moment.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($paiements as $paiement): ?>

                                        <tr>

                                            <!-- Identifiant -->
                                            <td>
                                                <?= (int) $paiement['id'] ?>
                                            </td>

                                            <!-- Locataire -->
                                            <td>
                                                <?= htmlspecialchars(
                                                    $paiement['nom_locataire'] . ' ' .
                                                    $paiement['prenom_locataire'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <!-- Maison et appartement -->
                                            <td>
                                                <?= htmlspecialchars(
                                                    $paiement['nom_maison'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                                /
                                                <?= htmlspecialchars(
                                                    $paiement['numero_appartement'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <!-- Montant -->
                                            <td>
                                                <strong>
                                                    <?= number_format(
                                                        (float) $paiement['montant'],
                                                        0,
                                                        ',',
                                                        ' '
                                                    ) ?>
                                                    FCFA
                                                </strong>
                                            </td>

                                            <!-- Période payée -->
                                            <td>
                                                <?= date(
                                                    'm/Y',
                                                    strtotime($paiement['periode_mois'])
                                                ) ?>
                                            </td>

                                            <!-- Date du paiement -->
                                            <td>
                                                <?= date(
                                                    'd/m/Y H:i',
                                                    strtotime($paiement['date_paiement'])
                                                ) ?>
                                            </td>

                                            <!-- Mode de paiement -->
                                            <td>
                                                <?php
                                                $modes = [
                                                    'especes' => 'Espèces',
                                                    'virement' => 'Virement',
                                                    'mobile_money' => 'Mobile Money',
                                                    'autre' => 'Autre'
                                                ];
                                                ?>

                                                <?= htmlspecialchars(
                                                    $modes[$paiement['mode_paiement']]
                                                        ?? $paiement['mode_paiement'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <!-- Utilisateur ayant enregistré le paiement -->
                                            <td>
                                                <?= htmlspecialchars(
                                                    $paiement['nom_utilisateur'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <!-- Statut -->
                                            <td>
                                                <?php if ($paiement['statut'] === 'valide'): ?>

                                                    <span class="badge badge-success">
                                                        Validé
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-danger">
                                                        Annulé
                                                    </span>

                                                <?php endif; ?>
                                            </td>

                                            <!-- Actions -->
                                            <td>

                                                <?php if (
                                                    estSuperAdmin() &&
                                                    $paiement['statut'] === 'valide'
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="/ImmoGestion/paiements/annuler.php"
                                                        onsubmit="return confirm('Voulez-vous vraiment annuler ce paiement ? Il sera conservé dans l’historique.');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= htmlspecialchars(
                                                                $_SESSION['csrf_token'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
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

                                                <?php elseif ($paiement['statut'] === 'annule'): ?>

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

