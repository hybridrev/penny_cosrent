<?php

require_once "../includes/admin_access.php";
if (!rentalAdminClientAllowed()) {
    http_response_code(403);
    exit("Aksi admin hanya dapat dilakukan dari jaringan lokal.");
}

session_start();
require_once "../config/database.php";

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
    exit("Request tidak valid.");
}

$feedbackId = filter_var($_POST["feedback_id"] ?? "", FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";

if ($action !== "delete" || $feedbackId === false || $feedbackId < 1) {
    header("Location: ../admin.php?tab=feedback&error=invalid");
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM feedbacks WHERE feedback_id = :feedback_id");
    $stmt->execute([":feedback_id" => $feedbackId]);
} catch (PDOException $e) {
    error_log("Admin feedback deletion failed: " . $e->getMessage());
    header("Location: ../admin.php?tab=feedback&error=failed");
    exit;
}

if ($stmt->rowCount() !== 1) {
    header("Location: ../admin.php?tab=feedback&error=feedback_not_found");
    exit;
}

header("Location: ../admin.php?tab=feedback&notice=feedback_deleted");
exit;
