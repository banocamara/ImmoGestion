<?php

require_once __DIR__ . '/../includes/auth.php';

exigerConnexion();
exigerSuperAdmin();

// Le fichier auth.php démarre déjà la session.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Générer le jeton CSRF.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreurs = [];

$nom = '';
$email = '';
$maisonId = '';

$maisons = [];
$motDePasse = '';
$confirmation = '';

// Récupérer les messages de succès éventuels.
$messageSucces = '';

if (isset($_GET['succes']) && $_GET['succes'] === 'ajout') {
    $messageSucces = 'L’administrateur a été créé avec succès.';
}

// Récupérer les maisons actives.
try {
    $stmt = $pdo->query(
        "SELECT id, nom
         FROM maisons
         WHERE actif = 1
         ORDER BY nom ASC"
    );

    $maisons = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log(
        'Erreur récupération des maisons : ' . $e->getMessage()
    );

    http_response_code(500);
    exit('Impossible de récupérer la liste des maisons pour le moment.');
}

// Traitement du formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF.
    $jeton = $_POST['csrf_token'] ?? '';

    if (
        !is_string($jeton) ||
        !hash_equals($_SESSION['csrf_token'], $jeton)
    ) {
        $erreurs[] =
            'La vérification de sécurité a échoué. Rechargez la page.';
    }

    // Récupérer les champs.
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    $maisonId = $_POST['maison_id'] ?? '';

    // Vérifier les types des données reçues.
    if (
        !is_string($nom) ||
        !is_string($email) ||
        !is_string($motDePasse) ||
        !is_string($confirmation) ||
        !is_string($maisonId)
    ) {
        $erreurs[] = 'Les données envoyées sont invalides.';
    } else {

        // Vérifier le nom.
        if ($nom === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom, 'UTF-8') > 100) {
            $erreurs[] = 'Le nom ne doit pas dépasser 100 caractères.';
        }

        // Vérifier l'adresse e-mail.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Veuillez saisir une adresse e-mail valide.';
        } elseif (mb_strlen($email, 'UTF-8') > 150) {
            $erreurs[] =
                'L’adresse e-mail ne doit pas dépasser 150 caractères.';
        }

        // Vérifier le mot de passe.
        if (strlen($motDePasse) < 8) {
            $erreurs[] =
                'Le mot de passe doit contenir au moins 8 caractères.';
        }

        if ($motDePasse !== $confirmation) {
            $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
        }

        // Vérifier la maison choisie.
        $maisonIdValide = filter_var(
            $maisonId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($maisonIdValide === false) {
            $erreurs[] = 'Veuillez sélectionner une maison.';
        }

        // Vérifier que l'adresse e-mail n'est pas déjà utilisée.
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

            try {
                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM utilisateurs
                     WHERE email = ?
                     LIMIT 1"
                );

                $stmt->execute([$email]);

                if ($stmt->fetchColumn()) {
                    $erreurs[] =
                        'Cette adresse e-mail est déjà utilisée.';
                }

            } catch (PDOException $e) {
                error_log(
                    'Erreur vérification e-mail : ' . $e->getMessage()
                );

                $erreurs[] =
                    'Impossible de vérifier l’adresse e-mail pour le moment.';
            }
        }

        // Vérifier la maison en base de données.
        if ($maisonIdValide !== false) {

            try {
                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM maisons
                     WHERE id = ?
                       AND actif = 1
                     LIMIT 1"
                );

                $stmt->execute([$maisonIdValide]);

                if (!$stmt->fetchColumn()) {
                    $erreurs[] =
                        'La maison sélectionnée est introuvable ou inactive.';
                }

            } catch (PDOException $e) {
                error_log(
                    'Erreur vérification maison : ' . $e->getMessage()
                );

                $erreurs[] =
                    'Impossible de vérifier la maison sélectionnée.';
            }
        }
    }

    // Créer l'administrateur si aucune erreur.
    if (empty($erreurs)) {

        try {
            $pdo->beginTransaction();

            // Vérifier à nouveau la maison dans la transaction.
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM maisons
                 WHERE id = ?
                   AND actif = 1
                 FOR UPDATE"
            );

            $stmt->execute([$maisonIdValide]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'La maison sélectionnée est inactive ou introuvable.'
                );
            }

            // Hacher le mot de passe.
            $motDePasseHache = password_hash(
                $motDePasse,
                PASSWORD_DEFAULT
            );

            if ($motDePasseHache === false) {
                throw new RuntimeException(
                    'Le hachage du mot de passe a échoué.'
                );
            }

            // Créer le compte administrateur.
            $stmt = $pdo->prepare(
                "INSERT INTO utilisateurs
                    (nom, email, mot_de_passe, role, actif)
                 VALUES
                    (?, ?, ?, 'admin', 1)"
            );

            $stmt->execute([
                $nom,
                $email,
                $motDePasseHache
            ]);

            $utilisateurId = (int) $pdo->lastInsertId();

            if ($utilisateurId <= 0) {
                throw new RuntimeException(
                    'Impossible de récupérer l’identifiant du compte créé.'
                );
            }

            // Affecter l'administrateur à la maison.
            $stmt = $pdo->prepare(
                "INSERT INTO affectations_admins
                    (utilisateur_id, maison_id)
                 VALUES
                    (?, ?)"
            );

            $stmt->execute([
                $utilisateurId,
                $maisonIdValide
            ]);

            // Valider la transaction.
            $pdo->commit();

            // Renouveler le jeton CSRF.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Rediriger après la création.
            header(
                'Location: /ImmoGestion/utilisateurs/index.php?succes=ajout'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erreur création administrateur : ' . $e->getMessage()
            );

            if (
                $e instanceof PDOException &&
                $e->getCode() === '23000'
            ) {
                $erreurs[] =
                    'Cette adresse e-mail existe déjà ou une affectation '
                    . 'identique est déjà enregistrée.';
            } elseif ($e instanceof RuntimeException) {
                $erreurs[] = $e->getMessage();
            } else {
                $erreurs[] =
                    'Une erreur est survenue lors de la création. '
                    . 'Vérifiez les données et réessayez.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <h1>Ajouter un administrateur</h1>
        </div>

        <div class="page-content">

            <div class="page-heading">
                <h2>Créer un compte administrateur</h2>

                <p>
                    Renseignez les informations et sélectionnez la maison
                    que cet administrateur sera chargé de gérer.
                </p>
            </div>

            <?php if ($messageSucces !== ''): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars(
                        $messageSucces,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

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

                        <label for="mot_de_passe">Mot de passe *</label>

                        <input
                            type="password"
                            id="mot_de_passe"
                            name="mot_de_passe"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <small>Au moins 8 caractères.</small>

                    </div>

                    <div class="form-group">

                        <label for="confirmation">
                            Confirmer le mot de passe *
                        </label>

                        <input
                            type="password"
                            id="confirmation"
                            name="confirmation"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>

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

                        <?php if (empty($maisons)): ?>
                            <small>
                                Aucune maison active n'est disponible.
                                Créez d'abord une maison.
                            </small>
                        <?php endif; ?>

                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= empty($maisons) ? 'disabled' : '' ?>
                        >
                            ➕ Créer l'administrateur
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
