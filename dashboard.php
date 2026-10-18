<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

exigerConnexion();

// Récupérer quelques statistiques depuis la base de données.
$nombreMaisons = (int) $pdo
    ->query('SELECT COUNT(*) FROM maisons')
    ->fetchColumn();

$nombreAppartements = (int) $pdo
    ->query('SELECT COUNT(*) FROM appartements')
    ->fetchColumn();

$nombreLocataires = (int) $pdo
    ->query('SELECT COUNT(*) FROM locataires')
    ->fetchColumn();

$nombreUtilisateurs = 0;

if (estSuperAdmin()) {
    $nombreUtilisateurs = (int) $pdo
        ->query('SELECT COUNT(*) FROM utilisateurs')
        ->fetchColumn();
}

$nom = $_SESSION['nom'] ?? 'Utilisateur';

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

```
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="main-content">

    <header class="topbar">

        <div style="display:flex; align-items:center; gap:12px;">
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
        </div>

        <div class="stats-grid">

            <article class="stat-card">
                <div class="stat-icon">🏢</div>
                <p>Nombre de maisons</p>
                <h3><?= $nombreMaisons ?></h3>
            </article>

            <article class="stat-card">
                <div class="stat-icon">🏘️</div>
                <p>Nombre d'appartements</p>
                <h3><?= $nombreAppartements ?></h3>
            </article>

            <article class="stat-card">
                <div class="stat-icon">👥</div>
                <p>Nombre de locataires</p>
                <h3><?= $nombreLocataires ?></h3>
            </article>

            <?php if (estSuperAdmin()): ?>
                <article class="stat-card">
                    <div class="stat-icon">⚙️</div>
                    <p>Nombre d'utilisateurs</p>
                    <h3><?= $nombreUtilisateurs ?></h3>
                </article>
            <?php endif; ?>

        </div>

        <section class="panel">
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

        <form action="logout.php" method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <button type="submit" class="btn">
                Se déconnecter
            </button>
        </form>

    </section>

</main>
```

</div>

<script src="assets/js/script.js"></script>

</body>
</html>
