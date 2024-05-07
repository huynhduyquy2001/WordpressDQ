<?php
function check_user_role_admin($request)
{
    // Kiểm tra xem $request có phải là một đối tượng của lớp WP_REST_Request không
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

    // Kiểm tra xem giải mã có thành công không
    if (!$decoded_data) {
        return new WP_Error('jwt_invalid', 'JWT token is invalid or expired', array('status' => 401));
    }

    // Lấy vai trò của người dùng từ dữ liệu giải mã
    $roles = $decoded_data['roles'];

    // Kiểm tra xem vai trò của người dùng có trong mảng roles hay không
    if (!empty($roles) && in_array('administrator', $roles)) {
        // Nếu người dùng có vai trò là administrator
        return true;
    } else {
        // Nếu không, trả về false hoặc xử lý theo yêu cầu của bạn
        return false;
    }

}

function register_api_logger()
{
    // Hook vào trước khi xử lý request API
    add_filter('rest_pre_dispatch', 'log_api_request', 10, 3);
}

function decode_jwt($jwt_token, $secret_key)
{
    // Phân tách token thành các phần
    $jwt_parts = explode('.', $jwt_token);

    // Kiểm tra xem token có đúng 3 phần không
    if (count($jwt_parts) !== 3) {
        // JWT không hợp lệ nếu không có đúng 3 phần
        return false;
    }

    // Giải mã phần header từ base64
    $header = json_decode(base64_decode($jwt_parts[0]), true);
    if ($header === null) {
        // Xử lý lỗi khi giải mã header không thành công
        return false;
    }

    // Giải mã phần payload từ base64
    $payload = json_decode(base64_decode($jwt_parts[1]), true);
    if ($payload === null) {
        // Xử lý lỗi khi giải mã payload không thành công
        return false;
    }

    // Giải mã chữ ký từ base64
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $jwt_parts[2]));
    if ($signature === false) {
        // Xử lý lỗi khi giải mã chữ ký không thành công
        return false;
    }

    // Tạo chuỗi đã ký
    $signed_data = $jwt_parts[0] . '.' . $jwt_parts[1];

    // Tính toán chữ ký mong đợi
    $expected_signature = hash_hmac('sha256', $signed_data, $secret_key, true);

    // So sánh chữ ký
    if (!hash_equals($signature, $expected_signature)) {
        // Chữ ký không hợp lệ
        return false;
    }

    // Trả về phần payload
    return $payload;
}
// Thêm vai trò vào payload của token JWT trước khi ký
function add_roles_to_jwt_token($data, $user)
{
    // Lấy vai trò của người dùng
    $user_roles = $user->roles;
    // Thêm vai trò vào payload
    $data['roles'] = $user_roles;

    return $data;
}
// $user_id = get_current_user_id(); // Lấy ID người dùng hoặc một định danh khác
// $limit = 60; // Số lượng yêu cầu tối đa
// $duration = 60; // Giới hạn trong 60 giây

// if (check_rate_limit($user_id, $limit, $duration)) {
//     // Thực hiện yêu cầu API
// } else {
//     // Trả về thông báo lỗi hoặc thử lại sau
//     wp_send_json_error('Bạn đã vượt quá số lượng yêu cầu tối đa.');
// }
// function check_rate_limit($user_id, $limit, $duration)
// {
//     $key = "api_requests_count_" . $user_id;
//     $current = get_transient($key);

//     if ($current === false) {
//         // Không có dữ liệu, thiết lập ban đầu
//         set_transient($key, 1, $duration); // $duration tính bằng giây
//         return true;
//     } else {
//         if ($current < $limit) {
//             // Tăng số lượng yêu cầu và cập nhật
//             set_transient($key, $current + 1, $duration);
//             return true;
//         }
//     }

//     return false; // Quá giới hạn
// }

