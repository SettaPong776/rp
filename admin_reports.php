<?php
// กำหนดชื่อหน้า
$page_title = "รายงาน";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินและเป็นแอดมินหรือไม่
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'building_staff'])) {
    header('Location: login.php');
    exit();
}

// ตัวแปรสำหรับกรองข้อมูล
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01'); // เริ่มต้นเดือนปัจจุบัน
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-d'); // วันปัจจุบัน
$category_id = isset($_GET['category_id']) ? clean_input($_GET['category_id']) : '';
$status = isset($_GET['status']) ? clean_input($_GET['status']) : '';

// สร้างรายงาน Excel
if (isset($_POST['export_excel'])) {
    // ตั้งค่า header
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="repair_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // สร้างเงื่อนไขสำหรับ query
    $where_conditions = [];
    $where_conditions[] = "r.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

    if (!empty($category_id)) {
        $where_conditions[] = "r.category_id = '$category_id'";
    }

    if (!empty($status)) {
        $where_conditions[] = "r.status = '$status'";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // สร้าง query สำหรับดึงข้อมูล พร้อมดึงชื่อผู้ที่อัพเดทสถานะเป็นเสร็จสิ้น
    $query = "SELECT r.request_id, r.title, r.description, r.location, r.priority, r.status, 
              c.category_name, u.fullname as requester_name, u.department, 
              r.created_at, r.completed_date, r.admin_remark,
              (SELECT u2.fullname FROM request_history h 
               JOIN users u2 ON h.user_id = u2.user_id 
               WHERE h.request_id = r.request_id AND h.status = 'completed' 
               ORDER BY h.created_at DESC LIMIT 1) as completed_by_user
              FROM repair_requests r 
              JOIN categories c ON r.category_id = c.category_id 
              JOIN users u ON r.user_id = u.user_id 
              WHERE $where_clause
              ORDER BY r.created_at DESC";

    $result = mysqli_query($conn, $query);

    // สร้างหัวตาราง
    echo '<!DOCTYPE html>';
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '</head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr>';
    echo '<th colspan="12" style="font-size: 16pt; text-align: center;">รายงานการแจ้งซ่อม</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th colspan="12" style="text-align: center;">วันที่ ' . thai_date($start_date, 'j F Y') . ' ถึง ' . thai_date($end_date, 'j F Y') . '</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th style="background-color: #f2f2f2;">หมายเลข</th>';
    echo '<th style="background-color: #f2f2f2;">เรื่อง</th>';
    echo '<th style="background-color: #f2f2f2;">รายละเอียด</th>';
    echo '<th style="background-color: #f2f2f2;">สถานที่</th>';
    echo '<th style="background-color: #f2f2f2;">หมวดหมู่</th>';
    echo '<th style="background-color: #f2f2f2;">ความสำคัญ</th>';
    echo '<th style="background-color: #f2f2f2;">สถานะ</th>';
    echo '<th style="background-color: #f2f2f2;">ผู้แจ้ง</th>';
    echo '<th style="background-color: #f2f2f2;">แผนก</th>';
    echo '<th style="background-color: #f2f2f2;">วันที่แจ้ง</th>';
    echo '<th style="background-color: #f2f2f2;">วันที่เสร็จสิ้น</th>';
    echo '<th style="background-color: #f2f2f2;">หมายเหตุ</th>';
    echo '<th style="background-color: #f2f2f2;">ผู้ดำเนินการ</th>';
    echo '</tr>';

    // แสดงข้อมูล
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr>';
            echo '<td>#' . $row['request_id'] . '</td>';
            echo '<td>' . $row['title'] . '</td>';
            echo '<td>' . $row['description'] . '</td>';
            echo '<td>' . ($row['location'] ?: '-') . '</td>';
            echo '<td>' . $row['category_name'] . '</td>';

            // แปลงค่าความสำคัญเป็นภาษาไทย
            $priority_text = "";
            switch ($row['priority']) {
                case 'low':
                    $priority_text = "ต่ำ";
                    break;
                case 'medium':
                    $priority_text = "ปานกลาง";
                    break;
                case 'high':
                    $priority_text = "สูง";
                    break;
                case 'urgent':
                    $priority_text = "เร่งด่วน";
                    break;
            }
            echo '<td>' . $priority_text . '</td>';

            // แปลงค่าสถานะเป็นภาษาไทย
            $status_text = "";
            switch ($row['status']) {
                case 'pending':
                    $status_text = "รอดำเนินการ";
                    break;
                case 'in_progress':
                    $status_text = "กำลังดำเนินการ";
                    break;
                case 'completed':
                    $status_text = "เสร็จสิ้น";
                    break;
                case 'rejected':
                    $status_text = "ยกเลิก";
                    break;
            }
            echo '<td>' . $status_text . '</td>';

            echo '<td>' . $row['requester_name'] . '</td>';
            echo '<td>' . ($row['department'] ?: '-') . '</td>';
            echo '<td>' . thai_date($row['created_at'], 'j F Y H:i') . '</td>';
            echo '<td>' . ($row['completed_date'] ? thai_date($row['completed_date'], 'j F Y H:i') : '-') . '</td>';
            echo '<td>' . ($row['admin_remark'] ?: '-') . '</td>';
            echo '<td>' . ($row['completed_by_user'] ?: '-') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="12" align="center">ไม่พบข้อมูล</td></tr>';
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

// ดึงข้อมูลสถิติรายการแจ้งซ่อม
$query = "SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
            COUNT(*) as total_count
          FROM repair_requests
          WHERE created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

if (!empty($category_id)) {
    $query .= " AND category_id = '$category_id'";
}

if (!empty($status)) {
    $query .= " AND status = '$status'";
}

$result = mysqli_query($conn, $query);
$request_stats = mysqli_fetch_assoc($result);

// ดึงข้อมูลประสิทธิภาพการแก้ไข
$query = "SELECT 
            AVG(CASE 
                WHEN status = 'completed' AND completed_date IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, created_at, completed_date) / 168 
                ELSE NULL 
            END) as avg_completion_time,
            MAX(CASE 
                WHEN status = 'completed' AND completed_date IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, created_at, completed_date) / 168 
                ELSE NULL 
            END) as max_completion_time,
            MIN(CASE 
                WHEN status = 'completed' AND completed_date IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, created_at, completed_date) / 168 
                ELSE NULL 
            END) as min_completion_time,
            COUNT(CASE 
                WHEN status = 'completed' AND completed_date IS NOT NULL 
                THEN 1 
                ELSE NULL 
            END) as completed_count
          FROM repair_requests
          WHERE created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

