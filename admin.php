<?php
require_once "includes/admin_access.php";
if (!rentalAdminClientAllowed()) {
    http_response_code(403);
    exit("Admin report hanya dapat dibuka dari jaringan lokal.");
}

session_start();
require_once "config/database.php";

if (empty($_SESSION["admin_csrf_token"])) {
    $_SESSION["admin_csrf_token"] = bin2hex(random_bytes(32));
}

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
};

$tabInput = $_GET["tab"] ?? "report";
$activeTab = is_string($tabInput) && in_array($tabInput, ["report", "costumes"], true)
    ? $tabInput
    : "report";
$searchInput = $_GET["q"] ?? "";
$search = is_string($searchInput) ? trim($searchInput) : "";
$searchPattern = "%" . $search . "%";
$loadError = false;
$costumeLoadError = false;
$editError = false;
$summary = [
    "total_rentals" => 0,
    "pending_count" => 0,
    "total_value" => 0,
];
$rentals = [];
$itemsByRental = [];
$editRental = null;
$editItems = [];
$costumes = [];
$editCostume = null;

$noticeMessages = [
    "updated" => "Transaksi berhasil diperbarui.",
    "deleted" => "Transaksi beserta detail dan pembayarannya berhasil dihapus.",
    "costume_created" => "Kostum berhasil ditambahkan.",
    "costume_updated" => "Detail kostum berhasil diperbarui.",
];
$errorMessages = [
    "invalid" => "Data perubahan tidak valid. Periksa kembali isian Anda.",
    "not_found" => "Transaksi rental tidak ditemukan.",
    "costume_not_found" => "Kostum tidak ditemukan.",
    "stock" => "Stok kostum tidak mencukupi untuk perubahan jumlah tersebut.",
    "duplicate" => "Nama kostum dan ukuran tersebut sudah terdaftar.",
    "image_required" => "Unggah gambar JPG untuk kostum baru yang belum memiliki preview.",
    "image_invalid" => "Gambar harus berupa JPG/JPEG yang valid dan berukuran maksimal 5 MB.",
    "image_exists" => "Preview gambar untuk nama kostum ini sudah ada; file tidak ditimpa.",
    "failed" => "Operasi gagal diproses. Silakan coba kembali.",
];
$noticeKey = $_GET["notice"] ?? "";
$errorKey = $_GET["error"] ?? "";
$noticeMessage = is_string($noticeKey) ? ($noticeMessages[$noticeKey] ?? "") : "";
$errorMessage = is_string($errorKey) ? ($errorMessages[$errorKey] ?? "") : "";

$editInput = $activeTab === "report" ? ($_GET["edit"] ?? "") : "";
$editId = is_string($editInput) ? filter_var($editInput, FILTER_VALIDATE_INT) : false;
if ($editInput !== "" && ($editId === false || $editId < 1)) {
    $editError = true;
}

$editCostumeInput = $activeTab === "costumes" ? ($_GET["edit_costume"] ?? "") : "";
$editCostumeId = is_string($editCostumeInput)
    ? filter_var($editCostumeInput, FILTER_VALIDATE_INT)
    : false;
if ($editCostumeInput !== "" && ($editCostumeId === false || $editCostumeId < 1)) {
    $editError = true;
}

