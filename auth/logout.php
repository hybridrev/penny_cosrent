<?php

session_start();

// Hapus seluruh data session
$_SESSION = [];

// Hapus cookie session PHP
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 3600,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => "Lax"
        ]
    );

}

session_destroy();

// Kembali ke beranda publik
header("Location: ../index.php");
exit;

?>