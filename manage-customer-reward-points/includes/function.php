<?php
function mcrp_render_page()
{
    // Mặc định khi load trang là 'mycred_default'
    $ctype = isset($_GET['ctype']) ? $_GET['ctype'] : 'mycred_default';
    error_log('$ctype: ' . $ctype);

    // Kiểm tra giá trị của ctype, nếu là coins thì hiển thị lịch sử giao dịch của loại tiền coins
    if ($ctype == 'coins') {
        $args = [
            'number' => -1,
            'ctype' => 'coins'
        ];
    } else {
        // Nếu không, hiển thị lịch sử giao dịch của 'mycred_default'
        $args = [
            'number' => -1,
            'ctype' => 'mycred_default'
        ];
    }

    $log = new myCRED_Query_Log($args);
    $data = [];

    // Khởi tạo một biến để lưu trữ số điểm của mỗi người dùng hiện tại
    $current_balance = array();

    // Khởi tạo một mảng để lưu trữ tổng số điểm lũy kế cho mỗi người dùng
    $accumulated_points = array();

    // Lặp qua các giao dịch của mỗi người dùng
    foreach ($log->results as $entry) {
        $user_id = $entry->user_id;

        // Kiểm tra xem người dùng có trong mảng $accumulated_points chưa
        if (!isset($accumulated_points[$user_id])) {
            // Nếu chưa có, khởi tạo tổng số điểm lũy kế là 0
            $accumulated_points[$user_id] = get_user_meta($user_id, $ctype, true);
        }
        // Lấy thông tin người dùng từ ID người dùng
        $user_data = get_userdata($user_id);

        // Kiểm tra xem liệu có thông tin người dùng hay không
        if ($user_data) {
            // Nếu có, lấy tên người dùng
            $user_name = $user_data->display_name;
        } else {
            // Nếu không, gán giá trị mặc định
            $user_name = 'Unknown';
        }
        // Cộng hoặc trừ điểm từ mỗi giao dịch vào tổng số điểm lũy kế của người dùng
        $accumulated_points[$user_id] -= $entry->creds;

        // Lưu trữ số điểm hiện tại của mỗi người dùng
        $current_balance[$user_id] = $accumulated_points[$user_id];

        // Format lại điểm thành số nguyên và ngày thành dạng "H:i:s Y-m-d"
        $points_formatted = number_format($entry->creds, null, ',', ',');

        $total_points_formatted = number_format($accumulated_points[$user_id], null, ',', ',');

        $date_formatted = date("H:i:s Y-m-d", $entry->time);

        $data[] = [
            'user_id' => $user_name,
            'points_added' => $entry->creds ? '+' . number_format($points_formatted, 0, ',', '.') : '',
            'points_deducted' => $entry->creds ? '-' . number_format($points_formatted, 0, ',', '.') : '',
            'history_balance' => number_format($total_points_formatted, 0, ',', '.'),
            'description' => $entry->entry,
            'date' => date("H:i:s Y-m-d", $date_formatted)
        ];
    }
    $log->reset_query();

    // Display the table
    $table = new MyCRED_Points_History_Table($data);
    $table->prepare_items();
    ?>
    <div class="wrap">
        <h2>Bảng quản lý lịch sử giao dịch</h2>
        <div class="nav-tab-wrapper">
            <a href="<?php echo add_query_arg('ctype', 'mycred_default'); ?>"
                class="nav-tab <?php echo ($ctype == 'mycred_default') ? 'nav-tab-active' : ''; ?>">Hiện Points</a>
            <a href="<?php echo add_query_arg('ctype', 'coins'); ?>"
                class="nav-tab <?php echo ($ctype == 'coins') ? 'nav-tab-active' : ''; ?>">Hiện Coins</a>
        </div>
        Số dư hiện tại: <?php echo number_format(get_user_meta(get_current_user_id(), $ctype, true), 0, ',', '.'); ?><br>
        <?php $table->search_box('Tìm kiếm', 'user_id'); ?>
        <form method="post" action="">
            <input type="hidden" name="export_excel" value="1">
            <button type="submit" class="button">Xuất Excel</button>
        </form>

        <?php
        if (isset($_POST['export_excel']) && $_POST['export_excel'] == 1) {
            $filename = 'data.xlsx';
            $table->export_to_excel($filename, $data);
            ?>
            <!-- Liên kết để tải xuống tệp Excel -->
            <a href="<?php echo $filename; ?>" download>Tải xuống tệp Excel</a>

        <?php } ?>

        <?php $table->display(); ?>

    </div>
    <?php
}

function add_points_for_product_review($comment_id, $comment_approved)
{
    if ($comment_approved === 1) {
        $comment = get_comment($comment_id);
        $user_id = $comment->user_id;
        $product_id = $comment->comment_post_ID;

        // Kiểm tra xem đây có phải là bình luận cho sản phẩm không
        if ('product' === get_post_type($product_id)) {
            // Số điểm bạn muốn cộng
            $points_to_add = 10000;

            // Lấy điểm hiện tại của người dùng
            $current_points = (int) get_user_meta($user_id, 'coins', true);
            // Tính toán điểm mới
            $new_points = $current_points + $points_to_add;

            // Cập nhật điểm cho người dùng
            update_user_meta($user_id, 'coins', $new_points);
        }
    }
}

