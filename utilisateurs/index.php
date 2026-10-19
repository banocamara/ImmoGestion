<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();
exigerSuperAdmin();

// Initialiser le jeton CSRF si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les utilisateurs et leurs maisons affectées
$sql = "
    SELECT
        u.id,
        u.nom,
        u.email,
        u.role,
        u.actif,
        u.created_at,
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
        u.actif,
        u.created_at
    ORDER BY u.id DESC
";

$stmt = $pdo->query($sql);
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Messages
$message = '';
$typeMessage = 'success';

if (isset($_GET['succes'])) {
    if ($_GET['succes'] === 'ajout') {
        $message = 'Utilisateur créé avec succès.';
    } elseif ($_GET['succes'] === 'modification') {
        $message = 'Utilisateur modifié avec succès.';
    } elseif ($_GET['succes'] === 'desactivation') {
        $message = 'Utilisateur désactivé avec succès.';
    }
}

if (isset($_GET['erreur'])) {
    $typeMessage = 'danger';

    if ($_GET['erreur'] === 'propre_compte') {
        $message = 'Vous ne pouvez pas désactiver votre propre compte.';
    } elseif ($_GET['erreur'] === 'super_admin') {
        $message = 'Un super-administrateur ne peut pas être désactivé depuis cette page.';
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
                <h2>Liste des utilisateurs</h2>
                <p>
                    Gérez les administrateurs et leurs affectations aux maisons.
                </p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($typeMessage, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div style="margin-bottom: 20px;">
                <a
                    href="/ImmoGestion/utilisateurs/ajouter.php"
                    class="btn btn-primary"
                >
                    ➕ Ajouter un administrateur
                </a>
            </div>

            <div class="panel">

                <div class="table-responsive">

                    <table class="table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Maison(s) affectée(s)</th>
                                <th>Statut</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($utilisateurs)): ?>

                                <tr>
                                    <td colspan="8">
                                        Aucun utilisateur enregistré.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($utilisateurs as $utilisateur): ?>

                                    <tr>

                                        <!-- ID -->
                                        <td>
                                            <?= (int) $utilisateur['id'] ?>
                                        </td>

                                        <!-- Nom -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $utilisateur['nom'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Email -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $utilisateur['email'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Rôle -->
                                        <td>
                                            <?php if ($utilisateur['role'] === 'super_admin'): ?>

                                                <span class="badge badge-info">
                                                    Super-administrateur
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-secondary">
                                                    Administrateur
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <!-- Maisons affectées -->
                                        <td>

                                            <?php if (!empty($utilisateur['maisons'])): ?>

                                                <?= htmlspecialchars(
                                                    $utilisateur['maisons'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            <?php elseif ($utilisateur['role'] === 'super_admin'): ?>

                                                Toutes les maisons

                                            <?php else: ?>

                                                <span class="badge badge-warning">
                                                    Aucune affectation
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Statut -->
                                        <td>

                                            <?php if ((int) $utilisateur['actif'] === 1): ?>

                                                <span class="badge badge-success">
                                                    Actif
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-secondary">
                                                    Inactif
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Date de création -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $utilisateur['created_at'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Actions -->
                                        <td>

                                            <a
                                                href="/ImmoGestion/utilisateurs/modifier.php?id=<?= (int) $utilisateur['id'] ?>"
                                                class="btn btn-secondary btn-sm"
                                            >
                                                ✏️ Modifier
                                            </a>

                                            <?php
                                            // Seuls les administrateurs actifs,
                                            // différents du compte connecté,
                                            // peuvent être désactivés.
                                            $peutDesactiver =
                                                $utilisateur['role'] === 'admin'
                                                && (int) $utilisateur['actif'] === 1
                                                && (int) $utilisateur['id'] !== (int) $_SESSION['utilisateur_id'];
                                            ?>

                                            <?php if ($peutDesactiver): ?>

                                                <form
                                                    action="/ImmoGestion/utilisateurs/supprimer.php"
                                                    method="POST"
                                                    style="display: inline;"
                                                    onsubmit="return confirm('Voulez-vous vraiment désactiver ce compte ?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $utilisateur['id'] ?>"
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

                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                    >
                                                        🚫 Désactiver
                                                    </button>

                                                </form>

                                            <?php elseif (
                                                $utilisateur['role'] === 'admin'
                                                && (int) $utilisateur['actif'] === 0
                                            ): ?>

                                                <span class="badge badge-secondary">
                                                    Compte désactivé
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

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>

