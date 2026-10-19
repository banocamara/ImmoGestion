```php
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Seul le super-administrateur peut gérer les maisons.
exigerSuperAdmin();

// Créer un jeton CSRF si nécessaire.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer toutes les maisons.
$requete = $pdo->query(
    'SELECT id, nom, adresse, ville, description, actif, statut
     FROM maisons
     ORDER BY id DESC'
);

$maisons = $requete->fetchAll();

// Récupérer le message de confirmation.
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Maisons - ImmoGestion</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <!-- Barre supérieure -->
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

                <h1>Gestion des maisons</h1>

            </div>

            <div class="user-info">
                <?= htmlspecialchars(
                    $_SESSION['nom'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        </header>

        <!-- Contenu principal -->
        <section class="page-content">

            <div class="page-heading">
                <h2>Liste des maisons</h2>
                <p>Consultez et gérez les biens immobiliers.</p>
            </div>

            <!-- Message de confirmation -->
            <?php if ($message !== ''): ?>

                <div class="panel">
                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <!-- Bouton d'ajout -->
            <div style="margin-bottom: 20px;">

                <a href="ajouter.php" class="btn">
                    + Ajouter une maison
                </a>

            </div>

            <!-- Tableau des maisons -->
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

                                        <!-- Identifiant -->
                                        <td>
                                            <?= (int) $maison['id'] ?>
                                        </td>

                                        <!-- Nom -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $maison['nom'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Adresse -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $maison['adresse'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Ville -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $maison['ville'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- État actif -->
                                        <td>
                                            <?php if ((int) $maison['actif'] === 1): ?>
                                                <span style="color: green;">
                                                    Oui
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #dc2626;">
                                                    Non
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Statut -->
                                        <td>
                                            <?= htmlspecialchars(
                                                $maison['statut'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <!-- Actions -->
                                        <td>

                                            <a href="modifier.php?id=<?= (int) $maison['id'] ?>">
                                                Modifier
                                            </a>

                                            <?php if ((int) $maison['actif'] === 1): ?>

                                                <form
                                                    action="desactiver.php"
                                                    method="POST"
                                                    style="display: inline;"
                                                    onsubmit="return confirm('Voulez-vous vraiment désactiver cette maison ?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $maison['id'] ?>"
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
                                                        style="border: 0; background: none; color: #dc2626; cursor: pointer;"
                                                    >
                                                        Désactiver
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span style="color: #6b7280;">
                                                    Désactivée
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

            <!-- Retour au tableau de bord -->
            <p>
                <a href="../dashboard.php">
                    ← Retour au tableau de bord
                </a>
            </p>

        </section>

    </main>

</div>

<script src="../assets/js/script.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


</body>
</html>
```
