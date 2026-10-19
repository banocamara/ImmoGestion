<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titrePage = $titrePage ?? 'ImmoGestion';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($titrePage, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="/ImmoGestion/assets/css/style.css">
</head>
<body>