function log_api_request($result, $wp_rest_server, $request)
{
    // Lấy thông tin về thời gian gọi API
    $request_time = current_time('mysql');
    // Kiểm tra đường dẫn của yêu cầu API
    $request_path = $request->get_route();

    // Kiểm tra xem đường dẫn có chứa '/custom/v1/' không (điều này phụ thuộc vào cách bạn định nghĩa route cho các custom API)
    if (strpos($request_path, '/custom/v1/') !== false) {
        // Lấy thông tin về địa chỉ IP của người gọi API
        $ip_address = $_SERVER['REMOTE_ADDR'];

        // Lấy thông tin về phương thức và đường dẫn của request
        $request_method = $request->get_method();
        $request_path = $request->get_route();

        // Kiểm tra nếu có lỗi
        $error = '';
        if (is_wp_error($result)) {
            $error = "Error: " . $result->get_error_message() . " | ";
        }

        // Ghi thông tin vào file log
        $log_data = "Time: $request_time | ";
        $log_data .= "IP Address: $ip_address | ";
        $log_data .= "Method: $request_method | ";
        $log_data .= "Path: $request_path | ";
        $log_data .= $error . "\n";

        // Đường dẫn tới thư mục wp-content/uploads
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];

        // Tạo thư mục log nếu nó không tồn tại
        if (!file_exists($upload_path . '/api-logs')) {
            mkdir($upload_path . '/api-logs', 0755, true);
        }

        // Đường dẫn tới file log
        $file_path = $upload_path . '/api-logs/api-log.txt';

        // Ghi dữ liệu vào file log
        file_put_contents($file_path, $log_data, FILE_APPEND);

        // Trả về kết quả để tiếp tục xử lý request API
        return $result;
        return;
    }

}
function custom_verify_jwt_token($request)
{
    $token = $request->get_header('Authorization');
    if (!$token) {
        return new WP_Error('no_token', 'JWT token is missing', array('status' => 401));
    }

    $secret_key = 'your-top-secrect-key'; // Thay bằng mã khóa bí mật của bạn
    try {
        $decoded = decode_jwt($token, $secret_key);
        // Xác thực thành công, bạn có thể trả về true hoặc đối tượng người dùng liên quan
        return true;
    } catch (Exception $e) {
        return new WP_Error('invalid_token', $e->getMessage(), array('status' => 401));
    }
}
function check_access_endpoint($request)
{
    // Lấy token từ header Authorization
    $jwt_token = $request->get_header('Authorization');

    // Bỏ tiền tố "Bearer" ra khỏi chuỗi token
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    // Thay YOUR_SECRET_KEY_HERE bằng khóa bí mật thực tế của bạn
    $secret_key = 'your-top-secrect-key';

    // Gọi hàm decode_jwt để giải mã token
    $decoded_data = decode_jwt($jwt_token, $secret_key);


    // Kiểm tra xem người dùng có vai trò là quản trị viên không
    if (in_array('administrator', $decoded_data['roles'])) {
        // Nếu là quản trị viên, cho phép truy cập vào danh sách đơn hàng
        return true;
    } else {
        // Nếu không phải quản trị viên, trả về false
        return false;
    }
}
function get_user_id_by_jwt($request)
{
    // Lấy token từ header Authorization
    $jwt_token = $request->get_header('Authorization');

    // Bỏ tiền tố "Bearer" ra khỏi chuỗi token
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    // Thay YOUR_SECRET_KEY_HERE bằng khóa bí mật thực tế của bạn
    $secret_key = 'your-top-secrect-key';

    // Gọi hàm decode_jwt để giải mã token
    $decoded_data = decode_jwt($jwt_token, $secret_key);

    return $decoded_data['data']['user']['id'];
}
// function custom_user_authentication_for_api()
// {
//     // Lấy thông tin xác thực từ request headers
//     $authorization = false;
//     if (function_exists('getallheaders')) {
//         $headers = getallheaders();
//         if (isset($headers['Authorization'])) {
//             $authorization = $headers['Authorization'];
//         }
//     } elseif (isset($_SERVER["Authorization"])) {
//         $authorization = $_SERVER["Authorization"];
//     }

//     // Kiểm tra xem Authorization header có tồn tại không
//     if (!$authorization) {
//         // Nếu không, trả về false để báo hiệu rằng yêu cầu không được xác thực
//         return false;
//     }

//     // Loại bỏ phần "Bearer " khỏi chuỗi Authorization để lấy token JWT
//     $token = str_replace('Bearer ', '', $authorization);
//     // Giải mã token JWT để kiểm tra xác thực
//     try {
//         $decodedToken = decode_jwt($token, 'your-top-secrect-key'); // Thay thế hàm decode_jwt bằng hàm thực sự bạn sử dụng để giải mã token JWT

//     } catch (Exception $e) {
//         var_dump($e);
//         // Xử lý lỗi nếu không thể giải mã token
//         return false;
//     }

//     // Kiểm tra xem token có hợp lệ hay không
//     if (!$decodedToken) {
//         // Nếu token không hợp lệ, trả về false để báo hiệu rằng yêu cầu không được xác thực
//         return false;
//     }
//     $user_info = get_userdata($decodedToken['data']['user']['id']);
//     // Kiểm tra xem người dùng được xác thực có đủ quyền hay không, dựa trên thông tin trong token
//     // Nếu không đủ quyền, trả về false
//     // Nếu đủ quyền, trả về người dùng hoặc thông tin xác thực khác nếu cần
//     return $user_info;
// }

function filter_woocommerce_rest_check_permissions($permission, $context, $object_id, $post_type)
{
    $authorization = false;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authorization = $headers['Authorization'];
        }
    } elseif (isset($_SERVER["Authorization"])) {
        $authorization = $_SERVER["Authorization"];
    }

    // Kiểm tra xem Authorization header có tồn tại không
    if (!$authorization) {
        // Nếu không, trả về false để báo hiệu rằng yêu cầu không được xác thực
        return false;
    }

    // Loại bỏ phần "Bearer " khỏi chuỗi Authorization để lấy token JWT
    $token = str_replace('Bearer ', '', $authorization);
    // Giải mã token JWT để kiểm tra xác thực
    try {
        $decodedToken = decode_jwt($token, 'your-top-secrect-key'); // Thay thế hàm decode_jwt bằng hàm thực sự bạn sử dụng để giải mã token JWT

    } catch (Exception $e) {
        var_dump($e);
        // Xử lý lỗi nếu không thể giải mã token
        return false;
    }
    $roles = $decodedToken['roles'];
    // Allow the customer service role.
    if (in_array("administrator", $roles)) {
        return true;
    } else if ($context === "delete" || $context === "edit") {
        // Nếu không phải admin và ngữ cảnh là "delete", không cho phép xóa và update
        return false;
    } else {
        // Cho phép các ngữ cảnh khác
        return true;
    }

}
;

