<?php
// กำหนดชื่อหน้า
$page_title = "ข้อมูลส่วนตัว";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ดึงข้อมูลผู้ใช้
$user_id = $_SESSION['user_id'];
$result = db_select("SELECT * FROM users WHERE user_id = ?", "i", [$user_id]);
$user = mysqli_fetch_assoc($result);

// จัดการการแก้ไขข้อมูลส่วนตัว
if (isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);

    // ตรวจสอบว่าอีเมลซ้ำหรือไม่ (ยกเว้นผู้ใช้ปัจจุบัน)
    $result = db_select("SELECT * FROM users WHERE email = ? AND user_id != ?", "si", [$email, $user_id]);

    if ($result && mysqli_num_rows($result) > 0) {
        $error = 'อีเมลนี้มีในระบบแล้ว กรุณาใช้อีเมลอื่น';
    } else {
        // อัพเดตข้อมูลผู้ใช้
        $update_success = db_execute(
            "UPDATE users SET fullname = ?, email = ?, department = ?, phone = ? WHERE user_id = ?",
            "ssssi",
            [$fullname, $email, $department, $phone, $user_id]
        );

        if ($update_success) {
            // อัพเดตข้อมูลใน session
            $_SESSION['fullname'] = $fullname;

            $success = 'อัพเดตข้อมูลส่วนตัวเรียบร้อยแล้ว';

            // ดึงข้อมูลผู้ใช้อีกครั้ง
            $result = db_select("SELECT * FROM users WHERE user_id = ?", "i", [$user_id]);
            $user = mysqli_fetch_assoc($result);
        } else {
            $error = 'เกิดข้อผิดพลาดในการอัพเดตข้อมูล';
        }
    }
}

// จัดการการเปลี่ยนรหัสผ่าน
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // ตรวจสอบรหัสผ่านปัจจุบัน
    if (!password_verify($current_password, $user['password'])) {
        $error_password = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    } elseif ($new_password !== $confirm_password) {
        $error_password = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($new_password) < 6) {
        $error_password = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        // เข้ารหัสรหัสผ่านใหม่
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // อัพเดตรหัสผ่าน
        $update_success = db_execute("UPDATE users SET password = ? WHERE user_id = ?", "si", [$hashed_password, $user_id]);

        if ($update_success) {
            $success_password = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
        } else {
            $error_password = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
        }
    }
}

// ดึงข้อมูลสถิติรายการแจ้งซ่อมของผู้ใช้
$result = db_select(
    "SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(*) as total_count
     FROM repair_requests 
     WHERE user_id = ?",
    "i",
    [$user_id]
);
$request_stats = mysqli_fetch_assoc($result);

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-user me-2"></i>ข้อมูลส่วนตัว
    </h1>
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

