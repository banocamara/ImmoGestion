<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// Récupérer toutes les maisons.
$requete = $pdo->query(
    'SELECT id, nom, adresse, ville, description, actif, statut
     FROM maisons
     ORDER BY id DESC'
);

$maisons = $requete->fetchAll();

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

?>

<!DOCTYPE html>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

```
<title>Maisons - ImmoGestion</title>

<link rel="stylesheet" href="../assets/css/style.css">
```

</head>
<body>

<div class="app-layout">

```
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="main-content">

    <header class="topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <button
                type="button"
                class="menu-toggle"
                id="menuToggle"
                aria-label="Ouvrir le menu"
            >☰</button>

            <h1>Gestion des maisons</h1>
        </div>

        <div class="user-info">
            <?= htmlspecialchars($_SESSION['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
    </header>

    <section class="page-content">

        <div class="page-heading">
            <h2>Liste des maisons</h2>
            <p>Consultez et gérez les biens immobiliers.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="panel">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div style="margin-bottom:20px;">
            <a href="ajouter.php" class="btn">
                + Ajouter une maison
            </a>
        </div>

        <section class="panel">

            <?php if (count($maisons) === 0): ?>

                <p>Aucune maison n'a encore été enregistrée.</p>

            <?php else: ?>

                <div class="table-container">

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Adresse</th>
                                <th>Ville</th>
                                <th>Actif</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($maisons as $maison): ?>
                                <tr>
                                    <td>
                                        <?= (int) $maison['id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($maison['nom'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($maison['adresse'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($maison['ville'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= (int) $maison['actif'] === 1 ? 'Oui' : 'Non' ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($maison['statut'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <a href="modifier.php?id=<?= (int) $maison['id'] ?>">
                                            Modifier
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                </div>

            <?php endif; ?>

        </section>

        <p>
            <a href="../dashboard.php">← Retour au tableau de bord</a>
        </p>

    </section>

</main>
```

</div>

<script src="../assets/js/script.js"></script>

</body>
</html>
