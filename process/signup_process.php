<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Format email tidak valid!");
    }

    if (strlen($password) < 8) {
        die("Password minimal 8 karakter!");
    }

    // Hash password
    $password_hash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    try {

        $sql = "INSERT INTO users
                (username, email, password_hash)
                VALUES (:username, :email, :password)";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            ":username" => $username,
            ":email" => $email,
            ":password" => $password_hash
        ]);

        header("Location: ../auth/login.php?registered=1");
        exit;

    } catch (PDOException $e) {

        if ($e->getCode() == "23000") {
            die("Email sudah terdaftar!");
        }

        die("Terjadi kesalahan saat mendaftar.");
    }

} else {
    header("Location: ../auth/signup.php");
    exit;
}

?>