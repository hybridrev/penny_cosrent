<?php

session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION["user_id"])) {

    header("Location: auth/login.php");
    exit;

}

?>