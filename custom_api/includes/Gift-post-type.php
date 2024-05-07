<?php

function custom_api_register_custom_post_type_endpoint()
{
    register_rest_route(
        'custom/v1',
        '/gift-post-type',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_gift_post_type',
            //'permission_callback' => 'check_user_role_admin',
        )
    );

    register_rest_route(
        'custom/v1',
        '/gift-post-type/create',
        array(
            'methods' => 'POST',
            'callback' => 'custom_api_create_gift_post',
            'permission_callback' => 'check_user_role_admin',
        )
    );
    register_rest_route(
        'custom/v1',
        '/gift-post-type/update/(?P<id>\d+)',  // Thay đổi route tùy thuộc vào cấu trúc URL bạn muốn
        array(
            'methods' => 'PUT',
            'callback' => 'custom_api_update_gift_post',
            'permission_callback' => 'check_user_role_admin',

        )
    );
    register_rest_route(
        'custom/v1',
        '/gift-post-type/delete/(?P<id>\d+)',  // Route với ID là một tham số động
        array(
            'methods' => 'DELETE',
            'callback' => 'custom_api_delete_gift_post',
            'permission_callback' => 'check_user_role_admin',
        )
    );
    // Đăng ký endpoint API
    register_rest_route(
        'custom/v1',
        '/decode_jwt_api',
        array(
            'methods' => 'GET',
            'callback' => 'check_user_role_admin'
        )
    );
    register_rest_route(
        'custom/v1',
        '/user-preferences/(?P<id>\d+)',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_user_preferences',
        )
    );
    register_rest_route(
        'custom/v1',
        '/redeemable-products',
        array(
            'methods' => 'GET',
            'callback' => 'get_redeemable_products',
        )
    );
    register_rest_route(
        'custom/v1',
        '/accept-gift-request/(?P<gift_post_id>\d+)',
        array(
            'methods' => 'POST',
            'callback' => 'custom_api_accept_gift_request',
        )
    );
    register_rest_route(
        'custom/v1',
        '/reject-gift-request/(?P<gift_post_id>\d+)',
        array(
            'methods' => 'POST',
            'callback' => 'custom_api_reject_gift_request',
        )
    );
    register_rest_route(
        'custom/v1',
        '/referrer-post-type',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_referrer_post_type',
            'permission_callback' => 'check_user_role_admin',
        )
    );
    register_rest_route(
        'custom/v1',
        '/referrer-post-type/(?P<ref_id>\d+)',
        array(
            'methods' => 'GET',
            'callback' => 'custom_api_get_referrer_post_type',
            'permission_callback' => 'check_user_role_admin',
        )
    );
}

function custom_api_get_gift_post_type($request)
{
    // Thiết lập các tham số mặc định
    $args = array(
        'post_type' => 'gift-post-type',
        'posts_per_page' => -1,
    );

    // Truy vấn dữ liệu từ Custom Post Type
    $custom_posts = new WP_Query($args);


    // Xử lý dữ liệu trước khi trả về (nếu cần)
    $formatted_posts = array();
    if ($custom_posts->have_posts()) {
        while ($custom_posts->have_posts()) {
            $custom_posts->the_post();

            // Lấy ID của bài đăng
            $post_id = get_the_ID();

            // Lấy ID của sản phẩm
            $product_id = get_post_meta($post_id, 'product_id', true);

            // Lấy giá của sản phẩm
            $product_price = get_post_meta($post_id, 'product_price', true);

            // Lấy ID của người dùng
            $user_id = get_post_meta($post_id, 'user_id', true);

            // Lấy trạng thái của quà tặng
            $gift_status = get_post_meta($post_id, 'gift_status', true);


            // Thêm dữ liệu vào mảng
            $formatted_posts[] = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'product_id' => $product_id,
                'product_price' => $product_price,
                'user_id' => $user_id,
                'gift_status' => $gift_status,
                // Thêm các trường dữ liệu khác nếu cần
            );
        }
    }


    // Trả về dữ liệu dưới dạng JSON
    return rest_ensure_response($formatted_posts);
}

