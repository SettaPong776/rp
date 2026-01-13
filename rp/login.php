<?php
// กำหนดชื่อหน้า
$page_title = "เข้าสู่ระบบ";

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
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    $error = '';

    // ตรวจสอบว่ามีข้อมูลครบหรือไม่
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        // ค้นหาผู้ใช้ในฐานข้อมูล
        $query = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            // ตรวจสอบรหัสผ่าน
            if (password_verify($password, $user['password'])) {
                // ล็อกอินสำเร็จ
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];

                // บันทึกการเข้าสู่ระบบใน log
                $user_id = $user['user_id'];
                $log_message = "ผู้ใช้ " . $user['fullname'] . " (" . $user['username'] . ") เข้าสู่ระบบ";

                // ส่งการแจ้งเตือนไปยัง Telegram (เฉพาะผู้ดูแลระบบ)
                if ($user['role'] == 'admin') {
                    send_telegram_notification("<b>การเข้าสู่ระบบ</b>\n\nผู้ดูแลระบบ: " . $user['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
                }

                // Redirect ไปยังหน้าที่เหมาะสม
                if (in_array($user['role'], ['admin', 'building_staff'])) {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = 'รหัสผ่านไม่ถูกต้อง';
            }
        } else {
            $error = 'ไม่พบชื่อผู้ใช้นี้ในระบบ';
        }
    }
}

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-lg">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="bx bx-lock-alt text-primary" style="font-size: 4rem;"></i>
                    <h2 class="mt-3 fw-bold">เข้าสู่ระบบ</h2>
                    <p class="text-muted">กรุณาเข้าสู่ระบบเพื่อใช้งานระบบแจ้งซ่อม</p>
                </div>

                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bx bx-error-circle me-1"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="mb-4">
                        <label for="username" class="form-label">ชื่อผู้ใช้</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username"
                                placeholder="กรอกชื่อผู้ใช้" required
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">รหัสผ่าน</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-lock-alt"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="กรอกรหัสผ่าน" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bx bx-log-in me-2"></i>เข้าสู่ระบบ
                        </button>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0">ยังไม่มีบัญชีผู้ใช้? <a href="register.php"
                            class="text-primary fw-bold">สมัครสมาชิก</a></p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <i class="bx bx-info-circle text-info me-3" style="font-size: 2rem;"></i>
                    <div>
                        <h5 class="mb-1">ต้องการความช่วยเหลือ?</h5>
                        <p class="text-muted mb-0">หากคุณมีปัญหาในการเข้าสู่ระบบ กรุณาติดต่อผู้ดูแลระบบ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>