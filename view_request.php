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
    $new_priority = trim($_POST['new_priority']);

    // อัพโหลดรูปภาพหลายรูป (สูงสุด 5 รูป)
    $file_paths = [];
    if (isset($_FILES['update_images']) && !empty(array_filter($_FILES['update_images']['size']))) {
        $upload_dir = 'uploads/';
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);

        $file_count = count($_FILES['update_images']['name']);
        if ($file_count > 5) {
            $error = 'อัพโหลดได้สูงสุด 5 รูปเท่านั้น';
        } else {
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['update_images']['error'][$i] !== UPLOAD_ERR_OK || $_FILES['update_images']['size'][$i] == 0)
                    continue;
                $file_ext = strtolower(pathinfo(basename($_FILES['update_images']['name'][$i]), PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_ext)) {
                    $error = "ไฟล์ '" . htmlspecialchars($_FILES['update_images']['name'][$i]) . "' ไม่ใช่นามสกุลที่รองรับ";
                    break;
                }
                if ($_FILES['update_images']['size'][$i] > 5 * 1024 * 1024) {
                    $error = "ไฟล์ '" . htmlspecialchars($_FILES['update_images']['name'][$i]) . "' มีขนาดเกิน 5MB";
                    break;
                }
                $new_name = uniqid() . '_' . $i . '.' . $file_ext;
                $dest = $upload_dir . $new_name;
                if (move_uploaded_file($_FILES['update_images']['tmp_name'][$i], $dest)) {
                    $file_paths[] = $dest;
                } else {
                    $error = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
                    break;
                }
            }
        }
    }
    $file_path = empty($file_paths) ? '' : json_encode($file_paths, JSON_UNESCAPED_UNICODE);

    // อัพเดตสถานะในฐานข้อมูล
    if ($new_status == 'completed') {
        $update_success = db_execute(
            "UPDATE repair_requests SET status = ?, admin_remark = ?, priority = ?, completed_date = NOW() WHERE request_id = ?",
            "sssi",
            [$new_status, $admin_remark, $new_priority, $request_id]
        );
    } else {
        $update_success = db_execute(
            "UPDATE repair_requests SET status = ?, admin_remark = ?, priority = ? WHERE request_id = ?",
            "sssi",
            [$new_status, $admin_remark, $new_priority, $request_id]
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

// ดึงประวัติการซ่อมของครุภัณฑ์เดียวกัน (ถ้ามีหมายเลขครุภัณฑ์)
$asset_history = null;
$asset_repair_count = 0;
if (!empty($request['asset_number'])) {
    $asset_history = db_select(
        "SELECT r.request_id, r.title, r.status, r.created_at, r.completed_date,
                u.fullname as requester_name, c.category_name
         FROM repair_requests r
         JOIN users u ON r.user_id = u.user_id
         JOIN categories c ON r.category_id = c.category_id
         WHERE r.asset_number = ? AND r.request_id != ?
         ORDER BY r.created_at DESC",
        "si",
        [$request['asset_number'], $request_id]
    );
    // นับจำนวนครั้งที่ซ่อมทั้งหมด (รวมรายการปัจจุบันด้วย)
    $count_result = db_select(
        "SELECT COUNT(*) as total FROM repair_requests WHERE asset_number = ?",
        "s",
        [$request['asset_number']]
    );
    $count_row = mysqli_fetch_assoc($count_result);
    $asset_repair_count = $count_row['total'];
}

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
            <a href="print_request.php?id=<?php echo $request_id; ?>" class="btn btn-danger" target="_blank">
                <i class="bx bxs-file-pdf me-1"></i>รายงาน PDF
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
                <?php if ($request['asset_number']): ?>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bx bx-barcode me-2 text-primary"></i>
                        <span>หมายเลขครุภัณฑ์:
                            <strong><?php echo htmlspecialchars($request['asset_number']); ?></strong>
                            <span class="badge bg-secondary ms-2">ซ่อมแล้ว <?php echo $asset_repair_count; ?> ครั้ง</span>
                        </span>
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
                        <?php
                        // รองรับทั้ง JSON array (ใหม่) และ path เดียว (เก่า)
                        $img_decoded = json_decode($request['image'], true);
                        if (is_array($img_decoded) && count($img_decoded) > 0) {
                            $images_list = $img_decoded;
                        } elseif (!empty($request['image'])) {
                            $images_list = [$request['image']];
                        } else {
                            $images_list = [];
                        }
                        ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($images_list as $idx => $img_path): ?>
                                <a href="<?php echo htmlspecialchars($img_path); ?>" target="_blank"
                                    title="รูปที่ <?php echo $idx + 1; ?>">
                                    <img src="<?php echo htmlspecialchars($img_path); ?>"
                                        alt="รูปภาพประกอบที่ <?php echo $idx + 1; ?>" class="img-thumbnail"
                                        style="width:120px;height:120px;object-fit:cover;">
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($images_list) > 1): ?>
                            <div class="form-text mt-1">คลิกที่รูปเพื่อดูขนาดเต็ม (<?php echo count($images_list); ?> รูป)</div>
                        <?php endif; ?>
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

        <?php if (!empty($request['asset_number']) && $asset_history && mysqli_num_rows($asset_history) > 0 && in_array($_SESSION['role'], ['admin', 'building_staff'])): ?>
            <!-- ประวัติการซ่อมของครุภัณฑ์นี้ (แสดงเฉพาะ admin/building_staff) -->
            <div class="card shadow mb-4 border-warning">
                <div class="card-header py-3" style="background-color: #FFF8E7;">
                    <h6 class="m-0 fw-bold text-warning">
                        <i class="bx bx-history me-2"></i>ประวัติการซ่อมครุภัณฑ์หมายเลข
                        <span class="text-dark"><?php echo htmlspecialchars($request['asset_number']); ?></span>
                        <span class="badge bg-warning text-dark ms-2">ซ่อมแล้วทั้งหมด <?php echo $asset_repair_count; ?>
                            ครั้ง</span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th>
                                    <th>เรื่อง</th>
                                    <th class="text-center">สถานะ</th>
                                    <th>วันที่แจ้ง</th>
                                    <th>วันที่เสร็จ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ah = mysqli_fetch_assoc($asset_history)): ?>
                                    <?php
                                    $status_badges = [
                                        'pending' => '<span class="badge bg-warning text-dark">รอดำเนินการ</span>',
                                        'in_progress' => '<span class="badge bg-info text-white">กำลังดำเนินการ</span>',
                                        'completed' => '<span class="badge bg-success">เสร็จสิ้น</span>',
                                        'rejected' => '<span class="badge bg-danger">ยกเลิก</span>'
                                    ];
                                    ?>
                                    <tr>
                                        <td>#<?php echo $ah['request_id']; ?></td>
                                        <td><?php echo htmlspecialchars($ah['title']); ?></td>
                                        <td class="text-center"><?php echo $status_badges[$ah['status']] ?? '-'; ?></td>
                                        <td><?php echo thai_date($ah['created_at'], 'j M Y'); ?></td>
                                        <td><?php echo $ah['completed_date'] ? thai_date($ah['completed_date'], 'j M Y') : '-'; ?>
                                        </td>
                                        <td>
                                            <a href="view_request.php?id=<?php echo $ah['request_id']; ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="bx bx-show-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
                                            <?php
                                            $h_decoded = json_decode($history['image'], true);
                                            $h_images = is_array($h_decoded) ? $h_decoded : [$history['image']];
                                            foreach ($h_images as $h_img):
                                                ?>
                                                <a href="<?php echo htmlspecialchars($h_img); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($h_img); ?>" class="img-thumbnail"
                                                        style="width:70px;height:70px;object-fit:cover;">
                                                </a>
                                            <?php endforeach; ?>
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
                                <label for="new_priority" class="form-label">ความสำคัญ <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-flag"></i>
                                    </span>
                                    <select class="form-select" id="new_priority" name="new_priority" required>
                                        <option value="low" <?php echo $request['priority'] == 'low' ? 'selected' : ''; ?>>ต่ำ
                                        </option>
                                        <option value="medium" <?php echo $request['priority'] == 'medium' ? 'selected' : ''; ?>>ปานกลาง</option>
                                        <option value="high" <?php echo $request['priority'] == 'high' ? 'selected' : ''; ?>>
                                            สูง</option>
                                        <option value="urgent" <?php echo $request['priority'] == 'urgent' ? 'selected' : ''; ?>>เร่งด่วน</option>
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
                                    <label class="form-label">แนบรูปภาพ (ถ้ามี) <span class="text-muted small">สูงสุด 5
                                            รูป</span></label>
                                    <input type="file" name="update_images[]" id="update_images" class="form-control"
                                        accept="image/*" multiple>
                                    <div class="form-text">jpg, jpeg, png, gif (ไม่เกิน 5MB ต่อรูป)</div>
                                    <div id="update-image-error" class="text-danger small mt-1" style="display:none;"></div>
                                    <div id="update-image-preview" class="d-flex flex-wrap gap-2 mt-2"></div>
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

    <script>
    (function () {
        const input    = document.getElementById('update_images');
        const errBox   = document.getElementById('update-image-error');
        const preview  = document.getElementById('update-image-preview');
        const MAX      = 5;

        if (!input) return;

        input.addEventListener('change', function () {
            preview.innerHTML = '';
            errBox.style.display = 'none';
            input.classList.remove('is-invalid');

            const files = Array.from(this.files);
            if (files.length > MAX) {
                errBox.textContent = `\u0e40\u0e25\u0e37\u0e2d\u0e01\u0e44\u0e14\u0e49\u0e2a\u0e39\u0e07\u0e2a\u0e38\u0e14 ${MAX} \u0e23\u0e39\u0e1b\u0e40\u0e17\u0e48\u0e32\u0e19\u0e31\u0e49\u0e19 (\u0e04\u0e38\u0e13\u0e40\u0e25\u0e37\u0e2d\u0e01 ${files.length} \u0e23\u0e39\u0e1b)`;
                errBox.style.display = 'block';
                input.classList.add('is-invalid');
                input.value = '';
                return;
            }

            files.forEach((file, idx) => {
                if (!file.type.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = function (e) {
                    const wrap  = document.createElement('div');
                    wrap.className = 'position-relative';
                    wrap.style.cssText = 'width:70px;height:70px;';

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-thumbnail';
                    img.style.cssText = 'width:70px;height:70px;object-fit:cover;';

                    const badge = document.createElement('span');
                    badge.className = 'position-absolute top-0 start-0 badge bg-primary';
                    badge.style.fontSize = '0.6rem';
                    badge.textContent = `\u0e23\u0e39\u0e1b ${idx + 1}`;

                    wrap.appendChild(img);
                    wrap.appendChild(badge);
                    preview.appendChild(wrap);
                };
                reader.readAsDataURL(file);
            });
        });

        // reset preview \u0e40\u0e21\u0e37\u0e48\u0e2d\u0e1b\u0e34\u0e14 modal
        const modal = document.getElementById('updateStatusModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                preview.innerHTML = '';
                errBox.style.display = 'none';
                input.value = '';
                input.classList.remove('is-invalid');
            });
        }
    })();
    </script>

    <?php
    // แสดงส่วน footer
    include 'includes/footer.php';
    ?>