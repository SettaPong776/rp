<?php
// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินอยู่หรือไม่
if (isset($_SESSION['user_id'])) {
    // บันทึกข้อมูลการออกจากระบบก่อนที่จะทำลาย session
    $user_id = $_SESSION['user_id'];
    $fullname = $_SESSION['fullname'];
    $username = $_SESSION['username'];
    $role = $_SESSION['role'];
    
    // ส่งการแจ้งเตือนไปยัง Telegram (เฉพาะผู้ดูแลระบบ)
    if ($role == 'admin') {
        send_telegram_notification("<b>การออกจากระบบ</b>\n\nผู้ดูแลระบบ: " . $fullname . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
    }
}

// ล้างข้อมูล session
session_unset();
session_destroy();

// Redirect ไปยังหน้าเข้าสู่ระบบ
header('Location: login.php');
exit();
?>