<?php
// กำหนดชื่อหน้า
$page_title = "จัดการผู้ใช้งาน";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินและเป็นแอดมินหรือไม่
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'building_staff'])) {
    header('Location: login.php');
    exit();
}

// จัดการการเพิ่มผู้ใช้ใหม่
if (isset($_POST['add_user'])) {
    if ($_SESSION['role'] == 'building_staff') {
        $error = 'คุณไม่มีสิทธิ์เพิ่มผู้ใช้ใหม่';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $department = trim($_POST['department']);
        $phone = trim($_POST['phone']);
        $role = trim($_POST['role']);

        // ตรวจสอบว่าชื่อผู้ใช้ซ้ำหรือไม่
        $result = db_select("SELECT * FROM users WHERE username = ?", "s", [$username]);

        if ($result && mysqli_num_rows($result) > 0) {
            $error = 'ชื่อผู้ใช้นี้มีในระบบแล้ว กรุณาใช้ชื่อผู้ใช้อื่น';
        } else {
            // ตรวจสอบว่าอีเมลซ้ำหรือไม่
            $result = db_select("SELECT * FROM users WHERE email = ?", "s", [$email]);

            if ($result && mysqli_num_rows($result) > 0) {
                $error = 'อีเมลนี้มีในระบบแล้ว กรุณาใช้อีเมลอื่น';
            } else {
                // เข้ารหัสรหัสผ่าน
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // บันทึกข้อมูลลงในฐานข้อมูล
                $insert_id = db_insert(
                    "INSERT INTO users (username, password, fullname, email, department, phone, role) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    "sssssss",
                    [$username, $hashed_password, $fullname, $email, $department, $phone, $role]
                );

                if ($insert_id) {
                    $success = 'เพิ่มผู้ใช้งานใหม่เรียบร้อยแล้ว';

                    // ส่งการแจ้งเตือนไปยัง Telegram
                    send_telegram_notification("<b>มีผู้ใช้ใหม่ในระบบ</b>\n\nชื่อผู้ใช้: $username\nชื่อ-นามสกุล: $fullname\nบทบาท: " . ($role == 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป') . "\nเพิ่มโดย: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
                } else {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
                }
            }
        }
    }
}

// จัดการการแก้ไขผู้ใช้
if (isset($_POST['edit_user'])) {
    if ($_SESSION['role'] == 'building_staff') {
        $error = 'คุณไม่มีสิทธิ์แก้ไขข้อมูลผู้ใช้';
    } else {
        $user_id = intval($_POST['user_id']);
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $department = trim($_POST['department']);
        $phone = trim($_POST['phone']);
        $role = trim($_POST['role']);

        // ตรวจสอบว่าอีเมลซ้ำหรือไม่ (ยกเว้นผู้ใช้ปัจจุบัน)
        $result = db_select("SELECT * FROM users WHERE email = ? AND user_id != ?", "si", [$email, $user_id]);

        if ($result && mysqli_num_rows($result) > 0) {
            $error = 'อีเมลนี้มีในระบบแล้ว กรุณาใช้อีเมลอื่น';
        } else {
            // อัพเดตข้อมูลผู้ใช้
            $update_success = db_execute(
                "UPDATE users SET fullname = ?, email = ?, department = ?, phone = ?, role = ? WHERE user_id = ?",
                "sssssi",
                [$fullname, $email, $department, $phone, $role, $user_id]
            );

            if ($update_success) {
                $success = 'อัพเดตข้อมูลผู้ใช้เรียบร้อยแล้ว';

                // ส่งการแจ้งเตือนไปยัง Telegram
                send_telegram_notification("<b>มีการอัพเดตข้อมูลผู้ใช้</b>\n\nรหัส: $user_id\nชื่อ-นามสกุล: $fullname\nบทบาท: " . ($role == 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป') . "\nอัพเดตโดย: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
            } else {
                $error = 'เกิดข้อผิดพลาดในการอัพเดตข้อมูล';
            }
        }
    }
}

// จัดการการรีเซ็ตรหัสผ่าน
if (isset($_POST['reset_password'])) {
    if ($_SESSION['role'] == 'building_staff') {
        $error = 'คุณไม่มีสิทธิ์รีเซ็ตรหัสผ่าน';
    } else {
        $user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'];

        // เข้ารหัสรหัสผ่านใหม่
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // อัพเดตรหัสผ่าน
        $update_success = db_execute("UPDATE users SET password = ? WHERE user_id = ?", "si", [$hashed_password, $user_id]);

        if ($update_success) {
            $success = 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว';

            // ดึงข้อมูลผู้ใช้
            $result = db_select("SELECT username, fullname FROM users WHERE user_id = ?", "i", [$user_id]);
            $user = mysqli_fetch_assoc($result);

            // ส่งการแจ้งเตือนไปยัง Telegram
            send_telegram_notification("<b>มีการรีเซ็ตรหัสผ่าน</b>\n\nรหัส: $user_id\nชื่อผู้ใช้: " . $user['username'] . "\nชื่อ-นามสกุล: " . $user['fullname'] . "\nรีเซ็ตโดย: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
        } else {
            $error = 'เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน';
        }
    }
}

// จัดการการลบผู้ใช้
if (isset($_POST['delete_user'])) {
    if ($_SESSION['role'] == 'building_staff') {
        $error = 'คุณไม่มีสิทธิ์ลบผู้ใช้';
    } else {
        $user_id = intval($_POST['user_id']);

        // ป้องกันการลบตัวเอง
        if ($user_id == $_SESSION['user_id']) {
            $error = 'ไม่สามารถลบบัญชีผู้ใช้ที่กำลังใช้งานอยู่ได้';
        } else {
            // ดึงข้อมูลผู้ใช้ก่อนลบ
            $result = db_select("SELECT username, fullname FROM users WHERE user_id = ?", "i", [$user_id]);
            $user = mysqli_fetch_assoc($result);

            // ลบผู้ใช้
            $delete_success = db_execute("DELETE FROM users WHERE user_id = ?", "i", [$user_id]);

            if ($delete_success) {
                $success = 'ลบผู้ใช้งานเรียบร้อยแล้ว';

                // ส่งการแจ้งเตือนไปยัง Telegram
                send_telegram_notification("<b>มีการลบผู้ใช้งาน</b>\n\nรหัส: $user_id\nชื่อผู้ใช้: " . $user['username'] . "\nชื่อ-นามสกุล: " . $user['fullname'] . "\nลบโดย: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
            } else {
                $error = 'เกิดข้อผิดพลาดในการลบผู้ใช้งาน';
            }
        }
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$users = db_select("SELECT * FROM users ORDER BY role, fullname");


// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-user me-2"></i>จัดการผู้ใช้งาน
    </h1>
    <?php if ($_SESSION['role'] != 'building_staff'): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bx bx-user-plus me-1"></i>เพิ่มผู้ใช้ใหม่
        </button>
    <?php endif; ?>
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

<!-- รายการผู้ใช้งาน -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-list-ul me-2"></i>รายการผู้ใช้งานทั้งหมด
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead class="bg-light">
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อผู้ใช้</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>อีเมล</th>
                        <th>แผนก</th>
                        <th>บทบาท</th>
                        <th>วันที่สร้าง</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = mysqli_fetch_assoc($users)): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['fullname']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo $user['department'] ?: '-'; ?></td>
                            <td>
                                <?php if ($user['role'] == 'admin'): ?>
                                    <span class="badge bg-danger">ผู้ดูแลระบบ</span>
                                <?php elseif ($user['role'] == 'building_staff'): ?>
                                    <span class="badge bg-warning text-dark">งานอาคาร</span>
                                <?php else: ?>
                                    <span class="badge bg-info">ผู้ใช้งานทั่วไป</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo thai_date($user['created_at'], 'j M Y'); ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if ($_SESSION['role'] != 'building_staff'): ?>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#editUserModal" data-user-id="<?php echo $user['user_id']; ?>"
                                            data-username="<?php echo $user['username']; ?>"
                                            data-fullname="<?php echo $user['fullname']; ?>"
                                            data-email="<?php echo $user['email']; ?>"
                                            data-department="<?php echo $user['department']; ?>"
                                            data-phone="<?php echo $user['phone']; ?>" data-role="<?php echo $user['role']; ?>">
                                            <i class="bx bx-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                            data-bs-target="#resetPasswordModal" data-user-id="<?php echo $user['user_id']; ?>"
                                            data-username="<?php echo $user['username']; ?>">
                                            <i class="bx bx-key"></i>
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                data-bs-target="#deleteUserModal" data-user-id="<?php echo $user['user_id']; ?>"
                                                data-username="<?php echo $user['username']; ?>">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted"><small>ไม่มีสิทธิ์จัดการ</small></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal เพิ่มผู้ใช้ใหม่ -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">เพิ่มผู้ใช้ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">ชื่อผู้ใช้ <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-user"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-lock-alt"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="fullname" class="form-label">ชื่อ-นามสกุล <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-id-card"></i>
                                </span>
                                <input type="text" class="form-control" id="fullname" name="fullname" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label">แผนก/ฝ่าย</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-building"></i>
                                </span>
                                <input type="text" class="form-control" id="department" name="department">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-phone"></i>
                                </span>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="role" class="form-label">บทบาท <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-user-circle"></i>
                                </span>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user">ผู้ใช้งานทั่วไป</option>
                                    <option value="building_staff">งานอาคาร</option>
                                    <option value="admin">ผู้ดูแลระบบ</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="add_user" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal แก้ไขผู้ใช้ -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">แก้ไขข้อมูลผู้ใช้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_username" class="form-label">ชื่อผู้ใช้</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-user"></i>
                                </span>
                                <input type="text" class="form-control" id="edit_username" readonly>
                            </div>
                            <small class="text-muted">ไม่สามารถแก้ไขชื่อผู้ใช้ได้</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_fullname" class="form-label">ชื่อ-นามสกุล <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-id-card"></i>
                                </span>
                                <input type="text" class="form-control" id="edit_fullname" name="fullname" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_department" class="form-label">แผนก/ฝ่าย</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-building"></i>
                                </span>
                                <input type="text" class="form-control" id="edit_department" name="department">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_phone" class="form-label">เบอร์โทรศัพท์</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-phone"></i>
                                </span>
                                <input type="text" class="form-control" id="edit_phone" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_role" class="form-label">บทบาท <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-user-circle"></i>
                                </span>
                                <select class="form-select" id="edit_role" name="role" required>
                                    <option value="user">ผู้ใช้งานทั่วไป</option>
                                    <option value="building_staff">งานอาคาร</option>
                                    <option value="admin">ผู้ดูแลระบบ</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal รีเซ็ตรหัสผ่าน -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">รีเซ็ตรหัสผ่าน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="reset_user_id" name="user_id">
                    <p>คุณกำลังจะรีเซ็ตรหัสผ่านของผู้ใช้: <strong id="reset_username"></strong></p>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">รหัสผ่านใหม่ <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-lock-alt"></i>
                            </span>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bx bx-info-circle me-1"></i>การรีเซ็ตรหัสผ่านจะมีผลทันที
                        และผู้ใช้ต้องใช้รหัสผ่านใหม่ในการเข้าสู่ระบบครั้งต่อไป
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="reset_password" class="btn btn-warning">
                        <i class="bx bx-reset me-1"></i>รีเซ็ตรหัสผ่าน
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ลบผู้ใช้ -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">ยืนยันการลบผู้ใช้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete_user_id" name="user_id">
                    <p>คุณแน่ใจหรือไม่ที่ต้องการลบผู้ใช้: <strong id="delete_username"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="bx bx-error-circle me-1"></i>การลบผู้ใช้จะลบข้อมูลทั้งหมดที่เกี่ยวข้องกับผู้ใช้นี้
                        รวมถึงรายการแจ้งซ่อมทั้งหมด การดำเนินการนี้ไม่สามารถย้อนกลับได้
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i>ลบผู้ใช้
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // เปิด modal แก้ไขผู้ใช้
        const editUserModal = document.getElementById('editUserModal');
        if (editUserModal) {
            editUserModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const username = button.getAttribute('data-username');
                const fullname = button.getAttribute('data-fullname');
                const email = button.getAttribute('data-email');
                const department = button.getAttribute('data-department');
                const phone = button.getAttribute('data-phone');
                const role = button.getAttribute('data-role');

                const modalUserId = editUserModal.querySelector('#edit_user_id');
                const modalUsername = editUserModal.querySelector('#edit_username');
                const modalFullname = editUserModal.querySelector('#edit_fullname');
                const modalEmail = editUserModal.querySelector('#edit_email');
                const modalDepartment = editUserModal.querySelector('#edit_department');
                const modalPhone = editUserModal.querySelector('#edit_phone');
                const modalRole = editUserModal.querySelector('#edit_role');

                modalUserId.value = userId;
                modalUsername.value = username;
                modalFullname.value = fullname;
                modalEmail.value = email;
                modalDepartment.value = department;
                modalPhone.value = phone;
                modalRole.value = role;
            });
        }

        // เปิด modal รีเซ็ตรหัสผ่าน
        const resetPasswordModal = document.getElementById('resetPasswordModal');
        if (resetPasswordModal) {
            resetPasswordModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const username = button.getAttribute('data-username');

                const modalUserId = resetPasswordModal.querySelector('#reset_user_id');
                const modalUsername = resetPasswordModal.querySelector('#reset_username');

                modalUserId.value = userId;
                modalUsername.textContent = username;
            });
        }

        // เปิด modal ลบผู้ใช้
        const deleteUserModal = document.getElementById('deleteUserModal');
        if (deleteUserModal) {
            deleteUserModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const username = button.getAttribute('data-username');

                const modalUserId = deleteUserModal.querySelector('#delete_user_id');
                const modalUsername = deleteUserModal.querySelector('#delete_username');

                modalUserId.value = userId;
                modalUsername.textContent = username;
            });
        }
    });
</script>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>