<?php

function custom_cart_api_endpoint()
{
    register_rest_route(
        'custom/v1',
        '/add-to-cart',
        array(
            'methods' => 'POST',
            'callback' => 'custom_api_add_to_cart',
            'permission_callback' => 'custom_verify_jwt_token',
        )
    );
    register_rest_route(
        'custom/v1',
        '/get-order-info',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_order_info',
            //'permission_callback' => 'custom_verify_jwt_token',
        )
    );
    register_rest_route(
        'custom/v1',
        '/get-shipping-fee',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_shipping_fee',
            //'permission_callback' => 'custom_verify_jwt_token',
        )
    );
}

function custom_api_get_shipping_fee($request)
{
    if (!$request instanceof WP_REST_Request) {
        return new WP_Error('invalid_request', 'Invalid REST request.', array('status' => 400));
    }

    // Lấy token từ header Authorization
    $jwt_token = $request->get_header('Authorization');

    // Kiểm tra xem token có tồn tại không
    if (!$jwt_token) {
        return new WP_Error('jwt_missing', 'Authorization header with JWT token is missing', array('status' => 401));
    }

    // Bỏ tiền tố "Bearer" ra khỏi chuỗi token
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    // Thay YOUR_SECRET_KEY_HERE bằng khóa bí mật thực tế của bạn
    $secret_key = 'your-top-secrect-key';

    // Gọi hàm decode_jwt để giải mã token
    $decoded_data = decode_jwt($jwt_token, $secret_key);
    $user_id = $decoded_data['data']['user']['id'];

    $params = $request->get_params();

    $str_courier_setting = get_option('sd_setting_courier');
    if (!Ship_Depot_Helper::check_null_or_empty($str_courier_setting)) {
        $courier_setting = json_decode($str_courier_setting);
        //Ship_Depot_Logger::wrlog('[sd_woocommerce_review_order_before_order_total] courier_setting: ' . print_r($courier_setting, true));
    }


    if (!$request instanceof WP_REST_Request) {
        return new WP_Error('invalid_request', 'Invalid REST request.', array('status' => 400));
    }

    // Lấy token từ header Authorization
    $jwt_token = $request->get_header('Authorization');

    // Kiểm tra xem token có tồn tại không
    if (!$jwt_token) {
        return new WP_Error('jwt_missing', 'Authorization header with JWT token is missing', array('status' => 401));
    }

    // Bỏ tiền tố "Bearer" ra khỏi chuỗi token
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    // Thay YOUR_SECRET_KEY_HERE bằng khóa bí mật thực tế của bạn
    $secret_key = 'your-top-secrect-key';

    // Gọi hàm decode_jwt để giải mã token
    $decoded_data = decode_jwt($jwt_token, $secret_key);
    $user_id = $decoded_data['data']['user']['id'];
    // Kiểm tra xem giải mã có thành công không
    if (!$decoded_data) {
        return new WP_Error('jwt_invalid', 'JWT token is invalid or expired', array('status' => 401));
    }

    // Kiểm tra xem WooCommerce đã được kích hoạt không
    if (!class_exists('WooCommerce')) {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'WooCommerce không được kích hoạt'), 500);
    }

    // Lấy thông tin giỏ hàng của người dùng từ transient
    $user_cart_key = 'custom_cart_items_' . $user_id;
    $list_cart_items = get_transient($user_cart_key);

    $list_packages_sizes = [];
    $list_items = [];
    $total_qty = 0;
    $item_regular_price_total = 0;
    if (!empty($list_cart_items) && is_array($list_cart_items)) {
        // Khởi tạo sub_total ban đầu
        $sub_total = 0;
        foreach ($list_cart_items as $item) {
            if (isset($item['quantity'], $item['product_id'])) {
                // Lấy thông tin sản phẩm từ product_id
                $product = wc_get_product($item['product_id']);
                if ($product && $product->is_visible()) { // Kiểm tra sản phẩm có tồn tại và là sản phẩm hiển thị
                    $total_qty += floatval($item['quantity']);

                    $package_size = new Ship_Depot_Package();

                    // Lấy thông tin kích thước của sản phẩm
                    $product_length = $product->get_length();
                    $product_width = $product->get_width();
                    $product_height = $product->get_height();
                    $product_weight = $product->get_weight();

                    // Gán thông tin kích thước cho đối tượng Package
                    $package_size->Length = Ship_Depot_Helper::ConvertToShipDepotDimension($product_length);
                    $package_size->Width = Ship_Depot_Helper::ConvertToShipDepotDimension($product_width);
                    $package_size->Height = Ship_Depot_Helper::ConvertToShipDepotDimension($product_height);
                    $package_size->Weight = Ship_Depot_Helper::ConvertToShipDepotWeight($product_weight);

                    $it = new Ship_Depot_Item();
                    $it->Sku = $product->get_sku();
                    $it->ID = $item['product_id'];
                    $it->Name = $product->get_name();
                    $it->Quantity = $item['quantity'];
                    $it->TotalPrice = floatval($item['line_total']);
                    $it->Length = $package_size->Length;
                    $it->Width = $package_size->Width;
                    $it->Height = $package_size->Height;
                    $it->Weight = $package_size->Weight;

                    $regular_price = $product->get_regular_price();
                    $item_regular_price_total += floatval($regular_price) * floatval($item['quantity']);
                    $it->RegularPrice = $regular_price;
                    $sub_total += floatval($item['line_total']) * floatval($item['quantity']);

                    array_push($list_packages_sizes, $package_size);
                    array_push($list_items, $it);
                }
            }
        }
    }

    // Tạo một đối tượng WC_Customer với ID của người dùng
    $customer = new WC_Customer($user_id);
    $shipping_address = $customer->get_shipping();
    // Khởi tạo đối tượng receiver
    $receiver = new Ship_Depot_Receiver();

    // Kiểm tra xem khách hàng có thông tin giao hàng không
    if ($customer && is_callable([$customer, 'get_shipping'])) {

        // Kiểm tra xem địa chỉ giao hàng có tồn tại không
        if ($shipping_address) {
            $receiver->Province = isset($params['billing_city']) ? $params['billing_city'] : '';
            $receiver->Address = isset($shipping_address['address_1']) ? $shipping_address['address_1'] : '';
            // Xử lý dữ liệu từ $params
            if (!empty($params)) {
                $receiver->District = isset($params['billing_district']) && $params['billing_district'] != SD_SELECT_DISTRICT_TEXT ? $params['billing_district'] : '';
                $receiver->Ward = isset($params['billing_ward']) && $params['billing_ward'] != SD_SELECT_WARD_TEXT ? $params['billing_ward'] : '';
            } else {
                // Lấy thông tin địa chỉ thanh toán từ đối tượng customer
                $user_data = $customer->get_data();
                $receiver->District = isset($user_data['billing']['district']) ? $user_data['billing']['district'] : '';
                $receiver->Ward = isset($user_data['billing']['ward']) ? $user_data['billing']['ward'] : '';
            }
        } else {
            // Địa chỉ giao hàng không tồn tại
            // Xử lý tùy ý, ví dụ:
            return new WP_Error('no_shipping_address', 'Không tìm thấy địa chỉ giao hàng', array('status' => 404));
        }
    } else {
        // Không có thông tin giao hàng
        // Xử lý tùy ý, ví dụ:
        return new WP_Error('no_customer_info', 'Không tìm thấy thông tin khách hàng', array('status' => 404));
    }

    $strListStr = get_option('sd_list_storages');
    $selected_storage = null;
    if (!Ship_Depot_Helper::check_null_or_empty($strListStr)) {
        $listStr = json_decode($strListStr);
        if (count($listStr) > 0) {
            foreach ($listStr as $str) {
                if ($str->IsDefault) {
                    $selected_storage = $str;
                }
            }

            if ($selected_storage == null) {
                $selected_storage = $listStr[0];
            }
        }
    }
    $str_sender_info = get_option('sd_sender_info');
    if (!Ship_Depot_Helper::check_null_or_empty($str_sender_info)) {
        $sender_info_obj = Ship_Depot_Helper::CleanJsonFromHTMLAndDecode($str_sender_info);
        $sender_info = new Ship_Depot_Shop_Info($sender_info_obj);
    }
    $is_cod = false;
    // Kiểm tra xem payment_method có tồn tại trong params không
    if (isset($params['payment_method']) && !Ship_Depot_Helper::check_null_or_empty($params['payment_method'])) {
        $payment_method = $params['payment_method'];
        Ship_Depot_Logger::wrlog('[sd_woocommerce_review_order_before_order_total] payment_method: ' . print_r($payment_method, true));

        // Kiểm tra xem payment_method có phải là 'cod' không
        if (!Ship_Depot_Helper::check_null_or_empty($payment_method) && $payment_method == 'cod') {
            $is_cod = true;
        }
    }
    $list_shipping = Ship_Depot_Order_Shipping::calculate_shipping_fee($is_cod, false, 0, $list_packages_sizes, $list_items, $receiver, isset($selected_storage) ? $selected_storage->WarehouseID : '', isset($sender_info) ? $sender_info : '', 0, $item_regular_price_total, 0, $courier_setting);
    return $list_shipping;
}



