<?php

require_once '/var/www/src/config/session.php';

/*
 * Xóa toàn bộ dữ liệu xác thực của khách hàng.
 *
 * Không dùng session_destroy() vì Session hiện còn
 * được sử dụng để lưu giỏ hàng.
 */
unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_name']
);

session_regenerate_id(true);

header('Location: /');
exit;