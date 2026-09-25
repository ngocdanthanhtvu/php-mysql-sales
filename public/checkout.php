<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đặt hàng';

$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header('Location: /cart.php');
    exit;
}

$customerName = '';
$phone = '';
$address = '';
$errorMessage = '';

/*
 * Đọc lại dữ liệu sản phẩm từ database.
 * Không sử dụng giá được gửi từ trình duyệt.
 */
function getCartItems($conn, $cart)
{
    $cartItems = [];
    $totalAmount = 0;

    $sql = "
        SELECT
            p.ProductID,
            p.ProductCode,
            p.ProductName,
            p.Price,
            p.StockQuantity,
            (
                SELECT pi.ImageFile
                FROM product_images pi
                WHERE pi.ProductID = p.ProductID
                  AND pi.IsPrimary = 1
                LIMIT 1
            ) AS ImageFile
        FROM products p
        WHERE p.ProductID = ?
          AND p.IsActive = 1
    ";

    $stmt = $conn->prepare($sql);

    foreach ($cart as $productID => $quantity) {

        $productID = (int) $productID;
        $quantity = (int) $quantity;

        if ($productID <= 0 || $quantity <= 0) {
            continue;
        }

        $stmt->bind_param(
            'i',
            $productID
        );

        $stmt->execute();

        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        $result->free();

        if (!$product) {
            continue;
        }

        $subtotal =
            (float) $product['Price'] * $quantity;

        $product['Quantity'] = $quantity;
        $product['Subtotal'] = $subtotal;

        $cartItems[] = $product;

        $totalAmount += $subtotal;
    }

    $stmt->close();

    return [
        'items' => $cartItems,
        'total' => $totalAmount
    ];
}

$cartData = getCartItems(
    $conn,
    $cart
);

$cartItems = $cartData['items'];
$totalAmount = $cartData['total'];

if (empty($cartItems)) {
    header('Location: /cart.php');
    exit;
}


/*
 * Xử lý đặt hàng.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['place_order'])) {

    $customerName =
        trim($_POST['customer_name'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $address =
        trim($_POST['address'] ?? '');

    if ($customerName === ''
        || $phone === ''
        || $address === '') {

        $errorMessage =
            'Vui lòng nhập đầy đủ thông tin đặt hàng.';

    } else {

        try {

            $conn->begin_transaction();

            /*
             * 1. Kiểm tra lại sản phẩm và tồn kho.
             * FOR UPDATE khóa các mẩu tin sản phẩm
             * trong thời gian xử lý đơn hàng.
             */
            $orderItems = [];
            $orderTotal = 0;

            $sqlProduct = "
                SELECT
                    ProductID,
                    ProductName,
                    Price,
                    StockQuantity
                FROM products
                WHERE ProductID = ?
                  AND IsActive = 1
                FOR UPDATE
            ";

            $stmtProduct =
                $conn->prepare($sqlProduct);

            foreach ($cart as $productID => $quantity) {

                $productID = (int) $productID;
                $quantity = (int) $quantity;

                if ($productID <= 0
                    || $quantity <= 0) {

                    throw new Exception(
                        'Giỏ hàng không hợp lệ.'
                    );
                }

                $stmtProduct->bind_param(
                    'i',
                    $productID
                );

                $stmtProduct->execute();

                $resultProduct =
                    $stmtProduct->get_result();

                $product =
                    $resultProduct->fetch_assoc();

                $resultProduct->free();

                if (!$product) {
                    throw new Exception(
                        'Một sản phẩm trong giỏ hàng không còn khả dụng.'
                    );
                }

                $stockQuantity =
                    (int) $product['StockQuantity'];

                if ($quantity > $stockQuantity) {

                    throw new Exception(
                        'Sản phẩm "'
                        . $product['ProductName']
                        . '" không đủ số lượng tồn kho.'
                    );
                }

                $price =
                    (float) $product['Price'];

                $subtotal =
                    $price * $quantity;

                $orderItems[] = [
                    'ProductID' => $productID,
                    'Quantity' => $quantity,
                    'UnitPrice' => $price
                ];

                $orderTotal += $subtotal;
            }

            $stmtProduct->close();

            if (empty($orderItems)) {
                throw new Exception(
                    'Giỏ hàng không có sản phẩm hợp lệ.'
                );
            }


            /*
             * 2. Tạo khách hàng.
             */
            $sqlCustomer = "
                INSERT INTO customers (
                    CustomerName,
                    Address,
                    Phone
                )
                VALUES (?, ?, ?)
            ";

            $stmtCustomer =
                $conn->prepare($sqlCustomer);

            $stmtCustomer->bind_param(
                'sss',
                $customerName,
                $address,
                $phone
            );

            $stmtCustomer->execute();

            $customerID =
                $conn->insert_id;

            $stmtCustomer->close();


            /*
             * 3. Tạo đơn hàng.
             */
            $status = 'Pending';

            $sqlOrder = "
                INSERT INTO orders (
                    TotalAmount,
                    Status,
                    CustomerID
                )
                VALUES (?, ?, ?)
            ";

            $stmtOrder =
                $conn->prepare($sqlOrder);

            $stmtOrder->bind_param(
                'dsi',
                $orderTotal,
                $status,
                $customerID
            );

            $stmtOrder->execute();

            $orderID =
                $conn->insert_id;

            $stmtOrder->close();


            /*
             * 4. Lưu chi tiết đơn hàng.
             */
            $sqlDetail = "
                INSERT INTO orderdetail (
                    Quantity,
                    UnitPrice,
                    OrderID,
                    ProductID
                )
                VALUES (?, ?, ?, ?)
            ";

            $stmtDetail =
                $conn->prepare($sqlDetail);


            /*
             * 5. Trừ tồn kho.
             */
            $sqlStock = "
                UPDATE products
                SET StockQuantity =
                    StockQuantity - ?
                WHERE ProductID = ?
            ";

            $stmtStock =
                $conn->prepare($sqlStock);


            foreach ($orderItems as $item) {

                $quantity =
                    (int) $item['Quantity'];

                $unitPrice =
                    (float) $item['UnitPrice'];

                $productID =
                    (int) $item['ProductID'];

                $stmtDetail->bind_param(
                    'idii',
                    $quantity,
                    $unitPrice,
                    $orderID,
                    $productID
                );

                $stmtDetail->execute();


                $stmtStock->bind_param(
                    'ii',
                    $quantity,
                    $productID
                );

                $stmtStock->execute();
            }

            $stmtDetail->close();
            $stmtStock->close();


            /*
             * 6. Hoàn tất transaction.
             */
            $conn->commit();


            /*
             * Chỉ xóa giỏ hàng sau khi COMMIT thành công.
             */
            $_SESSION['cart'] = [];

            header(
                'Location: /order-success.php?id='
                . $orderID
            );

            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            $errorMessage =
                $e->getMessage();
        }
    }
}


