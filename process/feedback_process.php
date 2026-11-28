<?php

session_start();
require_once "../config/database.php";

try {
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS feedbacks (
            feedback_id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) DEFAULT NULL,
            name varchar(100) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            message text NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (feedback_id),
            KEY user_id (user_id),
            CONSTRAINT feedbacks_ibfk_1 FOREIGN KEY (user_id)
                REFERENCES users (user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
} catch (PDOException $e) {
    error_log("Feedback table initialization failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../feedback.php");
    exit;
}

$csrfToken = $_POST["csrf_token"] ?? "";
if (
    !is_string($csrfToken)
    || !isset($_SESSION["feedback_csrf_token"])
    || !hash_equals($_SESSION["feedback_csrf_token"], $csrfToken)
) {
    http_response_code(403);
    exit("Request tidak valid.");
}

$nameInput = $_POST["name"] ?? "";
$emailInput = $_POST["email"] ?? "";
$messageInput = $_POST["message"] ?? "";

if (
    !is_string($nameInput)
    || !is_string($emailInput)
    || !is_string($messageInput)
) {
    header("Location: ../feedback.php?error=invalid");
    exit;
}

$name = trim($nameInput);
$email = trim($emailInput);
$message = trim($messageInput);
$length = function_exists("mb_strlen")
    ? mb_strlen($message, "UTF-8")
    : strlen($message);
$nameLength = function_exists("mb_strlen")
    ? mb_strlen($name, "UTF-8")
    : strlen($name);

if ($name !== "" && $nameLength > 100) {
    header("Location: ../feedback.php?error=invalid");
    exit;
}
if ($email !== "" && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100)) {
    header("Location: ../feedback.php?error=invalid");
    exit;
}
if ($length < 10) {
    header("Location: ../feedback.php?error=too_short");
    exit;
}
if ($length > 2000) {
    header("Location: ../feedback.php?error=too_long");
    exit;
}

$userId = isset($_SESSION["user_id"])
    ? filter_var($_SESSION["user_id"], FILTER_VALIDATE_INT)
    : null;
$userId = is_int($userId) && $userId > 0 ? $userId : null;

try {
    $stmt = $conn->prepare(
        "INSERT INTO feedbacks (user_id, name, email, message)
         VALUES (:user_id, :name, :email, :message)"
    );
    $stmt->execute([
        ":user_id" => $userId,
        ":name" => $name !== "" ? $name : null,
        ":email" => $email !== "" ? $email : null,
        ":message" => $message,
    ]);
} catch (PDOException $e) {
    error_log("Feedback submission failed: " . $e->getMessage());
    header("Location: ../feedback.php?error=server");
    exit;
}

unset($_SESSION["feedback_csrf_token"]);
header("Location: ../feedback.php?sent=success");
exit;
