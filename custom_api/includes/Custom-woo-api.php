<?php
add_filter('woocommerce_rest_pre_insert_shop_order_object', function ($order, $request, $creating) {
    // Lấy token từ header Authorization
    $jwt_token = $request->get_header('Authorization');

    // Bỏ tiền tố "Bearer" ra khỏi chuỗi token
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    // Thay YOUR_SECRET_KEY_HERE bằng khóa bí mật thực tế của bạn
    $secret_key = 'your-top-secrect-key';

    // Gọi hàm decode_jwt để giải mã token
    $decoded_data = decode_jwt($jwt_token, $secret_key);
    $user_id = $decoded_data['data']['user']['id'];

    if ($user_id) {
        $order->set_customer_id($user_id);
    }
    return $order;
}, 10, 3);

add_filter('woocommerce_rest_pre_insert_product_review', function ($review, $request, $creating) {
    // Lấy token từ header Authorization
    $jwt_token = $request->get_header('Authorization');

    // Bỏ tiền tố "Bearer" ra khỏi chuỗi token
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    // Thay YOUR_SECRET_KEY_HERE bằng khóa bí mật thực tế của bạn
    $secret_key = 'your-top-secrect-key';

    // Gọi hàm decode_jwt để giải mã token
    $decoded_data = decode_jwt($jwt_token, $secret_key);

    $user_id = $decoded_data['data']['user']['id'];
    // Thiết lập reviewer và reviewer_email dựa vào user_id
    $user = get_user_by('id', $user_id);
    if ($user) {
        $review->reviewer = $user->display_name;
        $review->reviewer_email = $user->user_email;
    }

    return $review;
}, 10, 3);

