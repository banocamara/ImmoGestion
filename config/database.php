<?php

// Charger les paramètres depuis le fichier .env
$fichierEnv = __DIR__ . '/../.env';

if (!is_file($fichierEnv)) {
    error_log('Le fichier .env est introuvable.');
    http_response_code(500);
    exit('La configuration de l’application est indisponible.');
}

$configuration = parse_ini_file($fichierEnv);

if ($configuration === false) {
    error_log('Impossible de lire le fichier .env.');
    http_response_code(500);
    exit('La configuration de l’application est indisponible.');
}

// Récupérer les paramètres de connexion
$host = $configuration['DB_HOST'] ?? '127.0.0.1';
$dbname = $configuration['DB_NAME'] ?? '';
$username = $configuration['DB_USER'] ?? '';
$password = $configuration['DB_PASS'] ?? '';

try {
    // Connexion à MySQL avec PDO
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    error_log('Erreur PDO : ' . $e->getMessage());

    http_response_code(500);
    exit('Impossible de se connecter à la base de données.');
}
