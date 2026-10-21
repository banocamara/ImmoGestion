<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

// Générer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer le message
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Récupérer les locations
$sql = "
    SELECT
        l.id,
        l.date_debut,
        l.date_fin,
        l.loyer_mensuel,
        l.jour_echeance,
        l.statut,
        a.numero AS numero_appartement,
        m.nom AS nom_maison,
        loc.nom AS nom_locataire,
        loc.prenom AS prenom_locataire
    FROM locations l
    INNER JOIN appartements a ON l.appartement_id = a.id
    INNER JOIN maisons m ON a.maison_id = m.id
    INNER JOIN locataires loc ON l.locataire_id = loc.id
";

$params = [];

// Un administrateur ne consulte que les locations de ses maisons attribuées
if (!estSuperAdmin()) {
    $sql .= "
        INNER JOIN affectations_admins aa
            ON aa.maison_id = m.id
    ";

    $sql .= "
        WHERE aa.utilisateur_id = :utilisateur_id
    ";

    $params['utilisateur_id'] = $_SESSION['utilisateur_id']
        ?? $_SESSION['user_id']
        ?? 0;
}

$sql .= " ORDER BY l.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titrePage = 'Gestion des locations';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <header class="topbar">
            <h1>Gestion des locations</h1>
        </header>

        <section class="page-content">

            <div class="page-heading">
                <h2>Contrats de location</h2>
                <p>
                    Consultez les locations et suivez les contrats de vos locataires.
                </p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="panel">

                <div class="panel-header">
                    <h3>Liste des locations</h3>

                    <?php if (estSuperAdmin()): ?>
                        <a href="ajouter.php" class="btn btn-primary">
                            + Nouvelle location
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($locations)): ?>

                    <p>Aucune location enregistrée pour le moment.</p>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table">

                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Locataire</th>
                                    <th>Maison</th>
                                    <th>Appartement</th>
                                    <th>Loyer mensuel</th>
                                    <th>Échéance</th>
                                    <th>Date de début</th>
                                    <th>Date de fin</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($locations as $location): ?>

                                    <tr>

                                        <td>
                                            <?= (int) $location['id'] ?>
                                        </td>

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
                                                2,
                                                ',',
                                                ' '
                                            ) ?> MRU
                                        </td>

                                        <td>
                                            Le <?= (int) $location['jour_echeance'] ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $location['date_debut'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?php if ($location['date_fin'] !== null): ?>
                                                <?= htmlspecialchars(
                                                    $location['date_fin'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            <?php else: ?>
                                                Non définie
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($location['statut'] === 'active'): ?>

                                                <span class="badge badge-success">
                                                    Active
                                                </span>

                                            <?php elseif ($location['statut'] === 'terminee'): ?>

                                                <span class="badge badge-secondary">
                                                    Terminée
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-danger">
                                                    Résiliée
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if (estSuperAdmin()): ?>

                                                <a
                                                    href="modifier.php?id=<?= (int) $location['id'] ?>"
                                                    class="btn btn-primary btn-sm"
                                                >
                                                    Modifier
                                                </a>

                                            <?php else: ?>

                                                <span>Consultation</span>

                                            <?php endif; ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>