<!-- ข้อมูลผู้ใช้ -->
<div class="row">
    <div class="col-lg-4 mb-4">
        <!-- ข้อมูลส่วนตัวโดยย่อ -->
        <div class="card shadow mb-4">
            <div class="card-body text-center">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=random"
                    alt="User Avatar" class="rounded-circle mb-3" width="150" height="150">
                <h4 class="fw-bold"><?php echo $user['fullname']; ?></h4>
                <p class="text-muted"><?php echo $user['role'] == 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป'; ?></p>

                <div class="d-flex justify-content-center">
                    <div class="text-center px-3">
                        <h5 class="mb-0"><?php echo $request_stats['total_count']; ?></h5>
                        <small class="text-muted">รายการทั้งหมด</small>
                    </div>
                    <div class="text-center px-3">
                        <h5 class="mb-0"><?php echo $request_stats['completed_count']; ?></h5>
                        <small class="text-muted">เสร็จสิ้น</small>
                    </div>
                    <div class="text-center px-3">
                        <h5 class="mb-0"><?php echo $request_stats['pending_count']; ?></h5>
                        <small class="text-muted">รอดำเนินการ</small>
                    </div>
                </div>

                <hr>

                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bx bx-envelope me-2 text-primary"></i>
                    <span><?php echo $user['email']; ?></span>
                </div>

                <?php if ($user['phone']): ?>
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bx bx-phone me-2 text-primary"></i>
                        <span><?php echo $user['phone']; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($user['department']): ?>
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bx bx-building me-2 text-primary"></i>
                        <span><?php echo $user['department']; ?></span>
                    </div>
                <?php endif; ?>

                <div class="d-flex align-items-center justify-content-center">
                    <i class="bx bx-calendar me-2 text-primary"></i>
                    <span>สมัครสมาชิกเมื่อ: <?php echo thai_date($user['created_at'], 'j F Y'); ?></span>
                </div>
            </div>
        </div>

        <!-- สถิติการแจ้งซ่อม -->
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bx bx-bar-chart-alt-2 me-2"></i>สถิติการแจ้งซ่อม
                </h6>
            </div>
            <div class="card-body">
                <canvas id="requestChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- แท็บเมนู -->
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile"
                            type="button" role="tab" aria-controls="profile" aria-selected="true">
                            <i class="bx bx-user me-1"></i>ข้อมูลส่วนตัว
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password"
                            type="button" role="tab" aria-controls="password" aria-selected="false">
                            <i class="bx bx-lock-alt me-1"></i>เปลี่ยนรหัสผ่าน
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="profileTabsContent">
                    <!-- แท็บข้อมูลส่วนตัว -->
                    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">ชื่อผู้ใช้</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username"
                                        value="<?php echo $user['username']; ?>" readonly>
                                </div>
                                <small class="text-muted">ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</small>
                            </div>
                            <div class="col-md-6">
                                <label for="fullname" class="form-label">ชื่อ-นามสกุล <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-id-card"></i>
                                    </span>
                                    <input type="text" class="form-control" id="fullname" name="fullname"
                                        value="<?php echo $user['fullname']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo $user['email']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="department" class="form-label">แผนก/ฝ่าย</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-building"></i>
                                    </span>
                                    <input type="text" class="form-control" id="department" name="department"
                                        value="<?php echo $user['department']; ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-phone"></i>
                                    </span>
                                    <input type="text" class="form-control" id="phone" name="phone"
                                        value="<?php echo $user['phone']; ?>">
                                </div>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bx bx-save me-1"></i>บันทึกข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- แท็บเปลี่ยนรหัสผ่าน -->
                    <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                        <?php if (isset($success_password)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bx bx-check-circle me-1"></i><?php echo $success_password; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($error_password)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bx bx-error-circle me-1"></i><?php echo $error_password; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="row g-3">
                            <div class="col-md-12">
                                <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="current_password"
                                        name="current_password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">รหัสผ่านใหม่ <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-lock-alt"></i>
                                    </span>
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                        required>
                                </div>
                                <small class="text-muted">รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่ <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-lock-alt"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password"
                                        name="confirm_password" required>
                                </div>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="bx bx-key me-1"></i>เปลี่ยนรหัสผ่าน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- รายการแจ้งซ่อมล่าสุด -->
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bx bx-list-ul me-2"></i>รายการแจ้งซ่อมล่าสุดของคุณ
                </h6>
                <a href="my_requests.php" class="btn btn-sm btn-primary">
                    ดูทั้งหมด <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
            <div class="card-body">
                <?php
                // ดึงรายการแจ้งซ่อมล่าสุด 5 รายการ
                $recent_requests = db_select(
                    "SELECT r.*, c.category_name 
                     FROM repair_requests r 
                     JOIN categories c ON r.category_id = c.category_id 
                     WHERE r.user_id = ? 
                     ORDER BY r.created_at DESC 
                     LIMIT 5",
                    "i",
                    [$user_id]
                );
                ?>

                <?php if (mysqli_num_rows($recent_requests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>หมายเลข</th>
                                    <th>เรื่อง</th>
                                    <th>หมวดหมู่</th>
                                    <th>สถานะ</th>
                                    <th>วันที่แจ้ง</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($request = mysqli_fetch_assoc($recent_requests)): ?>
                                    <tr>
                                        <td>#<?php echo $request['request_id']; ?></td>
                                        <td><?php echo $request['title']; ?></td>
                                        <td><?php echo $request['category_name']; ?></td>
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
                        <i class="bx bx-clipboard-x text-muted" style="font-size: 4rem;"></i>
                        <h5 class="mt-3">ไม่มีรายการแจ้งซ่อม</h5>
                        <p class="text-muted">คุณยังไม่มีรายการแจ้งซ่อมในระบบ</p>
                        <a href="create_request.php" class="btn btn-primary mt-3">
                            <i class="bx bx-plus-circle me-1"></i>แจ้งซ่อมใหม่
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // สร้างแผนภูมิสถิติการแจ้งซ่อม
        const requestCtx = document.getElementById('requestChart').getContext('2d');
        const requestChart = new Chart(requestCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น', 'ยกเลิก'],
                datasets: [{
                    data: [
                        <?php echo $request_stats['pending_count']; ?>,
                        <?php echo $request_stats['in_progress_count']; ?>,
                        <?php echo $request_stats['completed_count']; ?>,
                        <?php echo $request_stats['rejected_count']; ?>
                    ],
                    backgroundColor: [
                        '#FFC107', // รอดำเนินการ
                        '#0DCAF0', // กำลังดำเนินการ
                        '#20C997', // เสร็จสิ้น
                        '#DC3545'  // ยกเลิก
                    ],
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
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
                cutout: '65%',
                animation: {
                    animateScale: true
                }
            }
        });
    });
</script>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>