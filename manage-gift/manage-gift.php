<?php
/*
Plugin Name: Manage gift
Description: This is a simple custom plugin.
Version: 1.0
Author: Your Name
*/

// Thêm mã JavaScript vào phần đầu của file plugin


// Thêm mã PHP để xử lý AJAX request và tạo yêu cầu đổi quà
add_action('wp_ajax_redeem_gift_request', 'redeem_gift_request');
add_action('wp_ajax_nopriv_redeem_gift_request', 'redeem_gift_request');

function redeem_gift_request()
{
    // Lấy thông tin người dùng hiện tại
    $user = wp_get_current_user();
    // Lấy ID của người dùng hiện tại
    $user_id = $user->ID;
    // Lấy số điểm hiện tại của người dùng
    $current_balance = get_user_meta($user_id, 'coins', true);

    // Kiểm tra xem có các trường dữ liệu được gửi từ mã JavaScript không
    if (!empty($_POST['product_id']) && !empty($_POST['product_price']) && !empty($_POST['user_id'])) {
        $product_id = $_POST['product_id'];
        $product_price = $_POST['product_price'];
        $user_id = $_POST['user_id'];

        // Lấy số điểm của sản phẩm
        $product_points = get_post_meta($product_id, 'point', true);

        // Kiểm tra xem người dùng có đủ điểm để đổi quà không
        if ($current_balance >= $product_points) {
            // Trừ điểm từ tài khoản của người dùng
            $new_balance = $current_balance - $product_points;
            update_user_meta($user_id, 'coins', $new_balance);

            // Tạo bài viết yêu cầu đổi quà
            $user_name = get_userdata($user_id)->display_name;
            $product_title = get_the_title($product_id);
            $post_title = $user_name . ' đã gửi yêu cầu đổi quà ' . $product_title;
            $post_content = ''; // Nội dung của post, bạn có thể thay đổi nếu cần
            $post_type = 'gift-post-type'; // Đặt post type của yêu cầu là 'gift'
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
                update_post_meta($post_id, 'product_id', $product_id);
                update_post_meta($post_id, 'product_price', $product_price);
                update_post_meta($post_id, 'user_id', $user_id);
                update_post_meta($post_id, 'gift_status', 'Pending');
                echo 'success';
            } else {
                echo 'error';
            }
        } else {
            echo 'insufficient_points'; // Trả về mã lỗi nếu điểm không đủ
        }
    } else {
        echo 'missing_data'; // Trả về mã lỗi nếu dữ liệu bị thiếu
    }

    wp_die();
}

// Tạo shortcode để hiển thị sản phẩm có thể đổi quà
function display_gift_products()
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => 'Gift', // Đặt categories là 'gift'
            ),
        ),
    );

    $query = new WP_Query($args);

    ob_start();
    $user_id = get_current_user_id();
    $current_point = get_user_meta($user_id, 'coins', true);
    if ($query->have_posts()) {
        echo '<div>Số điểm thưởng hiện tại của bạn là:' . '<span id="current_point">' . $current_point . '</span>' . '</div>';
        echo '<table>';
        echo '<thead><tr><th>Ảnh</th><th>Tên sản phẩm</th><th>Điểm đổi</th><th>Đổi quà</th></tr></thead>';
        echo '<tbody>';
        while ($query->have_posts()) {
            $query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            $thumbnail = get_the_post_thumbnail($product_id, 'thumbnail');
            $title = get_the_title();
            $mycred_points = get_post_meta($product_id, 'point', true); // Thay 'mycred_reward_points' bằng key của meta data điểm myCred nếu khác
            echo '<tr>';
            echo '<td>' . $thumbnail . '</td>';
            echo '<td><a href="' . get_permalink() . '">' . $title . '</a></td>';
            echo '<td>' . $mycred_points . '</td>';
            echo '<td><button class="redeem-gift" 
            data-product-id="' . $product_id . '" 
            data-product-price="' . $mycred_points . '"
            data-user-id="' . get_current_user_id() . '">
            Đổi quà
    </button></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        wp_reset_postdata();
    } else {
        echo 'Không có sản phẩm nào.';
    }

    return ob_get_clean();
}
add_shortcode('gift_products', 'display_gift_products');