if (!empty($category_id)) {
    $query .= " AND category_id = '$category_id'";
}

$result = mysqli_query($conn, $query);
$completion_stats = mysqli_fetch_assoc($result);

// ดึงข้อมูลหมวดหมู่
$query = "SELECT * FROM categories ORDER BY category_name";
$categories = mysqli_query($conn, $query);

// ดึงข้อมูลรายละเอียดรายการแจ้งซ่อม
$query = "SELECT r.*, c.category_name, u.fullname as requester_name, u.department 
          FROM repair_requests r 
          JOIN categories c ON r.category_id = c.category_id 
          JOIN users u ON r.user_id = u.user_id 
          WHERE r.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

if (!empty($category_id)) {
    $query .= " AND r.category_id = '$category_id'";
}

if (!empty($status)) {
    $query .= " AND r.status = '$status'";
}

$query .= " ORDER BY r.created_at DESC";
$requests = mysqli_query($conn, $query);

// ดึงข้อมูลสถิติตามหมวดหมู่
$query = "SELECT c.category_name, 
            COUNT(*) as request_count,
            COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN r.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN r.status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN r.status = 'rejected' THEN 1 END) as rejected_count
          FROM categories c
          LEFT JOIN repair_requests r ON c.category_id = r.category_id
          WHERE (r.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' OR r.created_at IS NULL)
          GROUP BY c.category_id
          ORDER BY request_count DESC";

$category_stats = mysqli_query($conn, $query);

// ดึงข้อมูลสถิติตามเดือน (สำหรับกราฟ)
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as request_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
          FROM repair_requests
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month ASC";

$monthly_stats = mysqli_query($conn, $query);

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-bar-chart-alt-2 me-2"></i>รายงาน
    </h1>
    <form method="POST">
        <button type="submit" name="export_excel" class="btn btn-success">
            <i class="bx bx-export me-1"></i>ส่งออก Excel
        </button>
    </form>
</div>

<!-- ส่วนกรองข้อมูล -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-filter-alt me-2"></i>ตัวกรองข้อมูล
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">วันที่เริ่มต้น</label>
                <input type="date" class="form-control" id="start_date" name="start_date"
                    value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">วันที่สิ้นสุด</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label for="category_id" class="form-label">หมวดหมู่</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">ทั้งหมด</option>
                    <?php mysqli_data_seek($categories, 0); ?>
                    <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?php echo $category['category_id']; ?>" <?php echo $category_id == $category['category_id'] ? 'selected' : ''; ?>>
                            <?php echo $category['category_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">สถานะ</label>
                <select class="form-select" id="status" name="status">
                    <option value="">ทั้งหมด</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                    <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ
                    </option>
                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>ยกเลิก</option>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bx bx-filter me-1"></i>กรองข้อมูล
                </button>
                <a href="admin_reports.php" class="btn btn-outline-secondary">
                    <i class="bx bx-reset me-1"></i>ล้างตัวกรอง
                </a>
            </div>
        </form>
    </div>
</div>

<!-- สรุปสถิติ -->
<div class="row mb-4">
    <!-- รายการทั้งหมด -->
    <div class="col-6 col-md-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #EBF3FE;">
                            <i class="bx bx-package text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-primary"><?php echo $request_stats['total_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">รายการทั้งหมด</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- เสร็จสิ้น -->
    <div class="col-6 col-md-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #E6F8F1;">
                            <i class="bx bx-check-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-success"><?php echo $request_stats['completed_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">เสร็จสิ้น</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- กำลังดำเนินการ -->
    <div class="col-6 col-md-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #E7F4FF;">
                            <i class="bx bx-loader text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-info"><?php echo $request_stats['in_progress_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">กำลังดำเนินการ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- รอดำเนินการ -->
    <div class="col-6 col-md-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #FFF6E9;">
                            <i class="bx bx-time text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-warning"><?php echo $request_stats['pending_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">รอดำเนินการ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- แผนภูมิและประสิทธิภาพ -->
<div class="row mb-4">
    <!-- แผนภูมิแท่งแสดงสถิติรายเดือน -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bx bx-bar-chart-alt me-2"></i>สถิติรายการแจ้งซ่อมรายเดือน
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:350px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ประสิทธิภาพการแก้ไข -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bx bx-timer me-2"></i>ประสิทธิภาพการแก้ไข
                </h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <h2 class="display-4 text-primary fw-bold">
                        <?php echo $completion_stats['completed_count'] > 0 ? number_format($completion_stats['avg_completion_time'], 2) : '0.00'; ?>
                    </h2>
                    <p class="text-muted">เวลาเฉลี่ยในการแก้ไข (สัปดาห์)</p>
                </div>
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-success">
                            <?php echo $completion_stats['completed_count'] > 0 ? number_format($completion_stats['min_completion_time'], 2) : '0.00'; ?>
                        </h4>
                        <p class="text-muted">เร็วที่สุด (สัปดาห์)</p>
                    </div>
                    <div class="col-6">
                        <h4 class="text-danger">
                            <?php echo $completion_stats['completed_count'] > 0 ? number_format($completion_stats['max_completion_time'], 2) : '0.00'; ?>
                        </h4>
                        <p class="text-muted">ช้าที่สุด (สัปดาห์)</p>
                    </div>
                </div>
                <div class="progress mt-3">
                    <?php
                    $completion_percent = 0;
                    if ($request_stats['total_count'] > 0) {
                        $completion_percent = ($request_stats['completed_count'] / $request_stats['total_count']) * 100;
                    }
                    ?>
                    <div class="progress-bar bg-success" role="progressbar"
                        style="width: <?php echo $completion_percent; ?>%"
                        aria-valuenow="<?php echo $completion_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">อัตราการแก้ไขสำเร็จ: <?php echo round($completion_percent); ?>%</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- สถิติตามหมวดหมู่ -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-category me-2"></i>สถิติตามหมวดหมู่
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>หมวดหมู่</th>
                        <th class="text-center">รายการทั้งหมด</th>
                        <th class="text-center">เสร็จสิ้น</th>
                        <th class="text-center">กำลังดำเนินการ</th>
                        <th class="text-center">รอดำเนินการ</th>
                        <th class="text-center">ยกเลิก</th>
                        <th class="text-center">อัตราความสำเร็จ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($category = mysqli_fetch_assoc($category_stats)): ?>
                        <tr>
                            <td><?php echo $category['category_name']; ?></td>
                            <td class="text-center"><?php echo $category['request_count']; ?></td>
                            <td class="text-center"><?php echo $category['completed_count']; ?></td>
                            <td class="text-center"><?php echo $category['in_progress_count']; ?></td>
                            <td class="text-center"><?php echo $category['pending_count']; ?></td>
                            <td class="text-center"><?php echo $category['rejected_count']; ?></td>
                            <td class="text-center">
                                <?php
                                $success_rate = 0;
                                if ($category['request_count'] > 0) {
                                    $success_rate = ($category['completed_count'] / $category['request_count']) * 100;
                                }
                                echo round($success_rate) . '%';
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- รายละเอียดรายการแจ้งซ่อม -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-list-ul me-2"></i>รายละเอียดรายการแจ้งซ่อม
        </h6>
    </div>
    <div class="card-body">
        <?php if (mysqli_num_rows($requests) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable">
                    <thead class="bg-light">
                        <tr>
                            <th>หมายเลข</th>
                            <th>เรื่อง</th>
                            <th>หมวดหมู่</th>
                            <th>ผู้แจ้ง</th>
                            <th>แผนก</th>
                            <th>สถานะ</th>
                            <th>วันที่แจ้ง</th>
                            <th>วันที่เสร็จสิ้น</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = mysqli_fetch_assoc($requests)): ?>
                            <tr>
                                <td>#<?php echo $request['request_id']; ?></td>
                                <td><?php echo $request['title']; ?></td>
                                <td><?php echo $request['category_name']; ?></td>
                                <td><?php echo $request['requester_name']; ?></td>
                                <td><?php echo $request['department'] ?: '-'; ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'pending' => '<span class="badge bg-warning text-dark">รอดำเนินการ</span>',
                                        'in_progress' => '<span class="badge bg-info text-white">กำลังดำเนินการ</span>',
                                        'completed' => '<span class="badge bg-success">เสร็จสิ้น</span>',
                                        'rejected' => '<span class="badge bg-danger">ยกเลิก</span>'
                                    ];
                                    echo $status_badges[$request['status']];
                                    ?>
                                </td>
                                <td><?php echo thai_date($request['created_at'], 'j M Y'); ?></td>
                                <td><?php echo $request['completed_date'] ? thai_date($request['completed_date'], 'j M Y') : '-'; ?>
                                </td>
                                <td>
                                    <a href="view_request.php?id=<?php echo $request['request_id']; ?>"
                                        class="btn btn-sm btn-primary">
                                        <i class="bx bx-show-alt"></i> ดูรายละเอียด
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bx bx-search-alt text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">ไม่พบข้อมูล</h5>
                <p class="text-muted">ไม่พบรายการแจ้งซ่อมที่ตรงกับเงื่อนไขที่กำหนด</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // สร้างแผนภูมิแท่งแสดงสถิติรายเดือน
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const months = [];
        const requestData = [];
        const completedData = [];
        const pendingData = [];
        const inProgressData = [];

        <?php
        mysqli_data_seek($monthly_stats, 0);
        while ($month = mysqli_fetch_assoc($monthly_stats)):
            // แปลงรูปแบบเดือนเป็นภาษาไทย
            $m = date('n', strtotime($month['month'] . '-01'));
            $y = date('Y', strtotime($month['month'] . '-01'));
            $thai_month = [
                1 => 'ม.ค.',
                2 => 'ก.พ.',
                3 => 'มี.ค.',
                4 => 'เม.ย.',
                5 => 'พ.ค.',
                6 => 'มิ.ย.',
                7 => 'ก.ค.',
                8 => 'ส.ค.',
                9 => 'ก.ย.',
                10 => 'ต.ค.',
                11 => 'พ.ย.',
                12 => 'ธ.ค.'
            ];
            $thai_year = $y + 543;
            $display_month = $thai_month[$m] . ' ' . substr($thai_year, 2, 2);
            ?>
            months.push('<?php echo $display_month; ?>');
            requestData.push(<?php echo $month['request_count']; ?>);
            completedData.push(<?php echo $month['completed_count']; ?>);
            pendingData.push(<?php echo $month['pending_count']; ?>);
            inProgressData.push(<?php echo $month['in_progress_count']; ?>);
        <?php endwhile; ?>

        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'รายการทั้งหมด',
                        data: requestData,
                        backgroundColor: '#4E73DF',
                        borderWidth: 0,
                        borderRadius: 5
                    },
                    {
                        label: 'เสร็จสิ้น',
                        data: completedData,
                        backgroundColor: '#1CC88A',
                        borderWidth: 0,
                        borderRadius: 5
                    },
                    {
                        label: 'รอดำเนินการ',
                        data: pendingData,
                        backgroundColor: '#F6C23E',
                        borderWidth: 0,
                        borderRadius: 5
                    },
                    {
                        label: 'กำลังดำเนินการ',
                        data: inProgressData,
                        backgroundColor: '#36B9CC',
                        borderWidth: 0,
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                family: 'Prompt'
                            }
                        }
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                family: 'Prompt'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Prompt'
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    });
</script>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>