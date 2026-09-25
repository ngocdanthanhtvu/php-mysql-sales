<?php

require_once '/var/www/src/config/session.php';

$pageTitle = 'Trang chủ';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main>

    <section class="py-5 bg-light">

        <div class="container">

            <div class="row align-items-center">

                <div class="col-lg-7">

                    <h1 class="display-5 fw-bold">
                        Chào mừng đến với Sales Store
                    </h1>

                    <p class="lead text-muted">
                        Khám phá các sản phẩm đang có tại cửa hàng.
                    </p>

                    <a
                        href="/products.php"
                        class="btn btn-primary"
                    >
                        Xem sản phẩm
                    </a>

                </div>

            </div>

        </div>

    </section>

    <section class="container py-5">

        <h2 class="mb-3">
            Sản phẩm nổi bật
        </h2>

        <p class="text-muted">
            Danh sách sản phẩm sẽ được hiển thị tại đây
            trong bước tiếp theo.
        </p>

    </section>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';
