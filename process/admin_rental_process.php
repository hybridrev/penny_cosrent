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
$rentalIdInput = $_POST["rental_id"] ?? "";
$rentalId = is_string($rentalIdInput)
    ? filter_var($rentalIdInput, FILTER_VALIDATE_INT)
    : false;

if (
    !is_string($action)
    || !in_array($action, ["update", "delete"], true)
    || $rentalId === false
    || $rentalId < 1
) {
    $redirectAdmin("error=invalid");
}

$redirectAfterRollback = static function (string $query) use ($conn, $redirectAdmin): void {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $redirectAdmin($query);
};

if ($action === "delete") {
    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare(
            "SELECT status FROM rentals WHERE rental_id = :rental_id FOR UPDATE"
        );
        $stmt->execute([":rental_id" => $rentalId]);
        $rental = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rental) {
            $redirectAfterRollback("error=not_found");
        }

        $stmt = $conn->prepare(
            "SELECT costume_id, quantity
             FROM rental_details
             WHERE rental_id = :rental_id
             FOR UPDATE"
        );
        $stmt->execute([":rental_id" => $rentalId]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (in_array($rental["status"], ["pending", "confirmed", "ongoing"], true)) {
            $restoreStock = $conn->prepare(
                "UPDATE costumes SET stock = stock + :quantity
                 WHERE costume_id = :costume_id"
            );
            foreach ($details as $detail) {
                $restoreStock->execute([
                    ":quantity" => (int) $detail["quantity"],
                    ":costume_id" => (int) $detail["costume_id"],
                ]);
            }
        }

        $stmt = $conn->prepare("DELETE FROM payments WHERE rental_id = :rental_id");
        $stmt->execute([":rental_id" => $rentalId]);

        $stmt = $conn->prepare("DELETE FROM rental_details WHERE rental_id = :rental_id");
        $stmt->execute([":rental_id" => $rentalId]);

        $stmt = $conn->prepare("DELETE FROM rentals WHERE rental_id = :rental_id");
        $stmt->execute([":rental_id" => $rentalId]);

        $conn->commit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log("Admin rental deletion failed: " . $e->getMessage());
        $redirectAdmin("error=failed");
    }

    $redirectAdmin("notice=deleted");
}

$startInput = $_POST["start_date"] ?? "";
$returnInput = $_POST["return_date"] ?? "";
$status = $_POST["rental_status"] ?? "";
$paymentMethodInput = $_POST["payment_method"] ?? "";
$paymentStatus = $_POST["payment_status"] ?? "";
$quantitiesInput = $_POST["quantities"] ?? null;

if (
    !is_string($startInput)
    || !is_string($returnInput)
    || !is_string($status)
    || !is_string($paymentMethodInput)
    || !is_string($paymentStatus)
    || !is_array($quantitiesInput)
    || !in_array($status, ["pending", "confirmed", "ongoing", "completed", "cancelled"], true)
    || !in_array($paymentStatus, ["unpaid", "paid", "failed"], true)
) {
    $redirectAdmin("error=invalid");
}

