<?php
session_start();

require_once "../config/database.php";

$costumeRoot = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "assets"
    . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "costume";
$costumeFolders = glob($costumeRoot . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR);

if ($costumeFolders === false) {
    throw new RuntimeException("Gagal membaca folder katalog kostum.");
}

$availableFolders = [];
foreach ($costumeFolders as $folder) {
    if (is_file($folder . DIRECTORY_SEPARATOR . "1.jpg")) {
        $availableFolders[basename($folder)] = $folder;
    }
}

$name = $_GET["name"] ?? "";
if (!is_string($name) || !isset($availableFolders[$name])) {
    http_response_code(404);
    $name = "";
    $variants = [];
} else {
    $stmt = $conn->prepare(
        "SELECT costume_id, size, price_per_day, stock
         FROM costumes
         WHERE costume_name = :costume_name
         ORDER BY size, costume_id"
    );
    $stmt->execute([":costume_name" => $name]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$availableVariants = array_values(array_filter(
    $variants,
    static function ($variant) {
        return (int) $variant["stock"] > 0;
    }
));

$errorMessages = [
    "invalid_dates" => "Tanggal rental tidak valid. Tanggal kembali harus setelah tanggal mulai.",
    "invalid_details" => "Periksa kembali nama, email, nomor telepon, dan alamat Anda.",
    "unavailable" => "Kostum atau ukuran yang dipilih sudah tidak tersedia.",
    "server" => "Pemesanan gagal diproses. Silakan coba kembali.",
];
$error = $_GET["error"] ?? "";
if (!is_string($error)) {
    $error = "";
}
$errorMessage = $errorMessages[$error] ?? "";

$imageUrl = "../assets/images/costume/" . rawurlencode($name) . "/1.jpg";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Rental | <?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?> | Penny Cosrent</title>
    <link rel="stylesheet" href="../assets/css/homepage.css">
    <link rel="stylesheet" href="../assets/css/costume-detail.css">
</head>

<body class="home-page">
    <div class="blur-group">
        <header class="site-header">
            <a class="brand" href="../index.php">
                <img class="brand-logo" src="../assets/images/logo.png" alt="Logo Penny Cosrent">
            </a>
            <a class="logout-link" href="../index.php">Beranda</a>
        </header>

        <main class="home-content detail-content">
            <a
                class="back-to-catalog"
                href="<?= $name !== ""
                    ? "costume_detail.php?name=" . rawurlencode($name)
                    : "../index.php" ?>"
            >← Kembali ke detail kostum</a>

            <?php if ($name === ""): ?>
                <section class="costume-detail-panel">
                    <h1>Kostum tidak ditemukan</h1>
                    <p>Silakan pilih kostum dari katalog yang tersedia.</p>
                </section>
            <?php elseif (!$availableVariants): ?>
                <section class="costume-detail-panel">
                    <div class="detail-image-wrap">
                        <img
                            class="detail-image"
                            src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, "UTF-8") ?>"
                            alt="Kostum <?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
                        >
                    </div>
                    <div class="detail-information">
                        <p class="eyebrow">FORM RENTAL</p>
                        <h1><?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?></h1>
                        <p class="detail-message detail-error">
                            Data harga dan stok kostum ini belum tersedia atau sedang habis.
                        </p>
                    </div>
                </section>
            <?php else: ?>
                <section class="costume-detail-panel rental-form-panel">
                    <div class="detail-image-wrap">
                        <img
                            class="detail-image"
                            src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, "UTF-8") ?>"
                            alt="Kostum <?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
                        >
                    </div>

                    <div class="detail-information">
                        <p class="eyebrow">FORM RENTAL</p>
                        <h1><?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?></h1>
                        <p class="detail-description">
                            Isi data diri dan jadwal rental. Login tidak diperlukan.
                        </p>

                        <?php if ($errorMessage !== ""): ?>
                            <p class="detail-message detail-error">
                                <?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?>
                            </p>
                        <?php endif; ?>

                        <form class="rental-form" action="../process/rent_process.php" method="POST">
                            <input
                                type="hidden"
                                name="costume_name"
                                value="<?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
                            >

                            <div class="detail-form-field">
                                <label for="costume_id">Pilih ukuran</label>
                                <select id="costume_id" name="costume_id" required>
                                    <?php foreach ($availableVariants as $variant): ?>
                                        <?php
                                        $variantLabel = trim((string) $variant["size"]);
                                        if ($variantLabel === "") {
                                            $variantLabel = "Ukuran standar";
                                        }
                                        ?>
                                        <option value="<?= (int) $variant["costume_id"] ?>">
                                            <?= htmlspecialchars($variantLabel, ENT_QUOTES, "UTF-8") ?>
                                            — Rp <?= number_format((float) $variant["price_per_day"], 0, ",", ".") ?>/hari
                                            (stok <?= (int) $variant["stock"] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="detail-date-fields">
                                <div class="detail-form-field">
                                    <label for="start_date">Tanggal mulai</label>
                                    <input
                                        id="start_date"
                                        type="date"
                                        name="start_date"
                                        min="<?= date("Y-m-d") ?>"
                                        required
                                    >
                                </div>
                                <div class="detail-form-field">
                                    <label for="return_date">Tanggal kembali</label>
                                    <input
                                        id="return_date"
                                        type="date"
                                        name="return_date"
                                        min="<?= date("Y-m-d", strtotime("+1 day")) ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <fieldset class="renter-details">
                                <legend>Data diri</legend>
                                <div class="detail-form-field">
                                    <label for="renter_name">Nama lengkap</label>
                                    <input
                                        id="renter_name"
                                        type="text"
                                        name="renter_name"
                                        maxlength="100"
                                        autocomplete="name"
                                        required
                                    >
                                </div>
                                <div class="detail-form-field">
                                    <label for="renter_email">Email</label>
                                    <input
                                        id="renter_email"
                                        type="email"
                                        name="renter_email"
                                        maxlength="100"
                                        autocomplete="email"
                                        required
                                    >
                                </div>
                                <div class="detail-form-field">
                                    <label for="renter_phone">Nomor telepon</label>
                                    <input
                                        id="renter_phone"
                                        type="tel"
                                        name="renter_phone"
                                        maxlength="20"
                                        autocomplete="tel"
                                        required
                                    >
                                </div>
                                <div class="detail-form-field">
                                    <label for="renter_address">Alamat</label>
                                    <textarea
                                        id="renter_address"
                                        name="renter_address"
                                        rows="3"
                                        maxlength="1000"
                                        autocomplete="street-address"
                                        required
                                    ></textarea>
                                </div>
                            </fieldset>

                            <button class="rent-button" type="submit">Konfirmasi Rental</button>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const startDateInput = document.getElementById("start_date");
        const returnDateInput = document.getElementById("return_date");

        if (startDateInput && returnDateInput) {
            startDateInput.addEventListener("change", () => {
                if (!startDateInput.value) {
                    return;
                }

                const nextDay = new Date(startDateInput.value + "T00:00:00");
                nextDay.setDate(nextDay.getDate() + 1);
                const minimumReturnDate = [
                    nextDay.getFullYear(),
                    String(nextDay.getMonth() + 1).padStart(2, "0"),
                    String(nextDay.getDate()).padStart(2, "0"),
                ].join("-");

                returnDateInput.min = minimumReturnDate;
                if (returnDateInput.value && returnDateInput.value < minimumReturnDate) {
                    returnDateInput.value = "";
                }
            });
        }
    </script>
</body>
</html>
