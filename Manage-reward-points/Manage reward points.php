<?php
/*
Plugin Name: Manage reward points
Description: Manage reward points for WooCommerce Orders
Version: 1.0
Author: Your Name
*/

// Bao gồm tệp wp-load.php của WordPress
require_once (ABSPATH . 'wp-load.php');

// Hàm callback cho trang menu mới
function my_custom_menu_page()
{
    ?>
    <div class="wrap">
        <h1>Lịch sử mua hàng của tất cả người dùng</h1>
        <?php
        // Lấy tất cả các đơn hàng
        $orders = wc_get_orders(
            array(
                'numberposts' => -1, // Lấy tất cả các đơn hàng
            )
        );

        if ($orders) { ?>
            <h2>Danh sách lịch sử mua hàng của tất cả mọi người:</h2>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>ID Đơn hàng</th>
                        <th>Ngày đặt hàng</th>
                        <th>Tổng tiền</th>
                        <th>Khách hàng</th>
                        <th>Điểm của myCred</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order):
                        $order_id = $order->get_id();
                        $order_date = $order->get_date_created()->date('Y-m-d');
                        $order_total = $order->get_formatted_order_total();
                        $customer = $order->get_user();
                        $customer_name = $customer ? $customer->display_name : 'Khách vãng lai';

                        // Lấy điểm của myCred của khách hàng
                        $mycred_points = get_user_meta($customer->ID, 'mycred_default', true);
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url($order->get_view_order_url()); ?>">#
                                    <?php echo $order_id; ?>
                                </a></td>
                            <td>
                                <?php echo $order_date; ?>
                            </td>
                            <td>
                                <?php echo $order_total; ?>
                            </td>
                            <td>
                                <?php echo $customer_name; ?>
                            </td>
                            <td>
                                <?php echo $mycred_points; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php } else { ?>
            <p>Không có đơn hàng nào.</p>
        <?php } ?>
    </div>
    <?php
}




// Hàm để thêm menu vào trang quản trị
function my_custom_menu()
{
    // Thêm một mục menu mới vào trang quản trị
    add_menu_page(
        'Trang Quản trị Tùy chỉnh', // Tiêu đề của menu
        'Trang Tùy chỉnh', // Tên hiển thị trên menu
        'manage_options', // Quyền truy cập cần thiết để xem menu
        'my-custom-menu', // ID duy nhất cho menu
        'my_custom_menu_page', // Hàm callback để hiển thị nội dung của menu
        'dashicons-admin-generic', // URL hoặc class của biểu tượng menu
        99 // Thứ tự xuất hiện của menu trong menu trên trang quản trị (đặt cao hơn để hiển thị ở cuối)
    );
}

// Thêm hook để gọi hàm thêm menu
add_action('admin_menu', 'my_custom_menu');
?>