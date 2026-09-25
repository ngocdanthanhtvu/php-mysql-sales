<?php

require_once '/var/www/src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$keyword =
    trim($_GET['keyword'] ?? '');

$categoryID =
    (int) ($_GET['category'] ?? 0);


$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        p.StockQuantity,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile
    FROM products p, categories c
    WHERE p.CategoryID = c.CategoryID
      AND p.IsActive = 1
";


/*
 * Bổ sung điều kiện tìm kiếm.
 */
if ($keyword !== '') {

    $sql .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";
}


/*
 * Bổ sung điều kiện lọc danh mục.
 */
if ($categoryID > 0) {

    $sql .= "
        AND p.CategoryID = ?
    ";
}


$sql .= "
    ORDER BY p.ProductID DESC
";


$stmt = $conn->prepare($sql);


/*
 * Gắn tham số tùy theo điều kiện tìm kiếm/lọc.
 */
if ($keyword !== '' && $categoryID > 0) {

    $searchKeyword =
        '%' . $keyword . '%';

    $stmt->bind_param(
        'ssi',
        $searchKeyword,
        $searchKeyword,
        $categoryID
    );

} elseif ($keyword !== '') {

    $searchKeyword =
        '%' . $keyword . '%';

    $stmt->bind_param(
        'ss',
        $searchKeyword,
        $searchKeyword
    );

} elseif ($categoryID > 0) {

    $stmt->bind_param(
        'i',
        $categoryID
    );
}


$stmt->execute();

$result =
    $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {

    $products[] = [
        'id' => (int) $row['ProductID'],
        'code' => $row['ProductCode'],
        'name' => $row['ProductName'],
        'price' => (float) $row['Price'],
        'stock' => (int) $row['StockQuantity'],
        'category' => $row['CategoryName'],
        'image' => $row['ImageFile']
    ];
}

$result->free();
$stmt->close();
$conn->close();


echo json_encode(
    [
        'success' => true,
        'data' => $products
    ],
    JSON_UNESCAPED_UNICODE
);
