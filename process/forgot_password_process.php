<?php
session_start();

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../auth/forgot_password.php");
    exit;
}

$resetEmail = $_SESSION["password_reset_email"] ?? null;

if (!is_string($resetEmail) || $resetEmail === "") {
    unset($_SESSION["password_reset_email"]);

    $emailInput = $_POST["email"] ?? "";
    if (!is_string($emailInput)) {
        header("Location: ../auth/forgot_password.php?error=invalid_email");
        exit;
    }

    $email = trim($emailInput);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../auth/forgot_password.php?error=invalid_email");
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT email FROM users WHERE email = :email");
        $stmt->execute([":email" => $email]);
        $registeredEmail = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Password reset email lookup failed: " . $e->getMessage());
        header("Location: ../auth/forgot_password.php?error=server");
        exit;
    }

    if ($registeredEmail === false) {
        header("Location: ../auth/forgot_password.php?error=not_found");
        exit;
    }

    $_SESSION["password_reset_email"] = $registeredEmail;
    session_regenerate_id(true);
    header("Location: ../auth/forgot_password.php");
    exit;
}

$newPassword = $_POST["new_password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if (!is_string($newPassword) || !is_string($confirmPassword)) {
    header("Location: ../auth/forgot_password.php?error=password_mismatch");
    exit;
}

$passwordLength = function_exists("mb_strlen")
    ? mb_strlen($newPassword, "UTF-8")
    : preg_match_all("/./us", $newPassword);

if ($passwordLength === false || $passwordLength < 8) {
    header("Location: ../auth/forgot_password.php?error=password_short");
    exit;
}

if ($newPassword !== $confirmPassword) {
    header("Location: ../auth/forgot_password.php?error=password_mismatch");
    exit;
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare(
        "UPDATE users SET password_hash = :password_hash WHERE email = :email"
    );
    $stmt->execute([
        ":password_hash" => $passwordHash,
        ":email" => $resetEmail,
    ]);
} catch (PDOException $e) {
    error_log("Password reset update failed: " . $e->getMessage());
    header("Location: ../auth/forgot_password.php?error=server");
    exit;
}

if ($stmt->rowCount() !== 1) {
    unset($_SESSION["password_reset_email"]);
    header("Location: ../auth/forgot_password.php?error=account_not_found");
    exit;
}

unset($_SESSION["password_reset_email"]);
header("Location: ../auth/login.php?reset=success");
exit;