add_action('acfe/fields/button/name=reject', 'my_acf_button_ajax_reject', 10, 2);
function my_acf_button_ajax_reject($field, $post_id)
{

    // Cập nhật trạng thái của bài viết sang 'rejected'
    $updated = update_field('gift_status', 'rejected', $post_id);

    // Lấy thông tin người dùng từ bài viết
    $user_id = get_field('user_id', $post_id);

    $user_points = get_user_meta($user_id, 'coins', true); // Lấy điểm của người dùng từ trường meta

    if ($user_id) {
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
                        'name' => 'gift-notification',
                        'to' => array(
                            'subscriberId' => $novu_subcriberId,
                        ),
                        'payload' => array(
                            '__source' => 'wordpress-order-success',
                            'header' => 'Yêu cầu đổi quà của bạn đã bị từ chối',
                            'total_point' => $user_points,
                        ),
                    )
                ),
                'data_format' => 'body',
            )
        );
        wp_send_json_success("Success!");
        // Kết thúc quá trình xử lý ajax và tải lại trang
        wp_die();
    }
}


add_action('acfe/fields/button/name=accept', 'my_acf_button_ajax', 10, 2);
function my_acf_button_ajax($field, $post_id)
{
    // Cập nhật trạng thái của bài viết sang 'accepted'
    $updated = update_field('gift_status', 'accepted', $post_id);

    // Lấy thông tin người dùng từ bài viết
    $user_id = get_field('user_id', $post_id);

    $user_points = get_user_meta($user_id, 'coins', true); // Lấy điểm của người dùng từ trường meta

    // Lấy giá sản phẩm từ bài viết
    $product_price = get_field('product_price', $post_id); // Lấy giá sản phẩm từ trường ACF

    if ($user_points && $product_price) {
        // Tính toán điểm mới của người dùng
        $new_points = $user_points - $product_price;
        // Cập nhật điểm của người dùng
        update_user_meta($user_id, 'coins', $new_points);
        // Gửi JSON success message

        // Ghi vào log của loại tiền coins
        mycred_add(
            'Gift Payment', // Mô tả của giao dịch
            $user_id, // ID của người dùng
            -$product_price, // Số lượng coins thêm hoặc trừ (lưu ý sử dụng số âm để trừ)
            'Gift Payment', // Mô tả giao dịch (có thể là mã ghi nhớ hoặc mô tả khác)
            null, // Không cần thiết lập
            'coins', // Loại tiền cần ghi vào log
            'coins'
        );

        if ($user_id) {
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
                            'name' => 'gift-notification',
                            'to' => array(
                                'subscriberId' => $user_id, // Thay đổi theo cần thiết
                            ),
                            'payload' => array(
                                '__source' => 'wordpress-order-success',
                                'header' => 'Yêu cầu đổi quà của bạn đã được chấp nhận', // Thêm trường new_point vào payload
                                'total_point' => $new_points, // Thêm trường total_point vào payload
                                // Thêm bất kỳ thông tin nào khác từ đơn hàng vào payload nếu cần
                            ),
                        )
                    ),
                    'data_format' => 'body',
                )
            );
        }
        wp_send_json_success("Success!");
    }
}
add_action('admin_footer', 'custom_acf_admin_footer');
function custom_acf_admin_footer()
{
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            var productPrice = $('#acf-field_66176aa4f963e').val();
            // Kiểm tra xem productPrice có giá trị là "accepted" hay không
            if (productPrice.trim() === 'accepted' || productPrice.trim() === 'rejected') {
                // Thực hiện các hành động tương ứng nếu giá trị là "accept"
                // Ví dụ: Tắt chức năng click của button "accept"
                $('#accept').prop('disabled', true);
                $('#reject').prop('disabled', true);
            }
        });
    </script>
    <?php
}



add_action('acf/input/admin_enqueue_scripts', 'my_acf_admin_enqueue_scripts');
function my_acf_admin_enqueue_scripts()
{
    add_action('admin_print_scripts', 'my_acf_admin_print_scripts');
    function my_acf_admin_print_scripts()
    {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                acf.addAction('acfe/fields/button/success/name=accept', function (response, $el, data) {
                    location.reload();

                });
                acf.addAction('acfe/fields/button/success/name=reject', function (response, $el, data) {
                    location.reload();

                });
            });

        </script>
        <?php
    }
}







