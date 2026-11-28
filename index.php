<?php

session_start();
require_once "config/database.php";

$isLoggedIn = isset($_SESSION["user_id"], $_SESSION["username"]);
$costumeNamesStmt = $conn->query("SELECT DISTINCT costume_name FROM costumes");
$costumeNames = array_fill_keys($costumeNamesStmt->fetchAll(PDO::FETCH_COLUMN), true);

$costumeFolders = glob(
    __DIR__ . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "images"
        . DIRECTORY_SEPARATOR . "costume" . DIRECTORY_SEPARATOR . "*",
    GLOB_ONLYDIR
);

if ($costumeFolders === false) {
    throw new RuntimeException("Gagal membaca folder katalog kostum.");
}

sort($costumeFolders, SORT_NATURAL | SORT_FLAG_CASE);
$costumes = [];

foreach ($costumeFolders as $folder) {
    if (!is_file($folder . DIRECTORY_SEPARATOR . "1.jpg")) {
        continue;
    }

    $name = basename($folder);
    if (!isset($costumeNames[$name])) {
        continue;
    }

    $costumes[] = [
        "name" => $name,
        "image" => "assets/images/costume/" . rawurlencode($name) . "/1.jpg",
    ];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda | Penny Cosrent</title>
    <link rel="stylesheet" href="assets/css/homepage.css">
    <link rel="stylesheet" href="assets/css/costume-detail.css">
</head>

<body class="home-page">
    <div class="blur-group">
    <header class="site-header">
        <a class="brand" href="index.php">
            <img class="brand-logo" src="assets/images/logo.png" alt="Logo Penny Cosrent">
            <!-- <span>Penny Cosrent</span> -->
        </a>
        <nav class="header-actions" aria-label="Navigasi akun">
            <!-- <a class="logout-link" href="admin.php">Admin</a> -->
            <?php if ($isLoggedIn): ?>
                <a class="logout-link" href="auth/logout.php">Logout</a>
            <?php else: ?>
                <a class="logout-link" href="auth/login.php">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="home-content">
            <div class="welcome-card">
            <?php if ($isLoggedIn): ?>
                <p class="eyebrow">BERANDA</p>
                <h1 class="welcome-title">Halow, <?= htmlspecialchars($_SESSION["username"]) ?>!</h1>
                <p class="welcome-message">
                    Selamat datang di Penny Cosrent, <?= htmlspecialchars($_SESSION["username"]) ?>.
                </p>
                <span class="status-badge">Homepage</span>
            <?php else: ?>
                <p class="eyebrow">BERANDA</p>
                <h1 class="welcome-title">Selamat Datang</h1>
                <p class="welcome-message">
                    Login untuk melanjutkan layanan Penny Cosrent.
                </p>
                <a class="login-button" href="auth/login.php">Login</a>
            <?php endif; ?>
            </div>

        <section class="costume-section" aria-labelledby="costume-heading">
            <div class="catalog-heading">
                <p class="eyebrow">KOLEKSI</p>
                <h2 id="costume-heading">Katalog Kostum</h2>
                <p class="catalog-description">Silahkan pilih kostum favoritmu disini yawww :3.</p>
            </div>

            <?php if ($costumes): ?>
                <div class="costume-grid">
                    <?php foreach ($costumes as $costume): ?>
                        <a
                            class="costume-card-link"
                            href="pages/costume_detail.php?name=<?= rawurlencode($costume["name"]) ?>"
                        >
                            <article class="costume-card">
                                <img
                                    class="costume-image"
                                    src="<?= htmlspecialchars($costume["image"], ENT_QUOTES, "UTF-8") ?>"
                                    alt="Kostum <?= htmlspecialchars($costume["name"], ENT_QUOTES, "UTF-8") ?>"
                                    loading="lazy"
                                >
                                <div class="costume-card-content">
                                    <h3><?= htmlspecialchars($costume["name"], ENT_QUOTES, "UTF-8") ?></h3>
                                    <span class="costume-card-action">Lihat detail</span>
                                </div>
                            </article>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-catalog">Belum ada kostum dengan gambar preview 1.jpg.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer" style="padding: 30px;">
        <h2>Penny Cosrent</h2>
        <p class="footer-contact" style="padding-top: 10px">
            Contact :
            <a href="mailto:herlambang@abenmy.id">herlambang@abenmy.id</a>
        </p>
        <p class="footer-credit" style="padding-top: 10px">&copy; <?= date("Y") ?> Penny Cosrent &mdash; Made with love</p>
        <a
            class="footer-feedback-button"
            href="feedback.php"
        >
            Kritik &amp; Saran
        </a>
    </footer>
    </div>
</body>
</html>