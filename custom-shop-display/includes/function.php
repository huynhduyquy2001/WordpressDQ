<?php
function custom_product_content_shortcode()
{
    $current_user = wp_get_current_user();
    $display_option = $current_user->get('display');
    $product_content = '';

    if ($display_option === 'table') {
        $product_content = custom_product_table_content();
    } else {
        $product_content = custom_product_list_content();
    }

    return $product_content;
}
function custom_product_table_content()
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => 12 // Số lượng sản phẩm hiển thị
    );
    $products_query = new WP_Query($args);

    $table_html = '<table>';
    $table_html .= '<thead><tr><th>Image</th><th>Product Name</th><th>Sale Price</th><th>Actions</th></tr></thead>';
    $table_html .= '<tbody>';
    while ($products_query->have_posts()) {
        $products_query->the_post();
        $product_name = get_the_title();
        $regular_price = get_post_meta(get_the_ID(), '_regular_price', true); // Giá thường
        $sale_price = get_post_meta(get_the_ID(), '_sale_price', true); // Giá khuyến mãi

        // Xác định giá cần hiển thị
        $display_price = isset($sale_price) && $sale_price !== '' ? $sale_price : $regular_price;

        $product_image = get_the_post_thumbnail(get_the_ID(), 'thumbnail'); // Lấy hình ảnh thu nhỏ của sản phẩm
        $product_permalink = get_permalink(); // Lấy liên kết đến trang chi tiết sản phẩm
        $table_html .= '<tr><td>' . $product_image . '</td><td>' . $product_name . '</td><td>' . $display_price . '</td><td><a href="' . $product_permalink . '">Xem chi tiết</a></td></tr>';
    }
    $table_html .= '</tbody>';
    wp_reset_postdata(); // Đặt lại dữ liệu bài đăng sau vòng lặp
    $table_html .= '</table>';
    return $table_html;
}

function custom_product_list_content()
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => 12 // Number of products to display
    );
    $products_query = new WP_Query($args);

    $list_html = '<div class="product-list row">';
    $count = 0; // Initialize product count
    while ($products_query->have_posts()) {
        $products_query->the_post();
        $product_name = get_the_title();
        $product_image = get_the_post_thumbnail(get_the_ID(), 'thumbnail');
        $product_price = get_post_meta(get_the_ID(), '_regular_price', true);

        // Start the card
        if ($count % 3 === 0) {
            $list_html .= '<div class="row">';
        }
        $list_html .= '<div class="product-card col-4">';
        $list_html .= '<div class="product-thumbnail">' . $product_image . '</div>';
        $list_html .= '<div class="product-details">';
        $list_html .= '<h3 class="product-name">' . $product_name . '</h3>';
        $list_html .= '<p class="product-price">' . $product_price . '</p>';
        $list_html .= '</div>'; // Close product-details
        $list_html .= '</div>'; // Close product-card

        // End the row if it's the third product
        if (($count + 1) % 3 === 0) {
            $list_html .= '</div>'; // Close row
        }

        $count++; // Increment product count
    }
    // Close the row if the last row doesn't have 3 products
    if ($count % 3 !== 0) {
        $list_html .= '</div>'; // Close row
    }

    $list_html .= '</div>'; // Close product-list
    wp_reset_postdata(); // Reset post data after loop
    return $list_html;
}

function filter_products_callback()
{
    $startPrice = isset($_POST['start_price']) ? floatval($_POST['start_price']) : 0;
    $endPrice = isset($_POST['end_price']) ? floatval($_POST['end_price']) : PHP_FLOAT_MAX;
    $productCategory = isset($_POST['product_category']) ? sanitize_text_field($_POST['product_category']) : '';
    $origin = isset($_POST['origin']) ? $_POST['origin'] : array();
    if (is_array($origin)) {
        $origin = array_map('sanitize_text_field', $origin);
    }

    error_log(print_r($origin, true)); // Chuyển đổi mảng thành chuỗi và ghi log

    $size = isset($_POST['size']) ? $_POST['size'] : array();
    if (is_array($size)) {
        $size = array_map('sanitize_text_field', $size);
    }
    error_log(print_r($size, true)); // Chuyển đổi mảng thành chuỗi và ghi log


    $search_keyword = isset($_POST['search_keyword']) ? sanitize_text_field($_POST['search_keyword']) : '';
    // Thực hiện truy vấn để lấy sản phẩm với giá nằm trong khoảng từ $startPrice đến $endPrice và thuộc vào danh mục $productCategory
    // cũng như có nguồn gốc và màu sắc tương ứng
    $products = query_products_by_price_and_category($startPrice, $endPrice, $productCategory, $origin, $size, $search_keyword);

    // Trả về HTML của các sản phẩm tìm được
    echo $products;

    wp_die(); // Kết thúc quá trình xử lý yêu cầu AJAX
}

