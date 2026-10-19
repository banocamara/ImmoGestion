<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

exigerSuperAdmin();

// Créer un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
        m.ville AS ville_maison
    FROM appartements a
    INNER JOIN maisons m ON a.maison_id = m.id
    ORDER BY a.id DESC
";

$stmt = $pdo->query($sql);
$appartements = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="main-content">

    <div class="topbar">
        <h1>Gestion des appartements</h1>
        <a href="ajouter.php" class="btn btn-primary">
            + Ajouter un appartement
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success">
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
                            <th>Loyer mensuel</th>
                            <th>Occupation</th>
                            <th>État</th>
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
                                        $appartement['numero'],
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
                                    <a
                                        href="modifier.php?id=<?= (int) $appartement['id'] ?>"
                                        class="btn btn-secondary"
                                    >
                                        Modifier
                                    </a>

                                    <?php if ((int) $appartement['actif'] === 1): ?>

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

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Déjà désactivé
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

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