function custom_api_create_gift_post($request)
{
    // Lấy các tham số từ request
    $params = $request->get_params();
    $points = get_post_meta($params['product_id'], 'point', true);
    $product_name = get_the_title($params['product_id']);
    $user_id = get_user_id_by_jwt($request);
    $user_info = get_userdata($user_id);
    $user_name = $user_info->display_name;
    $title = $user_name . ' đã gửi yêu cầu đổi quà ' . $product_name;
    $product_categories = get_the_terms($params['product_id'], 'product_cat');
    $has_gift = false; // Biến để kiểm tra xem có sản phẩm nào trong danh mục là gift không
    foreach ($product_categories as $category) {
        // Kiểm tra xem có danh mục nào trong sản phẩm không có slug là "gift" không
        if ($category->slug === 'gift') {
            $has_gift = true;
            break;
        }
    }
    if (!$has_gift) {
        // Nếu không có danh mục nào có slug là "gift", trả về một đối tượng WP_Error
        return new WP_Error('no_gift', 'Sản phẩm  này không phải quà tặng!');
    }

    if (empty($params['product_id'])) {
        return new WP_Error('invalid_parameters', 'Missing required parameter: product_id', array('status' => 400));
    }

    // Lọc và xác thực dữ liệu
    $sanitized_data = array(
        'title' => $title,
        'product_id' => sanitize_text_field($params['product_id']),
        'product_price' => $points,
        'user_id' => $user_id,
        'gift_status' => 'Pending',
    );
    // Tạo một bài đăng mới
    $post_data = array(
        'post_title' => $sanitized_data['title'],
        'post_type' => 'gift-post-type',
        'post_status' => 'Pending',
        'post_status' => 'publish' // Đặt trạng thái của post là 'publish'
    );

    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id)) {
        return new WP_Error('create_post_error', 'Failed to create new post', array('status' => 500));
    }

    // Lưu thông tin bổ sung vào meta data của bài đăng
    update_post_meta($post_id, 'product_id', $sanitized_data['product_id']);
    update_post_meta($post_id, 'product_price', $sanitized_data['product_price']);
    update_post_meta($post_id, 'user_id', $sanitized_data['user_id']);
    update_post_meta($post_id, 'gift_status', $sanitized_data['gift_status']);

    return new WP_REST_Response(array('message' => 'Gift post created successfully', 'post_id' => $post_id), 200);
}


