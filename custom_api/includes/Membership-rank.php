<?php
// Thêm một hàm vào hook
add_action('woocommerce_order_status_completed', 'check_next_rank', 10, 1);

function check_next_rank($order_id)
{

    // Lấy thông tin đơn hàng từ ID đơn hàng
    $order = wc_get_order($order_id);

    // Kiểm tra xem đơn hàng có tồn tại không
    if (!$order) {
        return;
    }

    // Lấy UserID từ đơn hàng
    $user_id = $order->get_user_id();

    // Kiểm tra xem UserID có tồn tại không
    if (!$user_id) {
        return;
    }

    $members = get_field('member', 'option');
    $rank = get_field('rank', 'user_' . $user_id);
    $check = false;
    send_notification_by_novu($user_id, $order_id);
    foreach ($members as $member) {
        if ($member['id'] == $rank + 1) {
            $rules = $member['rules'];
            foreach ($rules as $rule) {
                if ($rule['rule_name'] === 'total_price') {

                    $rule_value = $rule['rule_value'];

                    // Lấy tổng giá trị của đơn hàng
                    $order_total = calculate_total_purchase($user_id); // Lấy tổng giá trị của đơn hàng

                    error_log('rule_value' . $rule_value);
                    // So sánh tổng giá trị của đơn hàng với giá trị của quy tắc
                    if ($order_total > $rule_value) {
                        //Viết hàm
                        $check = true;

                    } else {
                        $check = false;
                        break;
                    }
                } else if ($rule['rule_name'] === 'total_amount_purchased') {
                    $rule_value = $rule['rule_value'];
                    $total_purchase = calculate_total_amount_purchase($user_id);
                    if ($total_purchase > $rule_value) {
                        $check = true;
                    } else {
                        $check = false;
                        break;
                    }
                }
            }
            if ($check == true) {
                update_field('rank', $member['id'], 'user_' . $user_id);
                send_notification_by_novu($user_id, $order_id);
            }
        }


    }
}