<?php
// กำหนดชื่อหน้า
$page_title = "จัดการรายการแจ้งซ่อม";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินและเป็นแอดมินหรือไม่
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'building_staff'])) {
    header('Location: login.php');
    exit();
}

// อัพเดตสถานะรายการแจ้งซ่อม
if (isset($_POST['update_status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = trim($_POST['new_status']);
    $admin_remark = trim($_POST['admin_remark']);

    // อัพเดตสถานะในฐานข้อมูล
    if ($new_status == 'completed') {
        $update_success = db_execute(
            "UPDATE repair_requests SET status = ?, admin_remark = ?, completed_date = NOW() WHERE request_id = ?",
            "ssi",
            [$new_status, $admin_remark, $request_id]
        );
    } else {
        $update_success = db_execute(
            "UPDATE repair_requests SET status = ?, admin_remark = ? WHERE request_id = ?",
            "ssi",
            [$new_status, $admin_remark, $request_id]
        );
    }

    if ($update_success) {
        // บันทึกประวัติการอัพเดท
        add_request_history($request_id, $_SESSION['user_id'], $new_status, $admin_remark);

        // ดึงข้อมูลรายการแจ้งซ่อม
        $result = db_select(
            "SELECT r.*, u.fullname, u.email, c.category_name 
             FROM repair_requests r 
             JOIN users u ON r.user_id = u.user_id 
             JOIN categories c ON r.category_id = c.category_id 
             WHERE r.request_id = ?",
            "i",
            [$request_id]
        );
        $request = mysqli_fetch_assoc($result);

        // ส่งการแจ้งเตือนไปยัง Telegram
        $status_text = "";
        switch ($new_status) {
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

        send_telegram_notification("<b>มีการอัพเดตสถานะรายการแจ้งซ่อม</b>\n\nหมายเลข: #" . $request_id .
            "\nเรื่อง: " . $request['title'] .
            "\nผู้แจ้ง: " . $request['fullname'] .
            "\nหมวดหมู่: " . $request['category_name'] .
            "\nสถานะใหม่: " . $status_text .
            "\nหมายเหตุ: " . ($admin_remark ?: 'ไม่มี') .
            "\nอัพเดตโดย: " . $_SESSION['fullname'] .
            "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));

        $success = 'อัพเดตสถานะรายการแจ้งซ่อมเรียบร้อยแล้ว';
    } else {
        $error = 'เกิดข้อผิดพลาดในการอัพเดตสถานะ';
    }
}

// ดึงข้อมูลรายการแจ้งซ่อมทั้งหมด
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';

// สร้าง query ด้วย prepared statement
$query = "SELECT r.*, c.category_name, u.fullname as requester_name 
          FROM repair_requests r 
          JOIN categories c ON r.category_id = c.category_id 
          JOIN users u ON r.user_id = u.user_id 
          WHERE 1=1";
$types = "";
$params = [];

if ($status_filter) {
    $query .= " AND r.status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

if ($category_filter) {
    $query .= " AND r.category_id = ?";
    $types .= "i";
    $params[] = $category_filter;
}

if ($date_filter) {
    $query .= " AND DATE(r.created_at) = ?";
    $types .= "s";
    $params[] = $date_filter;
}

$query .= " ORDER BY r.created_at DESC";
$requests = db_select($query, $types, $params);

// ดึงข้อมูลหมวดหมู่
$categories = db_select("SELECT * FROM categories ORDER BY category_name");

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-list-ul me-2"></i>จัดการรายการแจ้งซ่อม
    </h1>
    <a href="create_request.php" class="btn btn-primary">
        <i class="bx bx-plus-circle me-1"></i>แจ้งซ่อมใหม่
    </a>
</div>

<!-- แสดงข้อความแจ้งเตือน -->
<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-1"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-1"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

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
                <label for="status" class="form-label">สถานะ</label>
                <select class="form-select" id="status" name="status">
                    <option value="">ทั้งหมด</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ
                    </option>
                    <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>
                        กำลังดำเนินการ</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น
                    </option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>ยกเลิก</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">หมวดหมู่</label>
                <select class="form-select" id="category" name="category">
                    <option value="">ทั้งหมด</option>
                    <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?php echo $category['category_id']; ?>" <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                            <?php echo $category['category_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date" class="form-label">วันที่แจ้ง</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="d-grid gap-2 w-100">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-filter me-1"></i>กรองข้อมูล
                    </button>
                    <a href="admin_requests.php" class="btn btn-outline-secondary">
                        <i class="bx bx-reset me-1"></i>ล้างตัวกรอง
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- รายการแจ้งซ่อม -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-list-ul me-2"></i>รายการแจ้งซ่อมทั้งหมด
        </h6>
        <a href="admin_reports.php" class="btn btn-sm btn-success">
            <i class="bx bx-export me-1"></i>ส่งออกรายงาน
        </a>
    </div>
    <div class="card-body">
        <?php if (mysqli_num_rows($requests) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable">
                    <thead class="bg-light">
                        <tr>
                            <th>หมายเลข</th>
                            <th>ผู้แจ้ง</th>
                            <th>เรื่อง</th>
                            <th>หมวดหมู่</th>
                            <th>สถานที่</th>
                            <th>สถานะ</th>
                            <th>ความสำคัญ</th>
                            <th>วันที่แจ้ง</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = mysqli_fetch_assoc($requests)): ?>
                            <tr>
                                <td>#<?php echo $request['request_id']; ?></td>
                                <td><?php echo $request['requester_name']; ?></td>
                                <td><?php echo $request['title']; ?></td>
                                <td><?php echo $request['category_name']; ?></td>
                                <td><?php echo $request['location'] ?: '-'; ?></td>
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
                                <td>
                                    <?php
                                    $priority_badges = [
                                        'low' => '<span class="badge bg-success">ต่ำ</span>',
                                        'medium' => '<span class="badge bg-warning text-dark">ปานกลาง</span>',
                                        'high' => '<span class="badge bg-danger">สูง</span>',
                                        'urgent' => '<span class="badge bg-danger"><i class="bx bx-error-circle me-1"></i>เร่งด่วน</span>'
                                    ];
                                    echo $priority_badges[$request['priority']];
                                    ?>
                                </td>
                                <td><?php echo thai_date($request['created_at'], 'j M Y'); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_request.php?id=<?php echo $request['request_id']; ?>"
                                            class="btn btn-sm btn-primary">
                                            <i class="bx bx-show-alt"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                            data-bs-target="#updateStatusModal"
                                            data-request-id="<?php echo $request['request_id']; ?>"
                                            data-title="<?php echo $request['title']; ?>"
                                            data-status="<?php echo $request['status']; ?>"
                                            data-remark="<?php echo $request['admin_remark']; ?>">
                                            <i class="bx bx-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bx bx-clipboard-x text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">ไม่พบรายการแจ้งซ่อม</h5>
                <p class="text-muted">ไม่พบรายการแจ้งซ่อมที่ตรงกับเงื่อนไขที่กำหนด</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal อัพเดตสถานะ -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">อัพเดตสถานะรายการแจ้งซ่อม</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="request_id" name="request_id">
                    <div class="mb-3">
                        <label class="form-label">หมายเลขรายการ</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-hash"></i>
                            </span>
                            <input type="text" class="form-control" id="request_number" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อเรื่อง</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-heading"></i>
                            </span>
                            <input type="text" class="form-control" id="request_title" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="new_status" class="form-label">สถานะใหม่ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-stats"></i>
                            </span>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <option value="pending">รอดำเนินการ</option>
                                <option value="in_progress">กำลังดำเนินการ</option>
                                <option value="completed">เสร็จสิ้น</option>
                                <option value="rejected">ยกเลิก</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="admin_remark" class="form-label">หมายเหตุ</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-text"></i>
                            </span>
                            <textarea class="form-control" id="admin_remark" name="admin_remark" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // เปิด modal อัพเดตสถานะ
        const updateStatusModal = document.getElementById('updateStatusModal');
        if (updateStatusModal) {
            updateStatusModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const requestId = button.getAttribute('data-request-id');
                const title = button.getAttribute('data-title');
                const status = button.getAttribute('data-status');
                const remark = button.getAttribute('data-remark');

                const modalRequestId = updateStatusModal.querySelector('#request_id');
                const modalRequestNumber = updateStatusModal.querySelector('#request_number');
                const modalRequestTitle = updateStatusModal.querySelector('#request_title');
                const modalNewStatus = updateStatusModal.querySelector('#new_status');
                const modalAdminRemark = updateStatusModal.querySelector('#admin_remark');

                modalRequestId.value = requestId;
                modalRequestNumber.value = '#' + requestId;
                modalRequestTitle.value = title;
                modalNewStatus.value = status;
                modalAdminRemark.value = remark;
            });
        }
    });
</script>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>