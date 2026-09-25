<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

/*
 * Kiểm tra ProductID nhận từ URL.
 */
$productID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productID <= 0) {
    header('Location: /products.php');
    exit;
}

/*
 * Lấy thông tin sản phẩm.
 *
 * products.CategoryID tham chiếu categories.CategoryID.
 * products.SupplierID tham chiếu suppliers.SupplierID.
 *
 * Chỉ cho phép khách hàng xem sản phẩm đang hoạt động.
 */
$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Description,
        p.Unit,
        p.Price,
        p.StockQuantity,
        c.CategoryName,
        s.SupplierName

    FROM
        products p,
        categories c,
        suppliers s

    WHERE
        p.CategoryID = c.CategoryID
        AND p.SupplierID = s.SupplierID
        AND p.ProductID = ?
        AND p.IsActive = 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $productID);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    $stmt->close();

    header('Location: /products.php');
    exit;
}

$cartMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_to_cart'])) {

    $quantity = isset($_POST['quantity'])
        ? (int) $_POST['quantity']
        : 1;

    if ($quantity < 1) {
        $quantity = 1;
    }

    $stockQuantity = (int) $product['StockQuantity'];

    if ($stockQuantity <= 0) {

        $cartMessage = 'Sản phẩm hiện đã hết hàng.';

    } else {

        $currentQuantity =
            $_SESSION['cart'][$productID] ?? 0;

        $newQuantity =
            $currentQuantity + $quantity;

        if ($newQuantity > $stockQuantity) {
            $newQuantity = $stockQuantity;
        }

        $_SESSION['cart'][$productID] =
            $newQuantity;

        $cartMessage =
            'Đã thêm sản phẩm vào giỏ hàng.';
    }
}

/*
 * Lấy toàn bộ hình ảnh của sản phẩm.
 */
$sqlImages = "
    SELECT
        ProductImageID,
        ImageFile,
        AltText,
        IsPrimary,
        SortOrder
    FROM product_images
    WHERE ProductID = ?
    ORDER BY
        IsPrimary DESC,
        SortOrder ASC,
        ProductImageID ASC
";

$stmtImages = $conn->prepare($sqlImages);
$stmtImages->bind_param('i', $productID);
$stmtImages->execute();

$productImages = $stmtImages->get_result();

$pageTitle = $product['ProductName'];

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-5">

    <div class="mb-4">
        <a
            href="/products.php"
            class="text-decoration-none"
        >
            &larr; Quay lại danh sách sản phẩm
        </a>
    </div>

    <div class="row g-5">

        <div class="col-lg-6">

            <?php if ($productImages->num_rows > 0): ?>

                <div class="row g-3">

                    <?php while (
                        $image = $productImages->fetch_assoc()
                    ): ?>

                        <div class="col-6">

                            <img
                                src="/uploads/products/<?=
                                    htmlspecialchars(
                                        $image['ImageFile']
                                    )
                                ?>"
                                alt="<?=
                                    htmlspecialchars(
                                        $image['AltText']
                                        ?: $product['ProductName']
                                    )
                                ?>"
                                class="img-fluid rounded border w-100"
                                style="
                                    height: 260px;
                                    object-fit: cover;
                                "
                            >

                            <?php if (
                                (int) $image['IsPrimary'] === 1
                            ): ?>

                                <span
                                    class="badge bg-primary mt-2"
                                >
                                    Ảnh chính
                                </span>

                            <?php endif; ?>

                        </div>

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <div
                    class="bg-light border rounded
                           d-flex align-items-center
                           justify-content-center
                           text-muted"
                    style="height: 400px;"
                >
                    Chưa có hình ảnh
                </div>

            <?php endif; ?>

        </div>

        <div class="col-lg-6">

            <p class="text-muted mb-2">
                <?=
                    htmlspecialchars(
                        $product['CategoryName']
                    )
                ?>
            </p>

            <h1 class="mb-3">
                <?=
                    htmlspecialchars(
                        $product['ProductName']
                    )
                ?>
            </h1>

            <p class="text-muted">
                Mã sản phẩm:
                <strong>
                    <?=
                        htmlspecialchars(
                            $product['ProductCode']
                        )
                    ?>
                </strong>
            </p>

            <p class="fs-3 fw-bold">
                <?=
                    number_format(
                        (float) $product['Price'],
                        0,
                        ',',
                        '.'
                    )
                ?> đ
            </p>

            <hr>

            <dl class="row">

                <dt class="col-sm-4">
                    Đơn vị tính
                </dt>

                <dd class="col-sm-8">
                    <?=
                        htmlspecialchars(
                            $product['Unit'] ?: 'Chưa cập nhật'
                        )
                    ?>
                </dd>

                <dt class="col-sm-4">
                    Nhà cung cấp
                </dt>

                <dd class="col-sm-8">
                    <?=
                        htmlspecialchars(
                            $product['SupplierName']
                        )
                    ?>
                </dd>

                <dt class="col-sm-4">
                    Tồn kho
                </dt>

                <dd class="col-sm-8">
                    <?=
                        (int) $product['StockQuantity']
                    ?>
                </dd>

            </dl>
            <?php if ($cartMessage !== ''): ?>

            <div class="alert alert-info">
                <?= htmlspecialchars($cartMessage) ?>
            </div>

        <?php endif; ?>

        <?php if ((int) $product['StockQuantity'] > 0): ?>

            <form method="post" class="mb-4">

                <div class="row g-3 align-items-end">

                    <div class="col-auto">

                        <label
                            for="quantity"
                            class="form-label"
                        >
                            Số lượng
                        </label>

                        <input
                            type="number"
                            name="quantity"
                            id="quantity"
                            class="form-control"
                            value="1"
                            min="1"
                            max="<?= (int) $product['StockQuantity'] ?>"
                            style="width: 100px;"
                        >

                    </div>

                    <div class="col-auto">

                        <button
                            type="submit"
                            name="add_to_cart"
                            class="btn btn-primary"
                        >
                            Thêm vào giỏ hàng
                        </button>

                    </div>

                </div>

            </form>

        <?php else: ?>

            <div class="alert alert-warning">
                Sản phẩm hiện đã hết hàng.
            </div>

        <?php endif; ?>
            <hr>

            <h5>Mô tả sản phẩm</h5>

            <?php if (!empty($product['Description'])): ?>

                <p>
                    <?=
                        nl2br(
                            htmlspecialchars(
                                $product['Description']
                            )
                        )
                    ?>
                </p>

            <?php else: ?>

                <p class="text-muted">
                    Sản phẩm chưa có mô tả.
                </p>

            <?php endif; ?>

        </div>

    </div>

</main>

<?php

$stmtImages->close();
$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';
