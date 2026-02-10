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

// จัดการการอัพเดตการตั้งค่า
if (isset($_POST['update_settings'])) {
    $site_name = clean_input($_POST['site_name']);
    $site_description = clean_input($_POST['site_description']);
    $telegram_bot_token = clean_input($_POST['telegram_bot_token']);
    $telegram_chat_id = clean_input($_POST['telegram_chat_id']);
    $notification_enabled = isset($_POST['notification_enabled']) ? 'true' : 'false';
    
    // อัพเดตการตั้งค่าในฐานข้อมูล
    $settings = [
        'site_name' => $site_name,
        'site_description' => $site_description,
        'telegram_bot_token' => $telegram_bot_token,
        'telegram_chat_id' => $telegram_chat_id,
        'notification_enabled' => $notification_enabled
    ];
    
    $success = true;
    
    foreach ($settings as $name => $value) {
        $query = "UPDATE settings SET setting_value = '$value' WHERE setting_name = '$name'";
        if (!mysqli_query($conn, $query)) {
            $success = false;
            $error = 'เกิดข้อผิดพลาดในการอัพเดตการตั้งค่า: ' . mysqli_error($conn);
            break;
        }
    }
    
    if ($success) {
        $success_message = 'อัพเดตการตั้งค่าเรียบร้อยแล้ว';
        
        // ส่งการแจ้งเตือนไปยัง Telegram
        if ($notification_enabled == 'true') {
            send_telegram_notification("<b>มีการอัพเดตการตั้งค่าระบบ</b>\n\nผู้ดำเนินการ: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
        }
    }
}

// ทดสอบการเชื่อมต่อกับ Telegram
if (isset($_POST['test_telegram'])) {
    $telegram_bot_token = clean_input($_POST['telegram_bot_token']);
    $telegram_chat_id = clean_input($_POST['telegram_chat_id']);
    
    // ส่งข้อความทดสอบไปยัง Telegram
    $url = "https://api.telegram.org/bot" . $telegram_bot_token . "/sendMessage";
    $data = [
        'chat_id' => $telegram_chat_id,
        'text' => "ทดสอบการเชื่อมต่อกับระบบแจ้งซ่อมออนไลน์\n\nหากคุณได้รับข้อความนี้ แสดงว่าการตั้งค่าสำเร็จแล้ว\n\nเวลา: " . thai_date(date('Y-m-d H:i:s')),
        'parse_mode' => 'HTML'
    ];
    
    // ใช้ cURL สำหรับการส่ง POST request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // ตรวจสอบผลลัพธ์
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

<!-- แท็บการตั้งค่า -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                    <i class="bx bx-globe me-1"></i>ตั้งค่าทั่วไป
                </button>
            </li>
            <!-- <li class="nav-item" role="presentation">
                <button class="nav-link" id="notification-tab" data-bs-toggle="tab" data-bs-target="#notification" type="button" role="tab" aria-controls="notification" aria-selected="false">
                    <i class="bx bx-bell me-1"></i>การแจ้งเตือน
                </button>-->
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="settingsTabContent">
            <!-- ตั้งค่าทั่วไป -->
            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                <form method="POST" id="general-form">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_name" class="form-label">ชื่อระบบ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-buildings"></i>
                                    </span>
                                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo $settings['site_name']; ?>" required>
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
                                    <input type="text" class="form-control" id="site_description" name="site_description" value="<?php echo $settings['site_description']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- ตั้งค่าการแจ้งเตือน -->
            <div class="tab-pane fade" id="notification" role="tabpanel" aria-labelledby="notification-tab">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-1"></i>
                            การแจ้งเตือนผ่าน Telegram จะช่วยให้คุณได้รับการแจ้งเตือนทันทีเมื่อมีการแจ้งซ่อมใหม่หรือมีการอัพเดตสถานะ
                        </div>
                    </div>
                </div>
                <form method="POST" id="notification-form">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="telegram_bot_token" class="form-label">Telegram Bot Token</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-key"></i>
                                    </span>
                                    <input type="text" class="form-control" id="telegram_bot_token" name="telegram_bot_token" value="<?php echo $settings['telegram_bot_token']; ?>">
                                </div>
                                <small class="text-muted">สร้าง Bot ด้วย <a href="https://t.me/BotFather" target="_blank">@BotFather</a> บน Telegram และนำ Token มาใส่ที่นี่</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="telegram_chat_id" class="form-label">Telegram Chat ID</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bx bx-chat"></i>
                                    </span>
                                    <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id" value="<?php echo $settings['telegram_chat_id']; ?>">
                                </div>
                                <small class="text-muted">ค้นหา Chat ID ด้วย <a href="https://t.me/getidsbot" target="_blank">@getidsbot</a> บน Telegram</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="notification_enabled" name="notification_enabled" <?php echo $settings['notification_enabled'] == 'true' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notification_enabled">เปิดใช้งานการแจ้งเตือนผ่าน Telegram</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="test_telegram" class="btn btn-info">
                                <i class="bx bx-test-tube me-1"></i>ทดสอบการเชื่อมต่อ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="d-flex justify-content-end mt-4">
            <button type="button" id="save-settings" class="btn btn-primary">
                <i class="bx bx-save me-1"></i>บันทึกการตั้งค่า
            </button>
        </div>
    </div>
</div>

<!-- คำแนะนำการใช้งาน Telegram 
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-help-circle me-2"></i>คำแนะนำการใช้งาน Telegram
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-3">การสร้าง Telegram Bot</h5>
                <ol>
                    <li>เปิดแอป Telegram และค้นหา <a href="https://t.me/BotFather" target="_blank">@BotFather</a></li>
                    <li>พิมพ์คำสั่ง <code>/newbot</code> และทำตามขั้นตอน</li>
                    <li>เมื่อสร้างเสร็จ คุณจะได้รับ Bot Token (เช่น <code>123456789:AbCdEfGhIjKlMnOpQrStUvWxYz</code>)</li>
                    <li>นำ Token มาใส่ในช่อง "Telegram Bot Token" ข้างต้น</li>
                </ol>
            </div>
            <div class="col-md-6">
                <h5 class="mb-3">การค้นหา Chat ID</h5>
                <ol>
                    <li>เปิดแอป Telegram และค้นหา <a href="https://t.me/getidsbot" target="_blank">@getidsbot</a></li>
                    <li>ส่งข้อความใดก็ได้ไปยัง Bot นี้</li>
                    <li>Bot จะส่งข้อมูลกลับมา ให้ค้นหาค่า "id" (เช่น <code>123456789</code>)</li>
                    <li>นำ ID มาใส่ในช่อง "Telegram Chat ID" ข้างต้น</li>
                </ol>
            </div>
        </div>
        <div class="alert alert-warning mt-3">
            <i class="bx bx-bulb me-1"></i>
            <strong>คำแนะนำ:</strong> คุณสามารถสร้างกลุ่มใน Telegram และเพิ่ม Bot ของคุณลงไปเพื่อให้ทีมงานทุกคนได้รับการแจ้งเตือนพร้อมกัน ในกรณีนี้ ให้ใช้ Group Chat ID แทน
        </div>
    </div>
</div>-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // เมื่อคลิกที่ปุ่มบันทึกการตั้งค่า
    document.getElementById('save-settings').addEventListener('click', function() {
        // รวบรวมข้อมูลจากทั้งสองฟอร์ม
        const generalForm = document.getElementById('general-form');
        const notificationForm = document.getElementById('notification-form');
        
        // สร้างฟอร์มใหม่สำหรับส่งข้อมูล
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        // เพิ่มข้อมูลจากฟอร์มทั่วไป
        const site_name = document.getElementById('site_name').value;
        const site_description = document.getElementById('site_description').value;
        
        // เพิ่มข้อมูลจากฟอร์มการแจ้งเตือน
        const telegram_bot_token = document.getElementById('telegram_bot_token').value;
        const telegram_chat_id = document.getElementById('telegram_chat_id').value;
        const notification_enabled = document.getElementById('notification_enabled').checked;
        
        // สร้าง input fields
        const fields = {
            'update_settings': 'true',
            'site_name': site_name,
            'site_description': site_description,
            'telegram_bot_token': telegram_bot_token,
            'telegram_chat_id': telegram_chat_id
        };
        
        // เพิ่ม notification_enabled หากมีการเลือก
        if (notification_enabled) {
            fields['notification_enabled'] = 'true';
        }
        
        // เพิ่ม input fields ลงในฟอร์ม
        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        
        // เพิ่มฟอร์มลงในเอกสารและส่ง
        document.body.appendChild(form);
        form.submit();
    });
});
</script>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>