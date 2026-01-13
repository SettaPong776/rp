<?php
// กำหนดชื่อหน้า
$page_title = "สมัครสมาชิก";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินอยู่แล้วหรือไม่
if (isset($_SESSION['user_id'])) {
    // ถ้าล็อกอินแล้ว ให้ redirect ไปยังหน้าที่เหมาะสม
    if (in_array($_SESSION['role'], ['admin', 'building_staff'])) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับข้อมูลจากฟอร์มและทำความสะอาด
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $fullname = clean_input($_POST['fullname']);
    $email = clean_input($_POST['email']);
    $department = clean_input($_POST['department']);
    $phone = clean_input($_POST['phone']);

    $error = '';

    // ตรวจสอบว่ามีข้อมูลครบหรือไม่
    if (empty($username) || empty($password) || empty($confirm_password) || empty($fullname) || empty($email)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } elseif ($password !== $confirm_password) {
        $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        // ตรวจสอบว่าชื่อผู้ใช้ซ้ำหรือไม่
        $query = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $error = 'ชื่อผู้ใช้นี้มีในระบบแล้ว กรุณาใช้ชื่อผู้ใช้อื่น';
        } else {
            // ตรวจสอบว่าอีเมลซ้ำหรือไม่
            $query = "SELECT * FROM users WHERE email = '$email'";
            $result = mysqli_query($conn, $query);

            if (mysqli_num_rows($result) > 0) {
                $error = 'อีเมลนี้มีในระบบแล้ว กรุณาใช้อีเมลอื่น';
            } else {
                // เข้ารหัสรหัสผ่าน
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // บันทึกข้อมูลลงในฐานข้อมูล
                $query = "INSERT INTO users (username, password, fullname, email, department, phone, role) 
                          VALUES ('$username', '$hashed_password', '$fullname', '$email', '$department', '$phone', 'user')";

                if (mysqli_query($conn, $query)) {
                    // สมัครสมาชิกสำเร็จ
                    $success = 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่านของคุณ';

                    // ส่งการแจ้งเตือนไปยัง Telegram
                    send_telegram_notification("<b>มีผู้ใช้ใหม่สมัครสมาชิก</b>\n\nชื่อผู้ใช้: $username\nชื่อ-นามสกุล: $fullname\nแผนก: $department\nเวลา: " . thai_date(date('Y-m-d H:i:s')));

                    // รีเซ็ตฟอร์ม
                    $username = $fullname = $email = $department = $phone = '';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . mysqli_error($conn);
                }
            }
        }
    }
}

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-lg">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="bx bx-user-plus text-primary" style="font-size: 4rem;"></i>
                    <h2 class="mt-3 fw-bold">สมัครสมาชิก</h2>
                    <p class="text-muted">สร้างบัญชีใหม่เพื่อใช้งานระบบแจ้งซ่อม</p>
                </div>

                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bx bx-error-circle me-1"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($success) && !empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bx bx-check-circle me-1"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <div class="text-center mb-4">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="bx bx-log-in me-2"></i>เข้าสู่ระบบ
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">ชื่อผู้ใช้ <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username"
                                        placeholder="กรอกชื่อผู้ใช้" required
                                        value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                                </div>
                                <small class="text-muted">ชื่อผู้ใช้สำหรับการเข้าสู่ระบบ</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="fullname" class="form-label">ชื่อ-นามสกุล <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-id-card"></i>
                                    </span>
                                    <input type="text" class="form-control" id="fullname" name="fullname"
                                        placeholder="กรอกชื่อ-นามสกุล" required
                                        value="<?php echo isset($fullname) ? htmlspecialchars($fullname) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="กรอกอีเมล"
                                    required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">แผนก/ฝ่าย</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-building"></i>
                                    </span>
                                    <input type="text" class="form-control" id="department" name="department"
                                        placeholder="กรอกแผนก/ฝ่าย"
                                        value="<?php echo isset($department) ? htmlspecialchars($department) : ''; ?>">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-phone"></i>
                                    </span>
                                    <input type="text" class="form-control" id="phone" name="phone"
                                        placeholder="กรอกเบอร์โทรศัพท์"
                                        value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-lock-alt"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password"
                                        placeholder="กรอกรหัสผ่าน" required>
                                </div>
                                <small class="text-muted">รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password"
                                        name="confirm_password" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bx bx-user-plus me-2"></i>สมัครสมาชิก
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0">มีบัญชีผู้ใช้แล้ว? <a href="login.php" class="text-primary fw-bold">เข้าสู่ระบบ</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>