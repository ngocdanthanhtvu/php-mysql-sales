<?php

$pageTitle = 'Sửa sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

$productID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productID <= 0) {
    die('Mã sản phẩm không hợp lệ.');
}


/*
 * Đọc dữ liệu hiện tại của sản phẩm
 */
$sql = "
    SELECT
        ProductID,
        ProductCode,
        ProductName,
        Description,
        Unit,
        Price,
        StockQuantity,
        IsActive,
        SupplierID,
        CategoryID
    FROM products
    WHERE ProductID = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $productID);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$stmt->close();

if (!$product) {
    die('Không tìm thấy sản phẩm.');
}


/*
 * Lấy danh sách danh mục
 */
$sqlCategories = "
    SELECT
        CategoryID,
        CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $conn->query($sqlCategories);


/*
 * Lấy danh sách nhà cung cấp
 */
$sqlSuppliers = "
    SELECT
        SupplierID,
        SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);


/*
 * Xử lý khi gửi form
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');

    $price = (float) ($_POST['price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;


    /*
     * Kiểm tra dữ liệu
     */
    if ($productCode === '') {

        $error = 'Mã sản phẩm không được để trống.';

    } elseif ($productName === '') {

        $error = 'Tên sản phẩm không được để trống.';

    } elseif ($price < 0) {

        $error = 'Giá sản phẩm không hợp lệ.';

    } elseif ($stockQuantity < 0) {

        $error = 'Số lượng tồn kho không hợp lệ.';

    } elseif ($categoryID <= 0) {

        $error = 'Vui lòng chọn danh mục.';

    } elseif ($supplierID <= 0) {

        $error = 'Vui lòng chọn nhà cung cấp.';

    } else {

        $sql = "
            UPDATE products
            SET
                ProductCode = ?,
                ProductName = ?,
                Description = ?,
                Unit = ?,
                Price = ?,
                StockQuantity = ?,
                IsActive = ?,
                SupplierID = ?,
                CategoryID = ?
            WHERE ProductID = ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'ssssdiiiii',
            $productCode,
            $productName,
            $description,
            $unit,
            $price,
            $stockQuantity,
            $isActive,
            $supplierID,
            $categoryID,
            $productID
        );

        if ($stmt->execute()) {

            header('Location: /products/');
            exit;

        } else {

            $error = 'Không thể cập nhật sản phẩm.';
        }

        $stmt->close();
    }
}


require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Sửa sản phẩm</h2>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="post">

        <div class="row">

            <div class="col-md-4 mb-3">

                <label for="productCode" class="form-label">
                    Mã sản phẩm
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="productCode"
                    name="product_code"
                    value="<?= htmlspecialchars(
                        $_POST['product_code']
                        ?? $product['ProductCode']
                    ) ?>"
                    required
                >

            </div>


            <div class="col-md-8 mb-3">

                <label for="productName" class="form-label">
                    Tên sản phẩm
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="productName"
                    name="product_name"
                    value="<?= htmlspecialchars(
                        $_POST['product_name']
                        ?? $product['ProductName']
                    ) ?>"
                    required
                >

            </div>

        </div>


        <div class="mb-3">

            <label for="description" class="form-label">
                Mô tả
            </label>

            <textarea
                class="form-control"
                id="description"
                name="description"
                rows="3"
            ><?= htmlspecialchars(
                $_POST['description']
                ?? $product['Description']
                ?? ''
            ) ?></textarea>

        </div>


        <div class="row">

            <div class="col-md-4 mb-3">

                <label for="unit" class="form-label">
                    Đơn vị tính
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="unit"
                    name="unit"
                    value="<?= htmlspecialchars(
                        $_POST['unit']
                        ?? $product['Unit']
                        ?? ''
                    ) ?>"
                >

            </div>


            <div class="col-md-4 mb-3">

                <label for="price" class="form-label">
                    Giá
                </label>

                <input
                    type="number"
                    class="form-control"
                    id="price"
                    name="price"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars(
                        $_POST['price']
                        ?? $product['Price']
                    ) ?>"
                    required
                >

            </div>


            <div class="col-md-4 mb-3">

                <label for="stockQuantity" class="form-label">
                    Tồn kho
                </label>

                <input
                    type="number"
                    class="form-control"
                    id="stockQuantity"
                    name="stock_quantity"
                    min="0"
                    value="<?= htmlspecialchars(
                        $_POST['stock_quantity']
                        ?? $product['StockQuantity']
                    ) ?>"
                    required
                >

            </div>

        </div>


        <div class="row">

            <div class="col-md-6 mb-3">

                <label for="categoryID" class="form-label">
                    Danh mục
                </label>

                <select
                    class="form-select"
                    id="categoryID"
                    name="category_id"
                    required
                >

                    <?php while ($category = $categories->fetch_assoc()): ?>

                        <?php

                        $selectedCategoryID =
                            $_POST['category_id']
                            ?? $product['CategoryID'];

                        ?>

                        <option
                            value="<?= $category['CategoryID'] ?>"
                            <?= (
                                $selectedCategoryID
                                == $category['CategoryID']
                            ) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $category['CategoryName']
                            ) ?>
                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <div class="col-md-6 mb-3">

                <label for="supplierID" class="form-label">
                    Nhà cung cấp
                </label>

                <select
                    class="form-select"
                    id="supplierID"
                    name="supplier_id"
                    required
                >

                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>

                        <?php

                        $selectedSupplierID =
                            $_POST['supplier_id']
                            ?? $product['SupplierID'];

                        ?>

                        <option
                            value="<?= $supplier['SupplierID'] ?>"
                            <?= (
                                $selectedSupplierID
                                == $supplier['SupplierID']
                            ) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $supplier['SupplierName']
                            ) ?>
                        </option>

                    <?php endwhile; ?>

                </select>

            </div>

        </div>


        <div class="form-check mb-3">

            <?php

            $currentIsActive = $_SERVER['REQUEST_METHOD'] === 'POST'
                ? isset($_POST['is_active'])
                : ((int) $product['IsActive'] === 1);

            ?>

            <input
                type="checkbox"
                class="form-check-input"
                id="isActive"
                name="is_active"
                value="1"
                <?= $currentIsActive ? 'checked' : '' ?>
            >

            <label class="form-check-label" for="isActive">
                Đang kinh doanh
            </label>

        </div>


        <button type="submit" class="btn btn-warning">
            Cập nhật
        </button>

        <a href="/products/" class="btn btn-secondary">
            Hủy
        </a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/footer.php';

$conn->close();