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