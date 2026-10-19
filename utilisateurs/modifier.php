<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();
exigerSuperAdmin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Créer un jeton CSRF si nécessaire
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifier l'identifiant de l'utilisateur
$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$id) {
    header('Location: /ImmoGestion/utilisateurs/index.php');
    exit;
}

// Récupérer l'utilisateur
$stmt = $pdo->prepare(
    "SELECT id, nom, email, role, actif
     FROM utilisateurs
     WHERE id = ?"
);

$stmt->execute([$id]);

$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    header('Location: /ImmoGestion/utilisateurs/index.php');
    exit;
}

// Empêcher la modification de son propre compte depuis cette page
// pour éviter de se désactiver accidentellement.
if ((int) $utilisateur['id'] === (int) $_SESSION['utilisateur_id']) {
    header(
        'Location: /ImmoGestion/utilisateurs/index.php?erreur=propre_compte'
    );
    exit;
}

// Récupérer les maisons actives
$stmt = $pdo->query(
    "SELECT id, nom
     FROM maisons
     WHERE actif = 1
     ORDER BY nom ASC"
);

$maisons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la maison actuellement attribuée
$stmt = $pdo->prepare(
    "SELECT maison_id
     FROM affectations_admins
     WHERE utilisateur_id = ?
     LIMIT 1"
);

$stmt->execute([$id]);

$maisonActuelle = $stmt->fetchColumn();

$maisonId = $maisonActuelle === false
    ? ''
    : (string) $maisonActuelle;

$nom = $utilisateur['nom'];
$email = $utilisateur['email'];
$actif = (int) $utilisateur['actif'];

