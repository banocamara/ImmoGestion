<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// Initialiser le jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer le message éventuel
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Récupérer les locataires
$sql = "
    SELECT
        id,
        nom,
        prenom,
        telephone,
        email,
        adresse,
        actif,
        created_at
    FROM locataires
    ORDER BY id DESC
";

$stmt = $pdo->query($sql);
$locataires = $stmt->fetchAll();

// Charger l'en-tête et la navigation
$titrePage = 'Gestion des locataires';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="main-content">

    <div class="topbar">
        <h1>Gestion des locataires</h1>

        <a href="ajouter.php" class="btn btn-primary">
            + Ajouter un locataire
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="panel">

        <h2>Liste des locataires</h2>

        <?php if (empty($locataires)): ?>

            <p>Aucun locataire enregistré pour le moment.</p>

        <?php else: ?>

            <div class="table-responsive">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom complet</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Adresse</th>
                            <th>État</th>
                            <th>Date d'inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($locataires as $locataire): ?>

                            <tr>

                                <td>
                                    <?= (int) $locataire['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $locataire['nom'] . ' ' . $locataire['prenom'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $locataire['telephone'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $locataire['email'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $locataire['adresse'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?php if ((int) $locataire['actif'] === 1): ?>

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
                                    <?= htmlspecialchars(
                                        $locataire['created_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>

                                    <a
                                        href="modifier.php?id=<?= (int) $locataire['id'] ?>"
                                        class="btn btn-secondary"
                                    >
                                        Modifier
                                    </a>

                                    <?php if ((int) $locataire['actif'] === 1): ?>

                                        <form
                                            action="desactiver.php"
                                            method="POST"
                                            style="display: inline;"
                                            onsubmit="return confirm('Voulez-vous vraiment désactiver ce locataire ?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $locataire['id'] ?>"
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
