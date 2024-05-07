<?php
function register_settings_endpoint()
{
    register_rest_route(
        'custom/v1',
        '/settings',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_settings',
            'permission_callback' => 'check_user_role_admin',
        )
    );
    register_rest_route(
        'custom/v1',
        '/membership-ranks',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_membership_ranks',
            'permission_callback' => 'check_user_role_admin',
        )
    );
    register_rest_route(
        'custom/v1',
        '/loyalty-points/(?P<ctype>\w+)',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_loyalty_points',
            'permission_callback' => 'check_user_role_admin',
        )
    );
}

function custom_api_get_settings($request)
{
    // Lấy tất cả các cài đặt
    $all_options = wp_load_alloptions();

    // Tạo một mảng chứa tất cả các cài đặt
    $settings = array();


    foreach ($all_options as $option_name => $option_value) {
        // Thêm tất cả cài đặt vào mảng settings
        $settings[$option_name] = $option_value;
    }

    return rest_ensure_response($settings);
}
function custom_api_get_membership_ranks($request)
{
    // Kiểm tra xem plugin ACF có được kích hoạt không
    if (function_exists('get_field')) {
        // Lấy danh sách các hạng thành viên từ nhóm trường "Member ranks settings field" bằng cách sử dụng ACF
        $membership_ranks = get_field('member', 'option');

        // Kiểm tra xem có dữ liệu được trả về từ nhóm trường không
        if ($membership_ranks) {
            // Trả về danh sách các hạng thành viên nếu có dữ liệu
            return rest_ensure_response($membership_ranks);
        } else {
            // Trả về một thông báo lỗi nếu không tìm thấy dữ liệu từ nhóm trường
            return new WP_Error('no_membership_ranks', 'No membership ranks found', array('status' => 404));
        }
    } else {
        // Trả về một thông báo lỗi nếu plugin ACF không được kích hoạt
        return new WP_Error('acf_not_activated', 'ACF plugin is not activated', array('status' => 400));
    }
}
function custom_api_get_loyalty_points($request)
{
    // Kiểm tra xem plugin MyCred có được kích hoạt không
    if (function_exists('mycred_get_post_meta')) {
        // Lấy ID của người dùng hiện tại
        $user_id = get_current_user_id();
        $ctype = $request['ctype'];
        // Kiểm tra giá trị của ctype, nếu là coins thì hiển thị lịch sử giao dịch của loại tiền coins
        if ($ctype == 'coins') {
            $args = [
                'number' => -1,
                'ctype' => 'coins'
            ];
        } else if ($ctype == 'points') {
            // Nếu không, hiển thị lịch sử giao dịch của 'mycred_default'
            $args = [
                'number' => -1,
                'ctype' => 'mycred_default'
            ];
        }
        $log = new myCRED_Query_Log($args);
        $results = $log->results;
        // Định dạng lại thời gian và thay thế user_id bằng tên người dùng
        foreach ($results as &$transaction) {
            // Định dạng lại thời gian
            $transaction->time = date('Y-m-d H:i:s', $transaction->time);

            // Lấy thông tin người dùng từ user_id và thay thế bằng tên người dùng
            $user_info = get_userdata($transaction->user_id);
            if ($user_info) {
                $transaction->user_name = $user_info->display_name;
            } else {
                // Nếu không tìm thấy thông tin người dùng, giữ nguyên user_id
                $transaction->user_id = 'Unknown User';
            }
        }
        return rest_ensure_response($results);

    } else {
        // Trả về một thông báo lỗi nếu plugin MyCred không được kích hoạt
        return new WP_Error('mycred_not_activated', 'MyCred plugin is not activated', array('status' => 400));
    }
}

function custom_api_get_user_preferences($request)
{
    // Kiểm tra xem MyCred đã được kích hoạt chưa
    if (function_exists('mycred')) {
        // Lấy ID người dùng từ yêu cầu API
        $user_id = $request['id'];
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Người dùng không tồn tại'), 404);
        }
        if ($user_id) {
            $current_total_price = calculate_total_purchase($user_id);
            $current_total_amount = calculate_total_amount_purchase($user_id);

            $coins = intval(get_user_meta($user_id, 'coins', true));
            $points = intval(get_user_meta($user_id, 'mycred_default', true));
            $rank = intval(get_user_meta($user_id, 'rank', true));

            $nextRank = '';
            $temp = $rank;
            $members = get_field('member', 'option');
            if ($rank == 1) {
                $rank = 'Đồng';
                $nextRank = 'Bạc';
            } else if ($rank == 2) {
                $rank = 'Bạc';
                $nextRank = 'Vàng';
            } else if ($rank == 3) {
                $rank = 'Vàng';
                $nextRank = 'Kim Cương';
            }

            // Dữ liệu để trả về dưới dạng JSON
            $data = array(
                'user_id' => $user_id,
                'coins' => $coins,
                'points' => $points,
                'current_rank' => $rank,
                'total_price' => $current_total_price,
                'total_amount' => $current_total_amount,
                'next_rank' => $nextRank
            );
            // Xử lý thông tin về tiến độ nâng cấp
            foreach ($members as $member) {
                if ($member['id'] == $temp + 1) {
                    $rules = $member['rules'];
                    foreach ($rules as $rule) {
                        if ($rule['rule_name'] === 'total_price') {
                            $rule_value = $rule['rule_value'];
                            // Lấy tổng giá trị của đơn hàng
                            $order_total = calculate_total_purchase($user_id);

                            if ($order_total < $rule_value) {
                                // Tính toán phần trăm
                                $process = $order_total * 100 / $rule_value;
                                $data['progress_total_price'] = $process . '%';
                                $data['rule_total_price'] = $rule_value; // Bổ sung thông tin về mức chi phí cần đạt
                            }
                        } else if ($rule['rule_name'] === 'total_amount_purchased') {
                            $rule_value = $rule['rule_value'];
                            $total_purchase = calculate_total_amount_purchase($user_id);
                            if ($total_purchase < $rule_value) {
                                // Tính toán phần trăm
                                $process = $total_purchase * 100 / $rule_value;
                                $data['progress_total_amount'] = $process . '%';
                                $data['rule_total_amount'] = $rule_value; // Bổ sung thông tin về mức số lượng đơn hàng cần đạt
                            }
                        }
                    }
                }
            }
            // Chuyển đổi đối tượng PHP thành JSON và trả về
            return rest_ensure_response($data);

        } else {
            return "Không tìm thấy người dùng.";
        }
    } else {
        return "MyCred chưa được cài đặt hoặc kích hoạt.";
    }
}