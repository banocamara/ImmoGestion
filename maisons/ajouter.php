<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

$message = '';
$succes = false;

// Créer le jeton CSRF s'il n'existe pas.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Valeurs initiales du formulaire.
$nom = '';
$adresse = '';
$ville = '';
$description = '';

// Traitement du formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupérer et vérifier le jeton CSRF.
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Votre session a expiré. Veuillez actualiser la page et réessayer.';

    } else {

        // Récupérer les champs.
        $nom = trim($_POST['nom'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Vérifier les champs obligatoires.
        if ($nom === '' || $ville === '') {

            $message = 'Le nom de la maison et la ville sont obligatoires.';

        } elseif (
            mb_strlen($nom, 'UTF-8') > 150
            || mb_strlen($ville, 'UTF-8') > 100
            || mb_strlen($adresse, 'UTF-8') > 255
        ) {

            $message = 'Certains champs dépassent la longueur autorisée.';

        } else {

            try {

                // Enregistrer la maison.
                $requete = $pdo->prepare(
                    'INSERT INTO maisons
                        (
                            nom,
                            adresse,
                            ville,
                            description,
                            actif,
                            statut,
                            created_at,
                            updated_at
                        )
                     VALUES
                        (
                            :nom,
                            :adresse,
                            :ville,
                            :description,
                            1,
                            :statut,
                            NOW(),
                            NOW()
                        )'
                );

                $requete->execute([
                    'nom' => $nom,
                    'adresse' => $adresse !== '' ? $adresse : null,
                    'ville' => $ville,
                    'description' => $description !== '' ? $description : null,
                    'statut' => 'active'
                ]);

                // Message de confirmation.
                $_SESSION['message'] = 'La maison a été ajoutée avec succès.';

                // Redirection après l'enregistrement.
                header('Location: index.php');
                exit;

            } catch (PDOException $e) {

                error_log(
                    'Erreur lors de l’ajout d’une maison : '
                    . $e->getMessage()
                );

                $message = 'Impossible d’enregistrer la maison. Vérifiez la base de données ou réessayez plus tard.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

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

                <h1>Ajouter une maison</h1>

            </div>

            <div class="user-info">
                <?= htmlspecialchars(
                    $_SESSION['nom'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        </header>

        <section class="page-content">

            <div class="page-heading">

                <h2>Nouvelle maison</h2>

                <p>
                    Renseignez les informations du bien immobilier.
                </p>

            </div>

            <?php if ($message !== ''): ?>

                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <section class="panel">

                <form action="ajouter.php" method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <div class="form-group">

                        <label for="nom">
                            Nom de la maison *
                        </label>

                        <input
                            type="text"
                            id="nom"
                            name="nom"
                            maxlength="150"
                            value="<?= htmlspecialchars(
                                $nom,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Exemple : Maison A"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="adresse">
                            Adresse
                        </label>

                        <input
                            type="text"
                            id="adresse"
                            name="adresse"
                            maxlength="255"
                            value="<?= htmlspecialchars(
                                $adresse,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Adresse de la maison"
                        >

                    </div>

                    <div class="form-group">

                        <label for="ville">
                            Ville *
                        </label>

                        <input
                            type="text"
                            id="ville"
                            name="ville"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $ville,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Exemple : Nouakchott"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="description">
                            Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            style="width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px;"
                            placeholder="Informations complémentaires"
                        ><?= htmlspecialchars(
                            $description,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Enregistrer la maison
                    </button>

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                        style="margin-left: 12px;"
                    >
                        Annuler
                    </a>

                </form>

            </section>

        </section>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>
