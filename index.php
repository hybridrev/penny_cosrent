<?php

require_once "includes/auth.php";

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda | Costume Rental</title>
    <link rel="stylesheet" href="assets/css/homepage.css">
</head>

<body class="home-page">
    <header class="site-header">
        <a class="brand" href="index.php">Costume Rental</a>
        <a class="logout-link" href="auth/logout.php">Logout</a>
    </header>

    <main class="home-content">
        <section class="welcome-card">
            <p class="eyebrow">BERANDA</p>
            <h1>Halo, <?= htmlspecialchars($_SESSION["username"]) ?>!</h1>
            <p class="welcome-message">
                Selamat datang di Costume Rental. Kami sedang menyiapkan pengalaman baru untuk Anda.
            </p>
            <span class="status-badge">Homepage</span>
        </section>
    </main>
</body>
</html>