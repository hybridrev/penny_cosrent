<?php
require_once "../config/database.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>

<body class="auth-page" onload="document.querySelector('input[name=username]').focus()">
    <main class="auth-card">
        <h1>Buat akun</h1>
        <p class="auth-description">Daftar untuk mulai menyewa kostum.</p>

        <form action="../process/signup_process.php" method="POST">
            <div class="form-field">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" autocomplete="username" required>
            </div>

            <div class="form-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" autocomplete="email" required>
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required>
            </div>

            <button type="submit">Daftar</button>
        </form>

        <p class="auth-footer">Sudah punya akun? <a href="login.php">Login</a></p>
    </main>
</body>
</html>