function custom_api_update_gift_post($request)
{
    $params = $request->get_params();
    $post_id = $request['id'];

    // Kiểm tra xem bài đăng có tồn tại không
    if (!get_post($post_id)) {
        return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
    }

    // Cập nhật thông tin của bài đăng
    $updated_data = array();

    if (!empty($params['title'])) {
        $updated_data['post_title'] = sanitize_text_field($params['title']);
    }

    // Cập nhật bài đăng
    wp_update_post(array('ID' => $post_id) + $updated_data);

    return new WP_REST_Response(array('message' => 'Gift post updated successfully', 'post_id' => $post_id), 200);
}
function custom_api_delete_gift_post($request)
{
    $post_id = $request['id'];

    // Kiểm tra xem bài đăng có tồn tại không
    if (!get_post($post_id)) {
        return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
    }

    // Xóa bài đăng
    wp_delete_post($post_id);

    return new WP_REST_Response(array('message' => 'Gift post deleted successfully', 'post_id' => $post_id), 200);
}
function custom_api_accept_gift_request($request)
{
    // Lấy ID của bài viết yêu cầu đổi quà từ yêu cầu POST
    $gift_post_id = $request->get_param('gift_post_id');

    // Lấy thông tin về sản phẩm từ $product_id
    $product = wc_get_product($gift_post_id);

    // Kiểm tra xem sản phẩm có tồn tại không
    if ($product) {
        // Kiểm tra xem sản phẩm có thuộc danh mục "Gift" không
        $terms = get_the_terms($gift_post_id, 'product_cat'); // Lấy danh sách các danh mục của sản phẩm

        // Kiểm tra xem danh mục của sản phẩm có chứa "Gift" không
        $is_gift = false;
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if ($term->slug === 'gift') {
                    $is_gift = true;
                    break;
                }
            }
        }

        // Nếu sản phẩm là quà tặng, $is_gift sẽ là true
        if ($is_gift) {
            // Sản phẩm là quà tặng
            // Thực hiện các thao tác cần thiết
            // Ví dụ: return true;
            return true;
        } else {
            return new WP_REST_Response(
                array(
                    'status' => 'error',
                    'message' => 'Sản phẩm không phải là gift'
                ),
                500
            );
        }
    } else {
        return new WP_REST_Response(
            array(
                'status' => 'error',
                'message' => 'Sản phẩm không tồn tại'
            ),
            404
        );
    }




    // Lấy product_id từ gift_post_id
    $product_id = get_post_meta($gift_post_id, 'product_id', true);
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
    // Cập nhật trạng thái của bài viết sang 'accepted'
    $old_status = get_post_meta($gift_post_id, 'gift_status', true);
    if ($old_status != 'Pending') {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Sản phẩm đã qua chấp nhận hoặc từ chối'), 500);
    }
    if (!$product_id) {
        // Nếu không tìm thấy product_id, trả về một lỗi
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Không tìm thấy sản phẩm'), 404);
    }

    $product_id = get_field('product_id', $gift_post_id);

    // Lấy số lượng hiện tại của sản phẩm

    $product_quantity = get_post_meta($product_id, 'amount_gift', true); // Lấy giá trị hiện tại của trường meta
    if ($product_quantity > 0) {
        $new_product_quantity = $product_quantity - 1; // Giảm giá trị đi 1
        update_post_meta($product_id, 'amount_gift', $new_product_quantity); // Cập nhật giá trị mới của trường meta
    } else {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Số lượng sản phẩm đã hết'), 400);
    }

    // Cập nhật trạng thái của bài viết sang 'accepted'
    $updated = update_field('gift_status', 'accepted', $gift_post_id);

    if ($updated) {
        // Lấy thông tin người dùng từ bài viết
        $user_id = get_field('user_id', $gift_post_id);
        $user_points = get_user_meta($user_id, 'coins', true); // Lấy điểm của người dùng từ trường meta

        // Lấy giá sản phẩm từ bài viết
        $product_price = get_field('product_price', $gift_post_id); // Lấy giá sản phẩm từ trường ACF

        if ($user_points && $product_price) {
            // Tính toán điểm mới của người dùng
            $new_points = $user_points - $product_price;
            // Cập nhật điểm của người dùng
            update_user_meta($user_id, 'coins', $new_points);

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

            // Gửi thông báo
            $novu_api_url = get_field('api_url', 'option');
            $novu_api_key = get_field('api_key', 'option');

            // $response = wp_remote_post(
            //     $novu_api_url,
            //     array(
            //         'method' => 'POST',
            //         'headers' => array(
            //             'Authorization' => 'ApiKey ' . $novu_api_key,
            //             'Content-Type' => 'application/json',
            //         ),
            //         'body' => json_encode(
            //             array(
            //                 'name' => 'gift-notification',
            //                 'to' => array(
            //                     'subscriberId' => $user_id, // Thay đổi theo cần thiết
            //                 ),
            //                 'payload' => array(
            //                     '__source' => 'wordpress-order-success',
            //                     'header' => 'Yêu cầu đổi quà của bạn đã được chấp nhận', // Thêm trường new_point vào payload
            //                     'total_point' => $new_points, // Thêm trường total_point vào payload
            //                     // Thêm bất kỳ thông tin nào khác từ đơn hàng vào payload nếu cần
            //                 ),
            //             )
            //         ),
            //         'data_format' => 'body',
            //     )
            // );

            return new WP_REST_Response(array('status' => 'success'), 200);
        } else {
            // Không tìm thấy điểm người dùng hoặc giá sản phẩm, trả về lỗi
            return new WP_REST_Response(array('status' => 'error', 'message' => 'User points or product price not found.'), 404);
        }
    } else {
        // Không cập nhật được trạng thái của bài viết, trả về lỗi
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Failed to update post status.'), 500);
    }
}

