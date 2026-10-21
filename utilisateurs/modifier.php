<?php

require_once __DIR__ . '/../includes/auth.php';

exigerConnexion();
exigerSuperAdmin();

// La session est déjà démarrée par auth.php.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Créer un jeton CSRF si nécessaire.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifier l'identifiant de l'utilisateur.
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

// Récupérer l'utilisateur.
$stmt = $pdo->prepare(
    "SELECT id, nom, email, role, actif
     FROM utilisateurs
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([$id]);

$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    header('Location: /ImmoGestion/utilisateurs/index.php');
    exit;
}

// Empêcher la modification de son propre compte.
if ((int) $utilisateur['id'] === (int) $_SESSION['utilisateur_id']) {
    header(
        'Location: /ImmoGestion/utilisateurs/index.php?erreur=propre_compte'
    );
    exit;
}

// Récupérer les maisons actives.
$stmt = $pdo->query(
    "SELECT id, nom, ville
     FROM maisons
     WHERE actif = 1
     ORDER BY nom ASC"
);

$maisons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les maisons actuellement affectées.
$stmt = $pdo->prepare(
    "SELECT maison_id
     FROM affectations_admins
     WHERE utilisateur_id = ?"
);

$stmt->execute([$id]);

$maisonsActuelles = array_map(
    'intval',
    $stmt->fetchAll(PDO::FETCH_COLUMN)
);

// Initialiser les valeurs du formulaire.
$nom = $utilisateur['nom'];
$email = $utilisateur['email'];
$actif = (int) $utilisateur['actif'];

$maisonsSelectionnees = $maisonsActuelles;

$erreurs = [];

