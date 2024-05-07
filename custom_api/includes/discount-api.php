<?php
add_action('rest_api_init', function () {
    // Đăng ký endpoint cho phương thức GET
    register_rest_route(
        'custom/v1',
        '/get-rules',
        array (
            'methods' => 'GET',
            'callback' => 'get_rules_api',
            'permission_callback' => 'check_user_role_admin',
        )
    );
});

function get_rules_api($request)
{
    // Tạo một đối tượng từ lớp DBTable
    $db_table = new \Wdr\App\Models\DBTable();

    // Gọi phương thức getRules() từ đối tượng $db_table
    $rules = $db_table->getRules();

    // Kiểm tra nếu mảng $rules không trống và có phần tử đầu tiên
    if (!empty($rules) && isset($rules[0]->title)) {
        // Lặp qua mỗi phần tử và chuyển đổi các trường filters và product_adjustments thành đối tượng PHP
        foreach ($rules as &$rule) {
            $rule->filters = json_decode($rule->filters, true);
            $rule->product_adjustments = json_decode($rule->product_adjustments, true);
        }

        // Trả về kết quả dưới dạng JSON
        return rest_ensure_response($rules);
    }

    // Nếu không có quy tắc hoặc không có title trong quy tắc đầu tiên, trả về thông báo lỗi hoặc giá trị mặc định
    return rest_ensure_response('Không tìm thấy quy tắc hoặc quy tắc không có tiêu đề');
}