function export_users_to_excel($data)
{
    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();

    // Get active sheet
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $sheet->setCellValue('A1', 'Username');
    $sheet->setCellValue('B1', 'Email');
    $sheet->setCellValue('C1', 'Role');

    // Fetch users
    $users = get_users();

    // Populate user data
    $row = 2;
    foreach ($users as $user) {
        $sheet->setCellValue('A' . $row, $user->user_login);
        $sheet->setCellValue('B' . $row, $user->user_email);
        $sheet->setCellValue('C' . $row, implode(', ', $user->roles));
        $row++;
    }

    // Set header for Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="user_data.xlsx"');
    header('Cache-Control: max-age=0');

    // Create Xlsx Writer
    $writer = new Xlsx($spreadsheet);

    // Write the file to output
    $writer->save('php://output');

    exit;
}
function export_excel_history_points($request)
{
    $ctype = $request['ctype'];
    $user_id = $request['user_id'];
    // Kiểm tra xem ctype và user_id có tồn tại và có kiểu dữ liệu đúng không
    if (empty($ctype)) {
        // Nếu không, trả về lỗi hoặc thực hiện các xử lý cần thiết
        // Ví dụ: Trả về một thông báo lỗi
        return new WP_Error('invalid_params', 'Invalid parameters.', array('status' => 400));
    }
    if (empty($user_id)) {
        // Kiểm tra giá trị của ctype, nếu là coins thì hiển thị lịch sử giao dịch của loại tiền coins
        if ($ctype == 'coins') {
            $args = [
                'number' => -1,
                'ctype' => 'coins',

            ];
        } else {
            // Nếu không, hiển thị lịch sử giao dịch của 'mycred_default'
            $args = [
                'number' => -1,
                'ctype' => 'mycred_default',

            ];
            $ctype = "mycred_default";
        }
    } else {
        // Kiểm tra giá trị của ctype, nếu là coins thì hiển thị lịch sử giao dịch của loại tiền coins
        if ($ctype == 'coins') {
            $args = [
                'number' => -1,
                'ctype' => 'coins',
                'user_id' => $user_id // Thêm user_id vào tham số truy vấn
            ];
        } else {
            // Nếu không, hiển thị lịch sử giao dịch của 'mycred_default'
            $args = [
                'number' => -1,
                'ctype' => 'mycred_default',
                'user_id' => $user_id // Thêm user_id vào tham số truy vấn
            ];
            $ctype = "mycred_default";
        }
    }


    $log = new myCRED_Query_Log($args);
    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();

    // Get active sheet
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $sheet->setCellValue('A1', 'Username');
    $sheet->setCellValue('B1', 'Points Added');
    $sheet->setCellValue('C1', 'Points Deducted');
    $sheet->setCellValue('D1', 'History Balance');
    $sheet->setCellValue('E1', 'Description');
    $sheet->setCellValue('F1', 'Date');
    $row = 2;

    // Khởi tạo một biến để lưu trữ số điểm của mỗi người dùng hiện tại
    $current_balance = array();

    // Khởi tạo một mảng để lưu trữ tổng số điểm lũy kế cho mỗi người dùng
    $accumulated_points = array();

    // Lặp qua các giao dịch của mỗi người dùng
    foreach ($log->results as $entry) {
        $user_id = $entry->user_id;

        // Kiểm tra xem người dùng có trong mảng $accumulated_points chưa
        if (!isset($accumulated_points[$user_id])) {
            // Nếu chưa có, khởi tạo tổng số điểm lũy kế là 0
            $accumulated_points[$user_id] = get_user_meta($user_id, $ctype, true);
        }

        // Cộng hoặc trừ điểm từ mỗi giao dịch vào tổng số điểm lũy kế của người dùng
        $accumulated_points[$user_id] -= $entry->creds;

        // Lưu trữ số điểm hiện tại của mỗi người dùng
        $current_balance[$user_id] = $accumulated_points[$user_id];

        // Format lại điểm thành số nguyên và ngày thành dạng "H:i:s Y-m-d"
        $points_formatted = number_format($entry->creds, null, ',', ',');

        $total_points_formatted = number_format($accumulated_points[$user_id], null, ',', ',');

        $date_formatted = date("H:i:s Y-m-d", $entry->time);

        // Điền dữ liệu vào file Excel
        $sheet->setCellValue('A' . $row, $user_id);
        // Kiểm tra nếu $entry->creds > 0, hiển thị trong cột B, ngược lại hiển thị trong cột C
        if ($entry->creds > 0) {
            $sheet->setCellValue('B' . $row, $points_formatted);
            $sheet->setCellValue('C' . $row, ''); // Đặt giá trị trống cho cột C
        } else {
            $sheet->setCellValue('B' . $row, ''); // Đặt giá trị trống cho cột B
            $sheet->setCellValue('C' . $row, $points_formatted);
        }
        $sheet->setCellValue('D' . $row, $total_points_formatted);
        $sheet->setCellValue('E' . $row, $entry->entry);
        $sheet->setCellValue('F' . $row, $date_formatted);
        $row++;
    }


    // Sau khi lặp qua tất cả các giao dịch, bạn có thể sử dụng $accumulated_points để có được tổng số điểm lũy kế cho mỗi người dùng và $current_balance để có được số điểm hiện tại của mỗi người dùng


    $log->reset_query();
    // Set header for Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="user_data.xlsx"');
    header('Cache-Control: max-age=0');

    // Create Xlsx Writer
    $writer = new Xlsx($spreadsheet);

    // Write the file to output
    $writer->save('php://output');

    exit;
    // Populate user data

}