<?php
// กำหนดชื่อหน้า
$page_title = "ตั้งค่าระบบ";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินและเป็นแอดมินหรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
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
        'text' => "ทดสอบการเชื่อมต่อกับระบบแจ้งซ่อมออนไลน์\n\nหากคุณได้รับข้อความนี้ แสดงว่าการตั้งค่าสำเร็จแล้ว\n\nเวลา: " . thai_date(date('Y-m-d H:i:s')),
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

// ดึงการตั้งค่าปัจจุบัน
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
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-1"></i><?php echo $dept_success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($dept_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-1"></i><?php echo $dept_error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
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
                <button class="nav-link <?php echo ($active_tab === 'departments') ? 'active' : ''; ?>"
                    id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab"
                    aria-controls="departments"
                    aria-selected="<?php echo ($active_tab === 'departments') ? 'true' : 'false'; ?>">
                    <i class="bx bx-building me-1"></i>จัดการแผนก/ฝ่าย
                    <span class="badge bg-primary ms-1"><?php echo count($departments_list); ?></span>
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
                                    <span class="badge bg-secondary"><?php echo count($departments_list); ?> รายการ</span>
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
                                                    <th id="dept-name-header" style="cursor:pointer;" onclick="sortDeptTable(window._deptSortDir==='asc'?'desc':'asc')">
                                                        ชื่อแผนก/ฝ่าย
                                                        <i class="bx bx-chevron-up ms-1" id="sort-icon"></i>
                                                    </th>
                                                    <th width="80" class="text-center">จัดการ</th>
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
                                                            <form method="POST" action="?tab=departments"
                                                                onsubmit="return confirmDelete('<?php echo htmlspecialchars($dept['name'], ENT_QUOTES); ?>')">
                                                                <input type="hidden" name="dept_id"
                                                                    value="<?php echo $dept['id']; ?>">
                                                                <button type="submit" name="delete_department"
                                                                    class="btn btn-sm btn-outline-danger" title="ลบ">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </form>
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
                if (e.target.id === 'departments-tab') {
                    saveBtnArea.style.display = 'none';
                } else {
                    saveBtnArea.style.display = 'flex';
                }
            });
        });

        // ซ่อนปุ่มบันทึกถ้า active tab คือ departments
        const activePill = document.querySelector('#settingsTabs .nav-link.active');
        if (activePill && activePill.id === 'departments-tab') {
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
        var btnAsc  = document.getElementById('sort-asc');
        var btnDesc = document.getElementById('sort-desc');
        var icon    = document.getElementById('sort-icon');
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

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>