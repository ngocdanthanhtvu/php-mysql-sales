<?php

$pageTitle = 'Thêm danh mục';

require_once '/var/www/src/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $categoryName = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($categoryName === '') {

        $error = 'Tên danh mục không được để trống.';

    } else {

        $sql = "
            INSERT INTO categories
                (CategoryName, Description)
            VALUES
                (?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'ss',
            $categoryName,
            $description
        );

        if ($stmt->execute()) {

            header('Location: /categories/');
            exit;

        } else {

            $error = 'Không thể thêm danh mục.';
        }

        $stmt->close();
    }
}

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Thêm danh mục</h2>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="post">

        <div class="mb-3">

            <label for="categoryName" class="form-label">
                Tên danh mục
            </label>

            <input
                type="text"
                class="form-control"
                id="categoryName"
                name="category_name"
                value="<?= htmlspecialchars($_POST['category_name'] ?? '') ?>"
                required
            >

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
            ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

        </div>

        <button type="submit" class="btn btn-primary">
            Lưu
        </button>

        <a href="/categories/" class="btn btn-secondary">
            Hủy
        </a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/footer.php';

$conn->close();