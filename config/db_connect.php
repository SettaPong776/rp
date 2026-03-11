<?php
// เริ่มการใช้งาน Session
session_start();

// ตั้งค่าการเชื่อมต่อกับฐานข้อมูล
$host = 'localhost';
$user = 'sql_help_lru_ac_th';
$password = '1eaea28b2c477';
$database = 'sql_help_lru_ac_th';

// เชื่อมต่อกับฐานข้อมูล
$conn = mysqli_connect($host, $user, $password, $database);

// ตรวจสอบการเชื่อมต่อ
if (!$conn) {
    die("การเชื่อมต่อล้มเหลว: " . mysqli_connect_error());
}

// ตั้งค่า charset เป็น utf8
mysqli_set_charset($conn, "utf8mb4");

// ฟังก์ชันสำหรับการแปลงวันที่เป็นรูปแบบไทย
function thai_date($datetime, $format = 'j F Y เวลา H:i น.')
{
    if (!$datetime)
        return "";

    $thai_month_arr = array(
        "0" => "",
        "1" => "มกราคม",
        "2" => "กุมภาพันธ์",
        "3" => "มีนาคม",
        "4" => "เมษายน",
        "5" => "พฤษภาคม",
        "6" => "มิถุนายน",
        "7" => "กรกฎาคม",
        "8" => "สิงหาคม",
        "9" => "กันยายน",
        "10" => "ตุลาคม",
        "11" => "พฤศจิกายน",
        "12" => "ธันวาคม"
    );

    $timestamp = strtotime($datetime);

    $thai_date_return = date("j", $timestamp);
    $thai_date_return .= " " . $thai_month_arr[date("n", $timestamp)];
    $thai_date_return .= " " . (date("Y", $timestamp) + 543);

    if (strpos($format, 'H:i') !== false) {
        $thai_date_return .= " เวลา " . date("H:i", $timestamp) . " น.";
    }

    return $thai_date_return;
}

// ฟังก์ชันสำหรับกรองข้อมูลที่รับมาจากฟอร์ม (สำหรับ display เท่านั้น)
function clean_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Execute a prepared statement SELECT query and return the result
 * @param string $query SQL query with ? placeholders
 * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
 * @param array $params Array of parameters to bind
 * @return mysqli_result|false Query result or false on failure
 */
function db_select($query, $types = "", $params = [])
{
    global $conn;

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return false;
    }

    if (!empty($types) && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    return $result;
}

/**
 * Execute a prepared statement INSERT/UPDATE/DELETE query
 * @param string $query SQL query with ? placeholders
 * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
 * @param array $params Array of parameters to bind
 * @return bool True on success, false on failure
 */
