<?php
/*
Plugin Name: Custom API Plugin
Description: A custom API plugin for Test Api.
Version: 1.0
Author: Your Name
*/

if (!class_exists('CustomApi')) {
    class CustomApi
    {
        function __construct()
        {
            $this->setup_constants();
            $this->includes();
            $this->init_hooks();
            $this->check_requirements();
        }

        private function setup_constants()
        {
            // Define plugin constants here
        }

        private function includes()
        {
            // Include other plugin files here
            $this->include_file('Gift-post-type.php');
            $this->include_file('Auth.php');
            $this->include_file('User.php');
            $this->include_file('Add-to-cart.php');
            $this->include_file('Custom-woo-api.php');
            $this->include_file('Product-api.php');
            $this->include_file('novu.php');
            $this->include_file('Membership-rank.php');
            $this->include_file('Discount-rules.php');
            $this->include_file('Custom-mycred.php');
            $this->include_file('discount-api.php');
        }

        private function include_file($file)
        {
            // Include a specific plugin file
            include_once (plugin_dir_path(__FILE__) . 'includes/' . $file);
        }

        private function init_hooks()
        {
            add_action('rest_api_init', 'custom_api_register_custom_post_type_endpoint');
            add_action('rest_api_init', 'register_settings_endpoint');
            add_action('rest_api_init', 'register_api_logger');
            add_action('rest_api_init', 'custom_cart_api_endpoint');
            add_action('rest_api_init', 'custom_products_api_endpoint');
            add_filter('jwt_auth_token_before_sign', 'add_roles_to_jwt_token', 10, 2);
            add_action('woocommerce_checkout_order_processed', 'send_novu_webhook_on_order_success');
            add_action('woocommerce_cart_calculate_fees', 'apply_discount_based_on_rules');
            add_filter('woocommerce_rest_check_permissions', 'filter_woocommerce_rest_check_permissions', 10, 4);
        }

        private function check_requirements()
        {
            // Check for necessary plugin requirements or dependencies
        }

        public function on_plugins_loaded()
        {
            // Setup plugin once all other plugins are loaded
        }
    }

}

// Function to extend affiliate_wp
function custom_api_load()
{
    global $custom_api_instance;
    // Setup CustomApi instance
    $custom_api_instance = new CustomApi();
    // Setup new type for referrals
    //affiliate_wp()->referrals->types_registry->register_type('peer', array('label' => __('Peer', 'affiliate-wp')));
}
// Add action to load affiliate_wp_extend_load function when plugins are loaded
add_action('plugins_loaded', 'custom_api_load');



// Đăng ký một custom REST API endpoint
// add_action('rest_api_init', function () {
//     register_rest_route(
//         'custom/v1',
//         '/share-facebook',
//         array (
//             'methods' => 'POST',
//             'callback' => 'share_to_facebook',
//         )
//     );
// });



// add_action('rest_api_init', function () {
//     register_rest_route(
//         'custom/v1',
//         '/shipping',
//         array (
//             'methods' => 'POST',
//             'callback' => 'custom_ship_depot_get_shipping',
//         )
//     );
// });
// Hàm callback để xử lý yêu cầu API
// function share_to_facebook($request)
// {
//     $message = 'Nội dung bài viết bạn muốn chia sẻ';
//     $link = 'https://woocommerce.dev-tn.com/san-pham/card-man-hinh-asus-dual-rtx-2060-6gb-gddr6-6g-evo';
//     $access_token = 'EAAZAsEqMspuQBO1l8ZAIjz0j48XIdaqdsk5nZAcWVcnvZCNSUM0pZBCEcbiQ2b0ZB4lq1ch1uByGvM9kvNauGS0IXjZABZAW3WvGCFUchBv58X58zWU3ko0YZBIQFRyPajgYZCo3gCnDhHCEl9Vtgqe4V45jEzJ2pBPpfZCjA2slGjThwOBLoSBwrUjOKqeuKk1yEEMKDGMzLeS5bZBABudIdPx8LkxGLoJZAsqaWx6FjNnidNhecjX1JSA1j';


//     // Gửi yêu cầu API đến Facebook
//     $facebook_api_url = 'https://graph.facebook.com/me/feed';
//     $args = array(
//         'message' => $message,
//         'link' => $link,
//         'access_token' => $access_token,
//     );

