<?php

session_start();

require_once __DIR__ . '/config/database.php';

// Si l'utilisateur est déjà connecté, ouvrir son tableau de bord.
if (isset($_SESSION['utilisateur_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Votre session a expiré. Veuillez réessayer.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !is_string($motDePasse)
            || $motDePasse === ''
        ) {
            $message = 'Adresse e-mail ou mot de passe incorrect.';
        } else {
            $requete = $pdo->prepare(
                'SELECT id, nom, email, mot_de_passe, role
                 FROM utilisateurs
                 WHERE email = :email
                 LIMIT 1'
            );

            $requete->execute(['email' => $email]);
            $utilisateur = $requete->fetch();

            if (
                $utilisateur
                && password_verify(
                    $motDePasse,
                    $utilisateur['mot_de_passe']
                )
            ) {
                // Renouveler l'identifiant de session après la connexion.
                session_regenerate_id(true);

                $_SESSION['utilisateur_id'] = (int) $utilisateur['id'];
                $_SESSION['nom'] = $utilisateur['nom'];
                $_SESSION['email'] = $utilisateur['email'];
                $_SESSION['role'] = $utilisateur['role'];

                // Renouveler le jeton CSRF.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: dashboard.php');
                exit;
            }

            $message = 'Adresse e-mail ou mot de passe incorrect.';
        }
    }
}
?>

<!DOCTYPE html>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ImmoGestion</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

<main class="login-card">

    <div class="login-logo">IG</div>

    <h1>ImmoGestion</h1>

    <p class="login-subtitle">
        Connectez-vous à votre espace de gestion immobilière.
    </p>

    <?php if ($message !== ''): ?>
        <div class="alert" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

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
            <label for="email">Adresse e-mail</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="exemple@email.com"
                autocomplete="username"
                required
            >
        </div>

        <div class="form-group">
            <label for="mot_de_passe">Mot de passe</label>

            <input
                type="password"
                id="mot_de_passe"
                name="mot_de_passe"
                placeholder="Votre mot de passe"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-block">
            Se connecter
        </button>

    </form>

</main>

</body>
</html>
