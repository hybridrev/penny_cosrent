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
$costumeIdInput = $_POST["costume_id"] ?? "";
$costumeId = is_string($costumeIdInput)
    ? filter_var($costumeIdInput, FILTER_VALIDATE_INT)
    : false;

if (
    !is_string($action)
    || !in_array($action, ["create", "update"], true)
    || ($action === "update" && ($costumeId === false || $costumeId < 1))
) {
    $redirectAdmin("tab=costumes&error=invalid");
}

$redirectError = static function (string $error) use ($action, $costumeId, $redirectAdmin): void {
    $query = "tab=costumes&error=" . rawurlencode($error);
    if ($action === "update" && $costumeId !== false) {
        $query .= "&edit_costume=" . (int) $costumeId;
    }

    $redirectAdmin($query);
};

$nameInput = $_POST["costume_name"] ?? "";
$categoryInput = $_POST["category"] ?? "";
$descriptionInput = $_POST["description"] ?? "";
$sizeInput = $_POST["size"] ?? "";
$priceInput = $_POST["price_per_day"] ?? "";
$stockInput = $_POST["stock"] ?? "";

if (
    !is_string($nameInput)
    || !is_string($categoryInput)
    || !is_string($descriptionInput)
    || !is_string($sizeInput)
    || !is_string($priceInput)
    || !is_string($stockInput)
) {
    $redirectError("invalid");
}

$costumeName = trim($nameInput);
$category = trim($categoryInput);
$description = trim($descriptionInput);
$size = trim($sizeInput);
$pricePerDay = trim($priceInput);
$stock = filter_var($stockInput, FILTER_VALIDATE_INT);

$nameLengthValid = preg_match('/^.{1,100}$/us', $costumeName) === 1;
$namePathSafe = preg_match('~[\\\\/:*?"<>|\x00-\x1F]~u', $costumeName) !== 1
    && !in_array($costumeName, [".", ".."], true)
    && preg_match('/[. ]$/u', $costumeName) !== 1;
$sizeIsValid = preg_match('/^.{1,20}$/us', $size) === 1;
$categoryIsValid = preg_match('/^.{0,50}$/us', $category) === 1;
$descriptionIsValid = preg_match('/^.{0,5000}$/us', $description) === 1;
$priceIsValid = preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $pricePerDay) === 1
    && (float) $pricePerDay > 0;

if (
    !$nameLengthValid
    || !$namePathSafe
    || !$sizeIsValid
    || !$categoryIsValid
    || !$descriptionIsValid
    || !$priceIsValid
    || $stock === false
    || $stock < 0
    || $stock > 1000000
) {
    $redirectError("invalid");
}

$costumeRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets"
    . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "costume";
$existingFolders = glob($costumeRoot . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR);
if ($existingFolders === false) {
    error_log("Unable to read costume image folders.");
    $redirectError("failed");
}

$existingNames = array_map("basename", $existingFolders);
$reservedName = preg_match(
    '/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\..*)?$/i',
    $costumeName
) === 1;
if (
    $reservedName
    && !in_array($costumeName, $existingNames, true)
) {
    $redirectError("invalid");
}

$targetFolder = $costumeRoot . DIRECTORY_SEPARATOR . $costumeName;
$imageFilePath = $targetFolder . DIRECTORY_SEPARATOR . "1.jpg";
$imageRelativePath = "assets/images/costume/" . $costumeName . "/1.jpg";
$imageExists = is_file($imageFilePath);

$upload = $_FILES["costume_image"] ?? null;
$uploadError = is_array($upload) && isset($upload["error"])
    ? (int) $upload["error"]
    : UPLOAD_ERR_NO_FILE;
$hasUpload = $uploadError !== UPLOAD_ERR_NO_FILE;

if ($hasUpload) {
    if (
        $uploadError !== UPLOAD_ERR_OK
        || !isset($upload["tmp_name"])
        || !is_string($upload["tmp_name"])
        || !is_uploaded_file($upload["tmp_name"])
    ) {
        $redirectError("image_invalid");
    }

    $uploadedSize = @filesize($upload["tmp_name"]);
    $imageInfo = @getimagesize($upload["tmp_name"]);
    if (
        $uploadedSize === false
        || $uploadedSize < 1
        || $uploadedSize > 5 * 1024 * 1024
        || $imageInfo === false
        || $imageInfo[2] !== IMAGETYPE_JPEG
    ) {
        $redirectError("image_invalid");
    }

    if ($imageExists) {
        $redirectError("image_exists");
    }
} elseif (!$imageExists) {
    $redirectError("image_required");
}

