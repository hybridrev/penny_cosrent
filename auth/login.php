<?php
require_once "../config/database.php";

$savedEmail = $_COOKIE["remember_email"] ?? "";
?>

<h2>Login</h2>

<?php
if (isset($_GET["registered"])) {
    echo "<p>Akun berhasil dibuat! Silakan login.</p>";
}
?>

<form action="../process/login_process.php" method="POST">

    <label>Email</label><br>
    <input
        type="email"
        name="email"
        value="<?= htmlspecialchars($savedEmail) ?>"
        required
    >
    <br><br>

    <label>Password</label><br>
    <input type="password" name="password" required>
    <br><br>

    <label>
        <input type="checkbox" name="remember_email" value="1">
        Ingat email saya
    </label>
    <br><br>

    <button type="submit">Login</button>

</form>

<p>Belum punya akun?
<a href="signup.php">Signup</a>
</p>