// Traitement du formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF.
    $jeton = $_POST['csrf_token'] ?? '';

    if (
        !is_string($jeton) ||
        !hash_equals($_SESSION['csrf_token'], $jeton)
    ) {
        $erreurs[] =
            'Jeton de sécurité invalide. Rechargez la page.';
    }

    // Récupérer les champs.
    $nomRecu = $_POST['nom'] ?? '';
    $emailRecu = $_POST['email'] ?? '';
    $nouveauMotDePasse = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    $maisonsRecues = $_POST['maisons'] ?? [];
    $actifEnvoye = $_POST['actif'] ?? '';

    // Vérifier les types des données reçues.
    if (
        !is_string($nomRecu) ||
        !is_string($emailRecu) ||
        !is_string($nouveauMotDePasse) ||
        !is_string($confirmation) ||
        !is_array($maisonsRecues) ||
        !is_string($actifEnvoye)
    ) {
        $erreurs[] = 'Les données envoyées sont invalides.';

    } else {

        $nom = trim($nomRecu);
        $email = trim($emailRecu);

        // Vérifier le nom.
        if ($nom === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom, 'UTF-8') > 100) {
            $erreurs[] =
                'Le nom ne doit pas dépasser 100 caractères.';
        }

        // Vérifier l'adresse e-mail.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] =
                'Veuillez saisir une adresse e-mail valide.';
        } elseif (mb_strlen($email, 'UTF-8') > 150) {
            $erreurs[] =
                'L’adresse e-mail ne doit pas dépasser 150 caractères.';
        }

        // Vérifier que l'e-mail n'appartient pas à un autre compte.
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM utilisateurs
                 WHERE email = ?
                   AND id <> ?
                 LIMIT 1"
            );

            $stmt->execute([$email, $id]);

            if ($stmt->fetchColumn()) {
                $erreurs[] =
                    'Cette adresse e-mail est déjà utilisée.';
            }
        }

        // Vérifier le nouveau mot de passe, s'il est renseigné.
        if ($nouveauMotDePasse !== '') {

            if (strlen($nouveauMotDePasse) < 8) {
                $erreurs[] =
                    'Le nouveau mot de passe doit contenir '
                    . 'au moins 8 caractères.';
            }

            if ($nouveauMotDePasse !== $confirmation) {
                $erreurs[] =
                    'Les deux mots de passe ne correspondent pas.';
            }
        }

        // Vérifier le statut.
        if (!in_array($actifEnvoye, ['0', '1'], true)) {
            $erreurs[] = 'Le statut sélectionné est invalide.';
        } else {
            $actif = (int) $actifEnvoye;
        }

        // Vérifier les maisons sélectionnées pour un administrateur.
        if ($utilisateur['role'] === 'admin') {

            $maisonsSelectionnees = [];

            if (count($maisonsRecues) === 0) {
                $erreurs[] =
                    'Veuillez sélectionner au moins une maison.';
            }

            foreach ($maisonsRecues as $maisonIdRecu) {

                if (
                    !is_string($maisonIdRecu) &&
                    !is_int($maisonIdRecu)
                ) {
                    $erreurs[] =
                        'La sélection des maisons est invalide.';
                    break;
                }

                $maisonIdValide = filter_var(
                    $maisonIdRecu,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($maisonIdValide === false) {
                    $erreurs[] =
                        'Un identifiant de maison est invalide.';
                    break;
                }

                $maisonsSelectionnees[] = (int) $maisonIdValide;
            }

            // Supprimer les doublons.
            $maisonsSelectionnees = array_values(
                array_unique($maisonsSelectionnees)
            );

            // Vérifier que toutes les maisons sélectionnées sont actives.
            if (
                !empty($maisonsSelectionnees) &&
                count($maisonsSelectionnees) === count($maisonsRecues)
            ) {
                $placeholders = implode(
                    ',',
                    array_fill(0, count($maisonsSelectionnees), '?')
                );

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM maisons
                     WHERE actif = 1
                       AND id IN ($placeholders)"
                );

                $stmt->execute($maisonsSelectionnees);

                $maisonsValides = array_map(
                    'intval',
                    $stmt->fetchAll(PDO::FETCH_COLUMN)
                );

                $maisonsATester = $maisonsSelectionnees;

                sort($maisonsValides);
                sort($maisonsATester);

                if ($maisonsValides !== $maisonsATester) {
                    $erreurs[] =
                        'Une ou plusieurs maisons sélectionnées '
                        . 'sont introuvables ou inactives.';
                }
            }

        } else {

            // Un super-administrateur n'a pas besoin d'affectations.
            $maisonsSelectionnees = [];
        }
    }

    // Enregistrer les modifications si aucune erreur.
    if (empty($erreurs)) {

        try {
            $pdo->beginTransaction();

            // Vérifier que le compte existe toujours.
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM utilisateurs
                 WHERE id = ?
                 FOR UPDATE"
            );

            $stmt->execute([$id]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Cet utilisateur n’existe plus.'
                );
            }

            // Revérifier les maisons avant de modifier les affectations.
            if (
                $utilisateur['role'] === 'admin' &&
                !empty($maisonsSelectionnees)
            ) {
                $placeholders = implode(
                    ',',
                    array_fill(0, count($maisonsSelectionnees), '?')
                );

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM maisons
                     WHERE actif = 1
                       AND id IN ($placeholders)
                     FOR UPDATE"
                );

                $stmt->execute($maisonsSelectionnees);

                $maisonsVerifiees = array_map(
                    'intval',
                    $stmt->fetchAll(PDO::FETCH_COLUMN)
                );

                $maisonsATester = $maisonsSelectionnees;

                sort($maisonsVerifiees);
                sort($maisonsATester);

                if ($maisonsVerifiees !== $maisonsATester) {
                    throw new RuntimeException(
                        'Une maison sélectionnée est devenue inactive '
                        . 'ou n’existe plus. Rechargez la page.'
                    );
                }
            }

            // Vérifier à nouveau l'unicité de l'e-mail.
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM utilisateurs
                 WHERE email = ?
                   AND id <> ?
                 LIMIT 1"
            );

            $stmt->execute([$email, $id]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Cette adresse e-mail est déjà utilisée.'
                );
            }

            // Modifier les informations du compte.
            if ($nouveauMotDePasse !== '') {

                $motDePasseHache = password_hash(
                    $nouveauMotDePasse,
                    PASSWORD_DEFAULT
                );

                if ($motDePasseHache === false) {
                    throw new RuntimeException(
                        'Le hachage du mot de passe a échoué.'
                    );
                }

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

            // Remplacer toutes les affectations de l'administrateur.
            if ($utilisateur['role'] === 'admin') {

                // Supprimer les anciennes affectations.
                $stmt = $pdo->prepare(
                    "DELETE FROM affectations_admins
                     WHERE utilisateur_id = ?"
                );

                $stmt->execute([$id]);

                // Enregistrer toutes les nouvelles affectations.
                $stmtAffectation = $pdo->prepare(
                    "INSERT INTO affectations_admins
                        (utilisateur_id, maison_id)
                     VALUES (?, ?)"
                );

                foreach ($maisonsSelectionnees as $maisonId) {
                    $stmtAffectation->execute([
                        $id,
                        $maisonId
                    ]);
                }
            }

            // Valider toutes les modifications.
            $pdo->commit();

            // Renouveler le jeton CSRF.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header(
                'Location: /ImmoGestion/utilisateurs/index.php'
                . '?succes=modification'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erreur modification utilisateur : '
                . $e->getMessage()
            );

            if ($e instanceof RuntimeException) {
                $erreurs[] = $e->getMessage();

            } elseif (
                $e instanceof PDOException &&
                $e->getCode() === '23000'
            ) {
                $erreurs[] =
                    'Cette adresse e-mail est déjà utilisée '
                    . 'ou une affectation identique existe déjà.';

            } else {
                $erreurs[] =
                    'Une erreur est survenue lors de la modification. '
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
            <h1>Modifier un utilisateur</h1>
        </div>

        <div class="page-content">

            <div class="page-heading">
                <h2>Informations du compte</h2>

                <p>
                    Modifiez les informations de l'utilisateur
                    et les maisons qu'il est autorisé à gérer.
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
                            Laissez ce champ vide pour conserver
                            le mot de passe actuel.
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

                            <label>Maisons à gérer *</label>

                            <p>
                                Cochez toutes les maisons que cet
                                administrateur doit pouvoir gérer.
                            </p>

                            <?php if (!empty($maisons)): ?>

                                <div style="
                                    display: grid;
                                    grid-template-columns:
                                        repeat(auto-fit, minmax(220px, 1fr));
                                    gap: 12px;
                                    margin-top: 12px;
                                ">

                                    <?php foreach ($maisons as $maison): ?>

                                        <label style="
                                            display: flex;
                                            align-items: flex-start;
                                            gap: 10px;
                                            padding: 12px;
                                            border: 1px solid #ddd;
                                            border-radius: 8px;
                                            cursor: pointer;
                                        ">

                                            <input
                                                type="checkbox"
                                                name="maisons[]"
                                                value="<?= (int) $maison['id'] ?>"
                                                <?= in_array(
                                                    (int) $maison['id'],
                                                    $maisonsSelectionnees,
                                                    true
                                                ) ? 'checked' : '' ?>
                                            >

                                            <span>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $maison['nom'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </strong>

                                                <?php if (!empty($maison['ville'])): ?>
                                                    <br>

                                                    <small>
                                                        <?= htmlspecialchars(
                                                            $maison['ville'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </small>
                                                <?php endif; ?>

                                            </span>

                                        </label>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <div class="alert alert-danger">
                                    Aucune maison active n'est disponible.
                                    Activez ou créez une maison avant
                                    de modifier les affectations.
                                </div>

                            <?php endif; ?>

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

                    <div style="
                        display: flex;
                        gap: 10px;
                        flex-wrap: wrap;
                    ">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
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
