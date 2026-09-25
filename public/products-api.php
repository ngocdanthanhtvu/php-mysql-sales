<?php

require_once '/var/www/src/config/session.php';

$pageTitle = 'Sản phẩm từ REST API';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<div class="container py-4">

    <h1 class="h3 mb-4">
        Sản phẩm từ REST API
    </h1>

    <!-- Tìm kiếm và lọc -->
    <form
        id="filter-form"
        class="row g-3 mb-4"
    >

        <div class="col-md-6">

            <label
                for="keyword"
                class="form-label"
            >
                Từ khóa
            </label>

            <input
                type="text"
                id="keyword"
                class="form-control"
                placeholder="Tên hoặc mã sản phẩm"
            >

        </div>

        <div class="col-md-4">

            <label
                for="category"
                class="form-label"
            >
                Danh mục
            </label>

            <select
                id="category"
                class="form-select"
            >
                <option value="">
                    Tất cả danh mục
                </option>
            </select>

        </div>

        <div class="col-md-2 d-flex align-items-end">

            <button
                type="submit"
                class="btn btn-primary w-100"
            >
                Tìm kiếm
            </button>

        </div>

    </form>


    <!-- Trạng thái tải -->
    <div
        id="loading"
        class="alert alert-info d-none"
    >
        Đang tải dữ liệu sản phẩm...
    </div>


    <!-- Thông báo lỗi -->
    <div
        id="error-message"
        class="alert alert-danger d-none"
    ></div>


    <!-- Danh sách sản phẩm -->
    <div
        id="product-list"
        class="row g-4"
    ></div>

</div>


<script>

const filterForm =
    document.getElementById('filter-form');

const keywordElement =
    document.getElementById('keyword');

const categoryElement =
    document.getElementById('category');

const loadingElement =
    document.getElementById('loading');

const errorElement =
    document.getElementById('error-message');

const productListElement =
    document.getElementById('product-list');


function formatPrice(price) {

    return Number(price).toLocaleString('vi-VN')
        + ' đ';
}


function escapeHtml(value) {

    const element =
        document.createElement('div');

    element.textContent =
        String(value ?? '');

    return element.innerHTML;
}


function createProductCard(product) {

    const productName =
        escapeHtml(product.name);

    const productCode =
        escapeHtml(product.code);

    const categoryName =
        escapeHtml(product.category);

    const imageUrl = product.image
        ? '/uploads/products/'
            + encodeURIComponent(product.image)
        : '';

    const imageHtml = imageUrl
        ? `
            <img
                src="${imageUrl}"
                class="card-img-top"
                alt="${productName}"
                style="
                    height: 220px;
                    object-fit: contain;
                "
            >
        `
        : `
            <div
                class="
                    d-flex
                    align-items-center
                    justify-content-center
                    bg-light
                    text-muted
                "
                style="height: 220px;"
            >
                Chưa có ảnh
            </div>
        `;

    return `
        <div class="col-md-6 col-lg-4">

            <div class="card h-100">

                ${imageHtml}

                <div class="card-body">

                    <div class="text-muted small mb-1">
                        ${categoryName}
                    </div>

                    <h2 class="h5">
                        ${productName}
                    </h2>

                    <div class="mb-2">
                        Mã:
                        ${productCode}
                    </div>

                    <div class="fw-bold mb-2">
                        ${formatPrice(product.price)}
                    </div>

                    <div class="mb-3">
                        Tồn kho:
                        ${product.stock}
                    </div>

                    <a
                        href="/product-detail.php?id=${product.id}"
                        class="btn btn-primary"
                    >
                        Xem chi tiết
                    </a>

                </div>

            </div>

        </div>
    `;
}


async function loadCategories() {

    try {

        const response =
            await fetch('/api/categories.php');

        if (!response.ok) {
            throw new Error(
                'Không thể tải danh mục.'
            );
        }

        const result =
            await response.json();

        if (!result.success) {
            throw new Error(
                'Dữ liệu danh mục không hợp lệ.'
            );
        }

        result.data.forEach(category => {

            const option =
                document.createElement('option');

            option.value =
                category.id;

            option.textContent =
                category.name;

            categoryElement.appendChild(option);
        });

    } catch (error) {

        errorElement.textContent =
            error.message;

        errorElement.classList.remove('d-none');
    }
}


async function loadProducts() {

    loadingElement.classList.remove('d-none');

    errorElement.classList.add('d-none');

    productListElement.innerHTML = '';


    const keyword =
        keywordElement.value.trim();

    const category =
        categoryElement.value;


    const params =
        new URLSearchParams();

    if (keyword !== '') {
        params.set('keyword', keyword);
    }

    if (category !== '') {
        params.set('category', category);
    }


    let apiUrl =
        '/api/products.php';

    if (params.toString() !== '') {

        apiUrl +=
            '?' + params.toString();
    }


    try {

        const response =
            await fetch(apiUrl);

        if (!response.ok) {
            throw new Error(
                'Không thể tải dữ liệu sản phẩm.'
            );
        }

        const result =
            await response.json();

        if (!result.success) {
            throw new Error(
                'API trả về kết quả không hợp lệ.'
            );
        }


        if (result.data.length === 0) {

            productListElement.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning">
                        Không tìm thấy sản phẩm phù hợp.
                    </div>
                </div>
            `;

            return;
        }


        productListElement.innerHTML =
            result.data
                .map(createProductCard)
                .join('');

    } catch (error) {

        errorElement.textContent =
            error.message;

        errorElement.classList.remove('d-none');

    } finally {

        loadingElement.classList.add('d-none');
    }
}


filterForm.addEventListener(
    'submit',
    function (event) {

        event.preventDefault();

        loadProducts();
    }
);


async function initializePage() {

    await loadCategories();

    await loadProducts();
}


initializePage();

</script>


<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>
