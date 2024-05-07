<?php
function send_novu_webhook_on_order_success($order_id)
{
    $order = wc_get_order($order_id);
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
                    'name' => 'test-email',
                    'to' => array(
                        'subscriberId' => $novu_subcriberId, // Thay đổi theo cần thiết
                    ),
                    'payload' => array(
                        '__source' => 'wordpress-order-success',
                        'order_id' => $order_id,
                        'order_total' => $order->get_total(),
                        // Thêm bất kỳ thông tin nào khác từ đơn hàng vào payload nếu cần
                    ),
                )
            ),
            'data_format' => 'body',
        )
    );

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        // Xử lý lỗi ở đây
    } else {
        // Xử lý phản hồi thành công
    }
}
function send_notification_by_novu($user_id, $order_id)
{
    $order = wc_get_order($order_id);
    $novu_api_url = get_field('api_url', 'option');
    $novu_api_key = get_field('api_key', 'option');
    //$novu_subcriberId = get_field('subscriber_id', 'option');
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
                    'name' => 'test-email',
                    'to' => array(
                        'subscriberId' => $user_id, // Thay đổi theo cần thiết
                    ),
                    'payload' => array(
                        '__source' => 'wordpress-order-success',
                        'order_id' => $order_id,
                        'order_total' => $order->get_total(),
                        // Thêm bất kỳ thông tin nào khác từ đơn hàng vào payload nếu cần
                    ),
                )
            ),
            'data_format' => 'body',
        )
    );
}

add_action('user_register', 'create_novu_subscriber_after_register', 10, 1);

function create_novu_subscriber_after_register($user_id)
{
    // Lấy thông tin của người dùng vừa được tạo
    $user_info = get_userdata($user_id);

    // Kiểm tra xem người dùng có tồn tại không
    if ($user_info) {
        // Gọi hàm create_novu_subscriber và truyền thông tin người dùng
        $result = create_novu_subscriber($user_info->ID, $user_info->user_email, $user_info->first_name, $user_info->last_name);
        // Kiểm tra kết quả
        if (is_string($result)) {
            // Ghi log nếu có lỗi
            error_log($result);
        } else {
            // Xử lý phản hồi từ API nếu cần
            // Ví dụ: lưu thông tin người đăng ký vào meta dữ liệu của người dùng
            update_user_meta($user_id, 'novu_subscriber_id', $result->subscriberId);
        }
    }
}
function create_novu_subscriber($user_id, $email, $firstName, $lastName)
{
    $url = 'https://api.novu.co/v1/subscribers';
    $api_key = '2a9988093a133fe6aa7b34f0b1bf86fe'; // Thay thế bằng khóa API thực tế của bạn
    $data = array(
        'subscriberId' => strval($user_id), // Sử dụng ID người dùng làm subscriberId
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $email,
        'phone' => '',
        'avatar' => '',
        'locale' => 'en-US',
        'data' => array(
            'isDeveloper' => true,
            'customKey' => 'customValue'
        )
    );

    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'ApiKey ' . $api_key
        ),
        'body' => json_encode($data),
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return "Something went wrong: $error_message";
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        return $data; // Trả về dữ liệu từ API
    }
}