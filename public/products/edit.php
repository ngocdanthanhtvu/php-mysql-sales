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
 * Lấy danh sách hình ảnh của sản phẩm
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
    ORDER BY SortOrder
";

$stmtImages = $conn->prepare($sqlImages);
$stmtImages->bind_param('i', $productID);
$stmtImages->execute();

$productImages = $stmtImages->get_result();


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
    if (isset($_POST['delete_image'])) {

        $imageID = (int) $_POST['delete_image'];

        try {

            /*
            * Lấy thông tin ảnh cần xóa.
            * Điều kiện ProductID rất quan trọng:
            * không cho phép xóa ảnh thuộc sản phẩm khác.
            */
            $sqlImage = "
                SELECT
                    ProductImageID,
                    ImageFile,
                    IsPrimary,
                    SortOrder
                FROM product_images
                WHERE ProductImageID = ?
                AND ProductID = ?
            ";

            $stmtImage = $conn->prepare($sqlImage);

            $stmtImage->bind_param(
                'ii',
                $imageID,
                $productID
            );

            $stmtImage->execute();

            $resultImage = $stmtImage->get_result();
            $imageToDelete = $resultImage->fetch_assoc();

            $stmtImage->close();

            if (!$imageToDelete) {
                throw new Exception(
                    'Không tìm thấy hình ảnh cần xóa.'
                );
            }

            /*
            * Ở bước kiểm thử đầu tiên,
            * chưa cho xóa ảnh chính.
            */

            $conn->begin_transaction();

            /*
            * Xóa mẩu tin ảnh.
            */
            $sqlDelete = "
                DELETE FROM product_images
                WHERE ProductImageID = ?
                AND ProductID = ?
            ";

            $stmtDelete = $conn->prepare($sqlDelete);

            $stmtDelete->bind_param(
                'ii',
                $imageID,
                $productID
            );

            $stmtDelete->execute();

            if ($stmtDelete->affected_rows !== 1) {
                throw new Exception(
                    'Không thể xóa thông tin hình ảnh.'
                );
            }

            $stmtDelete->close();

            /*
            * Nếu ảnh vừa xóa là ảnh chính,
            * chọn ảnh còn lại có SortOrder nhỏ nhất
            * làm ảnh chính mới.
            */
            if ((int) $imageToDelete['IsPrimary'] === 1) {

                $sqlNewPrimary = "
                    UPDATE product_images
                    SET IsPrimary = 1
                    WHERE ProductImageID = (
                        SELECT ProductImageID
                        FROM (
                            SELECT ProductImageID
                            FROM product_images
                            WHERE ProductID = ?
                            ORDER BY SortOrder
                            LIMIT 1
                        ) AS remaining_images
                    )
                ";

                $stmtNewPrimary =
                    $conn->prepare($sqlNewPrimary);

                $stmtNewPrimary->bind_param(
                    'i',
                    $productID
                );

                $stmtNewPrimary->execute();
                $stmtNewPrimary->close();
            }
            /*
            * Chuẩn hóa lại SortOrder.
            *
            * Ví dụ:
            * 1 2 3 4 5 6 8
            *
            * trở thành:
            * 1 2 3 4 5 6 7
            */
            $deletedSortOrder =
                (int) $imageToDelete['SortOrder'];

            $sqlSort = "
                UPDATE product_images
                SET SortOrder = SortOrder - 1
                WHERE ProductID = ?
                AND SortOrder > ?
            ";

            $stmtSort = $conn->prepare($sqlSort);

            $stmtSort->bind_param(
                'ii',
                $productID,
                $deletedSortOrder
            );

            $stmtSort->execute();
            $stmtSort->close();

            $conn->commit();

            /*
            * Chỉ xóa tập tin vật lý sau khi
            * database đã COMMIT thành công.
            */
            $filePath =
                '/var/www/html/uploads/products/'
                . $imageToDelete['ImageFile'];

            if (is_file($filePath)) {
                unlink($filePath);
            }

            header(
                'Location: /products/edit.php?id='
                . $productID
                . '&image_deleted=1'
            );

            exit;

        } catch (Throwable $e) {

            /*
            * Chỉ rollback nếu transaction
            * đang thực sự hoạt động.
            */
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                // Không cần xử lý thêm.
            }

            $error = $e->getMessage();
        }
    }

    if (isset($_POST['add_images'])) {

        $files = $_FILES['product_images'] ?? null;

        if (
            !$files
            || !isset($files['name'])
            || count($files['name']) === 0
            || $files['error'][0] === UPLOAD_ERR_NO_FILE
        ) {

            $error = 'Vui lòng chọn ít nhất một ảnh.';

        } else {

            /*
            * Thư mục lưu ảnh trong container
            */
            $uploadDir =
                '/var/www/html/uploads/products/';

            /*
            * Các kiểu ảnh được chấp nhận.
            * Không dựa vào phần mở rộng do người dùng gửi lên.
            */
            $allowedMimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            /*
            * Danh sách tập tin đã lưu.
            * Nếu có lỗi, ta dùng danh sách này để xóa lại.
            */
            $uploadedFiles = [];

            try {

                $conn->begin_transaction();

                /*
                * Tìm SortOrder lớn nhất hiện tại.
                */
                $sqlMaxSort = "
                    SELECT COALESCE(MAX(SortOrder), 0)
                        AS MaxSortOrder
                    FROM product_images
                    WHERE ProductID = ?
                ";

                $stmtMaxSort =
                    $conn->prepare($sqlMaxSort);

                $stmtMaxSort->bind_param(
                    'i',
                    $productID
                );

                $stmtMaxSort->execute();

                $maxSortResult =
                    $stmtMaxSort->get_result();

                $maxSortRow =
                    $maxSortResult->fetch_assoc();

                $sortOrder =
                    (int) $maxSortRow['MaxSortOrder'];

                $stmtMaxSort->close();


                /*
                * Chuẩn bị câu INSERT một lần,
                * sau đó dùng lại cho từng ảnh.
                */
                $sqlInsertImage = "
                    INSERT INTO product_images
                    (
                        ProductID,
                        ImageFile,
                        AltText,
                        IsPrimary,
                        SortOrder
                    )
                    VALUES (?, ?, ?, 0, ?)
                ";

                $stmtInsertImage =
                    $conn->prepare($sqlInsertImage);


                /*
                * Kiểm tra và lưu từng ảnh.
                */
                $fileCount =
                    count($files['name']);

                for ($i = 0; $i < $fileCount; $i++) {

                    /*
                    * Kiểm tra lỗi upload.
                    */
                    if (
                        $files['error'][$i]
                        !== UPLOAD_ERR_OK
                    ) {

                        throw new Exception(
                            'Có tập tin tải lên không thành công.'
                        );
                    }


                    /*
                    * Kiểm tra MIME type thực tế.
                    */
                    $finfo =
                        new finfo(FILEINFO_MIME_TYPE);

                    $mimeType =
                        $finfo->file(
                            $files['tmp_name'][$i]
                        );

                    if (
                        !isset(
                            $allowedMimeTypes[$mimeType]
                        )
                    ) {

                        throw new Exception(
                            'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.'
                        );
                    }


                    /*
                    * Sinh tên tập tin mới.
                    */
                    $extension =
                        $allowedMimeTypes[$mimeType];

                    $newFileName =
                        'product-'
                        . bin2hex(random_bytes(8))
                        . '.'
                        . $extension;


                    /*
                    * Di chuyển tập tin vào thư mục uploads.
                    */
                    $destination =
                        $uploadDir . $newFileName;

                    if (
                        !move_uploaded_file(
                            $files['tmp_name'][$i],
                            $destination
                        )
                    ) {

                        throw new Exception(
                            'Không thể lưu tập tin ảnh.'
                        );
                    }

                    /*
                    * Ghi nhớ để có thể xóa nếu rollback.
                    */
                    $uploadedFiles[] =
                        $destination;


                    /*
                    * Ảnh mới nằm sau các ảnh hiện có.
                    */
                    $sortOrder++;


                    /*
                    * Tạo AltText.
                    */
                    $altText =
                        $product['ProductName']
                        . ' - ảnh '
                        . $sortOrder;


                    /*
                    * Ghi thông tin ảnh vào database.
                    */
                    $stmtInsertImage->bind_param(
                        'issi',
                        $productID,
                        $newFileName,
                        $altText,
                        $sortOrder
                    );

                    if (
                        !$stmtInsertImage->execute()
                    ) {

                        throw new Exception(
                            'Không thể lưu thông tin ảnh.'
                        );
                    }
                }

                $stmtInsertImage->close();

                /*
                * Tất cả ảnh đều thành công.
                */
                $conn->commit();

                header(
                    'Location: /products/edit.php?id='
                    . $productID
                    . '&images_added=1'
                );

                exit;

            } catch (Throwable $e) {

                /*
                * Hủy thay đổi database.
                */
                $conn->rollback();


                /*
                * Transaction của MySQL không thể rollback
                * các tập tin đã ghi xuống ổ đĩa.
                * Vì vậy phải tự xóa chúng.
                */
                foreach (
                    $uploadedFiles as $uploadedFile
                ) {

                    if (file_exists($uploadedFile)) {
                        unlink($uploadedFile);
                    }
                }

                $error = $e->getMessage();
            }
        }
    }
    /*
     * Trường hợp 1:
     * Người dùng chọn một ảnh khác làm ảnh chính
     */
    if (isset($_POST['set_primary_image'])) {
        $imageID = (int) $_POST['set_primary_image'];

        try {

            $conn->begin_transaction();

            /*
             * Bỏ trạng thái ảnh chính của tất cả ảnh
             * thuộc sản phẩm hiện tại
             */
            $sqlResetPrimary = "
                UPDATE product_images
                SET IsPrimary = 0
                WHERE ProductID = ?
            ";

            $stmtResetPrimary =
                $conn->prepare($sqlResetPrimary);

            $stmtResetPrimary->bind_param(
                'i',
                $productID
            );

            $stmtResetPrimary->execute();


            /*
             * Đặt ảnh được chọn làm ảnh chính
             */
            $sqlSetPrimary = "
                UPDATE product_images
                SET IsPrimary = 1
                WHERE ProductImageID = ?
                  AND ProductID = ?
            ";

            $stmtSetPrimary =
                $conn->prepare($sqlSetPrimary);

            $stmtSetPrimary->bind_param(
                'ii',
                $imageID,
                $productID
            );

            $stmtSetPrimary->execute();


            /*
             * Nếu không có đúng 1 ảnh được cập nhật
             * thì xem thao tác là không hợp lệ
             */
            if ($stmtSetPrimary->affected_rows !== 1) {

                throw new Exception(
                    'Ảnh được chọn không hợp lệ.'
                );
            }


            /*
             * Cả hai UPDATE đều thành công
             */
            $conn->commit();


            /*
             * Redirect về trang sửa sản phẩm
             * và truyền trạng thái để hiển thị feedback
             */
            header(
                'Location: /products/edit.php?id='
                . $productID
                . '&primary_updated=1'
            );

            exit;

        } catch (Throwable $e) {

            /*
             * Nếu có lỗi:
             * khôi phục trạng thái trước transaction
             */
            $conn->rollback();

            $error = $e->getMessage();
        }

    } else {

        /*
         * Trường hợp 2:
         * Người dùng cập nhật thông tin sản phẩm
         */

        $productCode =
            trim($_POST['product_code'] ?? '');

        $productName =
            trim($_POST['product_name'] ?? '');

        $description =
            trim($_POST['description'] ?? '');

        $unit =
            trim($_POST['unit'] ?? '');

        $price =
            (float) ($_POST['price'] ?? 0);

        $stockQuantity =
            (int) ($_POST['stock_quantity'] ?? 0);

        $categoryID =
            (int) ($_POST['category_id'] ?? 0);

        $supplierID =
            (int) ($_POST['supplier_id'] ?? 0);

        $isActive =
            isset($_POST['is_active']) ? 1 : 0;


        /*
         * Kiểm tra dữ liệu
         */
        if ($productCode === '') {

            $error =
                'Mã sản phẩm không được để trống.';

        } elseif ($productName === '') {

            $error =
                'Tên sản phẩm không được để trống.';

        } elseif ($price < 0) {

            $error =
                'Giá sản phẩm không hợp lệ.';

        } elseif ($stockQuantity < 0) {

            $error =
                'Số lượng tồn kho không hợp lệ.';

        } elseif ($categoryID <= 0) {

            $error =
                'Vui lòng chọn danh mục.';

        } elseif ($supplierID <= 0) {

            $error =
                'Vui lòng chọn nhà cung cấp.';

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

                $error =
                    'Không thể cập nhật sản phẩm.';
            }

            $stmt->close();
        }
    }
}


