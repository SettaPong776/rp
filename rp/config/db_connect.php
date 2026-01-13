<?php
// เริ่มการใช้งาน Session
session_start();

// ตั้งค่าการเชื่อมต่อกับฐานข้อมูล
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'nakaiact_testrp';

// เชื่อมต่อกับฐานข้อมูล
$conn = mysqli_connect($host, $user, $password, $database);

// ตรวจสอบการเชื่อมต่อ
if (!$conn) {
    die("การเชื่อมต่อล้มเหลว: " . mysqli_connect_error());
}

// ตั้งค่า charset เป็น utf8
mysqli_set_charset($conn, "utf8mb4");

// ฟังก์ชันสำหรับการแปลงวันที่เป็นรูปแบบไทย
function thai_date($datetime, $format = 'j F Y เวลา H:i น.') {
    if (!$datetime) return "";
    
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

// ฟังก์ชันสำหรับกรองข้อมูลที่รับมาจากฟอร์ม
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// ฟังก์ชันสำหรับส่งการแจ้งเตือนผ่าน Telegram
function send_telegram_notification($message) {
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
function add_request_history($request_id, $user_id, $status, $remark = '') {
    global $conn;
    
    $remark = clean_input($remark);
    
    $query = "INSERT INTO request_history (request_id, user_id, status, remark) 
              VALUES ('$request_id', '$user_id', '$status', '$remark')";
    return mysqli_query($conn, $query);
}

// ฟังก์ชันแสดงข้อความแจ้งเตือน
function show_alert($message, $type = 'success') {
    return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
}


function insert_request_history($request_id, $user_id, $status, $remark = '', $image_path = '') {
    global $conn;
    
    // ทำความสะอาดข้อมูล
    $remark = clean_input($remark);
    $status = clean_input($status);
    $image_path = clean_input($image_path);
    
    // คำสั่ง SQL สำหรับบันทึกข้อมูลใหม่
    $query = "INSERT INTO request_history (request_id, user_id, status, remark, image) 
              VALUES ('$request_id', '$user_id', '$status', '$remark', '$image_path')";
    
    return mysqli_query($conn, $query);
}