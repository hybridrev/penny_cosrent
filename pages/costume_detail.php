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
$costumeFolder = is_string($name) ? ($availableFolders[$name] ?? null) : null;
$notFound = $costumeFolder === null;
$variants = [];

if ($notFound) {
    http_response_code(404);
    $name = "";
} else {
    $stmt = $conn->prepare(
        "SELECT costume_id, costume_name, category, description, size, price_per_day, stock
         FROM costumes
         WHERE costume_name = :costume_name
         ORDER BY size, costume_id"
    );
    $stmt->execute([":costume_name" => $name]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$isLoggedIn = isset($_SESSION["user_id"], $_SESSION["username"]);
$availableVariants = array_values(array_filter(
    $variants,
    static function ($variant) {
        return (int) $variant["stock"] > 0;
    }
));

$errorMessages = [
    "invalid_dates" => "Tanggal rental tidak valid. Tanggal kembali harus setelah tanggal mulai.",
    "unavailable" => "Ukuran kostum tersebut sudah tidak tersedia.",
    "server" => "Pemesanan gagal diproses. Silakan coba kembali.",
];
$error = $_GET["error"] ?? "";
if (!is_string($error)) {
    $error = "";
}
$errorMessage = $errorMessages[$error] ?? "";
$rentalSucceeded = isset($_GET["rented"]) && $_GET["rented"] === "success";

$description = "";
$category = "";
$sizes = [];
$lowestPrice = null;
$totalStock = 0;

foreach ($variants as $variant) {
    if ($description === "" && trim((string) $variant["description"]) !== "") {
        $description = (string) $variant["description"];
    }
    if ($category === "" && trim((string) $variant["category"]) !== "") {
        $category = (string) $variant["category"];
    }

    if ((int) $variant["stock"] > 0) {
        $size = trim((string) $variant["size"]);
        if ($size !== "" && !in_array($size, $sizes, true)) {
            $sizes[] = $size;
        }

        $price = (float) $variant["price_per_day"];
        $lowestPrice = $lowestPrice === null ? $price : min($lowestPrice, $price);
    }

    $totalStock += (int) $variant["stock"];
}

if (!$notFound && $description === "") {
    $description = "Kostum cosplay " . $name . ". Deskripsi produk lengkap belum ditambahkan.";
}

$imageUrl = "../assets/images/costume/" . rawurlencode($name) . "/1.jpg";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $notFound ? "Kostum tidak ditemukan" : htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?> | Penny Cosrent</title>
    <link rel="stylesheet" href="../assets/css/homepage.css">
    <link rel="stylesheet" href="../assets/css/costume-detail.css">
</head>

<body class="home-page">
    <div class="blur-group">
        <header class="site-header">
            <a class="brand" href="../index.php">
                <img class="brand-logo" src="../assets/images/logo.png" alt="Logo Penny Cosrent">
            </a>
            <?php if ($isLoggedIn): ?>
                <a class="logout-link" href="../auth/logout.php">Logout</a>
            <?php else: ?>
                <a class="logout-link" href="../auth/login.php">Login</a>
            <?php endif; ?>
        </header>

        <main class="home-content detail-content">
            <a class="back-to-catalog" href="../index.php">← Kembali ke katalog</a>

            <?php if ($notFound): ?>
                <section class="costume-detail-panel">
                    <h1>Kostum tidak ditemukan</h1>
                    <p>Silakan pilih kostum dari katalog yang tersedia.</p>
                </section>
            <?php else: ?>
                <section class="costume-detail-panel">
                    <div class="detail-image-wrap">
                        <img
                            class="detail-image"
                            src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, "UTF-8") ?>"
                            alt="Kostum <?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
                        >
                    </div>

                    <div class="detail-information">
                        <p class="eyebrow">DETAIL KOSTUM</p>
                        <h1><?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?></h1>
                        <p class="detail-description">
                            <?= nl2br(htmlspecialchars($description, ENT_QUOTES, "UTF-8")) ?>
                        </p>

                        <dl class="costume-specifications">
                            <div>
                                <dt>Kategori</dt>
                                <dd><?= $category !== "" ? htmlspecialchars($category, ENT_QUOTES, "UTF-8") : "Belum diisi" ?></dd>
                            </div>
                            <div>
                                <dt>Ukuran tersedia</dt>
                                <dd><?= $sizes ? htmlspecialchars(implode(", ", $sizes), ENT_QUOTES, "UTF-8") : "Belum tersedia" ?></dd>
                            </div>
                            <div>
                                <dt>Harga</dt>
                                <dd>
                                    <?= $lowestPrice !== null
                                        ? "Mulai Rp " . number_format($lowestPrice, 0, ",", ".") . " / hari"
                                        : "Belum tersedia" ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Stok</dt>
                                <dd><?= $variants ? (string) $totalStock . " tersedia" : "Belum diatur" ?></dd>
                            </div>
                        </dl>

                        <?php if ($rentalSucceeded): ?>
                            <p class="detail-message detail-success">Pemesanan berhasil dibuat.</p>
                        <?php endif; ?>

                        <?php if ($errorMessage !== ""): ?>
                            <p class="detail-message detail-error">
                                <?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!$variants): ?>
                            <p class="detail-message detail-error">
                                Data harga, ukuran, dan stok kostum ini belum dimasukkan ke database.
                            </p>
                            <button class="rent-button" type="button" disabled>Rent Now</button>
                        <?php elseif (!$availableVariants): ?>
                            <p class="detail-message detail-error">Kostum ini sedang tidak tersedia.</p>
                            <button class="rent-button" type="button" disabled>Rent Now</button>
                        <?php else: ?>
                            <a
                                class="rent-button"
                                href="rental_form.php?name=<?= rawurlencode($name) ?>"
                            >Rent Now</a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
