<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$orderID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($orderID <= 0) {
    header('Location: /');
    exit;
}


/*
 * Đọc thông tin đơn hàng.
 */
$sqlOrder = "
    SELECT
        o.OrderID,
        o.OrderDate,
        o.TotalAmount,
        o.Status,
        c.CustomerName,
        c.Phone,
        c.Address
    FROM orders o, customers c
    WHERE o.CustomerID = c.CustomerID
      AND o.OrderID = ?
";

$stmtOrder = $conn->prepare($sqlOrder);

$stmtOrder->bind_param(
    'i',
    $orderID
);

$stmtOrder->execute();

$resultOrder = $stmtOrder->get_result();
$order = $resultOrder->fetch_assoc();

$resultOrder->free();
$stmtOrder->close();

if (!$order) {
    header('Location: /');
    exit;
}


/*
 * Đọc chi tiết đơn hàng.
 */
$sqlDetail = "
    SELECT
        od.Quantity,
        od.UnitPrice,
        p.ProductCode,
        p.ProductName
    FROM orderdetail od, products p
    WHERE od.ProductID = p.ProductID
      AND od.OrderID = ?
    ORDER BY od.OrderDetailID
";

$stmtDetail = $conn->prepare($sqlDetail);

$stmtDetail->bind_param(
    'i',
    $orderID
);

$stmtDetail->execute();

$resultDetail = $stmtDetail->get_result();

$orderItems = [];

while ($item = $resultDetail->fetch_assoc()) {

    $item['Subtotal'] =
        (float) $item['UnitPrice']
        * (int) $item['Quantity'];

    $orderItems[] = $item;
}

$resultDetail->free();
$stmtDetail->close();


$pageTitle = 'Đặt hàng thành công';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="alert alert-success">

                <h1 class="h4">
                    Đặt hàng thành công
                </h1>

                <p class="mb-0">
                    Cảm ơn bạn đã đặt hàng.
                    Mã đơn hàng của bạn là
                    <strong>
                        #<?= (int) $order['OrderID'] ?>
                    </strong>.
                </p>

            </div>


            <div class="card mb-4">

                <div class="card-header">
                    <strong>
                        Thông tin đơn hàng
                    </strong>
                </div>

                <div class="card-body">

                    <p>
                        <strong>Khách hàng:</strong>
                        <?= htmlspecialchars(
                            $order['CustomerName']
                        ) ?>
                    </p>

                    <p>
                        <strong>Số điện thoại:</strong>
                        <?= htmlspecialchars(
                            $order['Phone']
                        ) ?>
                    </p>

                    <p>
                        <strong>Địa chỉ giao hàng:</strong>
                        <?= htmlspecialchars(
                            $order['Address']
                        ) ?>
                    </p>

                    <p>
                        <strong>Ngày đặt hàng:</strong>
                        <?= htmlspecialchars(
                            $order['OrderDate']
                        ) ?>
                    </p>

                    <p class="mb-0">
                        <strong>Trạng thái:</strong>
                        <?= htmlspecialchars(
                            $order['Status']
                        ) ?>
                    </p>

                </div>

            </div>


            <div class="card">

                <div class="card-header">
                    <strong>
                        Sản phẩm đã đặt
                    </strong>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table align-middle">

                            <thead>

                                <tr>

                                    <th>
                                        Sản phẩm
                                    </th>

                                    <th class="text-end">
                                        Đơn giá
                                    </th>

                                    <th class="text-center">
                                        Số lượng
                                    </th>

                                    <th class="text-end">
                                        Thành tiền
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($orderItems as $item): ?>

                                    <tr>

                                        <td>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $item['ProductName']
                                                ) ?>
                                            </strong>

                                            <div class="text-muted small">

                                                <?= htmlspecialchars(
                                                    $item['ProductCode']
                                                ) ?>

                                            </div>

                                        </td>

                                        <td class="text-end">

                                            <?= number_format(
                                                (float) $item['UnitPrice'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>
                                            đ

                                        </td>

                                        <td class="text-center">

                                            <?= (int) $item['Quantity'] ?>

                                        </td>

                                        <td class="text-end">

                                            <?= number_format(
                                                (float) $item['Subtotal'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>
                                            đ

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                            <tfoot>

                                <tr>

                                    <th
                                        colspan="3"
                                        class="text-end"
                                    >
                                        Tổng cộng
                                    </th>

                                    <th class="text-end">

                                        <?= number_format(
                                            (float) $order['TotalAmount'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                        đ

                                    </th>

                                </tr>

                            </tfoot>

                        </table>

                    </div>

                </div>

            </div>


            <div class="mt-4">

                <a
                    href="/products.php"
                    class="btn btn-primary"
                >
                    Tiếp tục mua hàng
                </a>

            </div>

        </div>

    </div>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>
