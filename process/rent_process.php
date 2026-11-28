<?php
session_start();

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit;
}

$costumeIdInput = $_POST["costume_id"] ?? null;
$costumeId = is_string($costumeIdInput)
    ? filter_var($costumeIdInput, FILTER_VALIDATE_INT)
    : false;
$costumeName = $_POST["costume_name"] ?? "";

if ($costumeId === false || $costumeId === null || !is_string($costumeName)) {
    header("Location: ../index.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT costume_name FROM costumes
     WHERE costume_id = :costume_id AND costume_name = :costume_name"
);
$stmt->execute([
    ":costume_id" => $costumeId,
    ":costume_name" => $costumeName,
]);
$registeredName = $stmt->fetchColumn();

if ($registeredName === false) {
    header("Location: ../index.php");
    exit;
}

$costumeFolders = glob(
    __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "assets"
        . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "costume"
        . DIRECTORY_SEPARATOR . "*",
    GLOB_ONLYDIR
);

if ($costumeFolders === false) {
    throw new RuntimeException("Gagal membaca folder katalog kostum.");
}

$hasCostumeFolder = false;
foreach ($costumeFolders as $folder) {
    if (basename($folder) === $registeredName && is_file($folder . DIRECTORY_SEPARATOR . "1.jpg")) {
        $hasCostumeFolder = true;
        break;
    }
}

if (!$hasCostumeFolder) {
    header("Location: ../index.php");
    exit;
}

$rentalFormUrl = "../pages/rental_form.php?name=" . rawurlencode($registeredName);
$redirectToRentalForm = static function (string $query = "") use ($rentalFormUrl): void {
    header("Location: " . $rentalFormUrl . ($query !== "" ? "&" . $query : ""));
    exit;
};

$renterNameInput = $_POST["renter_name"] ?? "";
$renterEmailInput = $_POST["renter_email"] ?? "";
$renterPhoneInput = $_POST["renter_phone"] ?? "";
$renterAddressInput = $_POST["renter_address"] ?? "";

if (
    !is_string($renterNameInput)
    || !is_string($renterEmailInput)
    || !is_string($renterPhoneInput)
    || !is_string($renterAddressInput)
) {
    $redirectToRentalForm("error=invalid_details");
}

$renterName = trim($renterNameInput);
$renterEmail = trim($renterEmailInput);
$renterPhone = trim($renterPhoneInput);
$renterAddress = trim($renterAddressInput);

if (
    $renterName === ""
    || strlen($renterName) > 100
    || !filter_var($renterEmail, FILTER_VALIDATE_EMAIL)
    || strlen($renterEmail) > 100
    || !preg_match('/^[0-9+().\-\s]{6,20}$/', $renterPhone)
    || $renterAddress === ""
    || strlen($renterAddress) > 1000
) {
    $redirectToRentalForm("error=invalid_details");
}

$startInput = $_POST["start_date"] ?? "";
$returnInput = $_POST["return_date"] ?? "";

if (!is_string($startInput) || !is_string($returnInput)) {
    $redirectToRentalForm("error=invalid_dates");
}

$parseDate = static function (string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (
        $date === false
        || ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0))
        || $date->format("Y-m-d") !== $value
    ) {
        return null;
    }

    return $date;
};

$startDate = $parseDate($startInput);
$returnDate = $parseDate($returnInput);
$today = new DateTimeImmutable("today");

if (
    $startDate === null
    || $returnDate === null
    || $startDate < $today
    || $returnDate <= $startDate
) {
    $redirectToRentalForm("error=invalid_dates");
}

$rentalDays = (int) $startDate->diff($returnDate)->days;
if ($rentalDays < 1) {
    $redirectToRentalForm("error=invalid_dates");
}

$userId = isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare(
        "SELECT costume_id, price_per_day, stock
         FROM costumes
         WHERE costume_id = :costume_id AND costume_name = :costume_name
         FOR UPDATE"
    );
    $stmt->execute([
        ":costume_id" => $costumeId,
        ":costume_name" => $registeredName,
    ]);
    $costume = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$costume || (int) $costume["stock"] < 1) {
        $conn->rollBack();
        $redirectToRentalForm("error=unavailable");
    }

    $pricePerDay = (float) $costume["price_per_day"];
    $totalPrice = $pricePerDay * $rentalDays;

    $stmt = $conn->prepare(
        "UPDATE costumes SET stock = stock - 1
         WHERE costume_id = :costume_id AND stock > 0"
    );
    $stmt->execute([":costume_id" => $costumeId]);

    if ($stmt->rowCount() !== 1) {
        $conn->rollBack();
        $redirectToRentalForm("error=unavailable");
    }

    $stmt = $conn->prepare(
        "INSERT INTO rentals
            (user_id, renter_name, renter_email, renter_phone, renter_address,
             start_date, return_date, total_price)
         VALUES
            (:user_id, :renter_name, :renter_email, :renter_phone, :renter_address,
             :start_date, :return_date, :total_price)"
    );
    $stmt->bindValue(
        ":user_id",
        $userId,
        $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );
    $stmt->bindValue(":renter_name", $renterName, PDO::PARAM_STR);
    $stmt->bindValue(":renter_email", $renterEmail, PDO::PARAM_STR);
    $stmt->bindValue(":renter_phone", $renterPhone, PDO::PARAM_STR);
    $stmt->bindValue(":renter_address", $renterAddress, PDO::PARAM_STR);
    $stmt->bindValue(":start_date", $startDate->format("Y-m-d"));
    $stmt->bindValue(":return_date", $returnDate->format("Y-m-d"));
    $stmt->bindValue(":total_price", $totalPrice);
    $stmt->execute();

    $rentalId = (int) $conn->lastInsertId();
    $stmt = $conn->prepare(
        "INSERT INTO rental_details
            (rental_id, costume_id, quantity, price, subtotal)
         VALUES (:rental_id, :costume_id, 1, :price, :subtotal)"
    );
    $stmt->execute([
        ":rental_id" => $rentalId,
        ":costume_id" => $costumeId,
        ":price" => $pricePerDay,
        ":subtotal" => $totalPrice,
    ]);

    $conn->commit();
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Costume rental failed: " . $e->getMessage());
    $redirectToRentalForm("error=server");
}

header(
    "Location: ../pages/costume_detail.php?name="
        . rawurlencode($registeredName)
        . "&rented=success"
);
exit;
