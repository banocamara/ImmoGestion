<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// Initialiser le jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreurs = [];

// Récupérer l'identifiant du locataire
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    $_SESSION['message'] = 'Identifiant de locataire invalide.';
    header('Location: index.php');
    exit;
}

// Récupérer le locataire dans la base de données
$stmt = $pdo->prepare(
    'SELECT id, nom, prenom, telephone, email, adresse, actif
     FROM locataires
     WHERE id = :id'
);

$stmt->execute(['id' => $id]);
$locataire = $stmt->fetch();

if (!$locataire) {
    $_SESSION['message'] = 'Locataire introuvable.';
    header('Location: index.php');
    exit;
}

// Préremplir les champs avec les données existantes
$nom = $locataire['nom'];
$prenom = $locataire['prenom'];
$telephone = $locataire['telephone'];
$email = $locataire['email'] ?? '';
$adresse = $locataire['adresse'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $erreurs[] = 'Le formulaire a expiré. Rechargez la page et réessayez.';
    }

    // Récupérer les données du formulaire
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');

    // Vérifier le nom
    if ($nom === '') {
        $erreurs[] = 'Le nom est obligatoire.';
    } elseif (mb_strlen($nom) > 100) {
        $erreurs[] = 'Le nom ne doit pas dépasser 100 caractères.';
    }

    // Vérifier le prénom
    if ($prenom === '') {
        $erreurs[] = 'Le prénom est obligatoire.';
    } elseif (mb_strlen($prenom) > 100) {
        $erreurs[] = 'Le prénom ne doit pas dépasser 100 caractères.';
    }

    // Vérifier le téléphone
    if ($telephone === '') {
        $erreurs[] = 'Le téléphone est obligatoire.';
    } elseif (mb_strlen($telephone) > 30) {
        $erreurs[] = 'Le téléphone ne doit pas dépasser 30 caractères.';
    }

    // Vérifier l'email facultatif
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'Veuillez saisir une adresse email valide.';
    } elseif (mb_strlen($email) > 150) {
        $erreurs[] = 'L’email ne doit pas dépasser 150 caractères.';
    }

    // Vérifier l'adresse facultative
    if (mb_strlen($adresse) > 255) {
        $erreurs[] = 'L’adresse ne doit pas dépasser 255 caractères.';
    }

    // Enregistrer les modifications
    if (empty($erreurs)) {

        try {
            $sql = "
                UPDATE locataires
                SET
                    nom = :nom,
                    prenom = :prenom,
                    telephone = :telephone,
                    email = :email,
                    adresse = :adresse
                WHERE id = :id
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'email' => $email !== '' ? $email : null,
                'adresse' => $adresse !== '' ? $adresse : null,
                'id' => $id
            ]);

            $_SESSION['message'] = 'Les informations du locataire ont été modifiées avec succès.';

            header('Location: index.php');
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $erreurs[] = 'Une erreur est survenue lors de la modification.';
        }
    }
}

$titrePage = 'Modifier un locataire';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="main-content">

    <div class="topbar">
        <h1>Modifier un locataire</h1>

        <a href="index.php" class="btn btn-secondary">
            Retour à la liste
        </a>
    </div>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($erreurs as $erreur): ?>
                    <li>
                        <?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="panel">

        <h2>Informations du locataire</h2>

        <form method="POST" action="">

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
                <label for="nom">Nom *</label>

                <input
                    type="text"
                    id="nom"
                    name="nom"
                    maxlength="100"
                    value="<?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="prenom">Prénom *</label>

                <input
                    type="text"
                    id="prenom"
                    name="prenom"
                    maxlength="100"
                    value="<?= htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="telephone">Téléphone *</label>

                <input
                    type="tel"
                    id="telephone"
                    name="telephone"
                    maxlength="30"
                    value="<?= htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Adresse email</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <div class="form-group">
                <label for="adresse">Adresse</label>

                <textarea
                    id="adresse"
                    name="adresse"
                    maxlength="255"
                    rows="3"
                ><?= htmlspecialchars($adresse, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                Enregistrer les modifications
            </button>

            <a href="index.php" class="btn btn-secondary">
                Annuler
            </a>

        </form>

    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
