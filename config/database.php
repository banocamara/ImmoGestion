<?php

// Paramètres de connexion à la base de données
$host = 'localhost';
$dbname = 'immo_gestion';
$username = 'root';
$password = '';

try {
    // Création de la connexion PDO
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // Activation des exceptions en cas d'erreur SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupération des résultats sous forme de tableaux associatifs
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // En production, ne pas afficher les détails techniques de l'erreur
    error_log('Erreur de connexion à la base de données : ' . $e->getMessage());

    http_response_code(500);
    exit('Impossible de se connecter à la base de données.');
}