require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<div class="container py-4">

    <div class="row g-4">

        <div class="col-lg-7">

            <h1 class="h3 mb-4">
                Thông tin đặt hàng
            </h1>

            <?php if ($errorMessage !== ''): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>

            <?php endif; ?>

            <form
                method="post"
                action="/checkout.php"
            >

                <div class="mb-3">

                    <label
                        for="customer_name"
                        class="form-label"
                    >
                        Họ và tên
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="customer_name"
                        name="customer_name"
                        value="<?=
                            htmlspecialchars($customerName)
                        ?>"
                        required
                    >

                </div>

                <div class="mb-3">

                    <label
                        for="phone"
                        class="form-label"
                    >
                        Số điện thoại
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="phone"
                        name="phone"
                        value="<?=
                            htmlspecialchars($phone)
                        ?>"
                        required
                    >

                </div>

                <div class="mb-3">

                    <label
                        for="address"
                        class="form-label"
                    >
                        Địa chỉ giao hàng
                    </label>

                    <textarea
                        class="form-control"
                        id="address"
                        name="address"
                        rows="3"
                        required
                    ><?= htmlspecialchars($address) ?></textarea>

                </div>

                <button
                    type="submit"
                    name="place_order"
                    class="btn btn-success"
                >
                    Xác nhận đặt hàng
                </button>

            </form>

        </div>


        <div class="col-lg-5">

            <div class="card">

                <div class="card-header">
                    <strong>
                        Đơn hàng của bạn
                    </strong>
                </div>

                <div class="card-body">

                    <?php foreach ($cartItems as $item): ?>

                        <div
                            class="d-flex
                                   justify-content-between
                                   align-items-start
                                   mb-3"
                        >

                            <div class="me-3">

                                <div class="fw-semibold">
                                    <?=
                                        htmlspecialchars(
                                            $item['ProductName']
                                        )
                                    ?>
                                </div>

                                <small class="text-muted">

                                    <?=
                                        (int) $item['Quantity']
                                    ?>

                                    ×

                                    <?=
                                        number_format(
                                            (float) $item['Price'],
                                            0,
                                            ',',
                                            '.'
                                        )
                                    ?>
                                    đ

                                </small>

                            </div>

                            <div class="text-nowrap">

                                <?=
                                    number_format(
                                        (float) $item['Subtotal'],
                                        0,
                                        ',',
                                        '.'
                                    )
                                ?>
                                đ

                            </div>

                        </div>

                    <?php endforeach; ?>

                    <hr>

                    <div
                        class="d-flex
                               justify-content-between
                               fs-5"
                    >

                        <strong>
                            Tổng cộng
                        </strong>

                        <strong>
                            <?=
                                number_format(
                                    $totalAmount,
                                    0,
                                    ',',
                                    '.'
                                )
                            ?>
                            đ
                        </strong>

                    </div>

                </div>

            </div>

            <a
                href="/cart.php"
                class="btn
                       btn-outline-secondary
                       mt-3"
            >
                Quay lại giỏ hàng
            </a>

        </div>

    </div>

</div>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>