$createdFolder = false;
$movedImage = false;
$removeNewImage = static function () use (&$createdFolder, &$movedImage, $imageFilePath, $targetFolder): void {
    if ($movedImage && is_file($imageFilePath) && !unlink($imageFilePath)) {
        error_log("Unable to remove a costume image after a failed database operation.");
    }
    if ($createdFolder && is_dir($targetFolder) && !rmdir($targetFolder)) {
        error_log("Unable to remove an empty costume image folder after a failed database operation.");
    }
};

try {
    $conn->beginTransaction();

    $oldName = null;
    if ($action === "update") {
        $stmt = $conn->prepare(
            "SELECT costume_name FROM costumes WHERE costume_id = :costume_id FOR UPDATE"
        );
        $stmt->execute([":costume_id" => $costumeId]);
        $existingCostume = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingCostume) {
            $conn->rollBack();
            $redirectError("costume_not_found");
        }
        $oldName = (string) $existingCostume["costume_name"];
    }

    if ($action === "update" && $oldName !== $costumeName) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM costumes WHERE costume_name = :costume_name"
        );
        $stmt->execute([":costume_name" => $costumeName]);
        if ((int) $stmt->fetchColumn() > 0) {
            $conn->rollBack();
            $redirectError("duplicate");
        }
    }

    $duplicateSql =
        "SELECT COUNT(*) FROM costumes
         WHERE costume_name = :costume_name AND size = :size";
    $duplicateParams = [
        ":costume_name" => $costumeName,
        ":size" => $size,
    ];
    if ($action === "update") {
        $duplicateSql .= " AND costume_id != :costume_id";
        $duplicateParams[":costume_id"] = $costumeId;
    }
    $stmt = $conn->prepare($duplicateSql);
    $stmt->execute($duplicateParams);
    if ((int) $stmt->fetchColumn() > 0) {
        $conn->rollBack();
        $redirectError("duplicate");
    }

    if ($hasUpload) {
        if (!is_dir($targetFolder)) {
            $createdFolder = @mkdir($targetFolder, 0755);
            if (!$createdFolder && !is_dir($targetFolder)) {
                error_log("Unable to create costume image folder.");
                $conn->rollBack();
                $redirectError("failed");
            }
        }

        if (is_file($imageFilePath)) {
            $conn->rollBack();
            $redirectError("image_exists");
        }

        if (!@move_uploaded_file($upload["tmp_name"], $imageFilePath)) {
            error_log("Unable to store uploaded costume image.");
            $conn->rollBack();
            $removeNewImage();
            $redirectError("failed");
        }
        $movedImage = true;
    }

    if ($action === "create") {
        $stmt = $conn->prepare(
            "UPDATE costumes
             SET category = :category, description = :description, image = :image
             WHERE costume_name = :costume_name"
        );
        $stmt->execute([
            ":category" => $category !== "" ? $category : null,
            ":description" => $description !== "" ? $description : null,
            ":image" => $imageRelativePath,
            ":costume_name" => $costumeName,
        ]);

        $stmt = $conn->prepare(
            "INSERT INTO costumes
                (costume_name, category, description, size, price_per_day, stock, image)
             VALUES
                (:costume_name, :category, :description, :size, :price_per_day, :stock, :image)"
        );
        $stmt->execute([
            ":costume_name" => $costumeName,
            ":category" => $category !== "" ? $category : null,
            ":description" => $description !== "" ? $description : null,
            ":size" => $size,
            ":price_per_day" => $pricePerDay,
            ":stock" => $stock,
            ":image" => $imageRelativePath,
        ]);
    } else {
        $stmt = $conn->prepare(
            "UPDATE costumes
             SET costume_name = :new_name,
                 category = :category,
                 description = :description,
                 image = :image
             WHERE costume_name = :old_name"
        );
        $stmt->execute([
            ":new_name" => $costumeName,
            ":category" => $category !== "" ? $category : null,
            ":description" => $description !== "" ? $description : null,
            ":image" => $imageRelativePath,
            ":old_name" => $oldName,
        ]);

        $stmt = $conn->prepare(
            "UPDATE costumes
             SET size = :size, price_per_day = :price_per_day, stock = :stock
             WHERE costume_id = :costume_id"
        );
        $stmt->execute([
            ":size" => $size,
            ":price_per_day" => $pricePerDay,
            ":stock" => $stock,
            ":costume_id" => $costumeId,
        ]);
    }

    $conn->commit();
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $removeNewImage();
    error_log("Admin costume save failed: " . $e->getMessage());
    $redirectError("failed");
}

$redirectAdmin(
    "tab=costumes&notice=" . ($action === "create" ? "costume_created" : "costume_updated")
);
