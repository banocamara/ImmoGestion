<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// Créer un jeton CSRF si nécessaire.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

// Récupérer l'identifiant depuis l'URL ou le formulaire.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

// Vérifier l'identifiant.
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant d’appartement invalide.');
}

// Récupérer l'appartement.
$requete = $pdo->prepare(
    'SELECT id, maison_id, numero, description, loyer_mensuel, statut
     FROM appartements
     WHERE id = :id'
);

$requete->execute(['id' => $id]);

$appartement = $requete->fetch();

if (!$appartement) {
    http_response_code(404);
    exit('Appartement introuvable.');
}

// Récupérer les maisons actives ainsi que la maison actuellement associée.
$requeteMaisons = $pdo->prepare(
    "SELECT id, nom, ville, actif
     FROM maisons
     WHERE actif = 1 OR id = :maison_id
     ORDER BY nom ASC"
);

$requeteMaisons->execute([
    'maison_id' => $appartement['maison_id']
]);

$maisons = $requeteMaisons->fetchAll();

// Valeurs initiales du formulaire.
$maisonId = (string) $appartement['maison_id'];
$numero = $appartement['numero'];
$description = $appartement['description'] ?? '';
$loyerMensuel = (string) $appartement['loyer_mensuel'];
$statut = $appartement['statut'];

// Traitement du formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF.
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Votre session a expiré. Veuillez réessayer.';
    } else {

        $maisonId = trim($_POST['maison_id'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $loyerMensuel = trim($_POST['loyer_mensuel'] ?? '');
        $statut = $_POST['statut'] ?? '';

        // Vérifier les champs obligatoires.
        if (
            $maisonId === ''
            || $numero === ''
            || $loyerMensuel === ''
        ) {
            $message = 'Veuillez remplir tous les champs obligatoires.';
        } elseif (
            !ctype_digit($maisonId)
            || (int) $maisonId < 1
        ) {
            $message = 'Veuillez sélectionner une maison valide.';
        } elseif (mb_strlen($numero) > 50) {
            $message = 'Le numéro ne doit pas dépasser 50 caractères.';
        } elseif (mb_strlen($description) > 5000) {
            $message = 'La description est trop longue.';
        } elseif (
            !is_numeric($loyerMensuel)
            || !is_finite((float) $loyerMensuel)
            || (float) $loyerMensuel < 0
            || (float) $loyerMensuel > 9999999999.99
        ) {
            $message = 'Veuillez saisir un loyer valide.';
        } elseif (
            !in_array(
                $statut,
                ['libre', 'occupe', 'indisponible'],
                true
            )
        ) {
            $message = 'Le statut sélectionné est invalide.';
        } else {

            // Vérifier que la maison existe et qu'elle est active.
            $verification = $pdo->prepare(
                'SELECT id
                 FROM maisons
                 WHERE id = :id AND actif = 1'
            );

            $verification->execute([
                'id' => (int) $maisonId
            ]);

            $maisonExiste = $verification->fetch();

            // Autoriser également la maison actuelle si elle est inactive,
            // à condition de ne pas changer l'association.
            if (
                !$maisonExiste
                && (int) $maisonId === (int) $appartement['maison_id']
            ) {
                $verification = $pdo->prepare(
                    'SELECT id
                     FROM maisons
                     WHERE id = :id'
                );

                $verification->execute([
                    'id' => (int) $maisonId
                ]);

                $maisonExiste = $verification->fetch();
            }

            if (!$maisonExiste) {

                $message =
                    'La maison sélectionnée est introuvable ou désactivée.';

            } else {

                try {

                    // Mettre à jour l'appartement.
                    $requete = $pdo->prepare(
                        'UPDATE appartements
                         SET maison_id = :maison_id,
                             numero = :numero,
                             description = :description,
                             loyer_mensuel = :loyer_mensuel,
                             statut = :statut
                         WHERE id = :id'
                    );

                    $requete->execute([
                        'maison_id' => (int) $maisonId,
                        'numero' => $numero,
                        'description' => $description !== ''
                            ? $description
                            : null,
                        'loyer_mensuel' => number_format(
                            (float) $loyerMensuel,
                            2,
                            '.',
                            ''
                        ),
                        'statut' => $statut,
                        'id' => $id
                    ]);

                    $_SESSION['message'] =
                        'L’appartement a été modifié avec succès.';

                    header('Location: index.php');
                    exit;

                } catch (PDOException $e) {

                    error_log(
                        'Erreur lors de la modification d’un appartement : '
                        . $e->getMessage()
                    );

                    $message =
                        'Impossible de modifier l’appartement. Veuillez réessayer.';
                }
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

    <title>Modifier un appartement - ImmoGestion</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

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

                <h1>Modifier un appartement</h1>

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
                <h2>Modification de l'appartement</h2>
                <p>Modifiez les informations nécessaires puis enregistrez.</p>
            </div>

            <?php if ($message !== ''): ?>

                <div class="panel" style="color: #dc2626; margin-bottom: 20px;">
                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <section class="panel">

                <form action="modifier.php?id=<?= (int) $id ?>" method="POST">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $id ?>"
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

                    <div class="form-group">

                        <label for="maison_id">
                            Maison <span aria-hidden="true">*</span>
                        </label>

                        <select name="maison_id" id="maison_id" required>

                            <option value="">
                                -- Sélectionner une maison --
                            </option>

                            <?php foreach ($maisons as $maison): ?>

                                <option
                                    value="<?= (int) $maison['id'] ?>"
                                    <?= $maisonId === (string) $maison['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $maison['nom'] . ' - ' . $maison['ville']
                                        . ((int) $maison['actif'] === 1
                                            ? ''
                                            : ' (Maison inactive)'),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="numero">
                            Numéro de l'appartement
                            <span aria-hidden="true">*</span>
                        </label>

                        <input
                            type="text"
                            name="numero"
                            id="numero"
                            maxlength="50"
                            value="<?= htmlspecialchars(
                                $numero,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="description">
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="description"
                            rows="4"
                            maxlength="5000"
                        ><?= htmlspecialchars(
                            $description,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>

                    </div>

                    <div class="form-group">

                        <label for="loyer_mensuel">
                            Loyer mensuel (MRU)
                            <span aria-hidden="true">*</span>
                        </label>

                        <input
                            type="number"
                            name="loyer_mensuel"
                            id="loyer_mensuel"
                            min="0"
                            max="9999999999.99"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $loyerMensuel,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="statut">Statut</label>

                        <select name="statut" id="statut" required>

                            <option
                                value="libre"
                                <?= $statut === 'libre' ? 'selected' : '' ?>
                            >
                                Libre
                            </option>

                            <option
                                value="occupe"
                                <?= $statut === 'occupe' ? 'selected' : '' ?>
                            >
                                Occupé
                            </option>

                            <option
                                value="indisponible"
                                <?= $statut === 'indisponible' ? 'selected' : '' ?>
                            >
                                Indisponible
                            </option>

                        </select>

                    </div>

                    <button type="submit" class="btn">
                        Enregistrer les modifications
                    </button>

                    <a
                        href="index.php"
                        class="btn"
                        style="margin-left: 10px;"
                    >
                        Annuler
                    </a>

                </form>

            </section>

        </section>

    </main>

</div>

<script src="../assets/js/script.js"></script>

</body>

</html>