//     $response = wp_remote_post(
//         $facebook_api_url,
//         array(
//             'method' => 'POST',
//             'body' => $args,
//         )
//     );

//     // Kiểm tra phản hồi từ Facebook và trả về kết quả
//     if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
//         return new WP_REST_Response(array('success' => true, 'message' => 'Bài viết đã được chia sẻ thành công lên Facebook.'), 200);
//     } else {
//         return new WP_Error('share_failed', 'Có lỗi xảy ra khi chia sẻ bài viết lên Facebook.', array('status' => 500));
//     }
// }
// function custom_ship_depot_get_shipping($request)
// {
//     $user_cart_key = 'custom_cart_items_1'; // Định danh transient

//     // Lấy thông tin giỏ hàng từ transient
//     $list_cart_items = get_transient($user_cart_key);

//     $list_packages_sizes = []; // Khởi tạo mảng trước vòng lặp
//     $list_items = []; // Khởi tạo mảng trước vòng lặp
//     $total_qty = 0; // Khởi tạo biến trước vòng lặp
//     $item_regular_price_total = 0; // Khởi tạo biến trước vòng lặp

//     if (!empty($list_cart_items)) {
//         foreach ($list_cart_items as $item) {
//             $total_qty += floatval($item['quantity']);
//             $product_id = $item['product_id'];

//             // Tìm sản phẩm dựa trên product_id
//             $product = wc_get_product($product_id);

//             // Kiểm tra xem sản phẩm có tồn tại không trước khi thêm vào danh sách
//             if ($product) {
//                 $product_data = $product->get_data();
//                 $package_size = new Ship_Depot_Package();
//                 $package_size->Length = isset($product_data['length']) ? Ship_Depot_Helper::ConvertToShipDepotDimension($product_data['length']) : 0;
//                 $package_size->Width = isset($product_data['width']) ? Ship_Depot_Helper::ConvertToShipDepotDimension($product_data['width']) : 0;
//                 $package_size->Height = isset($product_data['height']) ? Ship_Depot_Helper::ConvertToShipDepotDimension($product_data['height']) : 0;
//                 $package_size->Weight = isset($product_data['weight']) ? Ship_Depot_Helper::ConvertToShipDepotWeight($product_data['weight']) : 0;

//                 $it = new Ship_Depot_Item();
//                 $it->Sku = $product_data['sku'];
//                 $it->ID = $item['product_id'];
//                 $it->Name = $product_data['name'];
//                 $it->Quantity = $item['quantity'];
//                 $it->TotalPrice = $product->get_price();
//                 $it->Length = isset($product_data['length']) ? Ship_Depot_Helper::ConvertToShipDepotDimension($product_data['length']) : 0;
//                 $it->Width = isset($product_data['width']) ? Ship_Depot_Helper::ConvertToShipDepotDimension($product_data['width']) : 0;
//                 $it->Height = isset($product_data['height']) ? Ship_Depot_Helper::ConvertToShipDepotDimension($product_data['height']) : 0;
//                 $it->Weight = isset($product_data['weight']) ? Ship_Depot_Helper::ConvertToShipDepotWeight($product_data['weight']) : 0;
//                 //
//                 $regular_price = $product_data['regular_price'];
//                 $item_regular_price_total += floatval($regular_price) * floatval($item['quantity']);
//                 $it->RegularPrice = $regular_price;
//                 // Ship_Depot_Logger::wrlog('[sd_woocommerce_review_order_before_order_total] it: ' . print_r($it, true));
//                 //
//                 array_push($list_packages_sizes, $package_size);
//                 array_push($list_items, $it);
//             }
//         }
//     }
//     $params = $request->get_params();

//     // Lấy thông tin địa chỉ giao hàng từ dữ liệu
//     $receiver = new Ship_Depot_Receiver();
//     $receiver->FirstName = $params['first_name'];
//     $receiver->LastName = $params['last_name'];
//     $receiver->Province = $params['city'];
//     $receiver->Address = $params['address_1'];
//     $receiver->Phone = $params['phone'];



//     return new WP_REST_Response($receiver, 200);
// }





// add_action('woocommerce_cart_calculate_fees', function ($cart) {
//     $discount_rules = new \Wdr\App\Models\DBTable();
//     // Gọi phương thức render của lớp DiscountRules để render giao diện của tab
//     var_dump($discount_rules->getRules(null, null, null, null));
// }, 10, 1);

// Hook để đăng ký các endpoint API của bạn














