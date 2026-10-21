<?php

require_once __DIR__ . '/../includes/auth.php';

exigerSuperAdmin();

// La connexion PDO est chargée par auth.php.
// On utilise également database.php explicitement si nécessaire.
require_once __DIR__ . '/../config/database.php';

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

$maisons = $requete->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le message de confirmation.
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

function echapper($valeur): string
{
    return htmlspecialchars(
        (string) ($valeur ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

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
                <?= echapper($_SESSION['nom'] ?? '') ?>
            </div>

        </header>

        <section class="page-content">

            <div class="page-heading">

                <h2>Liste des maisons</h2>

                <p>
                    Consultez et gérez les biens immobiliers.
                </p>

            </div>

            <?php if ($message !== ''): ?>

                <div class="panel" role="status">
                    <?= echapper($message) ?>
                </div>

            <?php endif; ?>

            <div style="margin-bottom: 20px;">

                <a href="ajouter.php" class="btn btn-primary">
                    + Ajouter une maison
                </a>

            </div>

            <section class="panel">

                <?php if (empty($maisons)): ?>

                    <p>Aucune maison n'a encore été enregistrée.</p>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table">

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
                                            <?= echapper($maison['nom']) ?>
                                        </td>

                                        <td>
                                            <?= echapper($maison['adresse']) ?>
                                        </td>

                                        <td>
                                            <?= echapper($maison['ville']) ?>
                                        </td>

                                        <td>

                                            <?php if ((int) $maison['actif'] === 1): ?>

                                                <span class="badge badge-success">
                                                    Oui
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-secondary">
                                                    Non
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= echapper($maison['statut']) ?>
                                        </td>

                                        <td>

                                            <a
                                                href="modifier.php?id=<?= (int) $maison['id'] ?>"
                                                class="btn btn-secondary btn-sm"
                                            >
                                                ✏️ Modifier
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
                                                        value="<?= echapper($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                    >
                                                        Désactiver
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span class="badge badge-secondary">
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

            <p style="margin-top: 20px;">
                <a href="../dashboard.php">
                    ← Retour au tableau de bord
                </a>
            </p>

        </section>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>

<script src="../assets/js/script.js"></script>

</body>

</html>