function custom_api_get_order_info($request)
{

    if (!$request instanceof WP_REST_Request) {
        return new WP_Error('invalid_request', 'Invalid REST request.', array('status' => 400));
    }

    // Lấy token từ header Authorization
    $jwt_token = $request->get_header('Authorization');

    // Kiểm tra xem token có tồn tại không
    if (!$jwt_token) {
        return new WP_Error('jwt_missing', 'Authorization header with JWT token is missing', array('status' => 401));
    }

    // Bỏ tiền tố "Bearer" ra khỏi chuỗi token
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    // Thay YOUR_SECRET_KEY_HERE bằng khóa bí mật thực tế của bạn
    $secret_key = 'your-top-secrect-key';

    // Gọi hàm decode_jwt để giải mã token
    $decoded_data = decode_jwt($jwt_token, $secret_key);
    $user_id = $decoded_data['data']['user']['id'];
    // Kiểm tra xem giải mã có thành công không
    if (!$decoded_data) {
        return new WP_Error('jwt_invalid', 'JWT token is invalid or expired', array('status' => 401));
    }


    // Kiểm tra xem WooCommerce đã được kích hoạt không
    if (!class_exists('WooCommerce')) {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'WooCommerce không được kích hoạt'), 500);
    }

    // Lấy thông tin giỏ hàng của người dùng từ transient
    $user_cart_key = 'custom_cart_items_' . $user_id;
    $list_cart_items = get_transient($user_cart_key);

    $list_packages_sizes = [];
    $list_items = [];
    $total_qty = 0;
    $item_regular_price_total = 0;
    if (!empty($list_cart_items) && is_array($list_cart_items)) {
        foreach ($list_cart_items as $item) {
            if (isset($item['quantity'], $item['product_id'])) {
                // Lấy thông tin sản phẩm từ product_id
                $product = wc_get_product($item['product_id']);
                if ($product && $product->is_visible()) { // Kiểm tra sản phẩm có tồn tại và là sản phẩm hiển thị
                    $total_qty += floatval($item['quantity']);

                    $package_size = new Ship_Depot_Package();

                    // Lấy thông tin kích thước của sản phẩm
                    $product_length = $product->get_length();
                    $product_width = $product->get_width();
                    $product_height = $product->get_height();
                    $product_weight = $product->get_weight();

                    // Gán thông tin kích thước cho đối tượng Package
                    $package_size->Length = Ship_Depot_Helper::ConvertToShipDepotDimension($product_length);
                    $package_size->Width = Ship_Depot_Helper::ConvertToShipDepotDimension($product_width);
                    $package_size->Height = Ship_Depot_Helper::ConvertToShipDepotDimension($product_height);
                    $package_size->Weight = Ship_Depot_Helper::ConvertToShipDepotWeight($product_weight);

                    $it = new Ship_Depot_Item();
                    $it->Sku = $product->get_sku();
                    $it->ID = $item['product_id'];
                    $it->Name = $product->get_name();
                    $it->Quantity = $item['quantity'];
                    $it->TotalPrice = floatval($item['line_total']);
                    $it->Length = $package_size->Length;
                    $it->Width = $package_size->Width;
                    $it->Height = $package_size->Height;
                    $it->Weight = $package_size->Weight;

                    $regular_price = $product->get_regular_price();
                    $item_regular_price_total += floatval($regular_price) * floatval($item['quantity']);
                    $it->RegularPrice = $regular_price;

                    array_push($list_packages_sizes, $package_size);
                    array_push($list_items, $it);
                }
            }
        }
    }



    // Kiểm tra xem dữ liệu có tồn tại không
    if ($list_cart_items !== false) {
        // Dữ liệu tồn tại trong transient, bạn có thể sử dụng nó ở đây
        return rest_ensure_response($list_packages_sizes);
    } else {
        // Không tìm thấy dữ liệu trong transient
        return 'Không tìm thấy dữ liệu trong transient.';
    }


    // Trả về thông tin giỏ hàng của người dùng dưới dạng JSON
    return 'ok';
}