$paymentMethod = trim($paymentMethodInput);
if ($paymentMethod === "" || strlen($paymentMethod) > 50) {
    $redirectAdmin("error=invalid");
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
if ($startDate === null || $returnDate === null || $returnDate <= $startDate) {
    $redirectAdmin("error=invalid");
}
$rentalDays = (int) $startDate->diff($returnDate)->days;

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare(
        "SELECT status FROM rentals WHERE rental_id = :rental_id FOR UPDATE"
    );
    $stmt->execute([":rental_id" => $rentalId]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        $redirectAfterRollback("error=not_found");
    }

    $stmt = $conn->prepare(
        "SELECT detail_id, costume_id, quantity, price
         FROM rental_details
         WHERE rental_id = :rental_id
         FOR UPDATE"
    );
    $stmt->execute([":rental_id" => $rentalId]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$details || count($quantitiesInput) !== count($details)) {
        $redirectAfterRollback("error=invalid");
    }

    $newQuantities = [];
    foreach ($details as $detail) {
        $detailId = (int) $detail["detail_id"];
        $quantityInput = $quantitiesInput[$detailId] ?? null;
        $quantity = is_string($quantityInput)
            ? filter_var($quantityInput, FILTER_VALIDATE_INT)
            : false;

        if ($quantity === false || $quantity < 1 || $quantity > 100) {
            $redirectAfterRollback("error=invalid");
        }

        $newQuantities[$detailId] = $quantity;
    }

    $wasReserved = in_array($rental["status"], ["pending", "confirmed", "ongoing"], true);
    $willBeReserved = in_array($status, ["pending", "confirmed", "ongoing"], true);
    $updateStock = $conn->prepare(
        "UPDATE costumes SET stock = stock + :change
         WHERE costume_id = :costume_id"
    );
    $decreaseStock = $conn->prepare(
        "UPDATE costumes SET stock = stock - :quantity
         WHERE costume_id = :costume_id AND stock >= :required_quantity"
    );
    $updateDetail = $conn->prepare(
        "UPDATE rental_details
         SET quantity = :quantity, subtotal = :subtotal
         WHERE detail_id = :detail_id AND rental_id = :rental_id"
    );

    $totalPrice = 0.0;
    foreach ($details as $detail) {
        $detailId = (int) $detail["detail_id"];
        $costumeId = (int) $detail["costume_id"];
        $oldQuantity = (int) $detail["quantity"];
        $newQuantity = $newQuantities[$detailId];

        if ($wasReserved && !$willBeReserved) {
            $stockChange = $oldQuantity;
        } elseif (!$wasReserved && $willBeReserved) {
            $stockChange = -$newQuantity;
        } elseif ($wasReserved && $willBeReserved) {
            $stockChange = $oldQuantity - $newQuantity;
        } else {
            $stockChange = 0;
        }

        if ($stockChange > 0) {
            $updateStock->execute([
                ":change" => $stockChange,
                ":costume_id" => $costumeId,
            ]);
        } elseif ($stockChange < 0) {
            $decreaseStock->execute([
                ":quantity" => abs($stockChange),
                ":costume_id" => $costumeId,
                ":required_quantity" => abs($stockChange),
            ]);
            if ($decreaseStock->rowCount() !== 1) {
                $redirectAfterRollback("error=stock");
            }
        }

        $subtotal = (float) $detail["price"] * $rentalDays * $newQuantity;
        $totalPrice += $subtotal;
        $updateDetail->execute([
            ":quantity" => $newQuantity,
            ":subtotal" => $subtotal,
            ":detail_id" => $detailId,
            ":rental_id" => $rentalId,
        ]);
    }

    $stmt = $conn->prepare(
        "UPDATE rentals
         SET start_date = :start_date,
             return_date = :return_date,
             total_price = :total_price,
             status = :status
         WHERE rental_id = :rental_id"
    );
    $stmt->execute([
        ":start_date" => $startDate->format("Y-m-d"),
        ":return_date" => $returnDate->format("Y-m-d"),
        ":total_price" => $totalPrice,
        ":status" => $status,
        ":rental_id" => $rentalId,
    ]);

    $stmt = $conn->prepare(
        "SELECT payment_id, paid_at FROM payments WHERE rental_id = :rental_id FOR UPDATE"
    );
    $stmt->execute([":rental_id" => $rentalId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    $paidAt = $paymentStatus === "paid"
        ? (($payment && $payment["paid_at"] !== null) ? $payment["paid_at"] : date("Y-m-d H:i:s"))
        : null;

    if ($payment) {
        $stmt = $conn->prepare(
            "UPDATE payments
             SET payment_method = :payment_method,
                 payment_status = :payment_status,
                 amount = :amount,
                 paid_at = :paid_at
             WHERE rental_id = :rental_id"
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO payments (rental_id, payment_method, amount, payment_status, paid_at)
             VALUES (:rental_id, :payment_method, :amount, :payment_status, :paid_at)"
        );
    }

    $stmt->bindValue(":rental_id", $rentalId, PDO::PARAM_INT);
    $stmt->bindValue(":payment_method", $paymentMethod, PDO::PARAM_STR);
    $stmt->bindValue(":amount", $totalPrice);
    $stmt->bindValue(":payment_status", $paymentStatus, PDO::PARAM_STR);
    $stmt->bindValue(
        ":paid_at",
        $paidAt,
        $paidAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
    $stmt->execute();

    $conn->commit();
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Admin rental update failed: " . $e->getMessage());
    $redirectAdmin("error=failed");
}

$redirectAdmin("notice=updated");
