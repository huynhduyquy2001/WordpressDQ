<?php
/**
 * Plugin Name: My Custom Payment Gateway
 * Plugin URI: http://example.com/
 * Description: Thêm cổng thanh toán tùy chỉnh vào WooCommerce.
 * Version: 1.0
 * Author: Tên của bạn
 * Author URI: http://example.com/
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Thêm cổng thanh toán vào danh sách các cổng thanh toán có sẵn trong WooCommerce
add_filter( 'woocommerce_payment_gateways', 'add_custom_payment_gateway' );
function add_custom_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Custom_Payment_Gateway'; // Tên lớp của cổng thanh toán tùy chỉnh
    return $gateways;
}

// Định nghĩa lớp cổng thanh toán tùy chỉnh
add_action( 'plugins_loaded', 'init_custom_payment_gateway' );
function init_custom_payment_gateway(){
    class WC_Custom_Payment_Gateway extends WC_Payment_Gateway {
        // Constructor, init_form_fields và process_payment là các phương thức quan trọng cần được định nghĩa
        public function __construct() {
            $this->id                 = 'custom_gateway';
            $this->method_title       = __( 'Custom Gateway', 'woocommerce' );
            $this->method_description = __( 'Mô tả của cổng thanh toán tùy chỉnh.', 'woocommerce' );

            // Khởi tạo các trường cài đặt
            $this->init_form_fields();

            // Tải các thiết lập
            $this->init_settings();
            $this->title = $this->get_option( 'title' );

            // Lưu cài đặt
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Kích hoạt/ Vô hiệu hóa', 'woocommerce' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Kích hoạt Cổng Thanh Toán', 'woocommerce' ),
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => __( 'Tiêu đề', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'Đây là tiêu đề mà người dùng thấy trong quá trình thanh toán.', 'woocommerce' ),
                    'default'     => __( 'Thanh Toán Tùy Chỉnh', 'woocommerce' ),
                    'desc_tip'    => true,
                )
                // Thêm các trường khác nếu cần
            );
        }

        public function process_payment( $order_id ) {
            // Xử lý thanh toán ở đây
            // Bạn cần trả về một mảng với 'result' => 'success' và 'redirect' => URL thanh toán (nếu có)
        }
    }
}

add_action('woocommerce_payment_complete', 'custom_action_after_payment_complete');
function custom_action_after_payment_complete( $order_id ) {
    $order = wc_get_order( $order_id );
    $customer_email = $order->get_billing_email();
    
    // Thực hiện hành động, như gửi email thông báo
    wp_mail( $customer_email, 'Thanh toán thành công', 'Cảm ơn bạn đã mua hàng.' );
}
