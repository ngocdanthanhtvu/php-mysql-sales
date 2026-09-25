<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đăng ký tài khoản';

$customerName = '';
$phone = '';
$email = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($customerName === ''
        || $email === ''
        || $password === ''
        || $confirmPassword === '') {

        $errorMessage =
            'Vui lòng nhập đầy đủ các thông tin bắt buộc.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errorMessage =
            'Địa chỉ email không hợp lệ.';

    } elseif (strlen($password) < 6) {

        $errorMessage =
            'Mật khẩu phải có ít nhất 6 ký tự.';

    } elseif ($password !== $confirmPassword) {

        $errorMessage =
            'Mật khẩu xác nhận không khớp.';

    } else {

        /*
         * Kiểm tra email đã được đăng ký hay chưa.
         */
        $sqlCheck = "
            SELECT CustomerID
            FROM customers
            WHERE Email = ?
        ";

        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param('s', $email);
        $stmtCheck->execute();

        $checkResult = $stmtCheck->get_result();
        $existingCustomer = $checkResult->fetch_assoc();

        $checkResult->free();
        $stmtCheck->close();

        if ($existingCustomer) {

            $errorMessage =
                'Email này đã được sử dụng.';

        } else {

            /*
             * Không lưu mật khẩu gốc.
             * Chỉ lưu giá trị được tạo bởi password_hash().
             */
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $sqlInsert = "
                INSERT INTO customers (
                    CustomerName,
                    Phone,
                    Email,
                    PasswordHash
                )
                VALUES (?, ?, ?, ?)
            ";

            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->bind_param(
                'ssss',
                $customerName,
                $phone,
                $email,
                $passwordHash
            );

            $stmtInsert->execute();
            $stmtInsert->close();

            header('Location: /login.php?registered=1');
            exit;
        }
    }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-7 col-lg-6">

            <div class="card">

                <div class="card-body p-4">

                    <h2 class="mb-4">
                        Đăng ký tài khoản
                    </h2>

                    <?php if ($errorMessage !== ''): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>

                    <?php endif; ?>

                    <form method="post">

                        <div class="mb-3">

                            <label
                                for="customer_name"
                                class="form-label"
                            >
                                Họ và tên
                            </label>

                            <input
                                type="text"
                                name="customer_name"
                                id="customer_name"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $customerName
                                ) ?>"
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
                                name="phone"
                                id="phone"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $phone
                                ) ?>"
                            >

                        </div>

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
                                value="<?= htmlspecialchars(
                                    $email
                                ) ?>"
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
                                minlength="6"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label
                                for="confirm_password"
                                class="form-label"
                            >
                                Xác nhận mật khẩu
                            </label>

                            <input
                                type="password"
                                name="confirm_password"
                                id="confirm_password"
                                class="form-control"
                                minlength="6"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Đăng ký
                        </button>

                    </form>

                    <p class="text-center mt-3 mb-0">
                        Đã có tài khoản?
                        <a href="/login.php">
                            Đăng nhập
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
