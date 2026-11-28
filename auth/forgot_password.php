<?php
session_start();

if (isset($_GET["cancel"])) {
    unset($_SESSION["password_reset_email"]);
    header("Location: login.php");
    exit;
}

$isPasswordStage = isset($_SESSION["password_reset_email"])
    && is_string($_SESSION["password_reset_email"])
    && $_SESSION["password_reset_email"] !== "";

$errorMessages = [
    "invalid_email" => "Format email tidak valid. Silakan coba lagi.",
    "not_found" => "Email tersebut tidak terdaftar.",
    "password_short" => "Password baru minimal 8 karakter.",
    "password_mismatch" => "Konfirmasi password tidak cocok.",
    "account_not_found" => "Akun tidak ditemukan. Silakan masukkan email lagi.",
    "server" => "Terjadi kesalahan. Silakan coba kembali.",
];

$error = $_GET["error"] ?? "";
if (!is_string($error)) {
    $error = "";
}
$errorMessage = $errorMessages[$error] ?? "";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password | Penny Cosrent</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>

<body class="auth-page">
    <div class="auth-shell">
        <main class="auth-card">
            <p class="auth-eyebrow">PENNY COSRENT</p>
            <h1>Lupa Password?</h1>
            <!-- <p class="auth-warning">
                Fitur ini hanya simulasi akademik dan tidak aman untuk penggunaan produksi.
            </p> -->

            <?php if ($errorMessage !== ""): ?>
                <p class="auth-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?></p>
            <?php endif; ?>

            <?php if ($isPasswordStage): ?>
                <p class="auth-description">Email ditemukan. Masukkan password baru Anda.</p>
                <form action="../process/forgot_password_process.php" method="POST">
                    <div class="form-field">
                        <label for="new_password">Password baru</label>
                        <input
                            id="new_password"
                            type="password"
                            name="new_password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="confirm_password">Konfirmasi password baru</label>
                        <input
                            id="confirm_password"
                            type="password"
                            name="confirm_password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >
                    </div>

                    <button type="submit">Simpan Password Baru</button>
                </form>
            <?php else: ?>
                <p class="auth-description">Masukkan email yang terdaftar untuk melanjutkan.</p>
                <form action="../process/forgot_password_process.php" method="POST">
                    <div class="form-field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <button type="submit">Periksa Email</button>
                </form>
            <?php endif; ?>

            <p class="auth-footer">
                <a href="forgot_password.php?cancel=1">Kembali ke halaman login</a>
            </p>
        </main>
    </div>
</body>
</html>
