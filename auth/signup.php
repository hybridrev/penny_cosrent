<?php
require_once "../config/database.php";
?>

<h2>Signup</h2>

<form action="../process/signup_process.php" method="POST">

    <label>Username</label><br>
    <input type="text" name="username" required>
    <br><br>

    <label>Email</label><br>
    <input type="email" name="email" required>
    <br><br>

    <label>Password</label><br>
    <input type="password" name="password" required>
    <br><br>

    <button type="submit">Daftar</button>

</form>

<p>Sudah punya akun? 
<a href="login.php">Login</a>
</p>