$erreurs = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF
    $jeton = $_POST['csrf_token'] ?? '';

    if (
        !is_string($jeton) ||
        !hash_equals($_SESSION['csrf_token'], $jeton)
    ) {
        $erreurs[] = 'Jeton de sécurité invalide. Rechargez la page.';
    }

    // Récupérer les champs
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nouveauMotDePasse = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    $maisonId = $_POST['maison_id'] ?? '';

    $actifEnvoye = $_POST['actif'] ?? '';

    // Vérifier le nom
    if ($nom === '') {
        $erreurs[] = 'Le nom est obligatoire.';
    } elseif (mb_strlen($nom) > 100) {
        $erreurs[] = 'Le nom ne doit pas dépasser 100 caractères.';
    }

    // Vérifier l'e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'Veuillez saisir une adresse e-mail valide.';
    } elseif (mb_strlen($email) > 150) {
        $erreurs[] = 'L’adresse e-mail ne doit pas dépasser 150 caractères.';
    }

    // Vérifier que l'e-mail n'est pas utilisé par un autre compte
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM utilisateurs
             WHERE email = ? AND id <> ?"
        );

        $stmt->execute([$email, $id]);

        if ($stmt->fetchColumn()) {
            $erreurs[] = 'Cette adresse e-mail est déjà utilisée.';
        }
    }

    // Vérifier le nouveau mot de passe uniquement s'il est renseigné
    if ($nouveauMotDePasse !== '') {

        if (
            !is_string($nouveauMotDePasse) ||
            strlen($nouveauMotDePasse) < 8
        ) {
            $erreurs[] =
                'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }

        if (
            !is_string($confirmation) ||
            $nouveauMotDePasse !== $confirmation
        ) {
            $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
        }
    }

    // Vérifier le statut
    if (!in_array($actifEnvoye, ['0', '1'], true)) {
        $erreurs[] = 'Le statut sélectionné est invalide.';
    } else {
        $actif = (int) $actifEnvoye;
    }

    // Un super-administrateur ne reçoit pas d'affectation de maison.
    // Pour un administrateur ordinaire, une maison active est obligatoire.
    $maisonIdValide = null;

    if ($utilisateur['role'] === 'admin') {

        $maisonIdValide = filter_var(
            $maisonId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($maisonIdValide === false) {
            $erreurs[] = 'Veuillez sélectionner une maison.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM maisons
                 WHERE id = ? AND actif = 1"
            );

            $stmt->execute([$maisonIdValide]);

            if (!$stmt->fetchColumn()) {
                $erreurs[] =
                    'La maison sélectionnée est introuvable ou inactive.';
            }
        }
    }

    // Enregistrer les modifications
    if (empty($erreurs)) {

        try {
            $pdo->beginTransaction();

            // Modifier les informations principales
            if ($nouveauMotDePasse !== '') {

                $motDePasseHache = password_hash(
                    $nouveauMotDePasse,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    "UPDATE utilisateurs
                     SET nom = ?,
                         email = ?,
                         mot_de_passe = ?,
                         actif = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $nom,
                    $email,
                    $motDePasseHache,
                    $actif,
                    $id
                ]);

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE utilisateurs
                     SET nom = ?,
                         email = ?,
                         actif = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $nom,
                    $email,
                    $actif,
                    $id
                ]);
            }

            // Mettre à jour l'affectation pour un administrateur
            if ($utilisateur['role'] === 'admin') {

                // Supprimer les anciennes affectations
                $stmt = $pdo->prepare(
                    "DELETE FROM affectations_admins
                     WHERE utilisateur_id = ?"
                );

                $stmt->execute([$id]);

                // Enregistrer la nouvelle affectation
                $stmt = $pdo->prepare(
                    "INSERT INTO affectations_admins
                        (utilisateur_id, maison_id)
                     VALUES (?, ?)"
                );

                $stmt->execute([
                    $id,
                    $maisonIdValide
                ]);
            }

            $pdo->commit();

            // Renouveler le jeton CSRF
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header(
                'Location: /ImmoGestion/utilisateurs/index.php?succes=modification'
            );

            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erreur modification utilisateur : ' . $e->getMessage()
            );

            $erreurs[] =
                'Une erreur est survenue lors de la modification. '
                . 'Vérifiez les données et réessayez.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h1>Modifier un utilisateur</h1>
        </div>

        <div class="page-content">

            <div class="page-heading">
                <h2>Informations du compte</h2>

                <p>
                    Modifiez les informations de l'utilisateur et sa maison
                    d'affectation.
                </p>
            </div>

            <?php if (!empty($erreurs)): ?>

                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($erreurs as $erreur): ?>
                            <li>
                                <?= htmlspecialchars(
                                    $erreur,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            <?php endif; ?>

            <div class="panel">

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

                        <label for="nom">Nom complet *</label>

                        <input
                            type="text"
                            id="nom"
                            name="nom"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $nom,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="email">Adresse e-mail *</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            value="<?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="mot_de_passe">
                            Nouveau mot de passe
                        </label>

                        <input
                            type="password"
                            id="mot_de_passe"
                            name="mot_de_passe"
                            minlength="8"
                            autocomplete="new-password"
                        >

                        <small>
                            Laissez ce champ vide pour conserver le mot de passe actuel.
                        </small>

                    </div>

                    <div class="form-group">

                        <label for="confirmation">
                            Confirmer le nouveau mot de passe
                        </label>

                        <input
                            type="password"
                            id="confirmation"
                            name="confirmation"
                            minlength="8"
                            autocomplete="new-password"
                        >

                    </div>

                    <?php if ($utilisateur['role'] === 'admin'): ?>

                        <div class="form-group">

                            <label for="maison_id">Maison à gérer *</label>

                            <select
                                id="maison_id"
                                name="maison_id"
                                required
                            >

                                <option value="">
                                    -- Sélectionner une maison --
                                </option>

                                <?php foreach ($maisons as $maison): ?>

                                    <option
                                        value="<?= (int) $maison['id'] ?>"
                                        <?= (string) $maisonId === (string) $maison['id']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            $maison['nom'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    <?php else: ?>

                        <div class="form-group">

                            <label>Rôle</label>

                            <input
                                type="text"
                                value="Super-administrateur"
                                disabled
                            >

                            <small>
                                Le rôle du compte n'est pas modifiable ici.
                            </small>

                        </div>

                    <?php endif; ?>

                    <div class="form-group">

                        <label for="actif">Statut du compte *</label>

                        <select id="actif" name="actif" required>

                            <option
                                value="1"
                                <?= $actif === 1 ? 'selected' : '' ?>
                            >
                                Actif
                            </option>

                            <option
                                value="0"
                                <?= $actif === 0 ? 'selected' : '' ?>
                            >
                                Inactif
                            </option>

                        </select>

                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">

                        <button type="submit" class="btn btn-primary">
                            💾 Enregistrer les modifications
                        </button>

                        <a
                            href="/ImmoGestion/utilisateurs/index.php"
                            class="btn btn-secondary"
                        >
                            Annuler
                        </a>

                    </div>

                </form>

            </div>

        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </main>

</div>
