<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

// Générer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les messages
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Récupérer les locations avec les informations des appartements et des locataires
$sql = "
    SELECT
        locations.id,
        locations.date_debut,
        locations.date_fin,
        locations.loyer_mensuel,
        locations.jour_echeance,
        locations.statut,
        appartements.numero AS numero_appartement,
        maisons.nom AS nom_maison,
        locataires.nom AS nom_locataire,
        locataires.prenom AS prenom_locataire
    FROM locations
    INNER JOIN appartements
        ON locations.appartement_id = appartements.id
    INNER JOIN maisons
        ON appartements.maison_id = maisons.id
    INNER JOIN locataires
        ON locations.locataire_id = locataires.id
    ORDER BY locations.id DESC
";

$stmt = $pdo->query($sql);
$locations = $stmt->fetchAll();

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
                <p>Consultez les locations et suivez les contrats de vos locataires.</p>
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

                        <table>
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

                                        <!-- Identifiant de la location -->
                                        <td>
                                            <?= (int) $location['id'] ?>
                                        </td>

                                        <!-- Nom du locataire -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $location['nom_locataire'] . ' ' . $location['prenom_locataire'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Maison -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $location['nom_maison'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Appartement -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $location['numero_appartement'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Loyer mensuel -->
                                        <td>
                                            <?= number_format(
                                                (float) $location['loyer_mensuel'],
                                                2,
                                                ',',
                                                ' '
                                            ) ?>
                                        </td>

                                        <!-- Jour d'échéance -->
                                        <td>
                                            Le <?= (int) $location['jour_echeance'] ?>
                                        </td>

                                        <!-- Date de début -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $location['date_debut'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Date de fin -->
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

                                        <!-- Statut -->
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

                                        <!-- Actions -->
                                        <td>
                                            <?php if (estSuperAdmin()): ?>

                                                <a
                                                    href="modifier.php?id=<?= (int) $location['id'] ?>"
                                                    class="btn btn-primary"
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
