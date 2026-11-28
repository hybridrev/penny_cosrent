<?php
require_once "../config/database.php";

$savedEmail = $_COOKIE["remember_email"] ?? "";
$returnCostume = $_GET["return_costume"] ?? "";
if (!is_string($returnCostume)) {
    $returnCostume = "";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>

<body class="auth-page" onload="document.querySelector('input[name=email]').focus()">
    <div class="auth-shell">
        <!-- <header class="auth-header">
            <a class="auth-brand" href="../index.php" aria-label="Penny Cosrent - Beranda">
                <img class="auth-logo" src="../assets/images/logo.png" alt="Logo Penny Cosrent">
            </a>
            <a class="home-link" href="../index.php">Beranda</a>
        </header> -->

        <main class="auth-card">
            <p class="auth-eyebrow">PENNY COSRENT</p>
            <h1>Selamat datang</h1>
            <p class="auth-description">Masuk ke akun Anda.</p>

            <?php if (isset($_GET["registered"])): ?>
                <p class="auth-notice">Akun berhasil dibuat! Silakan login.</p>
            <?php endif; ?>
            <?php if (isset($_GET["reset"])): ?>
                <p class="auth-notice">Password berhasil diperbarui. Silakan login.</p>
            <?php endif; ?>

            <form action="../process/login_process.php" method="POST">
                <?php if ($returnCostume !== ""): ?>
                    <input
                        type="hidden"
                        name="return_costume"
                        value="<?= htmlspecialchars($returnCostume, ENT_QUOTES, "UTF-8") ?>"
                    >
                <?php endif; ?>

                <div class="form-field">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($savedEmail) ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="form-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>

                <label class="form-option" for="remember_email">
                    <input id="remember_email" type="checkbox" name="remember_email" value="1">
                    Ingat email saya
                </label>

                <button type="submit">Login</button>
            </form>

            <p class="auth-footer"><a href="forgot_password.php">Lupa Password?</a></p>
            <p class="auth-footer">Belum punya akun? <a href="signup.php">Daftar</a></p>
        </main>
    </div>
</body>
</html>
