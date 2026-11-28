<?php
require_once "../includes/admin_access.php";
if (!rentalAdminClientAllowed()) {
    http_response_code(403);
    exit("Aksi admin hanya dapat dilakukan dari jaringan lokal.");
}

session_start();
require_once "../config/database.php";

$redirectAdmin = static function (string $query): void {
    header("Location: ../admin.php?" . $query);
    exit;
};

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metode request tidak diizinkan.");
}

$csrfToken = $_POST["csrf_token"] ?? "";
if (
    !is_string($csrfToken)
    || !isset($_SESSION["admin_csrf_token"])
    || !hash_equals($_SESSION["admin_csrf_token"], $csrfToken)
) {
    http_response_code(403);
    exit("Request tidak valid. Muat ulang halaman admin dan coba kembali.");
}

$action = $_POST["action"] ?? "";
$userIdInput = $_POST["user_id"] ?? "";
$userId = is_string($userIdInput)
    ? filter_var($userIdInput, FILTER_VALIDATE_INT)
    : false;

if (
    !is_string($action)
    || !in_array($action, ["update", "delete"], true)
    || $userId === false
    || $userId < 1
) {
    $redirectAdmin("tab=users&error=invalid");
}

if ($action === "delete") {
    try {
        $conn->beginTransaction();

        $userStmt = $conn->prepare(
            "SELECT user_id FROM users WHERE user_id = :user_id FOR UPDATE"
        );
        $userStmt->execute([":user_id" => $userId]);
        if (!$userStmt->fetch(PDO::FETCH_ASSOC)) {
            $conn->rollBack();
            $redirectAdmin("tab=users&error=user_not_found");
        }

        $rentalStmt = $conn->prepare(
            "UPDATE rentals SET user_id = NULL WHERE user_id = :user_id"
        );
        $rentalStmt->execute([":user_id" => $userId]);

        $deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = :user_id");
        $deleteStmt->execute([":user_id" => $userId]);
        $conn->commit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log("Admin user deletion failed: " . $e->getMessage());
        $redirectAdmin("tab=users&error=failed");
    }

    if (isset($_SESSION["user_id"]) && (int) $_SESSION["user_id"] === $userId) {
        unset($_SESSION["user_id"]);
        session_regenerate_id(true);
    }

    $redirectAdmin("tab=users&notice=user_deleted");
}

$usernameInput = $_POST["username"] ?? "";
$emailInput = $_POST["email"] ?? "";
$newPassword = $_POST["new_password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if (
    !is_string($usernameInput)
    || !is_string($emailInput)
    || !is_string($newPassword)
    || !is_string($confirmPassword)
) {
    $redirectAdmin("tab=users&error=invalid");
}

$username = trim($usernameInput);
$email = trim($emailInput);
$usernameLength = preg_match_all("/./us", $username);
$emailLength = strlen($email);
$passwordLength = preg_match_all("/./us", $newPassword);

if (
    $usernameLength === false
    || $usernameLength < 1
    || $usernameLength > 50
    || $emailLength > 100
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || ($newPassword !== "" && ($passwordLength === false || $passwordLength < 8))
    || (($newPassword !== "" || $confirmPassword !== "") && $newPassword !== $confirmPassword)
) {
    $redirectAdmin("tab=users&edit_user=" . (int) $userId . "&error=invalid#user-form");
}

try {
    $existsStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = :user_id");
    $existsStmt->execute([":user_id" => $userId]);
    if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
        $redirectAdmin("tab=users&error=user_not_found");
    }

    if ($newPassword !== "") {
        $stmt = $conn->prepare(
            "UPDATE users
             SET username = :username, email = :email, password_hash = :password_hash
             WHERE user_id = :user_id"
        );
        $stmt->execute([
            ":username" => $username,
            ":email" => $email,
            ":password_hash" => password_hash($newPassword, PASSWORD_DEFAULT),
            ":user_id" => $userId,
        ]);
    } else {
        $stmt = $conn->prepare(
            "UPDATE users
             SET username = :username, email = :email
             WHERE user_id = :user_id"
        );
        $stmt->execute([
            ":username" => $username,
            ":email" => $email,
            ":user_id" => $userId,
        ]);
    }
} catch (PDOException $e) {
    if ($e->getCode() === "23000") {
        $redirectAdmin("tab=users&edit_user=" . (int) $userId . "&error=email_exists#user-form");
    }

    error_log("Admin user update failed: " . $e->getMessage());
    $redirectAdmin("tab=users&edit_user=" . (int) $userId . "&error=failed#user-form");
}

$redirectAdmin("tab=users&notice=user_updated");
