<?php

require_once "includes/auth.php";

?>

<h1>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?>!</h1>

<p>Selamat datang di Costume Rental Website.</p>

<a href="auth/logout.php">Logout</a>