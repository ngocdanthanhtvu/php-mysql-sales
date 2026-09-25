<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đăng nhập';

$email = '';
$errorMessage = '';

/*
 * Nếu khách hàng đã đăng nhập,
 * không cần đăng nhập lại.
 */
if (isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $errorMessage =
            'Vui lòng nhập email và mật khẩu.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errorMessage =
            'Địa chỉ email không hợp lệ.';

    } else {

        /*
         * Tìm khách hàng theo email.
         */
        $sql = "
            SELECT
                CustomerID,
                CustomerName,
                Email,
                PasswordHash
            FROM customers
            WHERE Email = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $customer = $result->fetch_assoc();

        $result->free();
        $stmt->close();

        /*
         * Kiểm tra mật khẩu.
         */
        if (
            $customer
            && !empty($customer['PasswordHash'])
            && password_verify(
                $password,
                $customer['PasswordHash']
            )
        ) {

            /*
             * Tạo session mới sau khi xác thực thành công.
             */
            session_regenerate_id(true);

            $_SESSION['customer_id'] =
                (int) $customer['CustomerID'];

            $_SESSION['customer_name'] =
                $customer['CustomerName'];

            header('Location: /');
            exit;

        } else {

            $errorMessage =
                'Email hoặc mật khẩu không đúng.';
        }
    }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-6 col-lg-5">

            <div class="card">

                <div class="card-body p-4">

                    <h2 class="mb-4">
                        Đăng nhập
                    </h2>

                    <?php if (
                        isset($_GET['registered'])
                        && $_GET['registered'] === '1'
                    ): ?>

                        <div class="alert alert-success">
                            Đăng ký thành công.
                            Vui lòng đăng nhập.
                        </div>

                    <?php endif; ?>

                    <?php if ($errorMessage !== ''): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>

                    <?php endif; ?>

                    <form method="post">

                        <div class="mb-3">

                            <label
                                for="email"
                                class="form-label"
                            >
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control"
                                value="<?= htmlspecialchars($email) ?>"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label
                                for="password"
                                class="form-label"
                            >
                                Mật khẩu
                            </label>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Đăng nhập
                        </button>

                    </form>

                    <p class="text-center mt-3 mb-0">
                        Chưa có tài khoản?
                        <a href="/register.php">
                            Đăng ký
                        </a>
                    </p>

                </div>

            </div>

        </div>

    </div>

</div>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