function custom_api_reject_gift_request($request)
{
    // Lấy ID của bài viết yêu cầu đổi quà từ yêu cầu POST
    $gift_post_id = $request->get_param('gift_post_id');

    // Lấy product_id từ gift_post_id
    $product_id = get_post_meta($gift_post_id, 'product_id', true);
    // Cập nhật trạng thái của bài viết sang 'accepted'
    $old_status = get_post_meta($gift_post_id, 'gift_status', true);
    if ($old_status != 'Pending') {
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Sản phẩm đã qua chấp nhận hoặc từ chối'), 500);
    }
    if (!$product_id) {
        // Nếu không tìm thấy product_id, trả về một lỗi
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Không tìm thấy sản phẩm'), 404);
    }

    $product_id = get_field('product_id', $gift_post_id);


    // Cập nhật trạng thái của bài viết sang 'accepted'
    $updated = update_field('gift_status', 'rejected', $gift_post_id);
    // Gửi thông báo
    $novu_api_url = get_field('api_url', 'option');
    $novu_api_key = get_field('api_key', 'option');
    $novu_subcriberId = get_field('subscriber_id', 'option');
    $user_id = get_field('user_id', $gift_post_id);
    $user_points = get_user_meta($user_id, 'coins', true);
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
    return new WP_REST_Response(array('status' => 'success'), 200);
}
function get_redeemable_products($request)
{
    $redeemable_products = array();

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

    // Tạo truy vấn
    $query = new WP_Query($args);

    // Kiểm tra xem có bài viết nào không
    if ($query->have_posts()) {
        // Lặp qua từng bài viết
        while ($query->have_posts()) {
            $query->the_post();
            // Lấy thông tin của bài viết
            $post_id = get_the_ID();
            $post_title = get_the_title();
            $post_image = get_the_post_thumbnail_url($post_id, 'full'); // Lấy URL của hình ảnh đặc trưng (size 'full')
            $post_points = get_post_meta($post_id, 'points', true); // Lấy số điểm từ meta field có key là 'points'

            // Tạo một mảng dữ liệu cho mỗi bài viết
            $product_data = array(
                'image' => $post_image,
                'id' => $post_id,
                'title' => $post_title,
                // Thêm URL hình ảnh vào mảng dữ liệu
                'points' => $post_points,
                // Bạn có thể thêm các trường dữ liệu khác của bài viết vào đây
            );

            // Thêm mảng dữ liệu vào mảng $redeemable_products
            $redeemable_products[] = $product_data;
        }
    }

    // Trả về mảng sản phẩm có thể quy đổi
    return rest_ensure_response($redeemable_products);
}
function custom_api_get_referrer_post_type($request)
{
    $params = $request->get_params();
    if (empty($params['ref_id'])) {
        // Thiết lập các tham số mặc định
        $args = array(
            'post_type' => 'referrer-post-type',
            'posts_per_page' => -1,
        );
    } else {
        $args = array(
            'post_type' => 'referrer-post-type',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'ref_id',
                    'value' => $params['ref_id'],
                    'compare' => '=',
                )
            )
        );
    }

    // Truy vấn dữ liệu từ Custom Post Type
    $custom_posts = new WP_Query($args);

    // Xử lý dữ liệu trước khi trả về (nếu cần)
    $formatted_posts = array();
    if ($custom_posts->have_posts()) {
        while ($custom_posts->have_posts()) {
            $custom_posts->the_post();

            // Lấy ID của bài đăng
            $post_id = get_the_ID();

            // Lấy thông tin về người được giới thiệu
            $receiver_id = get_post_meta($post_id, 'receiver_id', true);
            $receiver_info = get_userdata($receiver_id);

            // Tính tổng giá trị của các giao dịch mà người được giới thiệu đã thực hiện
            $transactions = wc_get_orders(
                array(
                    'customer' => $receiver_id,
                    'status' => 'completed', // chỉ tính các đơn hàng đã hoàn thành
                )
            );
            $total_spent = 0;
            foreach ($transactions as $transaction) {
                $total_spent += $transaction->get_total();
            }

            // Thêm thông tin vào mảng
            $formatted_posts[] = array(
                'receiver_id' => $receiver_id,
                'receiver_name' => $receiver_info->display_name,
                'receiver_email' => $receiver_info->user_email,
                'total_spent' => $total_spent,
                // Thêm các trường dữ liệu khác nếu cần
            );
        }
    }

    // Reset query
    wp_reset_postdata();

    // Trả về dữ liệu dưới dạng JSON
    return rest_ensure_response($formatted_posts);
}