<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

// Génération du jeton CSRF
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les travaux avec les informations associées
$sql = "
    SELECT
        t.id,
        t.titre,
        t.description,
        t.montant,
        t.date_travail,
        t.statut,
        m.nom AS maison_nom,
        a.numero AS appartement_numero,
        u.nom AS utilisateur_nom
    FROM travaux t
    INNER JOIN maisons m ON t.maison_id = m.id
    LEFT JOIN appartements a ON t.appartement_id = a.id
    LEFT JOIN utilisateurs u ON t.enregistre_par = u.id
    ORDER BY t.date_travail DESC, t.id DESC
";

$stmt = $pdo->query($sql);
$travaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des statistiques
$stats = [
    'total' => count($travaux),
    'prevu' => 0,
    'en_cours' => 0,
    'termine' => 0,
    'montant' => 0
];

foreach ($travaux as $travail) {
    if (isset($stats[$travail['statut']])) {
        $stats[$travail['statut']]++;
    }

    $stats['montant'] += (float) $travail['montant'];
}

// Formater les montants en MRU
function formaterMontant($montant)
{
    return number_format((float) $montant, 0, ',', ' ') . ' MRU';
}

// Libellés et classes CSS des statuts
$statuts = [
    'prevu' => ['Prévu', 'badge-warning'],
    'en_cours' => ['En cours', 'badge-info'],
    'termine' => ['Terminé', 'badge-success']
];

// Messages de confirmation
$messages = [
    'ajout' => 'Le travail a été ajouté avec succès.',
    'modification' => 'Le travail a été modifié avec succès.',
    'suppression' => 'Le travail a été supprimé avec succès.'
];

$succes = $_GET['succes'] ?? '';
$messageSucces = $messages[$succes] ?? '';

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h1>Gestion des travaux</h1>
        </div>

        <div class="page-content">

            <div class="page-header">

                <div>
                    <h2>Travaux immobiliers</h2>
                    <p>
                        Suivez les réparations et les dépenses de vos biens.
                    </p>
                </div>

                <a
                    href="/ImmoGestion/travaux/ajouter.php"
                    class="btn btn-primary"
                >
                    + Ajouter un travail
                </a>

            </div>

            <?php if ($messageSucces !== ''): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($messageSucces) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['erreur'])): ?>
                <div class="alert alert-danger">
                    <?php
                    $erreurs = [
                        'id' => 'Identifiant de travail invalide.',
                        'introuvable' => 'Le travail demandé est introuvable.'
                    ];

                    $codeErreur = $_GET['erreur'];

                    echo htmlspecialchars(
                        $erreurs[$codeErreur] ?? 'Une erreur est survenue.'
                    );
                    ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->

            <div class="stats-grid">

                <div class="stat-card">
                    <h3>Total des travaux</h3>
                    <p><?= $stats['total'] ?></p>
                </div>

                <div class="stat-card">
                    <h3>Travaux prévus</h3>
                    <p><?= $stats['prevu'] ?></p>
                </div>

                <div class="stat-card">
                    <h3>Travaux en cours</h3>
                    <p><?= $stats['en_cours'] ?></p>
                </div>

                <div class="stat-card">
                    <h3>Travaux terminés</h3>
                    <p><?= $stats['termine'] ?></p>
                </div>

                <div class="stat-card">
                    <h3>Dépenses enregistrées</h3>
                    <p><?= formaterMontant($stats['montant']) ?></p>
                </div>

            </div>

            <!-- Historique des travaux -->

            <div class="panel">

                <div class="panel-header">
                    <h3>Historique des travaux</h3>
                </div>

                <div class="table-responsive">

                    <table class="table">

                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Maison</th>
                                <th>Appartement</th>
                                <th>Montant</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Enregistré par</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($travaux)): ?>

                                <tr>
                                    <td colspan="8" class="text-center">
                                        Aucun travail enregistré pour le moment.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($travaux as $travail): ?>

                                    <?php
                                    $statut = $statuts[$travail['statut']]
                                        ?? ['Inconnu', 'badge-secondary'];
                                    ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?= htmlspecialchars($travail['titre']) ?>
                                            </strong>

                                            <?php if (!empty($travail['description'])): ?>
                                                <br>
                                                <small>
                                                    <?= nl2br(htmlspecialchars($travail['description'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($travail['maison_nom']) ?>
                                        </td>

                                        <td>
                                            <?php if ($travail['appartement_numero'] !== null): ?>
                                                <?= htmlspecialchars($travail['appartement_numero']) ?>
                                            <?php else: ?>
                                                Toute la maison
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= formaterMontant($travail['montant']) ?>
                                        </td>

                                        <td>
                                            <?= date(
                                                'd/m/Y',
                                                strtotime($travail['date_travail'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <span class="badge <?= htmlspecialchars($statut[1]) ?>">
                                                <?= htmlspecialchars($statut[0]) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $travail['utilisateur_nom']
                                                ?? 'Utilisateur inconnu'
                                            ) ?>
                                        </td>

                                        <td>

                                            <a
                                                href="/ImmoGestion/travaux/modifier.php?id=<?= (int) $travail['id'] ?>"
                                                class="btn btn-sm btn-secondary"
                                            >
                                                Modifier
                                            </a>

                                            <?php if (estSuperAdmin()): ?>

                                                <form
                                                    method="POST"
                                                    action="/ImmoGestion/travaux/supprimer.php"
                                                    style="display: inline;"
                                                    onsubmit="return confirm('Voulez-vous vraiment supprimer ce travail ?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $travail['id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-danger"
                                                    >
                                                        Supprimer
                                                    </button>

                                                </form>

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

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>

