<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

$message = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Votre session a expiré. Veuillez réessayer.';
    } else {

        $nom = trim($_POST['nom'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($nom === '' || $ville === '') {

            $message = 'Le nom de la maison et la ville sont obligatoires.';

        } elseif (
            mb_strlen($nom) > 150
            || mb_strlen($ville) > 100
            || mb_strlen($adresse) > 255
        ) {

            $message = 'Certains champs dépassent la longueur autorisée.';

        } else {

            try {

                $requete = $pdo->prepare(
                    'INSERT INTO maisons
                        (nom, adresse, ville, description, actif, statut, created_at, updated_at)
                     VALUES
                        (:nom, :adresse, :ville, :description, 1, :statut, NOW(), NOW())'
                );

                $requete->execute([
                    'nom' => $nom,
                    'adresse' => $adresse !== '' ? $adresse : null,
                    'ville' => $ville,
                    'description' => $description !== '' ? $description : null,
                    'statut' => 'active'
                ]);

                $_SESSION['message'] = 'La maison a été ajoutée avec succès.';

                header('Location: index.php');
                exit;

            } catch (PDOException $e) {

                error_log('Erreur lors de l’ajout d’une maison : ' . $e->getMessage());

                $message = 'Impossible d’enregistrer la maison. Vérifiez la structure de la base de données.';
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

```
<title>Ajouter une maison - ImmoGestion</title>

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

            <h1>Ajouter une maison</h1>
        </div>
    </header>

    <section class="page-content">

        <div class="page-heading">
            <h2>Nouvelle maison</h2>
            <p>Renseignez les informations du bien immobilier.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="panel">

            <form action="ajouter.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-group">
                    <label for="nom">Nom de la maison *</label>

                    <input
                        type="text"
                        id="nom"
                        name="nom"
                        maxlength="150"
                        value="<?= htmlspecialchars($_POST['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Exemple : Maison A"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="adresse">Adresse</label>

                    <input
                        type="text"
                        id="adresse"
                        name="adresse"
                        maxlength="255"
                        value="<?= htmlspecialchars($_POST['adresse'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Adresse de la maison"
                    >
                </div>

                <div class="form-group">
                    <label for="ville">Ville *</label>

                    <input
                        type="text"
                        id="ville"
                        name="ville"
                        maxlength="100"
                        value="<?= htmlspecialchars($_POST['ville'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Exemple : Nouakchott"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="description">Description</label>

                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        style="width:100%; padding:12px; border:1px solid #e5e7eb; border-radius:8px;"
                        placeholder="Informations complémentaires"
                    ><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button type="submit" class="btn">
                    Enregistrer la maison
                </button>

                <a href="index.php" style="margin-left:12px;">
                    Annuler
                </a>

            </form>

        </section>

    </section>

</main>
```

</div>

<script src="../assets/js/script.js"></script>

</body>
</html>
