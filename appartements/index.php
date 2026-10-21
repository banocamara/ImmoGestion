<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// Créer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer le message de confirmation
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Récupérer les appartements et les maisons associées
$sql = "
    SELECT
        a.id,
        a.maison_id,
        a.numero,
        a.description,
        a.loyer_mensuel,
        a.statut,
        a.actif,
        m.nom AS nom_maison,
        m.ville AS ville_maison,
        m.actif AS maison_active
    FROM appartements a
    INNER JOIN maisons m ON a.maison_id = m.id
    ORDER BY a.id DESC
";

$stmt = $pdo->query($sql);
$appartements = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Gestion des appartements - ImmoGestion</title>

<link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="app-layout">

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

            <h1>Gestion des appartements</h1>

        </div>

        <a href="ajouter.php" class="btn btn-primary">
            + Ajouter un appartement
        </a>
    </header>

    <section class="page-content">

        <?php if ($message !== ''): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="panel">

            <h2>Liste des appartements</h2>

            <?php if (empty($appartements)): ?>

                <p>Aucun appartement enregistré.</p>

            <?php else: ?>

                <div class="table-responsive">

                    <table>

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Maison</th>
                                <th>Ville</th>
                                <th>Numéro</th>
                                <th>Loyer mensuel (MRU)</th>
                                <th>Occupation</th>
                                <th>État de l'appartement</th>
                                <th>État de la maison</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($appartements as $appartement): ?>

                                <tr>

                                    <td>
                                        <?= (int) $appartement['id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $appartement['nom_maison'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $appartement['ville_maison'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) $appartement['numero'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (float) $appartement['loyer_mensuel'],
                                            0,
                                            ',',
                                            ' '
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            ucfirst($appartement['statut']),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php if ((int) $appartement['actif'] === 1): ?>

                                            <span class="badge badge-success">
                                                Actif
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-danger">
                                                Désactivé
                                            </span>

                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ((int) $appartement['maison_active'] === 1): ?>

                                            <span class="badge badge-success">
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-danger">
                                                Désactivée
                                            </span>

                                        <?php endif; ?>
                                    </td>

                                    <td>

                                        <a
                                            href="modifier.php?id=<?= (int) $appartement['id'] ?>"
                                            class="btn btn-secondary"
                                        >
                                            Modifier
                                        </a>

                                        <?php if (
                                            (int) $appartement['actif'] === 1
                                            && (int) $appartement['maison_active'] === 1
                                        ): ?>

                                            <form
                                                action="desactiver.php"
                                                method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirm('Voulez-vous vraiment désactiver cet appartement ?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $appartement['id'] ?>"
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
                                                    class="btn btn-danger"
                                                >
                                                    Désactiver
                                                </button>

                                            </form>

                                        <?php elseif ((int) $appartement['actif'] === 0): ?>

                                            <span class="text-muted">
                                                Déjà désactivé
                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                Maison désactivée
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

    </section>

</main>

</div>

<script src="../assets/js/script.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