function custom_api_add_to_cart($request)
{

    if (!$request instanceof WP_REST_Request) {
        return new WP_Error('invalid_request', 'Invalid REST request.', array('status' => 400));
    }

    // Lấy token từ header Authorization
    $jwt_token = $request->get_header('Authorization');

    // Kiểm tra xem token có tồn tại không
    if (!$jwt_token) {
        return new WP_Error('jwt_missing', 'Authorization header with JWT token is missing', array('status' => 401));
    }

    // Bỏ tiền tố "Bearer" ra khỏi chuỗi token
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    // Thay YOUR_SECRET_KEY_HERE bằng khóa bí mật thực tế của bạn
    $secret_key = 'your-top-secrect-key';

    // Gọi hàm decode_jwt để giải mã token
    $decoded_data = decode_jwt($jwt_token, $secret_key);
    $user_id = $decoded_data['data']['user']['id'];
    // Kiểm tra xem giải mã có thành công không
    if (!$decoded_data) {
        return new WP_Error('jwt_invalid', 'JWT token is invalid or expired', array('status' => 401));
    }



    // Kiểm tra xem WooCommerce đã được kích hoạt không
    if (!class_exists('WooCommerce')) {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'WooCommerce không được kích hoạt'), 500);
    }

    $product_id = $request['product_id'];
    $quantity = $request['quantity'];


    if (!$user_id) {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Người dùng chưa đăng nhập'), 403);
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Sản phẩm không được tìm thấy'), 404);
    }

    // Lấy thông tin giỏ hàng của người dùng từ transient
    $user_cart_key = 'custom_cart_items_' . $user_id;
    $user_cart_items = get_transient($user_cart_key);

    // Khởi tạo một mảng giỏ hàng mới nếu không tồn tại
    if (!$user_cart_items) {
        $user_cart_items = array();
    }

    // Kiểm tra xem sản phẩm đã tồn tại trong giỏ hàng của người dùng chưa
    if (isset($user_cart_items[$product_id])) {
        // Nếu đã tồn tại, cập nhật số lượng và thông tin sản phẩm
        $user_cart_items[$product_id]['quantity'] += $quantity;
    } else {
        // Nếu chưa tồn tại, thêm mới vào giỏ hàng của người dùng
        $product_data = wc_get_product($product_id);

        if ($product_data) {
            $product_name = $product_data->get_name();
            $product_image = $product_data->get_image('thumbnail'); // Link ảnh sản phẩm
            $product_price = $product_data->get_price(); // Giá sản phẩm

            // Thêm mới sản phẩm vào giỏ hàng
            $user_cart_items[$product_id] = array(
                'product_id' => $product_id,
                'name' => $product_name,
                'image' => $product_image,
                'price' => $product_price,
                'quantity' => $quantity
            );
        } else {
            // Nếu không tìm thấy thông tin sản phẩm, ghi nhận lỗi
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Không thể tìm thấy thông tin sản phẩm'), 404);
        }
    }

    // Lưu thông tin giỏ hàng của người dùng vào transient
    set_transient($user_cart_key, $user_cart_items, 30 * DAY_IN_SECONDS); // Thời gian sống transient: 30 ngày

    // Trả về thông tin giỏ hàng của người dùng dưới dạng JSON
    return rest_ensure_response($user_cart_items);

}



