<?php

session_start();

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Cari akun berdasarkan email
    $sql = "SELECT * FROM users WHERE email = :email";

    $stmt = $conn->prepare($sql);
    $stmt->execute([":email" => $email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifikasi password
    if ($user && password_verify($password, $user["password_hash"])) {

        // Membuat session baru
        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["username"] = $user["username"];

        // Mengatur cookies Remember Email
        if (isset($_POST["remember_email"])) {

            setcookie(
                "remember_email",
                $email,
                [
                    "expires" => time() + (86400 * 30),
                    "path" => "/",
                    "secure" => !empty($_SERVER["HTTPS"]),
                    "httponly" => true,
                    "samesite" => "Lax"
                ]
            );

        } else {

            setcookie(
                "remember_email",
                "",
                [
                    "expires" => time() - 3600,
                    "path" => "/",
                    "httponly" => true,
                    "samesite" => "Lax"
                ]
            );

        }

        header("Location: ../");
        exit;

    } else {

        echo "Email atau password salah!";

    }

} else {

    header("Location: ../auth/login.php");
    exit;

}

?>