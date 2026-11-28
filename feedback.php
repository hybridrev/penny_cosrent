<?php

session_start();
require_once "config/database.php";

if (empty($_SESSION["feedback_csrf_token"])) {
    $_SESSION["feedback_csrf_token"] = bin2hex(random_bytes(32));
}

$errorMessages = [
    "invalid" => "Data belum lengkap atau tidak valid.",
    "too_short" => "Kritik atau saran minimal 10 karakter.",
    "too_long" => "Pesan maksimal 2000 karakter.",
    "server" => "Pesan gagal disimpan. Silakan coba kembali.",
];

$error = $_GET["error"] ?? "";
$errorMessage = is_string($error) ? ($errorMessages[$error] ?? "") : "";
$sent = ($_GET["sent"] ?? "") === "success";

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
};

$defaultName = isset($_SESSION["username"]) && is_string($_SESSION["username"])
    ? $_SESSION["username"]
    : "";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kritik dan Saran | Penny Cosrent</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <main class="auth-card">
            <p class="auth-eyebrow">PENNY COSRENT</p>
            <h1>Kritik &amp; Saran</h1>
            <p class="auth-description">Sampaikan pendapat, masukan, atau saran untuk Penny Cosrent.</p>

            <?php if ($sent): ?>
                <p class="auth-notice">Terima kasih! Kritik dan saran Anda berhasil dikirim.</p>
            <?php endif; ?>

            <?php if ($errorMessage !== ""): ?>
                <p class="auth-error"><?= $escape($errorMessage) ?></p>
            <?php endif; ?>

            <form action="process/feedback_process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION["feedback_csrf_token"]) ?>">

                <div class="form-field">
                    <label for="name">Nama</label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        maxlength="100"
                        value="<?= $escape($defaultName) ?>"
                        placeholder="Nama Anda"
                    >
                </div>

                <div class="form-field">
                    <label for="email">Email (opsional)</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        maxlength="100"
                        placeholder="nama@email.com"
                    >
                </div>

                <div class="form-field">
                    <label for="message">Kritik atau Saran</label>
                    <textarea
                        id="message"
                        name="message"
                        maxlength="2000"
                        minlength="10"
                        rows="6"
                        required
                        placeholder="Tulis kritik atau saran Anda..."
                    ></textarea>
                </div>

                <button type="submit">Kirim Kritik &amp; Saran</button>
            </form>

            <p class="auth-footer">
                <a href="index.php">Kembali ke beranda</a>
            </p>
        </main>
    </div>
</body>
</html>
