<?php
// กำหนดชื่อหน้า
$page_title = "ตั้งค่าระบบ";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินและเป็นแอดมินหรือไม่
if (!isset($_SESSION['user_id']) || !is_admin_role($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// ========== Auto-create ตาราง departments ถ้ายังไม่มี ==========
$create_table_sql = "CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $create_table_sql);

// Insert ข้อมูลเริ่มต้นถ้าตารางยังว่าง
$check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM departments");
$cnt_row = mysqli_fetch_assoc($check);
if ($cnt_row['cnt'] == 0) {
    $default_departments = [
        'สำนักส่งเสริมวิชาการและงานทะเบียน',
        'สถาบันวิจัยและพัฒนา',
        'สำนักศิลปะและวัฒนธรรม',
        'สำนักวิทยบริการและเทคโนโลยีสารสนเทศ',
        'สำนักงานอธิการบดี กองกลาง',
        'สำนักงานอธิการบดี กองนโยบายและแผน',
        'สำนักงานอธิการบดี กองพัฒนานักศึกษา',
        'คณะครุศาสตร์',
        'คณะมนุษยศาสตร์และสังคมศาสตร์',
        'คณะวิทยาการจัดการ',
        'คณะวิทยาศาสตร์และเทคโนโลยี',
        'คณะเทคโนโลยีอุตสาหกรรม',
        'โรงเรียนสาธิตมหาวิทยาลัยราชภัฏเลย',
    ];
    foreach ($default_departments as $i => $dept) {
        db_insert("INSERT IGNORE INTO departments (name, sort_order) VALUES (?, ?)", "si", [$dept, $i + 1]);
    }
}

// ========== จัดการแผนก/ฝ่าย ==========
$dept_success = '';
$dept_error = '';

// เพิ่มแผนก
if (isset($_POST['add_department'])) {
    $dept_name = trim($_POST['dept_name']);
    if (empty($dept_name)) {
        $dept_error = 'กรุณากรอกชื่อแผนก/ฝ่าย';
    } else {
        // หา sort_order สูงสุด
        $max_order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(sort_order) as mo FROM departments"))['mo'] ?? 0;
        $result = db_insert("INSERT INTO departments (name, sort_order) VALUES (?, ?)", "si", [$dept_name, $max_order + 1]);
        if ($result) {
            $dept_success = "เพิ่มแผนก/ฝ่าย \"$dept_name\" เรียบร้อยแล้ว";
        } else {
            $dept_error = 'ไม่สามารถเพิ่มได้ อาจมีชื่อแผนก/ฝ่ายนี้อยู่แล้ว';
        }
    }
}

// ลบแผนก
if (isset($_POST['delete_department'])) {
    $dept_id = (int) $_POST['dept_id'];
    $result = db_execute("DELETE FROM departments WHERE id = ?", "i", [$dept_id]);
    if ($result) {
        $dept_success = 'ลบแผนก/ฝ่ายเรียบร้อยแล้ว';
    } else {
        $dept_error = 'ไม่สามารถลบแผนก/ฝ่ายได้';
    }
}

// แก้ไขแผนก
if (isset($_POST['edit_department'])) {
    $dept_id   = (int) $_POST['dept_id'];
    $dept_name = trim($_POST['dept_name_edit']);
    if (empty($dept_name)) {
        $dept_error = 'กรุณากรอกชื่อแผนก/ฝ่าย';
    } else {
        $result = db_execute("UPDATE departments SET name = ? WHERE id = ?", "si", [$dept_name, $dept_id]);
        if ($result) {
            $dept_success = "แก้ไขแผนก/ฝ่ายเป็น \"$dept_name\" เรียบร้อยแล้ว";
        } else {
            $dept_error = 'ไม่สามารถแก้ไขได้ อาจมีชื่อแผนก/ฝ่ายนี้อยู่แล้ว';
        }
    }
}

// ========== จัดการการอัพเดตการตั้งค่า ==========
if (isset($_POST['update_settings'])) {
    $site_name = clean_input($_POST['site_name']);
    $site_description = clean_input($_POST['site_description']);
    $telegram_bot_token = clean_input($_POST['telegram_bot_token']);
    $telegram_chat_id = clean_input($_POST['telegram_chat_id']);
    $notification_enabled = isset($_POST['notification_enabled']) ? 'true' : 'false';

    $settings_data = [
        'site_name' => $site_name,
        'site_description' => $site_description,
        'telegram_bot_token' => $telegram_bot_token,
        'telegram_chat_id' => $telegram_chat_id,
        'notification_enabled' => $notification_enabled
    ];

    $success = true;

    foreach ($settings_data as $name => $value) {
        $query = "UPDATE settings SET setting_value = '$value' WHERE setting_name = '$name'";
        if (!mysqli_query($conn, $query)) {
            $success = false;
            $error = 'เกิดข้อผิดพลาดในการอัพเดตการตั้งค่า: ' . mysqli_error($conn);
            break;
        }
    }

    if ($success) {
        $success_message = 'อัพเดตการตั้งค่าเรียบร้อยแล้ว';
        if ($notification_enabled == 'true') {
            send_telegram_notification("<b>มีการอัพเดตการตั้งค่าระบบ</b>\n\nผู้ดำเนินการ: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
        }
    }
}

// ทดสอบ Telegram
if (isset($_POST['test_telegram'])) {
    $telegram_bot_token = clean_input($_POST['telegram_bot_token']);
    $telegram_chat_id = clean_input($_POST['telegram_chat_id']);

    $url = "https://api.telegram.org/bot" . $telegram_bot_token . "/sendMessage";
    $data = [
        'chat_id' => $telegram_chat_id,
        'text' => "ทดสอบการเชื่อมต่อกับระบบแจ้ซ่อมออนไลน์\n\nหากคุณได้รับข้อความนี้ แสดงว่าการตั้งค่าสำเร็จแล้ว\n\nเวลา: " . thai_date(date('Y-m-d H:i:s')),
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        if ($response_data['ok']) {
            $test_success = 'ส่งข้อความทดสอบสำเร็จ กรุณาตรวจสอบในแชท Telegram ของคุณ';
        } else {
            $test_error = 'ไม่สามารถส่งข้อความได้: ' . $response_data['description'];
        }
    } else {
        $test_error = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับ Telegram API (HTTP Code: ' . $http_code . ')';
    }
}

// ========== จัดการ SMTP Settings ==========
if (isset($_POST['update_smtp'])) {
    $smtp_fields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from'];
    $smtp_ok = true;
    foreach ($smtp_fields as $field) {
        $val = trim($_POST[$field] ?? '');
        // UPSERT
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM settings WHERE setting_name = '$field'"));
        if ($exists['c'] > 0) {
            $q = db_execute("UPDATE settings SET setting_value = ? WHERE setting_name = ?", "ss", [$val, $field]);
        } else {
            $q = db_insert("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)", "ss", [$field, $val]);
        }
        if (!$q)
            $smtp_ok = false;
    }
    if ($smtp_ok) {
        $smtp_success = 'บันทึกการตั้งค่า SMTP เรียบร้อยแล้ว';
        // reload settings
        $result2 = mysqli_query($conn, "SELECT * FROM settings");
        while ($row = mysqli_fetch_assoc($result2))
            $settings[$row['setting_name']] = $row['setting_value'];
    } else {
        $smtp_error = 'เกิดข้อผิดพลาดในการบันทึก';
    }
}

// ========== ทดสอบส่งอีเมล ==========
if (isset($_POST['test_email'])) {
    $test_to = trim($_POST['test_email_to'] ?? '');
    if (empty($test_to) || !filter_var($test_to, FILTER_VALIDATE_EMAIL)) {
        $smtp_error = 'กรุณากรอกอีเมลผู้รับให้ถูกต้อง';
    } else {
        $sent = send_email(
            $test_to,
            'ทดสอบอีเมล - ระบบแจ้งซ่อม',
            '<h2>ทดสอบอีเมล</h2><p>หากคุณได้รับอีเมลนี้ แสดงว่าการตั้งค่า SMTP ใช้งานได้อย่างถูกต้อง เวลา: ' . date('d/m/Y H:i:s') . '</p>'
        );
        if ($sent) {
            $smtp_success = 'ส่งอีเมลทดสอบไปยัง ' . htmlspecialchars($test_to) . ' สำเร็จ! กรุณาตรวจสอบอีเมลของคุณ';
        } else {
            $smtp_error = 'ไม่สามารถส่งอีเมลได้ กรุณาตรวจสอบการตั้งค่า SMTP อีกครั้ง';
        }
    }
}

// ========== ส่งประกาศผ่านอีเมล ==========
$broadcast_success = '';
$broadcast_error = '';
if (isset($_POST['send_broadcast'])) {
    $bc_subject = trim($_POST['bc_subject'] ?? '');
    $bc_body_raw = trim($_POST['bc_body'] ?? '');
    $bc_target = $_POST['bc_target'] ?? 'all';   // all | building_staff | electrical_staff | plumbing_staff | ac_staff | maintenance_all | head_building | head_electrical | head_plumbing | head_ac | heads_all | admin | user

    if (empty($bc_subject) || empty($bc_body_raw)) {
        $broadcast_error = 'กรุณากรอกหัวข้อและเนื้อหาประกาศให้ครบถ้วน';
    } else {
        // ดึงอีเมล user ตาม target
        if ($bc_target === 'all') {
            $bc_query = "SELECT fullname, email FROM users WHERE email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'building_staff') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'building_staff' AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'electrical_staff') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'electrical_staff' AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'plumbing_staff') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'plumbing_staff' AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'ac_staff') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'ac_staff' AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'maintenance_all') {
            $bc_query = "SELECT fullname, email FROM users WHERE role IN ('building_staff','electrical_staff','plumbing_staff','ac_staff','head_building','head_electrical','head_plumbing','head_ac') AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'head_building') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'head_building' AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'head_electrical') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'head_electrical' AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'head_plumbing') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'head_plumbing' AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'head_ac') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'head_ac' AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'heads_all') {
            $bc_query = "SELECT fullname, email FROM users WHERE role IN ('head_building','head_electrical','head_plumbing','head_ac') AND email IS NOT NULL AND email != ''";
        } elseif ($bc_target === 'admin') {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''";
        } else {
            $bc_query = "SELECT fullname, email FROM users WHERE role = 'user' AND email IS NOT NULL AND email != ''";
        }

        $bc_result = mysqli_query($conn, $bc_query);
        $sent_count = 0;
        $fail_count = 0;

        // template อีเมล
        $site_name_bc = $settings['site_name'] ?? 'ระบบแจ้งซ่อม';

        while ($bc_user = mysqli_fetch_assoc($bc_result)) {
            $bc_html = '<!DOCTYPE html>
<html lang="th"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">
    <tr><td align="center">
      <table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#4e73df,#224abe);padding:28px 40px;text-align:center;">
            <div style="font-size:36px;margin-bottom:8px;">📢</div>
            <h1 style="color:#fff;margin:0;font-size:20px;">ประกาศจากระบบ</h1>
            <p style="color:rgba(255,255,255,0.85);margin:5px 0 0;font-size:13px;">' . htmlspecialchars($site_name_bc) . '</p>
          </td>
        </tr>
        <tr>
          <td style="padding:30px 40px;">
            <p style="color:#333;margin:0 0 6px;">เรียน คุณ <strong>' . htmlspecialchars($bc_user['fullname']) . '</strong>,</p>
            <div style="border-top:2px solid #e9ecef;margin:18px 0;"></div>
            <div style="color:#444;font-size:15px;line-height:1.8;">' . nl2br(htmlspecialchars($bc_body_raw)) . '</div>
            <div style="border-top:2px solid #e9ecef;margin:24px 0;"></div>
          </td>
        </tr>
        <tr>
          <td style="background:#f8f9fa;padding:16px 40px;text-align:center;border-top:1px solid #e9ecef;">
            <p style="margin:0;color:#aaa;font-size:12px;">อีเมลนี้ส่งโดยอัตโนมัติจาก ' . htmlspecialchars($site_name_bc) . ' กรุณาอย่าตอบกลับ</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>';

            $ok = send_email($bc_user['email'], $bc_subject, $bc_html);
            $ok ? $sent_count++ : $fail_count++;
        }

        if ($sent_count > 0) {
            $broadcast_success = "ส่งประกาศสำเร็จ {$sent_count} คน" . ($fail_count > 0 ? " (ล้มเหลว {$fail_count} คน)" : '');
        } else {
            $broadcast_error = 'ไม่มีผู้รับที่มีอีเมลในระบบ หรือส่งทั้งหมดไม่สำเร็จ';
        }
    }
}
// ========== ดึงการตั้งค่าปัจจุบัน ==========
$query = "SELECT * FROM settings";
$result = mysqli_query($conn, $query);
$settings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

// ดึงรายการแผนก/ฝ่ายทั้งหมด
$dept_result = mysqli_query($conn, "SELECT * FROM departments ORDER BY sort_order ASC, name ASC");
$departments_list = [];
while ($row = mysqli_fetch_assoc($dept_result)) {
    $departments_list[] = $row;
}

// ดึง active tab จาก URL
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-cog me-2"></i>ตั้งค่าระบบ
    </h1>
</div>

<!-- แสดงข้อความแจ้งเตือน -->
<?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-1"></i><?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-1"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($test_success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-1"></i><?php echo $test_success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($test_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-1"></i><?php echo $test_error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($dept_success)): ?>
<script>window._deptSuccess = <?php echo json_encode($dept_success); ?>;</script>
<?php endif; ?>
<?php if (!empty($dept_error)): ?>
<script>window._deptError = <?php echo json_encode($dept_error); ?>;</script>
<?php endif; ?>

<!-- แท็บการตั้งค่า -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($active_tab === 'general') ? 'active' : ''; ?>" id="general-tab"
                    data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general"
                    aria-selected="<?php echo ($active_tab === 'general') ? 'true' : 'false'; ?>">
                    <i class="bx bx-globe me-1"></i>ตั้งค่าทั่วไป
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($active_tab === 'smtp') ? 'active' : ''; ?>" id="smtp-tab"
                    data-bs-toggle="tab" data-bs-target="#smtp" type="button" role="tab">
                    <i class="bx bx-envelope me-1"></i>SMTP / อีเมล
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($active_tab === 'departments') ? 'active' : ''; ?>"
                    id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab"
                    aria-controls="departments"
                    aria-selected="<?php echo ($active_tab === 'departments') ? 'true' : 'false'; ?>">
                    <i class="bx bx-building me-1"></i>จัดการแผนก/ฝ่าย
                    <span class="badge bg-primary ms-1"><?php echo count($departments_list); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($active_tab === 'broadcast') ? 'active' : ''; ?>" id="broadcast-tab"
                    data-bs-toggle="tab" data-bs-target="#broadcast" type="button" role="tab">
                    <i class="bx bx-broadcast me-1"></i>ประกาศผ่านอีเมล
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="settingsTabContent">

            <!-- ===== Tab: ตั้งค่าทั่วไป ===== -->
            <div class="tab-pane fade <?php echo ($active_tab === 'general') ? 'show active' : ''; ?>" id="general"
                role="tabpanel" aria-labelledby="general-tab">
                <form method="POST" id="general-form">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_name" class="form-label">ชื่อระบบ <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-buildings"></i>
                                    </span>
                                    <input type="text" class="form-control" id="site_name" name="site_name"
                                        value="<?php echo $settings['site_name']; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_description" class="form-label">คำอธิบายระบบ</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-text"></i>
                                    </span>
                                    <input type="text" class="form-control" id="site_description"
                                        name="site_description" value="<?php echo $settings['site_description']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ===== Tab: SMTP ===== -->
            <div class="tab-pane fade <?php echo ($active_tab === 'smtp') ? 'show active' : ''; ?>" id="smtp"
                role="tabpanel" aria-labelledby="smtp-tab">

                <?php if (!empty($smtp_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bx bx-check-circle me-1"></i><?php echo $smtp_success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($smtp_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bx bx-error-circle me-1"></i><?php echo $smtp_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- ฟอร์ม SMTP -->
                    <div class="col-lg-7 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bx bx-server me-1"></i>ตั้งค่า SMTP Server</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="?tab=smtp">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="form-label fw-semibold">SMTP Host</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i
                                                        class="bx bx-server"></i></span>
                                                <input type="text" class="form-control" name="smtp_host"
                                                    value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                                    placeholder="smtp.gmail.com">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Port</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i
                                                        class="bx bx-hash"></i></span>
                                                <input type="number" class="form-control" name="smtp_port"
                                                    value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                                                    placeholder="587">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">อีเมล (Username)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i
                                                        class="bx bx-envelope"></i></span>
                                                <input type="email" class="form-control" name="smtp_user"
                                                    value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>"
                                                    placeholder="your@gmail.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">รหัสผ่าน (App Password)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i
                                                        class="bx bx-lock-alt"></i></span>
                                                <input type="password" class="form-control" name="smtp_pass"
                                                    id="smtp_pass"
                                                    value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>"
                                                    placeholder="xxxx xxxx xxxx xxxx">
                                                <button class="btn btn-outline-secondary" type="button"
                                                    onclick="toggleSmtpPass()">
                                                    <i class="bx bx-show" id="smtp_pass_eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">อีเมลผู้ส่ง (From Email)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bx bx-at"></i></span>
                                                <input type="email" class="form-control" name="smtp_from"
                                                    value="<?php echo htmlspecialchars($settings['smtp_from'] ?? ''); ?>"
                                                    placeholder="ปกติใช้อีเมลเดียวกับ Username">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="update_smtp" class="btn btn-primary">
                                                <i class="bx bx-save me-1"></i>บันทึกการตั้งค่า SMTP
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ทดสอบส่งอีเมล + คำแนะนำ -->
                    <div class="col-lg-5 mb-4">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bx bx-send me-1"></i>ทดสอบส่งอีเมล</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="?tab=smtp">
                                    <div class="mb-3">
                                        <label class="form-label">ส่งอีเมลทดสอบไปยัง</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i
                                                    class="bx bx-envelope"></i></span>
                                            <input type="email" class="form-control" name="test_email_to"
                                                placeholder="test@email.com" required>
                                        </div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="test_email" class="btn btn-success">
                                            <i class="bx bx-paper-plane me-1"></i>ส่งอีเมลทดสอบ
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card border-warning">
                            <div class="card-header bg-warning">
                                <h6 class="mb-0"><i class="bx bx-info-circle me-1"></i>คำแนะนำ Gmail</h6>
                            </div>
                            <div class="card-body small">
                                <ol class="mb-0 ps-3">
                                    <li class="mb-1">SMTP Host: <code>smtp.gmail.com</code></li>
                                    <li class="mb-1">Port: <code>465</code> (SSL) หรือ <code>587</code> (TLS)</li>
                                    <li class="mb-1">เปิดใช้ <strong>2-Step Verification</strong> บน Google Account</li>
                                    <li class="mb-1">ไปที่ <strong>Security → App passwords</strong></li>
                                    <li>สร้าง App Password แล้วใส่ในช่องรหัสผ่าน</li>
                                </ol>
                                <div class="mt-2">
                                    <a href="https://myaccount.google.com/apppasswords" target="_blank"
                                        class="btn btn-sm btn-outline-warning w-100">
                                        <i class="bx bx-link-external me-1"></i>ไป Google App Passwords
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- end tab smtp -->

            <!-- ===== Tab: จัดการแผนก/ฝ่าย ===== -->
            <div class="tab-pane fade <?php echo ($active_tab === 'departments') ? 'show active' : ''; ?>"
                id="departments" role="tabpanel" aria-labelledby="departments-tab">

                <div class="row">
                    <!-- ฟอร์มเพิ่มแผนก -->
                    <div class="col-md-5 mb-4">
                        <div class="card border-primary h-100">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bx bx-plus-circle me-1"></i>เพิ่มแผนก/ฝ่ายใหม่</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="?tab=departments">
                                    <div class="mb-3">
                                        <label for="dept_name" class="form-label fw-semibold">
                                            ชื่อแผนก/ฝ่าย <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="bx bx-building"></i>
                                            </span>
                                            <input type="text" class="form-control" id="dept_name" name="dept_name"
                                                placeholder="กรอกชื่อแผนก/ฝ่าย" required>
                                        </div>
                                        <small class="text-muted">ชื่อแผนก/ฝ่ายจะต้องไม่ซ้ำกัน</small>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="add_department" class="btn btn-primary">
                                            <i class="bx bx-plus me-1"></i>เพิ่มแผนก/ฝ่าย
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- รายการแผนกทั้งหมด -->
                    <div class="col-md-7 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">
                                    <i class="bx bx-list-ul me-1 text-primary"></i>รายการแผนก/ฝ่ายทั้งหมด
                                </h6>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary"><?php echo count($departments_list); ?>
                                        รายการ</span>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Sort">
                                        <button type="button" id="sort-asc" class="btn btn-outline-primary active"
                                            title="เรียง ก → ๙" onclick="sortDeptTable('asc')">
                                            <i class="bx bx-sort-a-z"></i> ก→๙
                                        </button>
                                        <button type="button" id="sort-desc" class="btn btn-outline-primary"
                                            title="เรียง ๙ → ก" onclick="sortDeptTable('desc')">
                                            <i class="bx bx-sort-z-a"></i> ๙→ก
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($departments_list)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bx bx-building" style="font-size:3rem;"></i>
                                        <p class="mt-2">ยังไม่มีแผนก/ฝ่าย</p>
                                    </div>
                                <?php else: ?>
                                    <div style="max-height: 420px; overflow-y: auto;">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th width="40">#</th>
                                                    <th id="dept-name-header" style="cursor:pointer;"
                                                        onclick="sortDeptTable(window._deptSortDir==='asc'?'desc':'asc')">
                                                        ชื่อแผนก/ฝ่าย
                                                        <i class="bx bx-chevron-up ms-1" id="sort-icon"></i>
                                                    </th>
                                                    <th width="110" class="text-center">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($departments_list as $i => $dept): ?>
                                                    <tr>
                                                        <td class="text-muted"><?php echo $i + 1; ?></td>
                                                        <td>
                                                            <i class="bx bx-building text-primary me-1"></i>
                                                            <?php echo htmlspecialchars($dept['name']); ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center gap-1">
                                                            <!-- ปุ่มแก้ไข -->
                                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                                title="แก้ไข"
                                                                onclick="openEditDept(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name'], ENT_QUOTES); ?>')">
                                                                <i class="bx bx-edit"></i>
                                                            </button>
                                                            <!-- ปุ่มลบ -->
                                                            <form method="POST" action="?tab=departments"
                                                                onsubmit="return confirmDelete('<?php echo htmlspecialchars($dept['name'], ENT_QUOTES); ?>')">
                                                                <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                                                                <button type="submit" name="delete_department"
                                                                    class="btn btn-sm btn-outline-danger" title="ลบ">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- end tab departments -->

            <!-- ===== Tab: ประกาศผ่านอีเมล ===== -->
            <div class="tab-pane fade <?php echo ($active_tab === 'broadcast') ? 'show active' : ''; ?>" id="broadcast"
                role="tabpanel">

                <?php if (!empty($broadcast_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bx bx-check-circle me-1"></i>
                        <?php echo $broadcast_success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($broadcast_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bx bx-error-circle me-1"></i>
                        <?php echo $broadcast_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">ส่งถึง <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_all"
                                            value="all" checked>
                                        <label class="form-check-label fw-semibold text-info" for="bc_all">
                                            <i class="bx bx-group me-1"></i>ทุกคน
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_user"
                                            value="user">
                                        <label class="form-check-label" for="bc_user">
                                            <i class="bx bx-user me-1"></i>ผู้ใช้งานทั่วไป
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_admin"
                                            value="admin">
                                        <label class="form-check-label fw-semibold text-danger" for="bc_admin">
                                            <i class="bx bx-shield-alt-2 me-1"></i>ผู้ดูแลระบบ
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_staff"
                                            value="building_staff">
                                        <label class="form-check-label" for="bc_staff">
                                            <i class="bx bx-hard-hat me-1"></i>งานอาคาร
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_electrical"
                                            value="electrical_staff">
                                        <label class="form-check-label" for="bc_electrical">
                                            <i class="bx bx-bolt-circle me-1"></i>งานไฟฟ้า
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_plumbing"
                                            value="plumbing_staff">
                                        <label class="form-check-label" for="bc_plumbing">
                                            <i class="bx bx-water me-1"></i>งานประปาฯ
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_ac"
                                            value="ac_staff">
                                        <label class="form-check-label" for="bc_ac">
                                            <i class="bx bx-wind me-1"></i>งานปรับอากาศ
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_maintenance_all"
                                            value="maintenance_all">
                                        <label class="form-check-label fw-semibold text-primary" for="bc_maintenance_all">
                                            <i class="bx bx-wrench me-1"></i>แจ้งฝ่ายซ่อมบำรุงทั้งหมด
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_heads_all"
                                            value="heads_all">
                                        <label class="form-check-label fw-semibold text-warning" for="bc_heads_all">
                                            <i class="bx bx-crown me-1"></i>หัวหน้างานทั้งหมด
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_head_building"
                                            value="head_building">
                                        <label class="form-check-label" for="bc_head_building">
                                            <i class="bx bx-hard-hat me-1"></i>หัวหน้างานอาคาร
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_head_electrical"
                                            value="head_electrical">
                                        <label class="form-check-label" for="bc_head_electrical">
                                            <i class="bx bx-bolt-circle me-1"></i>หัวหน้างานไฟฟ้า
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_head_plumbing"
                                            value="head_plumbing">
                                        <label class="form-check-label" for="bc_head_plumbing">
                                            <i class="bx bx-water me-1"></i>หัวหน้างานประปาฯ
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bc_target" id="bc_head_ac"
                                            value="head_ac">
                                        <label class="form-check-label" for="bc_head_ac">
                                            <i class="bx bx-wind me-1"></i>หัวหน้างานปรับอากาศ
                                        </label>
                                    </div>                   
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="bc_subject" class="form-label fw-semibold">หัวข้ออีเมล <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bx bx-heading"></i></span>
                                    <input type="text" class="form-control" id="bc_subject" name="bc_subject"
                                        placeholder="เช่น ประกาศการปิดสำนักงานวันที่ 5 มีนาคม 2568" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="bc_body" class="form-label fw-semibold">เนื้อหาประกาศ <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="bc_body" name="bc_body" rows="8"
                                    placeholder="พิมพ์เนื้อหาที่ต้องการประกาศที่นี่..." required></textarea>
                                <div class="form-text">รับการเว้นบรรทัด (Enter) เพื่อขึ้นบรรทัดใหม่</div>
                            </div>

                            <button type="button" id="btn-send-broadcast" class="btn btn-primary">
                                <i class="bx bx-send me-1"></i>ส่งประกาศ
                            </button>
                        </form>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3"><i class="bx bx-info-circle me-1 text-primary"></i>ข้อมูลผู้รับ
                                </h6>
                                <?php
                                $cnt_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE email IS NOT NULL AND email != ''"))['c'];
                                $cnt_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='user' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='building_staff' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_electrical = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='electrical_staff' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_plumbing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='plumbing_staff' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_ac = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='ac_staff' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_head_building = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='head_building' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_head_electrical = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='head_electrical' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_head_plumbing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='head_plumbing' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_head_ac = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='head_ac' AND email IS NOT NULL AND email != ''"))['c'];
                                $cnt_heads_all = $cnt_head_building + $cnt_head_electrical + $cnt_head_plumbing + $cnt_head_ac;
                                $cnt_maintenance = $cnt_staff + $cnt_electrical + $cnt_plumbing + $cnt_ac + $cnt_heads_all;
                                $cnt_admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='admin' AND email IS NOT NULL AND email != ''"))['c'];
                                ?>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <span class="badge bg-primary me-2"><?php echo $cnt_all; ?></span>
                                        <strong>ทุกคน</strong> (มีอีเมล)
                                    </li>
                                    <li class="mb-2">
                                        <span class="badge bg-secondary me-2"><?php echo $cnt_user; ?></span>
                                        ผู้ใช้งานทั่วไป
                                    </li>
                                    <li class="mb-1 mt-2"><small class="fw-bold text-muted text-uppercase">เจ้าหน้าที่</small></li>
                                    <li class="mb-2">
                                        <span class="badge bg-info text-dark me-2"><?php echo $cnt_staff; ?></span>
                                        งานอาคาร
                                    </li>
                                    <li class="mb-2">
                                        <span class="badge bg-warning text-dark me-2"><?php echo $cnt_electrical; ?></span>
                                        งานไฟฟ้า
                                    </li>
                                    <li class="mb-2">
                                        <span class="badge bg-info text-dark me-2"><?php echo $cnt_plumbing; ?></span>
                                        งานประปาฯ
                                    </li>
                                    <li class="mb-2">
                                        <span class="badge bg-success me-2"><?php echo $cnt_ac; ?></span>
                                        งานปรับอากาศ
                                    </li>
                                    <li class="mb-1 mt-2"><small class="fw-bold text-muted text-uppercase">หัวหน้างาน</small></li>
                                    <li class="mb-2">
                                        <span class="badge me-2" style="background:#b45309"><?php echo $cnt_head_building; ?></span>
                                        หัวหน้างานอาคาร
                                    </li>
                                    <li class="mb-2">
                                        <span class="badge me-2" style="background:#1d4ed8"><?php echo $cnt_head_electrical; ?></span>
                                        หัวหน้างานไฟฟ้า
                                    </li>
                                    <li class="mb-2">
                                        <span class="badge me-2" style="background:#0e7490"><?php echo $cnt_head_plumbing; ?></span>
                                        หัวหน้างานประปาฯ
                                    </li>
                                    <li class="mb-2">
                                        <span class="badge me-2" style="background:#374151"><?php echo $cnt_head_ac; ?></span>
                                        หัวหน้างานปรับอากาศ
                                    </li>
                                    <li class="mb-1 mt-2"><small class="fw-bold text-muted text-uppercase">รวม</small></li>
                                    <li class="mb-2">
                                        <span class="badge bg-primary me-2"><?php echo $cnt_maintenance; ?></span>
                                        <strong>ฝ่ายซ่อมบำรุงทั้งหมด</strong>
                                    </li>
                                    <li class="mb-2">
                                        <span class="badge bg-warning text-dark me-2"><?php echo $cnt_heads_all; ?></span>
                                        <strong>หัวหน้างานทั้งหมด</strong>
                                    </li>
                                    <li>
                                        <span class="badge bg-danger me-2"><?php echo $cnt_admin; ?></span>
                                        ผู้ดูแลระบบ
                                    </li>
                                </ul>
                                <hr>
                                <div class="alert alert-warning py-2 mb-0 small">
                                    <i class="bx bx-error-circle me-1"></i>
                                    ผู้ใช้ที่ไม่มีอีเมลในระบบจะไม่ได้รับประกาศ
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- end tab broadcast -->

        </div><!-- end tab-content -->

        <!-- ปุ่มบันทึก (แสดงเฉพาะ tab ทั่วไป) -->
        <div class="d-flex justify-content-end mt-4" id="save-btn-area">
            <button type="button" id="save-settings" class="btn btn-primary">
                <i class="bx bx-save me-1"></i>บันทึกการตั้งค่า
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        // ซ่อน/แสดงปุ่มบันทึก ตาม tab ที่เลือก
        const tabBtns = document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]');
        const saveBtnArea = document.getElementById('save-btn-area');

        tabBtns.forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function (e) {
                if (e.target.id === 'departments-tab' || e.target.id === 'smtp-tab' || e.target.id === 'broadcast-tab') {
                    saveBtnArea.style.display = 'none';
                } else {
                    saveBtnArea.style.display = 'flex';
                }
            });
        });

        // ซ่อนปุ่มบันทึกถ้า active tab คือ departments หรือ smtp หรือ broadcast
        const activePill = document.querySelector('#settingsTabs .nav-link.active');
        if (activePill && (activePill.id === 'departments-tab' || activePill.id === 'smtp-tab' || activePill.id === 'broadcast-tab')) {
            saveBtnArea.style.display = 'none';
        }

        // บันทึกการตั้งค่าทั่วไป
        document.getElementById('save-settings').addEventListener('click', function () {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const site_name = document.getElementById('site_name').value;
            const site_description = document.getElementById('site_description').value;

            const fields = {
                'update_settings': 'true',
                'site_name': site_name,
                'site_description': site_description,
                'telegram_bot_token': '',
                'telegram_chat_id': ''
            };

            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        });
    });

    // Toggle SMTP password
    function toggleSmtpPass() {
        const inp = document.getElementById('smtp_pass');
        const eye = document.getElementById('smtp_pass_eye');
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        eye.className = show ? 'bx bx-hide' : 'bx bx-show';
    }

    // ยืนยันการลบแผนก
    function confirmDelete(name) {
        return confirm('ต้องการลบแผนก/ฝ่าย "' + name + '" ใช่หรือไม่?\n\nหมายเหตุ: ผู้ใช้ที่สังกัดแผนกนี้จะไม่ถูกลบ');
    }

    // ===== Sort departments table =====
    window._deptSortDir = 'asc'; // ค่าเริ่มต้น

    function sortDeptTable(direction) {
        window._deptSortDir = direction;

        var tbody = document.querySelector('#departments tbody');
        if (!tbody) return;

        var rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort(function (a, b) {
            var nameA = a.querySelectorAll('td')[1]?.textContent.trim() || '';
            var nameB = b.querySelectorAll('td')[1]?.textContent.trim() || '';
            return direction === 'asc'
                ? nameA.localeCompare(nameB, 'th')
                : nameB.localeCompare(nameA, 'th');
        });

        // ล้าง tbody แล้ว append ใหม่
        tbody.innerHTML = '';
        rows.forEach(function (row, idx) {
            // อัพเดตเลขลำดับ
            var numCell = row.querySelector('td:first-child');
            if (numCell) numCell.textContent = idx + 1;
            // animation
            row.style.opacity = '0';
            tbody.appendChild(row);
            setTimeout(function () { row.style.transition = 'opacity 0.2s'; row.style.opacity = '1'; }, idx * 30);
        });

        // อัพเดตสถานะปุ่ม
        var btnAsc = document.getElementById('sort-asc');
        var btnDesc = document.getElementById('sort-desc');
        var icon = document.getElementById('sort-icon');
        if (btnAsc && btnDesc) {
            btnAsc.classList.toggle('active', direction === 'asc');
            btnDesc.classList.toggle('active', direction === 'desc');
        }
        if (icon) {
            icon.className = direction === 'asc'
                ? 'bx bx-chevron-up ms-1'
                : 'bx bx-chevron-down ms-1';
        }
    }

    // เรียงตัวอักษร A→Z ตอนโหลดหน้า
    document.addEventListener('DOMContentLoaded', function () {
        var depTab = document.getElementById('departments-tab');
        if (depTab) {
            // เรียงทันทีถ้า tab departments active อยู่
            if (depTab.classList.contains('active')) {
                sortDeptTable('asc');
            }
            // เรียงเมื่อเปิด tab departments
            depTab.addEventListener('shown.bs.tab', function () {
                sortDeptTable(window._deptSortDir || 'asc');
            });
        }
    });
</script>

<!-- ===== hidden form สำหรับ submit แก้ไขแผนก ===== -->
<form id="editDeptForm" method="POST" action="?tab=departments" style="display:none;">
    <input type="hidden" name="dept_id" id="edit_dept_id">
    <input type="hidden" name="dept_name_edit" id="edit_dept_name_hidden">
    <input type="hidden" name="edit_department" value="1">
</form>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// แสดง popup แจ้งเตือนผลลัพธ์การจัดการแผนก
document.addEventListener('DOMContentLoaded', function() {
    if (window._deptError) {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: window._deptError,
            confirmButtonColor: '#e74a3b',
            confirmButtonText: 'ตกลง'
        });
    } else if (window._deptSuccess) {
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: window._deptSuccess,
            confirmButtonColor: '#1cc88a',
            confirmButtonText: 'ตกลง',
            timer: 2500,
            timerProgressBar: true
        });
    }
});
</script>
<script>
function openEditDept(id, currentName) {
    Swal.fire({
        title: '<i class="bx bx-edit me-2 text-warning"></i>แก้ไขแผนก/ฝ่าย',
        html:
            '<div class="text-start">' +
            '<label class="form-label fw-semibold mb-1">ชื่อแผนก/ฝ่าย <span class="text-danger">*</span></label>' +
            '<div class="input-group">' +
            '<span class="input-group-text bg-light"><i class="bx bx-building"></i></span>' +
            '<input type="text" id="swal-dept-name" class="form-control" value="' + currentName + '" placeholder="กรอกชื่อแผนก/ฝ่าย">' +
            '</div></div>',
        showCancelButton: true,
        confirmButtonText: '<i class="bx bx-save me-1"></i>บันทึก',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#f6c23e',
        cancelButtonColor: '#6c757d',
        customClass: { confirmButton: 'text-dark' },
        focusConfirm: false,
        didOpen: function() {
            const inp = document.getElementById('swal-dept-name');
            inp.focus(); inp.select();
            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); Swal.clickConfirm(); }
            });
        },
        preConfirm: function() {
            const val = document.getElementById('swal-dept-name').value.trim();
            if (!val) { Swal.showValidationMessage('กรุณากรอกชื่อแผนก/ฝ่าย'); return false; }
            return val;
        }
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            document.getElementById('edit_dept_id').value = id;
            document.getElementById('edit_dept_name_hidden').value = result.value;
            document.getElementById('editDeptForm').submit();
        }
    });
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnSend = document.getElementById('btn-send-broadcast');
    if (!btnSend) return;

    btnSend.addEventListener('click', function () {
        const subject = document.getElementById('bc_subject')?.value.trim();
        const body    = document.getElementById('bc_body')?.value.trim();
        const target  = document.querySelector('input[name="bc_target"]:checked');
        const targetLabel = target ? target.closest('.form-check').querySelector('label').textContent.trim() : '';

        if (!subject || !body) {
            Swal.fire({
                icon: 'warning',
                title: 'กรอกข้อมูลให้ครบถ้วน',
                text: 'กรุณากรอกหัวข้ออีเมลและเนื้อหาประกาศก่อนส่ง',
                confirmButtonColor: '#4e73df'
            });
            return;
        }

        Swal.fire({
            icon: 'question',
            title: 'ยืนยันการส่งประกาศ',
            html:
                '<div class="text-start">' +
                '<p class="mb-1">📤 <strong>ส่งถึง:</strong> ' + targetLabel + '</p>' +
                '<p class="mb-0">📧 <strong>หัวข้อ:</strong> ' + subject + '</p>' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: '✔️ ส่งเลย',
            cancelButtonText: '❌ ยกเลิก',
            confirmButtonColor: '#4e73df',
            cancelButtonColor: '#6c757d',
            reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                // เพิ่ม hidden input name="send_broadcast" แล้ว submit
                const form = btnSend.closest('form');
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'send_broadcast';
                hidden.value = '1';
                form.appendChild(hidden);

                // แสดง loading
                Swal.fire({
                    title: 'กำลังส่ง...',
                    text: 'โปรดรอสักครู่',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });
                form.submit();
            }
        });
    });
});
</script>