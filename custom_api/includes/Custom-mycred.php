<?php
function add_initial_coins($user_id)
{
    $initial_points = 50000;  // Số coins ban đầu bạn muốn cộng

    // Cập nhật điểm cho người dùng mới
    update_user_meta($user_id, 'coins', $initial_points);
    update_user_meta($user_id, 'rank', 'Unranked');

    // Tùy chọn: Ghi log để kiểm tra
    error_log("Added initial 50000 coins to user #{$user_id}");
}

// Gắn hàm vào hook user_register
add_action('user_register', 'add_initial_coins', 10, 1);

function save_data_to_cookie()
{
    // Lấy ID của người dùng hiện tại
    $current_user_id = get_current_user_id();

    // Kiểm tra nếu có tham số 'ref' trong URL
    if (isset($_GET['ref']) && !isset($_COOKIE['check_ref_cookie'])) {
        $referrer_id = intval($_GET['ref']);
        setcookie('ref_cookie_name', $referrer_id, time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
    }

    // Tạo URL chia sẻ với ref=user_id của người dùng hiện tại
    $share_url = add_query_arg('ref', $current_user_id, home_url('/'));

    // In ra liên kết chia sẻ
    echo '<a href="' . esc_url($share_url) . '">Chia sẻ trang web</a>';
}

add_action('user_register', 'after_user_register');

function after_user_register($user_id)
{
    $cookieValue = $_COOKIE['ref_cookie_name'];
    // Kiểm tra xem người dùng hiện tại có trong session hay không và cookie 'check_ref_cookie' chưa được thiết lập
    if (isset($user_id) && isset($cookieValue)) {
        $current_coins = (int) get_user_meta($cookieValue, 'coins', true);
        // Cập nhật số điểm coins cho người dùng mới đăng ký
        update_user_meta($cookieValue, 'coins', $current_coins + 10000);
        // Ghi vào log của loại tiền coins
        mycred_add(
            'Referrers', // Mô tả của giao dịch
            $cookieValue, // ID của người dùng
            10000, // Số lượng coins thêm hoặc trừ (lưu ý sử dụng số âm để trừ)
            'Bonus points for referrers: ' . $user_id, // Mô tả giao dịch (có thể là mã ghi nhớ hoặc mô tả khác)
            null, // Không cần thiết lập
            'coins', // Loại tiền cần ghi vào log
            'coins'
        );
        setcookie('check_ref_cookie', 1, time() + 3600, COOKIEPATH, COOKIE_DOMAIN);

        // Tạo bài viết yêu cầu đổi quà
        $user_name = get_userdata($user_id)->display_name;
        $user_name_ref = get_userdata($cookieValue)->display_name;
        $post_title = $user_name_ref . ' đã tăng 10000 coins giới thiệu trang web cho ' . $user_name;
        $post_content = ''; // Nội dung của post, bạn có thể thay đổi nếu cần
        $post_type = 'referrer-post-type'; // Đặt post type của yêu cầu là 'gift'
        $post_status = 'publish'; // Đặt trạng thái của post là 'publish'

        $post_args = array(
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_type' => $post_type,
            'post_status' => $post_status
        );

        $post_id = wp_insert_post($post_args);

        // Cập nhật các trường dữ liệu trong bài viết mới
        if ($post_id) {
            update_post_meta($post_id, 'ref_id', $cookieValue);
            update_post_meta($post_id, 'receiver_id', $user_id);
            echo 'success';
        } else {
            echo 'error';
        }
        wp_die();
    }
}
add_action('template_redirect', 'save_data_to_cookie');

add_shortcode('mycred_balance', 'mycred_balance_shortcode');

// Hàm callback cho shortcode
function mycred_balance_shortcode($atts)
{
    echo '<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">';

    // Lấy ID của người dùng hiện tại
    $user_id = get_current_user_id();
    error_log('user_id: ' . $user_id);
    // Kiểm tra xem người dùng có tồn tại hay không
    if ($user_id) {
        $current_total_price = calculate_total_purchase($user_id);
        $current_total_amount = calculate_total_amount_purchase($user_id);

        $coins = get_user_meta($user_id, 'coins', true);
        $points = get_user_meta($user_id, 'mycred_default', true);

        $rank = get_user_meta($user_id, 'rank', true);
        $nextRank = '';
        $temp = $rank;
        $members = get_field('member', 'option');
        if ($rank == 1) {
            $rank = 'Unranked';
            $nextRank = 'Đồng';
        } else if ($rank == 2) {
            $rank = 'Đồng';
            $nextRank = 'Bạc';
        } else if ($rank == 3) {
            $rank = 'Bạc';
            $nextRank = 'Vàng';
        } else if ($rank == 4) {
            $rank = 'Vàng';
            $nextRank = 'Kim cương';
        } else if ($rank == 5) {
            $rank = 'Kim cương';
            $nextRank = 'Not found';
        }

        ?>
        <div>
            <p><b>Số dư đồng Points hiện tại:</b> <?php echo $points; ?></p>
            <p><b>Số dư đồng Coins hiện tại:</b> <?php echo $coins; ?></p>
            <p><b>Cấp độ người dùng hiện tại:</b> <?php echo $rank; ?></p>

            <p><b>Tổng tiền đã sử dụng:</b> <?php echo $current_total_price; ?> VNĐ</p>
            <p><b>Số đơn hàng đã mua:</b> <?php echo $current_total_amount; ?></p>
            <p><b style="color: red;">
                    <b>Cấp độ tiếp theo:</b> <?php echo $nextRank; ?>, điều kiện nâng cấp:
                </b></p>
        </div>
        <?php
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
                            ?>
                            <p><b>Mức chi phí cần đạt:</b> <?php echo $rule_value; ?></p>

                            <div class="w3-light-grey">
                                <div class="w3-container w3-green w3-center" style="width:<?php echo $process; ?>%"> <?php echo $process; ?>%</div>
                            </div><br>
                            <?php
                            // Thoát khỏi vòng lặp sau khi tìm thấy điều kiện nâng cấp
                        }

                    } else if ($rule['rule_name'] === 'total_amount_purchased') {
                        $rule_value = $rule['rule_value'];
                        $total_purchase = calculate_total_amount_purchase($user_id);
                        if ($total_purchase < $rule_value) {
                            // Viết hàm xử lý trong trường hợp này
                            $process = $total_purchase * 100 / $rule_value;
                            ?>
                                <p><b>Số lượng đơn hàng cần đạt:</b> <?php echo $rule_value; ?></p>

                                <div class="w3-light-grey">
                                    <div class="w3-container w3-red w3-center" style="width:<?php echo $process; ?>%"> <?php echo $process; ?>%</div>
                                </div><br>
                            <?php
                        }
                    }
                }
            }
        }
    }
}