function db_execute($query, $types = "", $params = [])
{
    global $conn;

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return false;
    }

    if (!empty($types) && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    $success = mysqli_stmt_execute($stmt);
    if (!$success) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Execute a prepared statement INSERT and return the insert ID
 * @param string $query SQL query with ? placeholders
 * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
 * @param array $params Array of parameters to bind
 * @return int|false Insert ID on success, false on failure
 */
function db_insert($query, $types = "", $params = [])
{
    global $conn;

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return false;
    }

    if (!empty($types) && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $insert_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $insert_id;
}

// ฟังก์ชันสำหรับส่งการแจ้งเตือนผ่าน Telegram
function send_telegram_notification($message)
{
    global $conn;

    // ดึงข้อมูลการตั้งค่า Telegram
    $query = "SELECT setting_value FROM settings WHERE setting_name = 'telegram_bot_token'";
    $result = mysqli_query($conn, $query);
    $token = mysqli_fetch_assoc($result)['setting_value'];

    $query = "SELECT setting_value FROM settings WHERE setting_name = 'telegram_chat_id'";
    $result = mysqli_query($conn, $query);
    $chat_id = mysqli_fetch_assoc($result)['setting_value'];

    $query = "SELECT setting_value FROM settings WHERE setting_name = 'notification_enabled'";
    $result = mysqli_query($conn, $query);
    $notification_enabled = mysqli_fetch_assoc($result)['setting_value'];

    // ตรวจสอบว่าเปิดใช้งานการแจ้งเตือนหรือไม่
    if ($notification_enabled !== 'true' || empty($token) || empty($chat_id)) {
        return false;
    }

    // ส่งข้อความผ่าน Telegram Bot API
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    // ใช้ cURL สำหรับการส่ง POST request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    curl_close($ch);

    return ($response !== false);
}

// ฟังก์ชันสำหรับบันทึกประวัติการอัพเดท
function add_request_history($request_id, $user_id, $status, $remark = '')
{
    $query = "INSERT INTO request_history (request_id, user_id, status, remark) VALUES (?, ?, ?, ?)";
    return db_execute($query, "iiss", [$request_id, $user_id, $status, $remark]);
}

// ฟังก์ชันแสดงข้อความแจ้งเตือน
function show_alert($message, $type = 'success')
{
    return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
}


function insert_request_history($request_id, $user_id, $status, $remark = '', $image_path = '')
{
    $query = "INSERT INTO request_history (request_id, user_id, status, remark, image) VALUES (?, ?, ?, ?, ?)";
    return db_execute($query, "iisss", [$request_id, $user_id, $status, $remark, $image_path]);
}

/**
 * รายการ role ของเจ้าหน้าที่ทุกประเภท (ยกเว้น admin และ user)
 */
function get_all_staff_roles()
{
    return [
        'building_staff', 'electrical_staff', 'plumbing_staff', 'ac_staff',
        'head_building', 'head_electrical', 'head_plumbing', 'head_ac'
    ];
}

/**
 * ตรวจสอบว่า role นั้นเป็น staff หรือ admin (มีสิทธิ์เข้าแผงควบคุม)
 */
function is_staff_role($role)
{
    return in_array($role, [
        'admin',
        'building_staff', 'electrical_staff', 'plumbing_staff', 'ac_staff',
        'head_building', 'head_electrical', 'head_plumbing', 'head_ac'
    ]);
}

/**
 * แปลงค่า role เป็นชื่อภาษาไทย
 */
function get_role_label($role)
{
    $labels = [
        'admin'            => 'ผู้ดูแลระบบ',
        'building_staff'   => 'งานอาคาร',
        'electrical_staff' => 'งานไฟฟ้า',
        'plumbing_staff'   => 'งานประปาและระบบสุขาภิบาล',
        'ac_staff'         => 'งานระบบปรับอากาศ',
        'head_building'    => 'หัวหน้างานอาคาร',
        'head_electrical'  => 'หัวหน้างานไฟฟ้า',
        'head_plumbing'    => 'หัวหน้างานประปาฯ',
        'head_ac'          => 'หัวหน้างานปรับอากาศ',
        'user'             => 'ผู้ใช้งานทั่วไป',
    ];
    return $labels[$role] ?? 'ผู้ใช้งาน';
}

/**
 * ส่งอีเมลผ่าน PHP mail() หรือ SMTP
 * @param string $to อีเมลผู้รับ
 * @param string $subject หัวเรื่อง
 * @param string $body เนื้อหา HTML
 * @return bool
 */
function send_email($to, $subject, $body)
{
    global $conn;

    // ดึงการตั้งค่า SMTP จากฐานข้อมูล (ถ้ามี)
    $smtp_host = '';
    $smtp_user = '';
    $smtp_pass = '';
    $smtp_port = 587;
    $mail_from = '';
    $mail_from_name = 'ระบบแจ้งซ่อม';

    $result = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('smtp_host','smtp_user','smtp_pass','smtp_port','smtp_from','site_name')");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            switch ($row['setting_name']) {
                case 'smtp_host':
                    $smtp_host = $row['setting_value'];
                    break;
                case 'smtp_user':
                    $smtp_user = $row['setting_value'];
                    break;
                case 'smtp_pass':
                    $smtp_pass = $row['setting_value'];
                    break;
                case 'smtp_port':
                    $smtp_port = (int) $row['setting_value'];
                    break;
                case 'smtp_from':
                    $mail_from = $row['setting_value'];
                    break;
                case 'site_name':
                    $mail_from_name = $row['setting_value'];
                    break;
            }
        }
    }

    if (empty($mail_from))
        $mail_from = $smtp_user;

    // ถ้ามีการตั้งค่า SMTP ให้ใช้ PHPMailer
    if (!empty($smtp_host) && !empty($smtp_user) && !empty($smtp_pass)) {
        return send_email_smtp($to, $subject, $body, $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $mail_from, $mail_from_name);
    }

    // Fallback: ใช้ PHP mail()
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$mail_from_name} <noreply@repair.local>\r\n";
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/**
 * ส่งอีเมลผ่าน PHPMailer (รองรับ SSL/TLS/STARTTLS)
 */
