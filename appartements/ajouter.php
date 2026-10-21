<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerSuperAdmin();

// Créer un jeton CSRF.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$maisonId = '';
$numero = '';
$description = '';
$loyerMensuel = '';
$statut = 'libre';

try {
    // Récupérer uniquement les maisons actives.
    $requete = $pdo->query(
        "SELECT id, nom, ville
         FROM maisons
         WHERE actif = 1
         ORDER BY nom ASC"
    );

    $maisons = $requete->fetchAll();

} catch (PDOException $e) {
    error_log('Erreur de récupération des maisons : ' . $e->getMessage());
    http_response_code(500);
    exit('Impossible de récupérer les maisons pour le moment.');
}

// Traiter le formulaire.
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

        // Vérifier que les champs reçus sont des valeurs simples.
        $champsValides =
            isset($_POST['maison_id'], $_POST['numero'], $_POST['loyer_mensuel'])
            && is_string($_POST['maison_id'])
            && is_string($_POST['numero'])
            && is_string($_POST['loyer_mensuel'])
            && (!isset($_POST['description']) || is_string($_POST['description']))
            && (!isset($_POST['statut']) || is_string($_POST['statut']));

        if (!$champsValides) {
            $message = 'Les données du formulaire sont invalides.';
        } else {

            $maisonId = trim($_POST['maison_id']);
            $numero = trim($_POST['numero']);
            $description = trim($_POST['description'] ?? '');
            $loyerMensuel = trim($_POST['loyer_mensuel']);
            $statut = $_POST['statut'] ?? 'libre';

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

            } elseif (mb_strlen($numero, 'UTF-8') > 50) {
                $message = 'Le numéro ne doit pas dépasser 50 caractères.';

            } elseif (mb_strlen($description, 'UTF-8') > 5000) {
                $message = 'La description ne doit pas dépasser 5000 caractères.';

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

                try {
                    // Vérifier que la maison existe et est active.
                    $verification = $pdo->prepare(
                        'SELECT id
                         FROM maisons
                         WHERE id = :id
                           AND actif = 1
                         LIMIT 1'
                    );

                    $verification->execute([
                        'id' => (int) $maisonId
                    ]);

                    if (!$verification->fetch()) {
                        $message = 'La maison sélectionnée est introuvable ou désactivée.';

                    } else {
                        // Enregistrer l'appartement.
                        $requete = $pdo->prepare(
                            'INSERT INTO appartements
                                (maison_id, numero, description, loyer_mensuel, statut)
                             VALUES
                                (:maison_id, :numero, :description, :loyer_mensuel, :statut)'
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
                            'statut' => $statut
                        ]);

                        $_SESSION['message'] = 'L’appartement a été ajouté avec succès.';

                        header('Location: index.php');
                        exit;
                    }

                } catch (PDOException $e) {
                    error_log(
                        'Erreur lors de l’ajout d’un appartement : '
                        . $e->getMessage()
                    );

                    $message = 'Impossible d’enregistrer l’appartement. Veuillez réessayer.';
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

<title>Ajouter un appartement - ImmoGestion</title>

<link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="app-layout">

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

            <h1>Ajouter un appartement</h1>

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
            <h2>Nouvel appartement</h2>
            <p>Renseignez les informations de l'appartement.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="panel">

            <?php if (count($maisons) === 0): ?>

                <p>
                    Aucune maison active n'est disponible.
                    Ajoutez ou réactivez une maison avant de continuer.
                </p>

                <a href="../maisons/index.php" class="btn btn-primary">
                    Gérer les maisons
                </a>

            <?php else: ?>

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
                        <label for="maison_id">
                            Maison *
                        </label>

                        <select name="maison_id" id="maison_id" required>
                            <option value="">-- Sélectionner une maison --</option>

                            <?php foreach ($maisons as $maison): ?>
                                <option
                                    value="<?= (int) $maison['id'] ?>"
                                    <?= $maisonId === (string) $maison['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $maison['nom'] . ' - ' . $maison['ville'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="numero">
                            Numéro de l'appartement *
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
                            placeholder="Ex. : Appartement 1"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>

                        <textarea
                            name="description"
                            id="description"
                            rows="4"
                            maxlength="5000"
                            placeholder="Décrivez l'appartement..."
                        ><?= htmlspecialchars(
                            $description,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="loyer_mensuel">
                            Loyer mensuel (MRU) *
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
                            placeholder="Ex. : 25000"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="statut">Statut</label>

                        <select name="statut" id="statut" required>

                            <option
                                value="libre"
                                <?= $statut === 'libre' ? 'selected' : '' ?>
                            >Libre</option>

                            <option
                                value="occupe"
                                <?= $statut === 'occupe' ? 'selected' : '' ?>
                            >Occupé</option>

                            <option
                                value="indisponible"
                                <?= $statut === 'indisponible' ? 'selected' : '' ?>
                            >Indisponible</option>

                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Enregistrer l'appartement
                    </button>

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                        style="margin-left:10px;"
                    >
                        Annuler
                    </a>

                </form>

            <?php endif; ?>

        </section>

    </section>

</main>

</div>

<script src="../assets/js/script.js"></script>

</body>
</html>
