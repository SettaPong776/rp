<?php
// กำหนดชื่อหน้า
$page_title = "สมัครสมาชิก";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ========== Auto-create ตาราง departments ถ้ายังไม่มี ==========
$create_dept_table = "CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $create_dept_table);

// Insert ข้อมูลเริ่มต้นถ้าตารางว่าง
$_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM departments"))['c'];
if ($_cnt == 0) {
    $default_depts = [
        'สำนักส่งเสริมวิชาการและงานทะเบียน', 'สถาบันวิจัยและพัฒนา',
        'สำนักศิลปะและวัฒนธรรม', 'สำนักวิทยบริการและเทคโนโลยีสารสนเทศ',
        'สำนักงานอธิการบดี กองกลาง', 'สำนักงานอธิการบดี กองนโยบายและแผน',
        'สำนักงานอธิการบดี กองพัฒนานักศึกษา', 'คณะครุศาสตร์',
        'คณะมนุษยศาสตร์และสังคมศาสตร์', 'คณะวิทยาการจัดการ',
        'คณะวิทยาศาสตร์และเทคโนโลยี', 'คณะเทคโนโลยีอุตสาหกรรม',
        'โรงเรียนสาธิตมหาวิทยาลัยราชภัฏเลย',
    ];
    foreach ($default_depts as $i => $d) {
        db_insert("INSERT IGNORE INTO departments (name, sort_order) VALUES (?, ?)", "si", [$d, $i + 1]);
    }
}

// ดึงรายการแผนก/ฝ่ายจาก DB
$dept_rows = mysqli_query($conn, "SELECT name FROM departments ORDER BY name ASC");
$departments_from_db = [];
while ($r = mysqli_fetch_assoc($dept_rows)) {
    $departments_from_db[] = $r['name'];
}

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
    if (empty($username) || empty($password) || empty($confirm_password) || empty($fullname) || empty($email) || empty($department) || empty($phone)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } elseif ($password !== $confirm_password) {
        $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
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
                    "INSERT INTO users (username, password, fullname, email, department, phone, role) VALUES (?, ?, ?, ?, ?, ?, 'user')",
                    "ssssss",
                    [$username, $hashed_password, $fullname, $email, $department, $phone]
                );

                if ($insert_id) {
                    // สมัครสมาชิกสำเร็จ
                    $success = 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่านของคุณ';

                    // ส่งการแจ้งเตือนไปยัง Telegram
                    send_telegram_notification("<b>มีผู้ใช้ใหม่สมัครสมาชิก</b>\n\nชื่อผู้ใช้: $username\nชื่อ-นามสกุล: $fullname\nแผนก: $department\nเวลา: " . thai_date(date('Y-m-d H:i:s')));

                    // รีเซ็ตฟอร์ม
                    $username = $fullname = $email = $department = $phone = '';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
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

                        <!-- แผนก/ฝ่าย เต็มความกว้าง -->
                        <div class="mb-3">
                            <label for="department" class="form-label">แผนก/ฝ่าย <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-start gap-0">
                                <span class="input-group-text bg-light"
                                    style="border-radius:.375rem 0 0 .375rem; border-right:0; height:38px;">
                                    <i class="bx bx-building"></i>
                                </span>
                                <div style="flex:1; min-width:0;">
                                    <select id="department" name="department" class="form-control"
                                        placeholder="พิมพ์เพื่อค้นหาแผนก/ฝ่าย">
                                        <option value="">-- เลือกแผนก/ฝ่าย --</option>
                                        <?php
                                        $selected_dept = isset($department) ? $department : '';
                                        foreach ($departments_from_db as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept); ?>"
                                                <?php echo ($selected_dept === $dept) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div id="dept-error" class="text-danger small mt-1" style="display:none;">
                            กรุณาเลือกแผนก/ฝ่าย
                        </div>

                        <!-- เบอร์โทรศัพท์ เต็มความกว้าง -->
                        <div class="mb-3">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bx bx-phone"></i>
                                </span>
                                <input type="text" class="form-control" id="phone" name="phone"
                                    placeholder="กรอกเบอร์โทรศัพท์" required
                                    value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                            </div>
                        </div>

                        <!-- รหัสผ่าน 2 คอลัมน์ -->
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
                                <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
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

<!-- Tom Select CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<style>
    /* ปรับให้ Tom Select เข้ากับ input-group */
    #department+.ts-wrapper .ts-control {
        border-radius: 0 .375rem .375rem 0 !important;
        border-left: 0 !important;
    }

    #department+.ts-wrapper {
        width: 100%;
    }

    .ts-wrapper.single .ts-control {
        background-color: #fff;
    }

    .ts-wrapper.single.input-active .ts-control {
        background-color: #fff;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var ts = new TomSelect('#department', {
            create: false,
            allowEmptyOption: true,
            placeholder: 'พิมพ์เพื่อค้นหาแผนก/ฝ่าย',
            // ไม่เรียงลำดับ เพื่อให้ -- เลือกแผนก/ฝ่าย -- อยู่บนสุดเสมอ
            sortField: false,
            onInitialize: function () {
                // ถ้ายังไม่มีแผนกถูกเลือกจริง ให้ล้าง item ออก
                // เพื่อให้กล่องแสดง placeholder แทน
                if (this.getValue() === '') {
                    this.clear(true);
                }
            }
        });

        // JS Validation: บังคับเลือกแผนก/ฝ่าย ก่อน submit
        // (Tom Select ซ่อน <select> ดั้งเดิม ทำให้ browser required ไม่ทำงาน)
        var form = document.querySelector('form');
        var deptError = document.getElementById('dept-error');

        form.addEventListener('submit', function (e) {
            var val = ts.getValue();
            var tsControl = document.querySelector('#department + .ts-wrapper .ts-control');

            if (!val || val === '') {
                e.preventDefault();
                // แสดง border แดง
                if (tsControl) tsControl.style.border = '1px solid #dc3545';
                // แสดงข้อความ error
                if (deptError) deptError.style.display = 'block';
                // scroll ไปยังช่องแผนก
                document.querySelector('.ts-wrapper').scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                if (tsControl) tsControl.style.border = '';
                if (deptError) deptError.style.display = 'none';
            }
        });

        // ล้าง error เมื่อเลือกแผนกแล้ว
        ts.on('change', function (value) {
            var tsControl = document.querySelector('#department + .ts-wrapper .ts-control');
            if (value && value !== '') {
                if (tsControl) tsControl.style.border = '';
                if (deptError) deptError.style.display = 'none';
            }
        });
    });
</script>