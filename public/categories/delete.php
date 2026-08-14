<?php

require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /categories/');
    exit;
}

$categoryID = isset($_POST['id'])
    ? (int) $_POST['id']
    : 0;

if ($categoryID <= 0) {
    header('Location: /categories/');
    exit;
}

$sql = "
    DELETE FROM categories
    WHERE CategoryID = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $categoryID);

$stmt->execute();

$stmt->close();
$conn->close();

header('Location: /categories/');
exit;