if ($activeTab === "report") {
try {
    $summaryStmt = $conn->query(
        "SELECT
            COUNT(*) AS total_rentals,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(total_price), 0) AS total_value
         FROM rentals"
    );
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    $rentalSql =
        "SELECT
            r.rental_id,
            r.user_id,
            COALESCE(NULLIF(cp.full_name, ''), NULLIF(r.renter_name, ''), u.username, 'Tidak diketahui') AS customer_name,
            COALESCE(NULLIF(u.username, ''), 'Perental tamu') AS username,
            COALESCE(NULLIF(u.email, ''), NULLIF(r.renter_email, ''), '—') AS customer_email,
            COALESCE(NULLIF(cp.phone, ''), NULLIF(r.renter_phone, ''), '—') AS customer_phone,
            COALESCE(NULLIF(cp.address, ''), NULLIF(r.renter_address, ''), '—') AS customer_address,
            r.start_date,
            r.return_date,
            r.total_price,
            r.status,
            r.created_at,
            p.payment_method,
            p.payment_status
         FROM rentals r
         LEFT JOIN users u ON u.user_id = r.user_id
         LEFT JOIN customer_profiles cp ON cp.user_id = r.user_id
         LEFT JOIN payments p ON p.rental_id = r.rental_id";

    $rentalParams = [];
    if ($search !== "") {
        $rentalSql .=
            " WHERE (
                cp.full_name LIKE :profile_name
                OR r.renter_name LIKE :renter_name
                OR u.username LIKE :username
                OR u.email LIKE :user_email
                OR r.renter_email LIKE :renter_email
                OR cp.phone LIKE :profile_phone
                OR r.renter_phone LIKE :renter_phone
                OR EXISTS (
                    SELECT 1
                    FROM rental_details rd_search
                    INNER JOIN costumes c_search ON c_search.costume_id = rd_search.costume_id
                    WHERE rd_search.rental_id = r.rental_id
                        AND c_search.costume_name LIKE :costume_name
                )
            )";

        $rentalParams = [
            ":profile_name" => $searchPattern,
            ":renter_name" => $searchPattern,
            ":username" => $searchPattern,
            ":user_email" => $searchPattern,
            ":renter_email" => $searchPattern,
            ":profile_phone" => $searchPattern,
            ":renter_phone" => $searchPattern,
            ":costume_name" => $searchPattern,
        ];
    }

    $rentalSql .= " ORDER BY r.created_at DESC, r.rental_id DESC";
    $rentalsStmt = $conn->prepare($rentalSql);
    $rentalsStmt->execute($rentalParams);
    $rentals = $rentalsStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rentals) {
        $rentalPlaceholders = [];
        $rentalIdParams = [];
        foreach ($rentals as $index => $rental) {
            $placeholder = ":rental_" . $index;
            $rentalPlaceholders[] = $placeholder;
            $rentalIdParams[$placeholder] = (int) $rental["rental_id"];
        }

        $itemsSql =
            "SELECT
                rd.rental_id,
                rd.detail_id,
                c.costume_name,
                c.size,
                rd.quantity,
                rd.subtotal
             FROM rental_details rd
             LEFT JOIN costumes c ON c.costume_id = rd.costume_id
             WHERE rd.rental_id IN (" . implode(", ", $rentalPlaceholders) . ")
             ORDER BY rd.rental_id DESC, rd.detail_id";
        $itemsStmt = $conn->prepare($itemsSql);
        $itemsStmt->execute($rentalIdParams);

        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itemsByRental[(int) $item["rental_id"]][] = $item;
        }
    }

    if ($editInput !== "" && !$editError) {
        $editStmt = $conn->prepare(
            "SELECT
                r.rental_id,
                r.user_id,
                r.renter_name,
                r.renter_email,
                r.renter_phone,
                r.renter_address,
                r.start_date,
                r.return_date,
                r.total_price,
                r.status,
                u.username,
                u.email AS user_email,
                cp.full_name AS profile_name,
                cp.phone AS profile_phone,
                cp.address AS profile_address,
                p.payment_method,
                p.payment_status
             FROM rentals r
             LEFT JOIN users u ON u.user_id = r.user_id
             LEFT JOIN customer_profiles cp ON cp.user_id = r.user_id
             LEFT JOIN payments p ON p.rental_id = r.rental_id
             WHERE r.rental_id = :rental_id"
        );
        $editStmt->execute([":rental_id" => $editId]);
        $editRental = $editStmt->fetch(PDO::FETCH_ASSOC);

        if ($editRental) {
            $editItemsStmt = $conn->prepare(
                "SELECT
                    rd.detail_id,
                    rd.costume_id,
                    rd.quantity,
                    rd.price,
                    c.costume_name,
                    c.size
                 FROM rental_details rd
                 LEFT JOIN costumes c ON c.costume_id = rd.costume_id
                 WHERE rd.rental_id = :rental_id
                 ORDER BY rd.detail_id"
            );
            $editItemsStmt->execute([":rental_id" => $editId]);
            $editItems = $editItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $editError = true;
        }
    }
} catch (PDOException $e) {
    error_log("Admin rental report failed: " . $e->getMessage());
    $loadError = true;
}
} else {
    try {
        $costumesStmt = $conn->query(
            "SELECT costume_id, costume_name, category, description, size, price_per_day, stock
             FROM costumes
             ORDER BY costume_name, size, costume_id"
        );
        $costumes = $costumesStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($editCostumeInput !== "" && !$editError) {
            $editCostumeStmt = $conn->prepare(
                "SELECT costume_id, costume_name, category, description, size, price_per_day, stock
                 FROM costumes
                 WHERE costume_id = :costume_id"
            );
            $editCostumeStmt->execute([":costume_id" => $editCostumeId]);
            $editCostume = $editCostumeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$editCostume) {
                $editError = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Admin costume report failed: " . $e->getMessage());
        $costumeLoadError = true;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $activeTab === "costumes" ? "Kelola Kostum" : "Laporan Rental" ?> | Penny Cosrent</title>
    <link rel="stylesheet" href="assets/css/homepage.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body class="home-page">
    <header class="admin-header">
        <a class="admin-brand" href="index.php">
            <img src="assets/images/logo.png" alt="Logo Penny Cosrent">
            <span>Penny Cosrent</span>
        </a>
        <a class="admin-home-link" href="index.php">Kembali ke beranda</a>
    </header>

    <main class="admin-content">
        <section class="admin-heading">
            <p class="eyebrow">PROTOTYPE ADMIN · JARINGAN LOKAL</p>
            <h1><?= $activeTab === "costumes" ? "Kelola Kostum" : "Laporan Data Rental" ?></h1>
            <p>
                <?= $activeTab === "costumes"
                    ? "Tambah kostum baru atau perbarui detail dan stok katalog."
                    : "Data transaksi, customer, dan pembayaran." ?>
            </p>
        </section>

        <nav class="admin-tabs" aria-label="Menu admin">
            <a class="<?= $activeTab === "report" ? "is-active" : "" ?>" href="admin.php?tab=report">Report Rental</a>
            <a class="<?= $activeTab === "costumes" ? "is-active" : "" ?>" href="admin.php?tab=costumes">Kelola Kostum</a>
        </nav>

        <p class="admin-prototype-warning">
            CRUD tanpa password dapat diakses melalui localhost atau jaringan lokal privat. Jangan ekspos server ini ke internet.
        </p>

        <?php if ($noticeMessage !== ""): ?>
            <p class="admin-notice"><?= $escape($noticeMessage) ?></p>
        <?php endif; ?>
        <?php if ($errorMessage !== "" || $editError): ?>
            <p class="admin-error">
                <?= $escape(
                    $errorMessage !== ""
                        ? $errorMessage
                        : ($activeTab === "costumes" ? "Kostum tidak ditemukan." : "Transaksi rental tidak ditemukan.")
                ) ?>
            </p>
        <?php endif; ?>

        <?php if ($activeTab === "report"): ?>
        <?php if ($loadError): ?>
            <p class="admin-error">
                Laporan tidak dapat dimuat. Pastikan migrasi kolom data rental sudah dijalankan.
            </p>
        <?php else: ?>
            <section class="report-summary" aria-label="Ringkasan rental">
                <article class="summary-card">
                    <span>Total pesanan</span>
                    <strong><?= (int) $summary["total_rentals"] ?></strong>
                </article>
                <article class="summary-card">
                    <span>Menunggu konfirmasi</span>
                    <strong><?= (int) $summary["pending_count"] ?></strong>
                </article>
                <article class="summary-card">
                    <span>Total nilai pesanan</span>
                    <strong>Rp <?= number_format((float) $summary["total_value"], 0, ",", ".") ?></strong>
                </article>
            </section>

            <?php if ($editRental): ?>
                <section class="report-table-section edit-rental-section" id="edit-rental">
                    <div class="report-title">
                        <h2>Edit Rental #<?= (int) $editRental["rental_id"] ?></h2>
                        <a class="admin-home-link" href="admin.php">Batal</a>
                    </div>

                    <form class="rental-edit-form" action="process/admin_rental_process.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION["admin_csrf_token"]) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="rental_id" value="<?= (int) $editRental["rental_id"] ?>">

                        <div class="edit-customer-summary">
                            <strong>
                                <?= $escape(
                                    $editRental["profile_name"]
                                        ?: ($editRental["renter_name"] ?: ($editRental["username"] ?: "Perental tamu"))
                                ) ?>
                            </strong>
                            <span><?= $escape($editRental["user_email"] ?: $editRental["renter_email"] ?: "Email tidak tersedia") ?></span>
                        </div>

                        <div class="edit-form-grid">
                            <div class="edit-form-field">
                                <label for="start_date">Tanggal sewa</label>
                                <input
                                    id="start_date"
                                    type="date"
                                    name="start_date"
                                    value="<?= $escape($editRental["start_date"]) ?>"
                                    required
                                >
                            </div>
                            <div class="edit-form-field">
                                <label for="return_date">Tanggal pengembalian</label>
                                <input
                                    id="return_date"
                                    type="date"
                                    name="return_date"
                                    value="<?= $escape($editRental["return_date"]) ?>"
                                    required
                                >
                            </div>
                            <div class="edit-form-field">
                                <label for="rental_status">Status rental</label>
                                <select id="rental_status" name="rental_status" required>
                                    <?php foreach (["pending", "confirmed", "ongoing", "completed", "cancelled"] as $status): ?>
                                        <option
                                            value="<?= $escape($status) ?>"
                                            <?= $editRental["status"] === $status ? "selected" : "" ?>
                                        ><?= $escape(ucfirst($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h3>Item rental dan jumlah</h3>
                        <?php if ($editItems): ?>
                            <?php foreach ($editItems as $item): ?>
                                <div class="edit-item-row">
                                    <span>
                                        <?= $escape($item["costume_name"] ?? "Kostum tidak tersedia") ?>
                                        <?= $item["size"] !== null ? "(" . $escape($item["size"]) . ")" : "" ?>
                                    </span>
                                    <label for="quantity_<?= (int) $item["detail_id"] ?>">Jumlah</label>
                                    <input
                                        id="quantity_<?= (int) $item["detail_id"] ?>"
                                        type="number"
                                        name="quantities[<?= (int) $item["detail_id"] ?>]"
                                        min="1"
                                        max="100"
                                        value="<?= (int) $item["quantity"] ?>"
                                        required
                                    >
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="admin-error">Detail item rental tidak tersedia, transaksi ini tidak dapat diedit.</p>
                        <?php endif; ?>

                        <h3>Pembayaran</h3>
                        <div class="edit-form-grid">
                            <div class="edit-form-field">
                                <label for="payment_method">Metode pembayaran</label>
                                <input
                                    id="payment_method"
                                    type="text"
                                    name="payment_method"
                                    maxlength="50"
                                    value="<?= $escape($editRental["payment_method"] ?? "") ?>"
                                    placeholder="Contoh: Transfer bank"
                                    required
                                >
                            </div>
                            <div class="edit-form-field">
                                <label for="payment_status">Status pembayaran</label>
                                <select id="payment_status" name="payment_status" required>
                                    <?php foreach (["unpaid", "paid", "failed"] as $paymentStatus): ?>
                                        <option
                                            value="<?= $escape($paymentStatus) ?>"
                                            <?= ($editRental["payment_status"] ?? "unpaid") === $paymentStatus ? "selected" : "" ?>
                                        ><?= $escape(ucfirst($paymentStatus)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button class="admin-action-button" type="submit" <?= !$editItems ? "disabled" : "" ?>>
                            Simpan Perubahan
                        </button>
                    </form>
                </section>
            <?php endif; ?>

            <section class="report-table-section" aria-labelledby="rentals-heading">
                <div class="report-title">
                    <h2 id="rentals-heading">Data Perental</h2>
                    <span><?= count($rentals) ?> transaksi<?= $search !== "" ? " ditemukan" : "" ?></span>
                </div>

                <form class="report-search" action="admin.php" method="GET">
                    <label for="report-search-input">Cari nama, email, telepon, username, atau kostum</label>
                    <div>
                        <input
                            id="report-search-input"
                            type="search"
                            name="q"
                            value="<?= $escape($search) ?>"
                            placeholder="Masukkan kata pencarian"
                        >
                        <button class="admin-action-button" type="submit">Cari</button>
                        <?php if ($search !== ""): ?>
                            <a class="admin-cancel-link" href="admin.php">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($rentals): ?>
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>ID / Status</th>
                                    <th>Customer</th>
                                    <th>Item rental</th>
                                    <th>Tanggal</th>
                                    <th>Total</th>
                                    <th>Pembayaran</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rentals as $rental): ?>
                                    <?php $rentalId = (int) $rental["rental_id"]; ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= $rentalId ?></strong>
                                            <span class="rental-status status-<?= $escape($rental["status"]) ?>">
                                                <?= $escape(ucfirst((string) $rental["status"])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= $escape($rental["customer_name"]) ?></strong>
                                            <span>Username: <?= $escape($rental["username"]) ?></span>
                                            <span><?= $escape($rental["customer_email"]) ?></span>
                                            <span><?= $escape($rental["customer_phone"]) ?></span>
                                            <span><?= nl2br($escape($rental["customer_address"])) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($itemsByRental[$rentalId])): ?>
                                                <ul class="rental-items">
                                                    <?php foreach ($itemsByRental[$rentalId] as $item): ?>
                                                        <li>
                                                            <?= $escape($item["costume_name"] ?? "Kostum dihapus") ?>
                                                            <?php if (!empty($item["size"])): ?>
                                                                (<?= $escape($item["size"]) ?>)
                                                            <?php endif; ?>
                                                            × <?= (int) $item["quantity"] ?>
                                                            <span>
                                                                Rp <?= number_format((float) $item["subtotal"], 0, ",", ".") ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span>Detail tidak tersedia</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $escape(date("d-m-Y", strtotime($rental["start_date"]))) ?>
                                            <span>sampai</span>
                                            <?= $escape(date("d-m-Y", strtotime($rental["return_date"]))) ?>
                                        </td>
                                        <td>Rp <?= number_format((float) $rental["total_price"], 0, ",", ".") ?></td>
                                        <td>
                                            <span><?= $escape($rental["payment_method"] ?? "Belum diatur") ?></span>
                                            <span><?= $escape(ucfirst((string) ($rental["payment_status"] ?? "unpaid"))) ?></span>
                                        </td>
                                        <td>
                                            <div class="report-actions">
                                                <a class="admin-edit-link" href="admin.php?edit=<?= $rentalId ?>#edit-rental">Edit</a>
                                                <form
                                                    action="process/admin_rental_process.php"
                                                    method="POST"
                                                    onsubmit="return confirm('Hapus transaksi #<?= $rentalId ?> beserta detail dan pembayarannya?');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION["admin_csrf_token"]) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="rental_id" value="<?= $rentalId ?>">
                                                    <button class="admin-delete-button" type="submit">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="report-empty">
                        <?= $search !== "" ? "Tidak ada transaksi yang cocok dengan pencarian." : "Belum ada data rental." ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php else: ?>
            <?php if ($costumeLoadError): ?>
                <p class="admin-error">Data kostum tidak dapat dimuat dari database.</p>
            <?php else: ?>
                <section class="report-table-section costume-form-section" id="costume-form">
                    <div class="report-title">
                        <h2><?= $editCostume ? "Edit Kostum" : "Tambah Kostum" ?></h2>
                        <?php if ($editCostume): ?>
                            <a class="admin-cancel-link" href="admin.php?tab=costumes">Batal</a>
                        <?php endif; ?>
                    </div>

                    <?php
                    $formCostumeName = $editCostume["costume_name"] ?? "";
                    $formCostumeImage = "assets/images/costume/"
                        . rawurlencode((string) $formCostumeName) . "/1.jpg";
                    $formCostumeImageFile = __DIR__ . DIRECTORY_SEPARATOR . "assets"
                        . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "costume"
                        . DIRECTORY_SEPARATOR . (string) $formCostumeName
                        . DIRECTORY_SEPARATOR . "1.jpg";
                    ?>

                    <form
                        class="rental-edit-form costume-edit-form"
                        action="process/admin_costume_process.php"
                        method="POST"
                        enctype="multipart/form-data"
                    >
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION["admin_csrf_token"]) ?>">
                        <input type="hidden" name="action" value="<?= $editCostume ? "update" : "create" ?>">
                        <?php if ($editCostume): ?>
                            <input type="hidden" name="costume_id" value="<?= (int) $editCostume["costume_id"] ?>">
                        <?php endif; ?>

                        <div class="edit-form-grid">
                            <div class="edit-form-field">
                                <label for="costume_name">Nama kostum</label>
                                <input
                                    id="costume_name"
                                    type="text"
                                    name="costume_name"
                                    maxlength="100"
                                    value="<?= $escape($formCostumeName) ?>"
                                    required
                                >
                            </div>
                            <div class="edit-form-field">
                                <label for="category">Kategori</label>
                                <input
                                    id="category"
                                    type="text"
                                    name="category"
                                    maxlength="50"
                                    value="<?= $escape($editCostume["category"] ?? "") ?>"
                                >
                            </div>
                            <div class="edit-form-field">
                                <label for="size">Ukuran / varian</label>
                                <input
                                    id="size"
                                    type="text"
                                    name="size"
                                    maxlength="20"
                                    value="<?= $escape($editCostume["size"] ?? "") ?>"
                                    required
                                >
                            </div>
                            <div class="edit-form-field">
                                <label for="price_per_day">Harga per hari (Rp)</label>
                                <input
                                    id="price_per_day"
                                    type="number"
                                    name="price_per_day"
                                    min="0.01"
                                    max="9999999999.99"
                                    step="0.01"
                                    value="<?= $escape($editCostume["price_per_day"] ?? "") ?>"
                                    required
                                >
                            </div>
                            <div class="edit-form-field">
                                <label for="stock">Stok</label>
                                <input
                                    id="stock"
                                    type="number"
                                    name="stock"
                                    min="0"
                                    max="1000000"
                                    step="1"
                                    value="<?= $escape($editCostume["stock"] ?? "0") ?>"
                                    required
                                >
                            </div>
                            <div class="edit-form-field">
                                <label for="costume_image">Preview gambar (JPG/JPEG, maks. 5 MB)</label>
                                <input
                                    id="costume_image"
                                    type="file"
                                    name="costume_image"
                                    accept=".jpg,.jpeg,image/jpeg"
                                >
                            </div>
                        </div>

                        <?php if ($editCostume && is_file($formCostumeImageFile)): ?>
                            <img class="costume-admin-preview" src="<?= $escape($formCostumeImage) ?>" alt="Preview <?= $escape($formCostumeName) ?>">
                        <?php endif; ?>

                        <div class="edit-form-field">
                            <label for="description">Deskripsi</label>
                            <textarea
                                id="description"
                                name="description"
                                maxlength="5000"
                                rows="4"
                            ><?= $escape($editCostume["description"] ?? "") ?></textarea>
                        </div>

                        <p class="costume-form-help">
                            Kostum dengan nama yang sama dikelompokkan di katalog. Tambahkan ukuran berbeda sebagai varian.
                            Nama, kategori, dan deskripsi berlaku untuk semua varian; ukuran, harga, dan stok hanya untuk varian ini.
                            Unggah JPG jika nama belum memiliki preview; gambar yang sudah ada tidak ditimpa.
                        </p>
                        <button class="admin-action-button" type="submit">
                            <?= $editCostume ? "Simpan Perubahan" : "Tambah Kostum" ?>
                        </button>
                    </form>
                </section>

                <section class="report-table-section" aria-labelledby="costumes-heading">
                    <div class="report-title">
                        <h2 id="costumes-heading">Daftar Kostum</h2>
                        <span><?= count($costumes) ?> varian</span>
                    </div>
                    <?php if ($costumes): ?>
                        <div class="report-table-wrap">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Kostum</th>
                                        <th>Ukuran</th>
                                        <th>Harga / hari</th>
                                        <th>Stok</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($costumes as $costume): ?>
                                        <tr>
                                            <td>
                                                <strong><?= $escape($costume["costume_name"]) ?></strong>
                                                <span><?= $escape($costume["category"] ?? "Tanpa kategori") ?></span>
                                                <?php if (!empty($costume["description"])): ?>
                                                    <span><?= $escape($costume["description"]) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $escape($costume["size"] ?? "—") ?></td>
                                            <td>Rp <?= number_format((float) $costume["price_per_day"], 0, ",", ".") ?></td>
                                            <td><?= (int) $costume["stock"] ?></td>
                                            <td>
                                                <a
                                                    class="admin-edit-link"
                                                    href="admin.php?tab=costumes&amp;edit_costume=<?= (int) $costume["costume_id"] ?>#costume-form"
                                                >Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="report-empty">Belum ada data kostum.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
