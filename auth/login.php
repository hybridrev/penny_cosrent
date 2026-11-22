<?php
require_once "../config/database.php";

$savedEmail = $_COOKIE["remember_email"] ?? "";
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
    <main class="auth-card">
        <h1>Selamat datang</h1>
        <p class="auth-description">Masuk ke akun Costume Rental Anda.</p>

        <?php if (isset($_GET["registered"])): ?>
            <p class="auth-notice">Akun berhasil dibuat! Silakan login.</p>
        <?php endif; ?>

        <form action="../process/login_process.php" method="POST">
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

        <p class="auth-footer">Belum punya akun? <a href="signup.php">Daftar</a></p>
    </main>
</body>
</html>
