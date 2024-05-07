<?php
function calculate_total_purchase($current_user_id)
{
    $total_price = 0;
    // Kiểm tra xem người dùng có đăng nhập không
    if ($current_user_id) {
        // Lấy danh sách các đơn hàng đã hoàn thành của người dùng
        $orders = wc_get_orders(
            array(
                'customer' => $current_user_id, // Lọc theo ID của người dùng
                'status' => 'completed' // Chỉ lấy các đơn hàng đã hoàn thành
            )
        );
        // Kiểm tra xem có đơn hàng nào không
        if ($orders) {
            foreach ($orders as $order) {
                $total_price += $order->get_total();
            }
            return $total_price;
        } else {
            return 0;
        }
    } else {
        return 0;
    }

}


function calculate_total_amount_purchase($current_user_id)
{
    // Kiểm tra xem người dùng có đăng nhập không
    if ($current_user_id) {
        // Lấy danh sách các đơn hàng đã hoàn thành của người dùng
        $orders = wc_get_orders(
            array(
                'customer' => $current_user_id, // Lọc theo ID của người dùng
                'status' => 'completed' // Chỉ lấy các đơn hàng đã hoàn thành
            )
        );

        // Đếm số lượng đơn hàng
        $order_count = count($orders);

        // Trả về tổng số lượng đơn hàng
        return $order_count;
    } else {
        return 0;
    }
}


// Hàm lấy số ngày đã đăng ký của người dùng
function get_user_registered_days($user_id)
{
    // Lấy ngày đăng ký của người dùng
    $registration_date = get_user_meta($user_id, 'user_registered', true);
    error_log('registration_date' . $registration_date);
    if (!empty($registration_date)) {
        // Tính toán số ngày từ ngày đăng ký đến ngày hiện tại
        $registration_timestamp = strtotime($registration_date);
        $current_timestamp = current_time('timestamp');
        $days_registered = floor(($current_timestamp - $registration_timestamp) / (60 * 60 * 24));

        return $days_registered;
    } else {
        return 0; // Trả về 0 nếu không có thông tin về ngày đăng ký
    }
}


function get_user_product_purchase_count($product_id, $user_id = null)
{
    if (!$user_id)
        $user_id = get_current_user_id();

    if (!$user_id)
        return 0;

    // Lấy tất cả các đơn hàng đã hoàn thành của người dùng
    $completed_orders = wc_get_orders(
        array(
            'customer' => $user_id,
            'status' => 'completed',
            'limit' => -1,
        )
    );
    $purchase_count = 0;
    // Lặp qua từng đơn hàng
    foreach ($completed_orders as $order) {
        // Kiểm tra xem sản phẩm có tồn tại trong đơn hàng không
        $order_items = $order->get_items();
        foreach ($order_items as $item) {
            if ($item->get_product_id() == $product_id) {
                // Nếu sản phẩm tồn tại trong đơn hàng, tăng biến đếm số lần mua lên 1
                $purchase_count++;
                break;
            }
        }
    }
    return $purchase_count;
}


//lấy số đơn hàng đã mua
function get_user_order_count()
{
    // Kiểm tra nếu người dùng đã đăng nhập
    if (is_user_logged_in()) {
        // Lấy ID của người dùng hiện tại
        $user_id = get_current_user_id();

        // Đếm số lượng đơn hàng của người dùng
        $order_count = count(
            wc_get_orders(
                array(
                    'customer' => $user_id,
                    'status' => array('completed', 'processing') // Chỉ tính các đơn hàng đã hoàn thành hoặc đang xử lý
                )
            )
        );

        return $order_count;
    }

    return 0;
}