<?php
// กำหนดชื่อหน้า
$page_title = "รายละเอียดการแจ้งซ่อม";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    if (in_array($_SESSION['role'], ['admin', 'building_staff'])) {
        header('Location: admin_requests.php');
    } else {
        header('Location: my_requests.php');
    }
    exit();
}

$request_id = intval($_GET['id']);

// ดึงข้อมูลรายการแจ้งซ่อม
$result = db_select(
    "SELECT r.*, c.category_name, u.fullname as requester_name, u.email as requester_email, u.department as requester_department, u.phone as requester_phone 
     FROM repair_requests r 
     JOIN categories c ON r.category_id = c.category_id 
     JOIN users u ON r.user_id = u.user_id 
     WHERE r.request_id = ?",
    "i",
    [$request_id]
);

// ตรวจสอบว่ามีข้อมูลหรือไม่
if (!$result || mysqli_num_rows($result) == 0) {
    if (in_array($_SESSION['role'], ['admin', 'building_staff'])) {
        header('Location: admin_requests.php');
    } else {
        header('Location: my_requests.php');
    }
    exit();
}

$request = mysqli_fetch_assoc($result);

// ตรวจสอบสิทธิ์การเข้าถึง
if (!in_array($_SESSION['role'], ['admin', 'building_staff']) && $request['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php');
    exit();
}

// อัพเดตสถานะรายการแจ้งซ่อม (สำหรับแอดมิน)
if (in_array($_SESSION['role'], ['admin', 'building_staff']) && isset($_POST['update_status'])) {
    $new_status = trim($_POST['new_status']);
    $admin_remark = trim($_POST['admin_remark']);

    // อัพโหลดรูปภาพ
    $file_path = "";
    if (isset($_FILES['update_image']) && $_FILES['update_image']['error'] === 0) {
        $file_tmp = $_FILES['update_image']['tmp_name'];
        $file_name = basename($_FILES['update_image']['name']);
        $file_size = $_FILES['update_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_ext) && $file_size <= 5 * 1024 * 1024) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);

            $new_file_name = uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file_tmp, $file_path)) {
                $error = "ไม่สามารถอัพโหลดไฟล์ได้";
                $file_path = null;
            }
        } else {
            $error = "ไฟล์ไม่ถูกต้อง หรือ ขนาดเกิน 5MB";
        }
    }

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
        insert_request_history($request_id, $_SESSION['user_id'], $new_status, $admin_remark, $file_path);

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
            "\nผู้แจ้ง: " . $request['requester_name'] .
            "\nหมวดหมู่: " . $request['category_name'] .
            "\nสถานะใหม่: " . $status_text .
            "\nหมายเหตุ: " . ($admin_remark ?: 'ไม่มี') .
            "\nอัพเดตโดย: " . $_SESSION['fullname'] .
            "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));

        $success = 'อัพเดตสถานะรายการแจ้งซ่อมเรียบร้อยแล้ว';

        // ดึงข้อมูลรายการแจ้งซ่อมอีกครั้งเพื่ออัพเดตข้อมูลที่แสดง
        $result = db_select(
            "SELECT r.*, c.category_name, u.fullname as requester_name, u.email as requester_email, u.department as requester_department, u.phone as requester_phone 
             FROM repair_requests r 
             JOIN categories c ON r.category_id = c.category_id 
             JOIN users u ON r.user_id = u.user_id 
             WHERE r.request_id = ?",
            "i",
            [$request_id]
        );
        $request = mysqli_fetch_assoc($result);
    } else {
        $error = 'เกิดข้อผิดพลาดในการอัพเดตสถานะ';
    }
}


// ดึงข้อมูลประวัติการอัพเดท
$history_result = db_select(
    "SELECT h.*, u.fullname 
     FROM request_history h 
     JOIN users u ON h.user_id = u.user_id 
     WHERE h.request_id = ? 
     ORDER BY h.created_at DESC",
    "i",
    [$request_id]
);

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-detail me-2"></i>รายละเอียดการแจ้งซ่อม #<?php echo $request_id; ?>
    </h1>
    <div>
       
        <?php if (in_array($_SESSION['role'], ['admin', 'building_staff'])): ?>
            <a href="admin_requests.php" class="btn btn-secondary">
                <i class="bx bx-arrow-back me-1"></i>กลับ
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                <i class="bx bx-edit me-1"></i>อัพเดตสถานะ
            </button>
             <a href="print_request.php?id=<?php echo $request_id; ?>" class="btn btn-danger" target="_blank">
            <i class="bx bxs-file-pdf me-1"></i>รายงาน PDF
        </a>
        <?php else: ?>
            <a href="my_requests.php" class="btn btn-secondary">
                <i class="bx bx-arrow-back me-1"></i>กลับ
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- แสดงข้อความแจ้งเตือน -->
<?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-1"></i>บันทึกรายการแจ้งซ่อมเรียบร้อยแล้ว
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

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