function send_email_smtp($to, $subject, $body, $host, $port, $username, $password, $from, $from_name)
{
    $phpmailer_path = __DIR__ . '/../libs/phpmailer/';
    if (!file_exists($phpmailer_path . 'PHPMailer.php')) {
        error_log('PHPMailer not found at: ' . $phpmailer_path);
        return false;
    }

    require_once $phpmailer_path . 'Exception.php';
    require_once $phpmailer_path . 'PHPMailer.php';
    require_once $phpmailer_path . 'SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Port = $port;

        // กำหนด encryption ตาม port
        if ($port == 465) {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;   // SSL
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // TLS
        }

        // รองรับ self-signed cert (institutional mail)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ];

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($from, $from_name);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * ส่งอีเมลแจ้งผู้แจ้งซ่อมเมื่อสถานะเปลี่ยนแปลง
 */
function send_status_update_email($to_email, $to_name, $request_id, $title, $category, $location, $new_status, $status_text, $remark, $updated_by)
{
    // สร้าง badge + icon ตามสถานะ
    $status_config = [
        'pending' => ['color' => '#ffc107', 'text_color' => '#333', 'icon' => '⏳', 'label' => 'รอดำเนินการ'],
        'in_progress' => ['color' => '#0dcaf0', 'text_color' => '#fff', 'icon' => '🔧', 'label' => 'กำลังดำเนินการ'],
        'completed' => ['color' => '#198754', 'text_color' => '#fff', 'icon' => '✅', 'label' => 'เสร็จสิ้น'],
        'rejected' => ['color' => '#dc3545', 'text_color' => '#fff', 'icon' => '❌', 'label' => 'ยกเลิก'],
    ];
    $cfg = $status_config[$new_status] ?? ['color' => '#6c757d', 'text_color' => '#fff', 'icon' => '📋', 'label' => $status_text];

    // สร้าง base URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $view_link = $base_url . '/view_request.php?id=' . $request_id;

    $subject = $cfg['icon'] . ' อัปเดตสถานะแจ้งซ่อม #' . $request_id . ' - ' . $cfg['label'];

    $body = '<!DOCTYPE html>
<html lang="th"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">
    <tr><td align="center">
      <table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#4e73df,#224abe);padding:30px 40px;text-align:center;">
            <div style="font-size:40px;margin-bottom:8px;">' . $cfg['icon'] . '</div>
            <h1 style="color:#fff;margin:0;font-size:22px;">อัปเดตสถานะแจ้งซ่อม</h1>
            <p style="color:rgba(255,255,255,0.85);margin:6px 0 0;">ระบบแจ้งซ่อมและบำรุงรักษา</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:30px 40px;">
            <p style="color:#333;font-size:15px;margin:0 0 6px;">เรียน คุณ <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
            <p style="color:#555;font-size:14px;margin:0 0 24px;line-height:1.7;">
              รายการแจ้งซ่อมของคุณ <strong>#' . $request_id . '</strong> ได้รับการอัปเดตสถานะใหม่แล้ว
            </p>

            <!-- Status Badge -->
            <div style="text-align:center;margin-bottom:24px;">
              <span style="display:inline-block;background:' . $cfg['color'] . ';color:' . $cfg['text_color'] . ';padding:10px 30px;border-radius:30px;font-size:18px;font-weight:700;">
                ' . $cfg['icon'] . ' ' . $cfg['label'] . '
              </span>
            </div>

            <!-- Request Details -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fc;border-radius:10px;border:1px solid #e3e6f0;overflow:hidden;margin-bottom:20px;">
              <tr><td style="background:#4e73df;padding:10px 20px;">
                <span style="color:#fff;font-weight:700;">รายละเอียดคำร้อง #' . $request_id . '</span>
              </td></tr>
              <tr><td style="padding:16px 20px;">
                <table width="100%" cellpadding="6">
                  <tr>
                    <td width="35%" style="color:#888;font-size:13px;">📌 หัวข้อ</td>
                    <td style="color:#333;font-weight:700;">' . htmlspecialchars($title) . '</td>
                  </tr>
                  <tr style="background:#f0f2ff;">
                    <td style="color:#888;font-size:13px;">📂 หมวดหมู่</td>
                    <td style="color:#333;">' . htmlspecialchars($category) . '</td>
                  </tr>
                  ' . (!empty($location) ? '<tr><td style="color:#888;font-size:13px;">📍 สถานที่</td><td style="color:#333;">' . htmlspecialchars($location) . '</td></tr>' : '') . '
                  <tr style="background:#f0f2ff;">
                    <td style="color:#888;font-size:13px;">👤 อัปเดตโดย</td>
                    <td style="color:#333;">' . htmlspecialchars($updated_by) . '</td>
                  </tr>
                  <tr>
                    <td style="color:#888;font-size:13px;">🕐 เวลา</td>
                    <td style="color:#333;">' . date('d/m/Y H:i') . ' น.</td>
                  </tr>
                </table>
              </td></tr>
            </table>

            <!-- Remark -->
            ' . (!empty($remark) ? '
            <div style="background:#fffbf0;border-left:4px solid #ffc107;border-radius:4px;padding:14px 18px;margin-bottom:20px;">
              <p style="margin:0 0 4px;color:#888;font-size:11px;text-transform:uppercase;letter-spacing:1px;">หมายเหตุจากเจ้าหน้าที่</p>
              <p style="margin:0;color:#555;font-size:14px;line-height:1.7;">' . nl2br(htmlspecialchars($remark)) . '</p>
            </div>' : '') . '

            <!-- CTA -->
            <div style="text-align:center;margin-top:24px;">
              <a href="' . $view_link . '" style="display:inline-block;background:linear-gradient(135deg,#4e73df,#224abe);color:#fff;text-decoration:none;padding:13px 32px;border-radius:8px;font-size:14px;font-weight:700;">
                🔍 ดูรายละเอียดการแจ้งซ่อม
              </a>
            </div>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8f9fa;padding:16px 40px;text-align:center;border-top:1px solid #e9ecef;">
            <p style="margin:0;color:#aaa;font-size:12px;">อีเมลนี้ส่งโดยอัตโนมัติจากระบบแจ้งซ่อม กรุณาอย่าตอบกลับ</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>';

    return send_email($to_email, $subject, $body);
}

/**
 * ส่งอีเมล OTP สำหรับรีเซ็ตรหัสผ่าน
 * @param string $to_email อีเมลผู้รับ
 * @param string $to_name ชื่อผู้รับ
 * @param string $otp รหัส OTP 6 หลัก
 * @return bool
 */
function send_password_reset_email($to_email, $to_name, $otp)
{
    $subject = 'รหัสยืนยันการรีเซ็ตรหัสผ่าน - ระบบแจ้งซ่อม';
    $body = '<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
    <tr><td align="center">
      <table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#4e73df,#224abe);padding:35px 40px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:24px;letter-spacing:1px;">🔐 รีเซ็ตรหัสผ่าน</h1>
            <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;">ระบบแจ้งซ่อมและบำรุงรักษา</p>
          </td>
        </tr>
        <tr>
          <td style="padding:40px 40px 30px;">
            <p style="color:#333;font-size:16px;margin:0 0 16px;">เรียน คุณ ' . htmlspecialchars($to_name) . ',</p>
            <p style="color:#555;font-size:15px;line-height:1.7;margin:0 0 28px;">
              เราได้รับคำขอรีเซ็ตรหัสผ่านของคุณ กรุณาใช้รหัส OTP ด้านล่างเพื่อยืนยันตัวตน<br>
              รหัสนี้จะหมดอายุภายใน <strong>10 นาที</strong>
            </p>
            <div style="text-align:center;margin:30px 0;">
              <div style="display:inline-block;background:linear-gradient(135deg,#f8f9ff,#eef0fb);border:2px dashed #4e73df;border-radius:16px;padding:24px 50px;">
                <p style="margin:0 0 6px;color:#888;font-size:13px;letter-spacing:2px;text-transform:uppercase;">รหัส OTP ของคุณ</p>
                <span style="font-size:48px;font-weight:900;color:#4e73df;letter-spacing:12px;">' . $otp . '</span>
              </div>
            </div>
            <div style="background:#fff8e1;border-left:4px solid #ffc107;border-radius:4px;padding:14px 18px;margin:24px 0;">
              <p style="margin:0;color:#856404;font-size:14px;">
                ⚠️ หากคุณไม่ได้ร้องขอการรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยต่ออีเมลนี้
              </p>
            </div>
          </td>
        </tr>
        <tr>
          <td style="background:#f8f9fa;padding:20px 40px;text-align:center;border-top:1px solid #e9ecef;">
            <p style="margin:0;color:#aaa;font-size:12px;">อีเมลนี้ส่งโดยอัตโนมัติจากระบบแจ้งซ่อม กรุณาอย่าตอบกลับ</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';

    return send_email($to_email, $subject, $body);
}