function query_products_by_price_and_category($startPrice, $endPrice, $productCategory, $origin, $size, $search_keyword)
{
    $meta_query = array(
        'relation' => 'AND',
    );

    // Kiểm tra nếu cả $startPrice và $endPrice không tồn tại hoặc có giá trị mặc định là 0
    if ((!isset($startPrice) || empty($startPrice)) && (!isset($endPrice) || empty($endPrice))) {
        // Không thêm điều kiện giá vào meta_query
    } else {
        // Thêm điều kiện giá vào meta_query chỉ khi $startPrice và $endPrice tồn tại và không rỗng
        $meta_query[] = array(
            'key' => '_regular_price',
            'value' => array($startPrice, $endPrice),
            'type' => 'NUMERIC',
            'compare' => 'BETWEEN'
        );
    }

    $tax_query = array(); // Khởi tạo biến $tax_query
    // Tiếp theo, bạn có thể thêm các phần tử vào mảng $tax_query

    // Nếu có nguồn gốc được chọn, thêm điều kiện cho truy vấn
    if (!empty($origin)) {
        $tax_query[] = array(
            'taxonomy' => 'pa_origin',
            'field' => 'slug',
            'terms' => explode(",", $origin),
            'operator' => 'IN'
        );
        error_log('Đã thêm điều kiện cho truy vấn: ' . $origin);
    }

    // Nếu có màu sắc được chọn, thêm điều kiện cho truy vấn
    if (!empty($size)) {
        $tax_query[] = array(
            'taxonomy' => 'pa_size',
            'field' => 'slug',
            'terms' => explode(",", $size),
            'operator' => 'IN'
        );
    }

    // Thêm điều kiện cho truy vấn tìm kiếm
    $search_query = array();
    if (!empty($search_keyword)) {
        $search_query = array(
            's' => $search_keyword,
        );
    }

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => $meta_query,
        'tax_query' => $tax_query, // Thêm tax_query vào args
        's' => $search_keyword
    );

    // Nếu có danh mục sản phẩm được chọn, thêm điều kiện cho truy vấn
    if (!empty($productCategory)) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => $productCategory,
        );
    }

    error_log('' . json_encode($args));
    $products_query = new WP_Query($args);

    // Đếm số lượng sản phẩm được tìm thấy
    $product_count = $products_query->found_posts;

    // Hiển thị thông tin số lượng sản phẩm phía trên bảng
    $table_html = '<p>Tìm thấy ' . $product_count . ' sản phẩm</p>';

    $table_html .= '<table>';
    $table_html .= '<thead><tr><th>Image</th><th>Product Name</th><th>Sale Price</th><th>Actions</th></tr></thead>';
    $table_html .= '<tbody>';
    while ($products_query->have_posts()) {
        $products_query->the_post();
        $product_name = get_the_title();
        $regular_price = get_post_meta(get_the_ID(), '_regular_price', true);
        $sale_price = get_post_meta(get_the_ID(), '_sale_price', true);

        // Xác định giá cần hiển thị
        $display_price = isset($sale_price) && $sale_price !== '' ? $sale_price : $regular_price;

        $product_image = get_the_post_thumbnail(get_the_ID(), 'thumbnail');
        $product_permalink = get_permalink();
        $table_html .= '<tr><td>' . $product_image . '</td><td>' . $product_name . '</td><td>' . $display_price . '</td><td><a href="' . $product_permalink . '">Xem chi tiết</a></td></tr>';
    }
    $table_html .= '</tbody>';
    $table_html .= '</table>';

    wp_reset_postdata(); // Đặt lại dữ liệu bài đăng sau vòng lặp

    return $table_html;

}
// Hàm để hiển thị danh sách danh mục sản phẩm dưới dạng select option
function display_product_categories()
{
    $args = array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    );

    $product_categories = get_terms($args);

    if (!empty($product_categories)) {
        $output = '<select name="product_category" id="product_category">';
        $output .= '<option value="">Chọn danh mục sản phẩm</option>';
        foreach ($product_categories as $category) {
            $output .= '<option value="' . $category->term_id . '">' . $category->name . '</option>';
        }
        $output .= '</select>';
    } else {
        $output = 'Không có danh mục sản phẩm nào.';
    }

    return $output;
}
function custom_all_product_attributes_checkboxes_shortcode()
{
    // Lấy danh sách tất cả các thuộc tính
    $product_attributes = wc_get_attribute_taxonomies();

    // Kiểm tra xem có thuộc tính nào không
    if (empty($product_attributes)) {
        return 'Không có thuộc tính nào được tìm thấy.';
    }
    // Bắt đầu tạo danh sách checkbox options
    $output = '';
    foreach ($product_attributes as $attribute) {
        // Lấy thông tin thuộc tính
        $attribute_name = $attribute->attribute_label;
        $attribute_slug = $attribute->attribute_name;

        // Lấy các giá trị của thuộc tính
        $attribute_terms = get_terms('pa_' . $attribute_slug);

        // Kiểm tra xem thuộc tính có các giá trị không
        if (!empty($attribute_terms) && !is_wp_error($attribute_terms)) {
            // Bắt đầu checkbox cho thuộc tính
            $output .= '<fieldset>';
            $output .= '<legend>' . esc_html($attribute_name) . '</legend>';

            // Thêm các checkbox
            foreach ($attribute_terms as $term) {
                $output .= '<label><input type="checkbox" name="' . esc_attr($attribute_slug) . '[]" value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</label>&nbsp;&nbsp;&nbsp;';
            }

            $output .= '</fieldset>';
        }
    }
    return $output;
}

function novu_api_key_shortcodes()
{
    $novu_api_key = get_field('api_key', 'option');
    if ($novu_api_key) {
        return $novu_api_key;
    } else {
        return 'Ko có dữ liệu';
    }
}