<!-- แสดงสถานะรายการ -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h4 class="mb-3"><?php echo $request['title']; ?></h4>
                <div class="mb-3">
                    <?php
                    $status_badges = [
                        'pending' => '<span class="badge bg-warning text-dark fs-6">รอดำเนินการ</span>',
                        'in_progress' => '<span class="badge bg-info text-white fs-6">กำลังดำเนินการ</span>',
                        'completed' => '<span class="badge bg-success fs-6">เสร็จสิ้น</span>',
                        'rejected' => '<span class="badge bg-danger fs-6">ยกเลิก</span>'
                    ];
                    echo $status_badges[$request['status']];

                    $priority_badges = [
                        'low' => '<span class="badge bg-success ms-2 fs-6">ความสำคัญ: ต่ำ</span>',
                        'medium' => '<span class="badge bg-warning text-dark ms-2 fs-6">ความสำคัญ: ปานกลาง</span>',
                        'high' => '<span class="badge bg-danger ms-2 fs-6">ความสำคัญ: สูง</span>',
                        'urgent' => '<span class="badge bg-danger ms-2 fs-6"><i class="bx bx-error-circle me-1"></i>ความสำคัญ: เร่งด่วน</span>'
                    ];
                    echo $priority_badges[$request['priority']];
                    ?>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <i class="bx bx-calendar me-2 text-primary"></i>
                    <span>วันที่แจ้ง: <?php echo thai_date($request['created_at']); ?></span>
                </div>
                <?php if ($request['status'] == 'completed' && $request['completed_date']): ?>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bx bx-check-circle me-2 text-success"></i>
                        <span>วันที่เสร็จสิ้น: <?php echo thai_date($request['completed_date']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="bx bx-category me-2 text-primary"></i>
                    <span>หมวดหมู่: <?php echo $request['category_name']; ?></span>
                </div>
                <?php if ($request['location']): ?>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bx bx-map me-2 text-primary"></i>
                        <span>สถานที่: <?php echo $request['location']; ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">ข้อมูลผู้แจ้ง</h5>
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($request['requester_name']); ?>&background=random"
                                alt="User Avatar" class="rounded-circle me-3" width="50" height="50">
                            <div>
                                <h6 class="mb-0"><?php echo $request['requester_name']; ?></h6>
                                <small class="text-muted"><?php echo $request['requester_email']; ?></small>
                            </div>
                        </div>
                        <?php if ($request['requester_department']): ?>
                            <div class="d-flex align-items-center mb-2">
                                <i class="bx bx-building me-2 text-primary"></i>
                                <span><?php echo $request['requester_department']; ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($request['requester_phone']): ?>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-phone me-2 text-primary"></i>
                                <span><?php echo $request['requester_phone']; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- รายละเอียดการแจ้งซ่อม -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bx bx-text me-2"></i>รายละเอียด
                </h6>
            </div>
            <div class="card-body">
                <p class="card-text"><?php echo nl2br(htmlspecialchars($request['description'])); ?></p>

                <?php if ($request['image']): ?>
                    <div class="mt-4">
                        <h6 class="fw-bold">รูปภาพประกอบ</h6>
                        <a href="<?php echo $request['image']; ?>" target="_blank">
                            <img src="<?php echo $request['image']; ?>" alt="รูปภาพประกอบการแจ้งซ่อม"
                                class="img-fluid img-thumbnail" style="max-height: 300px;">
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($request['admin_remark']): ?>
                    <div class="mt-4">
                        <h6 class="fw-bold">หมายเหตุจากผู้ดูแลระบบ</h6>
                        <div class="alert alert-info">
                            <?php echo nl2br(htmlspecialchars($request['admin_remark'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- ประวัติการอัพเดท -->
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bx bx-history me-2"></i>ประวัติการอัพเดท
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="timeline p-3">
                    <?php
                    // ดึงข้อมูลประวัติพร้อมรูปภาพ เรียงจากล่าสุดก่อน
                    $history_result = db_select(
                        "SELECT h.*, u.fullname 
                         FROM request_history h 
                         LEFT JOIN users u ON h.user_id = u.user_id 
                         WHERE h.request_id = ? 
                         ORDER BY h.created_at DESC",
                        "i",
                        [$request_id]
                    );

                    if (mysqli_num_rows($history_result) > 0):
                        while ($history = mysqli_fetch_assoc($history_result)):
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-item-content">
                                    <div class="timeline-item-date text-muted mb-1 small">
                                        <?php echo thai_date($history['created_at']); ?>
                                    </div>
                                    <div class="timeline-item-title d-flex align-items-center">
                                        <?php
                                        $status_classes = [
                                            'pending' => 'warning',
                                            'in_progress' => 'info',
                                            'completed' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $status_icons = [
                                            'pending' => 'bx-time',
                                            'in_progress' => 'bx-loader',
                                            'completed' => 'bx-check-circle',
                                            'rejected' => 'bx-x-circle'
                                        ];
                                        $status_texts = [
                                            'pending' => 'รอดำเนินการ',
                                            'in_progress' => 'กำลังดำเนินการ',
                                            'completed' => 'เสร็จสิ้น',
                                            'rejected' => 'ยกเลิก'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $status_classes[$history['status']]; ?> me-2">
                                            <i class="bx <?php echo $status_icons[$history['status']]; ?>"></i>
                                            <?php echo $status_texts[$history['status']]; ?>
                                        </span>
                                        <span>โดย <?php echo htmlspecialchars($history['fullname']); ?></span>
                                    </div>

                                    <?php if ($history['remark']): ?>
                                        <div class="timeline-item-body mt-2">
                                            <?php echo nl2br(htmlspecialchars($history['remark'])); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($history['image']): ?>
                                        <div class="timeline-item-images mt-2 d-flex flex-wrap gap-2">
                                            <a href="<?php echo $history['image']; ?>" target="_blank">
                                                <img src="<?php echo $history['image']; ?>" class="img-fluid img-thumbnail"
                                                    style="width: 80px; height: 80px; object-fit: cover;">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        endwhile;
                    else:
                        ?>
                        <div class="text-center py-4">
                            <i class="bx bx-info-circle text-muted" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">ไม่มีประวัติการอัพเดท</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (in_array($_SESSION['role'], ['admin', 'building_staff'])): ?>
        <!-- Modal อัพเดตสถานะ -->
        <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateStatusModalLabel">อัพเดตสถานะรายการแจ้งซ่อม</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="new_status" class="form-label">สถานะใหม่ <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-stats"></i>
                                    </span>
                                    <select class="form-select" id="new_status" name="new_status" required>
                                        <option value="pending" <?php echo $request['status'] == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                                        <option value="in_progress" <?php echo $request['status'] == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                                        <option value="completed" <?php echo $request['status'] == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                                        <option value="rejected" <?php echo $request['status'] == 'rejected' ? 'selected' : ''; ?>>ยกเลิก</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="admin_remark" class="form-label">หมายเหตุ</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-text"></i>
                                    </span>
                                    <textarea class="form-control" id="admin_remark" name="admin_remark"
                                        rows="3"><?php echo $request['admin_remark']; ?></textarea>
                                </div>

                                <div class="mt-2">
                                    <label class="form-label">แนบรูปภาพ (ถ้ามี)</label>
                                    <input type="file" name="update_image" class="form-control" accept="image/*">
                                    <input type="hidden" name="history_id" value="<?= $history_id; ?>">
                                    <div class="form-text">jpg, jpeg, png, gif (ไม่เกิน 5MB)</div>
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
    <?php endif; ?>

    <style>
        .timeline {
            position: relative;
            max-height: 400px;
            overflow-y: auto;
        }

        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }

        .timeline-item:before {
            content: "";
            position: absolute;
            left: 5px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #e9ecef;
        }

        .timeline-item:last-child:before {
            height: 0;
        }

        .timeline-item:after {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #6563ff;
        }

        .timeline-item-content {
            padding-bottom: 20px;
        }

        .timeline-item:last-child .timeline-item-content {
            padding-bottom: 0;
        }
    </style>

    <?php
    // แสดงส่วน footer
    include 'includes/footer.php';
    ?>