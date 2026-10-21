<?php

require_once __DIR__ . '/../includes/auth.php';

exigerConnexion();
exigerSuperAdmin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Générer un jeton CSRF si nécessaire.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$utilisateurs = [];
$erreur = '';

// Récupérer les messages de succès et d'erreur.
$messageSucces = '';
$messageErreur = '';

if (isset($_GET['succes'])) {
    switch ($_GET['succes']) {
        case 'ajout':
            $messageSucces = 'L’utilisateur a été créé avec succès.';
            break;

        case 'modification':
            $messageSucces = 'L’utilisateur a été modifié avec succès.';
            break;

        case 'desactivation':
            $messageSucces = 'Le compte a été désactivé avec succès.';
            break;

        case 'activation':
            $messageSucces = 'Le compte a été activé avec succès.';
            break;
    }
}

if (isset($_GET['erreur'])) {
    $messageErreur = 'Une erreur est survenue. Vérifiez votre demande.';
}

// Charger tous les utilisateurs et leurs maisons affectées.
try {
    $sql = "
        SELECT
            u.id,
            u.nom,
            u.email,
            u.role,
            u.actif,
            GROUP_CONCAT(
                DISTINCT m.nom
                ORDER BY m.nom ASC
                SEPARATOR ', '
            ) AS maisons
        FROM utilisateurs u
        LEFT JOIN affectations_admins aa
            ON aa.utilisateur_id = u.id
        LEFT JOIN maisons m
            ON m.id = aa.maison_id
        GROUP BY
            u.id,
            u.nom,
            u.email,
            u.role,
            u.actif
        ORDER BY
            CASE
                WHEN u.role = 'super_admin' THEN 0
                ELSE 1
            END,
            u.nom ASC
    ";

    $stmt = $pdo->query($sql);
    $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log(
        'Erreur chargement utilisateurs : ' . $e->getMessage()
    );

    $erreur =
        'Impossible de charger la liste des utilisateurs pour le moment.';
}

// Calculer les statistiques.
$totalUtilisateurs = count($utilisateurs);
$totalAdmins = 0;
$totalSuperAdmins = 0;
$totalActifs = 0;
$totalInactifs = 0;

foreach ($utilisateurs as $utilisateur) {

    if ($utilisateur['role'] === 'super_admin') {
        $totalSuperAdmins++;
    } else {
        $totalAdmins++;
    }

    if ((int) $utilisateur['actif'] === 1) {
        $totalActifs++;
    } else {
        $totalInactifs++;
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h1>Gestion des utilisateurs</h1>
        </div>

        <div class="page-content">

            <div class="page-heading">

                <div>
                    <h2>Liste des utilisateurs</h2>

                    <p>
                        Consultez les comptes, les rôles et les maisons
                        affectées à chaque administrateur.
                    </p>
                </div>

                <a
                    href="/ImmoGestion/utilisateurs/ajouter.php"
                    class="btn btn-primary"
                >
                    + Ajouter un administrateur
                </a>

            </div>

            <?php if ($messageSucces !== ''): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars(
                        $messageSucces,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <?php if ($messageErreur !== ''): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars(
                        $messageErreur,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <?php if ($erreur !== ''): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars(
                        $erreur,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php else: ?>

                <!-- Statistiques -->

                <div style="
                    display: grid;
                    grid-template-columns: repeat(
                        auto-fit,
                        minmax(180px, 1fr)
                    );
                    gap: 15px;
                    margin-bottom: 25px;
                ">

                    <div class="panel">
                        <h3>Total utilisateurs</h3>
                        <p style="font-size: 28px; font-weight: bold;">
                            <?= $totalUtilisateurs ?>
                        </p>
                    </div>

                    <div class="panel">
                        <h3>Administrateurs</h3>
                        <p style="font-size: 28px; font-weight: bold;">
                            <?= $totalAdmins ?>
                        </p>
                    </div>

                    <div class="panel">
                        <h3>Super-administrateurs</h3>
                        <p style="font-size: 28px; font-weight: bold;">
                            <?= $totalSuperAdmins ?>
                        </p>
                    </div>

                    <div class="panel">
                        <h3>Comptes actifs</h3>
                        <p style="font-size: 28px; font-weight: bold;">
                            <?= $totalActifs ?>
                        </p>
                    </div>

                </div>

                <!-- Tableau des utilisateurs -->

                <div class="panel">

                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        flex-wrap: wrap;
                        gap: 10px;
                        margin-bottom: 20px;
                    ">

                        <h3>Comptes enregistrés</h3>

                        <span>
                            <?= $totalInactifs ?>
                            compte(s) inactif(s)
                        </span>

                    </div>

                    <?php if (empty($utilisateurs)): ?>

                        <p>
                            Aucun utilisateur n'a été enregistré.
                        </p>

                    <?php else: ?>

                        <div style="overflow-x: auto;">

                            <table class="table">

                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Adresse e-mail</th>
                                        <th>Rôle</th>
                                        <th>Maisons affectées</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($utilisateurs as $utilisateur): ?>

                                        <tr>

                                            <td>
                                                <?= (int) $utilisateur['id'] ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $utilisateur['nom'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $utilisateur['email'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <td>

                                                <?php if (
                                                    $utilisateur['role'] === 'super_admin'
                                                ): ?>

                                                    <span class="badge badge-info">
                                                        Super-administrateur
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-secondary">
                                                        Administrateur
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php if (
                                                    $utilisateur['role'] === 'super_admin'
                                                ): ?>

                                                    <span class="badge badge-info">
                                                        Toutes les maisons
                                                    </span>

                                                <?php elseif (
                                                    !empty($utilisateur['maisons'])
                                                ): ?>

                                                    <?= htmlspecialchars(
                                                        $utilisateur['maisons'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                <?php else: ?>

                                                    <span class="badge badge-warning">
                                                        Aucune maison affectée
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php if (
                                                    (int) $utilisateur['actif'] === 1
                                                ): ?>

                                                    <span class="badge badge-success">
                                                        Actif
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-danger">
                                                        Inactif
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <div style="
                                                    display: flex;
                                                    gap: 6px;
                                                    flex-wrap: wrap;
                                                ">

                                                    <a
                                                        href="/ImmoGestion/utilisateurs/modifier.php?id=<?= (int) $utilisateur['id'] ?>"
                                                        class="btn btn-secondary btn-sm"
                                                    >
                                                        Modifier
                                                    </a>

                                                    <?php if (
                                                        $utilisateur['role'] !== 'super_admin'
                                                        && (int) $utilisateur['id']
                                                            !== (int) $_SESSION['utilisateur_id']
                                                    ): ?>

                                                        <form
                                                            method="POST"
                                                            action="/ImmoGestion/utilisateurs/supprimer.php"
                                                            onsubmit="return confirm('Voulez-vous vraiment changer le statut de ce compte ?');"
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
                                                                value="<?= (int) $utilisateur['id'] ?>"
                                                            >

                                                            <?php if (
                                                                (int) $utilisateur['actif'] === 1
                                                            ): ?>

                                                                <button
                                                                    type="submit"
                                                                    class="btn btn-danger btn-sm"
                                                                >
                                                                    Désactiver
                                                                </button>

                                                            <?php else: ?>

                                                                <button
                                                                    type="submit"
                                                                    class="btn btn-primary btn-sm"
                                                                >
                                                                    Réactiver
                                                                </button>

                                                            <?php endif; ?>

                                                        </form>

                                                    <?php endif; ?>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>
