<?php

function custom_products_api_endpoint()
{
    register_rest_route(
        'custom/v1',
        '/get-products',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_products',
            //'permission_callback' => 'custom_verify_jwt_token',
        )
    );
}
function custom_api_get_products($request)
{
    // Kiểm tra xem WooCommerce đã được kích hoạt hay chưa
    if (!function_exists('WC')) {
        return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'text-domain'), array('status' => 400));
    }

    // Thiết lập các tham số cho truy vấn
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1, // Lấy tất cả sản phẩm
    );

    // Lấy danh sách sản phẩm
    $products = get_posts($args);

    // Kiểm tra xem có sản phẩm nào không
    if (empty($products)) {
        return new WP_Error('no_products_found', __('No products found.', 'text-domain'), array('status' => 404));
    }

    // Chuẩn bị dữ liệu để trả về
    $data = array();

    foreach ($products as $product) {
        $product_data = wc_get_product($product->ID);
        // Lấy URL ảnh sản phẩm
        $image_id = $product_data->get_image_id();
        $image_url = wp_get_attachment_url($image_id);
        // Thêm thông tin sản phẩm vào mảng dữ liệu
        $data[] = array(
            'id' => $product->ID,
            'name' => $product_data->get_name(),
            'price' => $product_data->get_price(),
            'description' => $product_data->get_description(),
            'image' => $image_url
            // Thêm các trường dữ liệu khác cần thiết tại đây
        );
    }

    // Trả về dữ liệu dưới dạng JSON
    return rest_ensure_response($data);
}
