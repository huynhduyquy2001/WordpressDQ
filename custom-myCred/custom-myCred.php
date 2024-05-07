<?php
/*
Plugin Name: Custom MyCred Plugin
Description: A custom MyCred plugin for additional functionalities.
Version: 1.0
Author: Your Name
*/

/**
 * Hiển thị số điểm của sản phẩm trên trang single product.
 */
function custom_display_product_points()
{
    // Kiểm tra xem MyCred đã được kích hoạt chưa
    if (!function_exists('mycred_get_post_meta')) {
        return;
    }
    // Lấy ID của người dùng hiện tại
    $user_id = get_current_user_id();

    // Lấy số điểm hiện tại của người dùng
    $current_balance = mycred_get_users_cred($user_id);
    error_log(mycred_get_users_cred($user_id));

    // Kiểm tra xem có lỗi xảy ra hay không
    if ($current_balance !== false) {
        // Hiển thị số điểm hiện tại của người dùng
        error_log('Số điểm hiện tại của bạn là: ' . $current_balance);
    } else {
        // Xử lý lỗi
        error_log('Có lỗi xảy ra khi lấy số điểm của bạn.');
    }


    // Lấy ID sản phẩm hiện tại
    global $product;
    $product_id = $product->get_id();

    // Lấy số điểm của sản phẩm
    $product_points = mycred_get_post_meta($product_id, 'point', true);
    error_log('Đã hiện điểm' . $product_points);
    // Kiểm tra nếu sản phẩm có số điểm
    if ($product_points) {
        // Hiển thị số điểm
        echo '<p>Số điểm thưởng: ' . $product_points . '</p>';

    }
}

// Hook để hiển thị số điểm của sản phẩm trên trang single product
add_action('woocommerce_single_product_summary', 'custom_display_product_points', 25);
function add_point($order_id)
{
    // Lấy thông tin đơn hàng từ ID đơn hàng
    $order = wc_get_order($order_id);
    // Lấy ID của người dùng hiện tại
    $user_id = get_current_user_id();

    // Lấy số điểm hiện tại của người dùng
    $current_balance = mycred_get_users_cred($user_id);
    // Kiểm tra xem đơn hàng có tồn tại không
    if (!$order) {
        return;
    }

    // Khởi tạo biến để tính tổng số điểm
    $total_points = 0;

    // Lặp qua từng sản phẩm trong đơn hàng
    foreach ($order->get_items() as $item) {
        // Lấy ID của sản phẩm
        $product_id = $item->get_product_id();

        // Lấy số điểm của sản phẩm (sử dụng MyCred hoặc bất kỳ phương thức nào bạn đã thiết lập)
        $product_points = mycred_get_post_meta($product_id, 'point', true);

        // Kiểm tra xem sản phẩm có điểm không và nếu có thì thêm vào tổng số điểm
        if ($product_points) {
            // Lấy số lượng sản phẩm trong đơn hàng
            $quantity = $item->get_quantity();

            // Tính số điểm của sản phẩm dựa trên số lượng
            $total_points += $product_points * $quantity;
        }
    }

    // Khởi tạo biến tổng số lượng sản phẩm
    $total_quantity = 0;
    // Khởi tạo biến tổng giá trị đơn hàng
    $total_order_price = 0;


    $mycred_rules = get_field('mycred_rules', 'option');
    if (is_array($mycred_rules)) {
        foreach ($mycred_rules as $rule) {
            if ($rule['rule_name'] === 'total_amount') {
                // Lặp qua từng sản phẩm trong đơn hàng
                foreach ($order->get_items() as $item) {
                    // Lấy số lượng sản phẩm
                    $quantity = $item->get_quantity();
                    $total_quantity += $quantity;
                }
                if ($total_quantity >= $rule['rule_value']) {
                    $total_points += $rule['reward_points'];
                }
            } else if ($rule['rule_name'] === 'total_price') {
                // Lặp qua từng sản phẩm trong đơn hàng
                foreach ($order->get_items() as $item) {
                    // Lấy giá của sản phẩm
                    $product = $item->get_product();
                    $price = $product->get_price();

                    // Lấy số lượng sản phẩm
                    $quantity = $item->get_quantity();

                    // Tính tổng giá trị cho sản phẩm
                    $total_product_price = $price * $quantity;

                    // Cộng vào tổng giá trị đơn hàng
                    $total_order_price += $total_product_price;
                }
                if ($total_order_price >= $rule['rule_value']) {
                    $total_points += $rule['reward_points'];
                }
            }
        }

        // Lấy ID người dùng từ đơn hàng
        $user_id = $order->get_user_id();
        // Kiểm tra xem người dùng có ID không (không phải khách hàng vãng lai)
        if ($user_id) {
            // Thêm tổng số điểm vào tài khoản của người dùng
            mycred_add('points', $user_id, $total_points, 'Tích điểm từ đơn hàng #' . $order_id);
            //gửi thông báo
            // Gửi thông báo
            $novu_api_url = get_field('api_url', 'option');
            $novu_api_key = get_field('api_key', 'option');
            $novu_subcriberId = get_field('subscriber_id', 'option');

            $response = wp_remote_post(
                $novu_api_url,
                array(
                    'method' => 'POST',
                    'headers' => array(
                        'Authorization' => 'ApiKey ' . $novu_api_key,
                        'Content-Type' => 'application/json',
                    ),
                    'body' => json_encode(
                        array(
                            'name' => 'points-notification',
                            'to' => array(
                                'subscriberId' => $user_id, // Thay đổi theo cần thiết
                            ),
                            'payload' => array(
                                '__source' => 'wordpress-order-success',
                                'order_id' => $order_id,
                                'order_total' => $order->get_total(),
                                'new_point' => $total_points, // Thêm trường new_point vào payload
                                'total_point' => $total_points + $current_balance, // Thêm trường total_point vào payload
                                // Thêm bất kỳ thông tin nào khác từ đơn hàng vào payload nếu cần
                            ),
                        )
                    ),
                    'data_format' => 'body',
                )
            );

        }
    }
}



// Gắn hàm vào hook 'woocommerce_order_status_completed' để thực hiện khi đơn hàng được hoàn thành
add_action('woocommerce_order_status_complete', 'add_point');