function apply_discount_based_on_rules($cart)
{
    if (function_exists('get_field') && have_rows('discount_rule', 'option')) {
        $discount_rules = get_field('discount_rule', 'option');

        if (is_array($discount_rules)) {
            $discount = 0;
            $check = false;
            foreach ($discount_rules as $discount_rule) {
                if ($discount_rule['rule'] == 'order' && is_array($discount_rule['order_rule'])) {
                    foreach ($discount_rule['order_rule'] as $item) {
                        if ($item['logical'] === 'AND') {

                            if ($item['rule']['rule_name'] === 'Number of Ordered') {
                                foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                                    $product_id = $cart_item['product_id'];
                                    $user_purchase_count = get_user_product_purchase_count($product_id);
                                    if ($user_purchase_count > $item['rule']['rule_value']) {
                                        $check = true; // Nếu một điều kiện không đạt, đặt $check là false và thoát khỏi vòng lặp
                                        break;
                                    }
                                }
                            } else if ($item['rule']['rule_name'] === 'Total Order') {
                                $order_total = WC()->cart->subtotal;
                                if ($order_total > floatval($item['rule']['rule_value'])) {
                                    $check = true;
                                }
                            }
                        } else if ($item['logical'] === 'OR') {
                            if ($item['rule']['rule_name'] === 'Number of Ordered') {
                                foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                                    $product_id = $cart_item['product_id'];
                                    $user_purchase_count = get_user_product_purchase_count($product_id);
                                    if ($user_purchase_count > $item['rule']['rule_value']) {
                                        $check = true; // Nếu một điều kiện đạt, đặt $check là true và thoát khỏi vòng lặp
                                        break;
                                    }
                                }
                            } else if ($item['rule']['rule_name'] === 'Total Order') {
                                $order_total = WC()->cart->subtotal;
                                if ($order_total > floatval($item['rule']['rule_value'])) {

                                    $check = true; // Nếu một điều kiện đạt, đặt $check là true và thoát khỏi vòng lặp
                                    break;
                                }
                            }
                        }
                    }
                } else if ($discount_rule['rule'] == 'user' && is_array($discount_rule['user_rule'])) {
                    // Xử lý khi quy tắc là user
                    if ($item['logical'] === 'AND') {
                        if ($item['rule']['rule_name'] === 'New Customer') {
                            $current_user = wp_get_current_user();
                            if ($current_user->ID !== 0) {
                                // Kiểm tra nếu người dùng hiện tại đang đăng nhập
                                $user_registered = strtotime($current_user->user_registered); // Chuyển đổi ngày tạo tài khoản thành dạng thời gian Unix
                                $current_time = current_time('timestamp'); // Lấy thời gian hiện tại
                                $seconds_diff = $current_time - $user_registered; // Tính hiệu của thời gian hiện tại và thời gian tạo tài khoản

                                // Chia số giây đã trôi qua cho 86400 để tính số ngày đã đăng ký
                                $days_registered = floor($seconds_diff / (60 * 60 * 24));
                                if ($days_registered < $item['rule_value']) {
                                    $check = true;
                                }

                            }
                        } else if ($item['rule']['rule_name'] === 'Old Customer') {
                            $current_user = wp_get_current_user();
                            if ($current_user->ID !== 0) {
                                // Kiểm tra nếu người dùng hiện tại đang đăng nhập
                                $user_registered = strtotime($current_user->user_registered); // Chuyển đổi ngày tạo tài khoản thành dạng thời gian Unix
                                $current_time = current_time('timestamp'); // Lấy thời gian hiện tại
                                $seconds_diff = $current_time - $user_registered; // Tính hiệu của thời gian hiện tại và thời gian tạo tài khoản

                                // Chia số giây đã trôi qua cho 86400 để tính số ngày đã đăng ký
                                $days_registered = floor($seconds_diff / (60 * 60 * 24));
                                if ($days_registered > $item['rule_value']) {
                                    $check = true;
                                }

                            }
                        }
                    }
                }
                if ($check) {
                    $total_price = 0;

                    // Tính tổng giá của giỏ hàng
                    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                        $total_price += $cart_item['line_total'];
                    }

                    // Giảm giá dựa trên phần trăm của tổng giá của đơn hàng
                    $discount_percent = 0.1; // Giảm giá 10%
                    $discount_amount = $total_price * $discount_percent;
                    $cart->add_fee(__('Discount', 'your-text-domain'), -$discount_amount);
                }
            }
        } else {
            // Xử lý khi không tìm thấy trường 'discount_rule' hoặc hàm get_field không tồn tại
        }
    }
}