require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Sửa sản phẩm</h2>


    <?php if (
        isset($_GET['primary_updated'])
        && $_GET['primary_updated'] === '1'
    ): ?>

        <div class="alert alert-success">
            Đã cập nhật ảnh chính thành công.
        </div>

    <?php endif; ?>

    <?php if (
        isset($_GET['image_deleted'])
        && $_GET['image_deleted'] === '1'
    ): ?>

        <div class="alert alert-success">
            Đã xóa hình ảnh sản phẩm thành công.
        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['images_added'])
        && $_GET['images_added'] === '1'
    ): ?>

        <div class="alert alert-success">
            Đã thêm hình ảnh sản phẩm thành công.
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form
        method="post"
        enctype="multipart/form-data"
    >

        <div class="row">

            <div class="col-md-4 mb-3">

                <label
                    for="productCode"
                    class="form-label"
                >
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

                <label
                    for="productName"
                    class="form-label"
                >
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

            <label
                for="description"
                class="form-label"
            >
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

                <label
                    for="unit"
                    class="form-label"
                >
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

                <label
                    for="price"
                    class="form-label"
                >
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

                <label
                    for="stockQuantity"
                    class="form-label"
                >
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

                <label
                    for="categoryID"
                    class="form-label"
                >
                    Danh mục
                </label>

                <select
                    class="form-select"
                    id="categoryID"
                    name="category_id"
                    required
                >

                    <?php while (
                        $category =
                            $categories->fetch_assoc()
                    ): ?>

                        <?php

                        $selectedCategoryID =
                            $_POST['category_id']
                            ?? $product['CategoryID'];

                        ?>

                        <option
                            value="<?=
                                $category['CategoryID']
                            ?>"
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

                <label
                    for="supplierID"
                    class="form-label"
                >
                    Nhà cung cấp
                </label>

                <select
                    class="form-select"
                    id="supplierID"
                    name="supplier_id"
                    required
                >

                    <?php while (
                        $supplier =
                            $suppliers->fetch_assoc()
                    ): ?>

                        <?php

                        $selectedSupplierID =
                            $_POST['supplier_id']
                            ?? $product['SupplierID'];

                        ?>

                        <option
                            value="<?=
                                $supplier['SupplierID']
                            ?>"
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


        <div class="mb-4">

            <label class="form-label">
                Hình ảnh sản phẩm
            </label>

            <div class="row g-3">

                <?php while (
                    $image =
                        $productImages->fetch_assoc()
                ): ?>

                    <div class="col-md-3">

                        <div class="card h-100">

                            <img
                                src="/uploads/products/<?=
                                    htmlspecialchars(
                                        $image['ImageFile']
                                    )
                                ?>"
                                class="card-img-top"
                                alt="<?=
                                    htmlspecialchars(
                                        $image['AltText']
                                        ?? ''
                                    )
                                ?>"
                                style="
                                    height: 160px;
                                    object-fit: contain;
                                "
                            >

                            <div class="card-body">

                                <small class="text-muted">
                                    Thứ tự:
                                    <?= $image['SortOrder'] ?>
                                </small>

                                <div class="mt-2">

                                    <?php if (
                                        (int) $image['IsPrimary'] === 1
                                    ): ?>

                                        <span
                                            class="badge bg-success"
                                        >
                                            Ảnh chính
                                        </span>

                                    <?php else: ?>

                                        <button
                                            type="submit"
                                            class="btn btn-outline-primary btn-sm"
                                            name="set_primary_image"
                                            value="<?= $image['ProductImageID'] ?>"
                                            formaction="/products/edit.php?id=<?= $productID ?>"
                                            formmethod="post"
                                        >
                                            Đặt làm ảnh chính
                                        </button>

                                    <?php endif; ?>

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-sm ms-2"
                                        name="delete_image"
                                        value="<?= $image['ProductImageID'] ?>"
                                        formaction="/products/edit.php?id=<?= $productID ?>"
                                        formmethod="post"
                                        onclick="return confirm('Bạn có chắc muốn xóa ảnh này?');"
                                    >
                                        Xóa ảnh
                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            </div>
        </div>
        <div class="mb-4">
            <label
                for="productImages"
                class="form-label"
            >
                Thêm hình ảnh
            </label>

            <input
                type="file"
                class="form-control"
                id="productImages"
                name="product_images[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
            >

            <div class="form-text">
                Có thể chọn nhiều ảnh cùng lúc.
                Chấp nhận JPG, PNG và WebP.
            </div>
             <button
                type="submit"
                class="btn btn-outline-success mt-2"
                name="add_images"
                value="1"
                formaction="/products/edit.php?id=<?= $productID ?>"
                formmethod="post"
            >
                Thêm ảnh
            </button>
        </div>

        <div class="form-check mb-3">

            <?php

            $currentIsActive =
                $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? isset($_POST['is_active'])
                    : (
                        (int) $product['IsActive']
                        === 1
                    );

            ?>


            <input
                type="checkbox"
                class="form-check-input"
                id="isActive"
                name="is_active"
                value="1"
                <?= $currentIsActive
                    ? 'checked'
                    : '' ?>
            >
            <label
                class="form-check-label"
                for="isActive"
            >
                Đang kinh doanh
            </label>

        </div>


        <button
            type="submit"
            class="btn btn-warning"
        >
            Cập nhật
        </button>

        <a
            href="/products/"
            class="btn btn-secondary"
        >
            Hủy
        </a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/footer.php';

$conn->close();