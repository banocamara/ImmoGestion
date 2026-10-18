<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

$message = '';
$maison = null;

// Vérifier l'identifiant transmis dans l'URL.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant de maison invalide.');
}

// Récupérer la maison.
$requete = $pdo->prepare(
    'SELECT id, nom, adresse, ville, description, actif, statut
     FROM maisons
     WHERE id = :id'
);

$requete->execute(['id' => $id]);
$maison = $requete->fetch();

if (!$maison) {
    http_response_code(404);
    exit('Maison introuvable.');
}

// Traiter le formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Votre session a expiré. Veuillez réessayer.';
    } else {

        $nom = trim($_POST['nom'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($nom === '' || $ville === '') {
            $message = 'Le nom et la ville sont obligatoires.';
        } elseif (
            mb_strlen($nom) > 150
            || mb_strlen($ville) > 100
            || mb_strlen($adresse) > 255
        ) {
            $message = 'Certains champs sont trop longs.';
        } else {

            try {
                $requete = $pdo->prepare(
                    'UPDATE maisons
                     SET nom = :nom,
                         adresse = :adresse,
                         ville = :ville,
                         description = :description,
                         updated_at = NOW()
                     WHERE id = :id'
                );

                $requete->execute([
                    'nom' => $nom,
                    'adresse' => $adresse !== '' ? $adresse : null,
                    'ville' => $ville,
                    'description' => $description !== '' ? $description : null,
                    'id' => $id
                ]);

                $_SESSION['message'] = 'La maison a été modifiée avec succès.';

                header('Location: index.php');
                exit;

            } catch (PDOException $e) {
                error_log('Erreur de modification de maison : ' . $e->getMessage());
                $message = 'Impossible de modifier la maison.';
            }
        }

        // Conserver les valeurs saisies si la validation échoue.
        $maison['nom'] = $nom;
        $maison['adresse'] = $adresse;
        $maison['ville'] = $ville;
        $maison['description'] = $description;
    }
}

?>

<!DOCTYPE html>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

```
<title>Modifier une maison - ImmoGestion</title>

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

            <h1>Modifier une maison</h1>
        </div>
    </header>

    <section class="page-content">

        <div class="page-heading">
            <h2>Modification : <?= htmlspecialchars($maison['nom'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Modifiez les informations de cette maison.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="panel">

            <form action="modifier.php" method="POST">

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $maison['id'] ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-group">
                    <label for="nom">Nom de la maison *</label>
                    <input
                        type="text"
                        id="nom"
                        name="nom"
                        maxlength="150"
                        value="<?= htmlspecialchars($maison['nom'], ENT_QUOTES, 'UTF-8') ?>"
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
                        value="<?= htmlspecialchars($maison['adresse'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="ville">Ville *</label>
                    <input
                        type="text"
                        id="ville"
                        name="ville"
                        maxlength="100"
                        value="<?= htmlspecialchars($maison['ville'], ENT_QUOTES, 'UTF-8') ?>"
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
                    ><?= htmlspecialchars($maison['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button type="submit" class="btn">
                    Enregistrer les modifications
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
