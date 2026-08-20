<?php
/**
 * API: room_history.php
 * ดึงประวัติการซ่อมของตึก + ห้อง (สำหรับ AJAX call)
 */
require_once '../config/db_connect.php';

// ต้อง Login ก่อน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$building   = trim($_GET['building'] ?? '');
$room       = trim($_GET['room'] ?? '');
$exclude_id = intval($_GET['exclude_id'] ?? 0);

// ต้องระบุอาคารและห้องอย่างน้อย 1 อย่าง
if (empty($building) && empty($room)) {
    echo json_encode(['count' => 0, 'records' => []]);
    exit();
}

// สร้าง WHERE condition
$conditions = [];
$params     = [];
$types      = '';

$clean_building = preg_replace('/\s+/', '', $building);
$clean_room     = preg_replace('/\s+/', '', $room);

if (!empty($building) && !empty($room)) {
    // ค้นหาทั้งสองแบบละเว้นช่องว่าง (Space-insensitive) ให้แมทช์แม่นยำแม้เว้นวรรคไม่เท่ากัน
    $conditions[] = "(REPLACE(r.location, ' ', '') LIKE ? AND REPLACE(r.location, ' ', '') LIKE ?)";
    $params[]     = '%' . $clean_building . '%';
    $params[]     = '%' . $clean_room . '%';
    $types       .= 'ss';
} elseif (!empty($building)) {
    $conditions[] = "REPLACE(r.location, ' ', '') LIKE ?";
    $params[]     = '%' . $clean_building . '%';
    $types       .= 's';
} else {
    $conditions[] = "REPLACE(r.location, ' ', '') LIKE ?";
    $params[]     = '%' . $clean_room . '%';
    $types       .= 's';
}

// ถ้ามี exclude_id (รายการปัจจุบัน ไม่ต้องนับ)
if ($exclude_id > 0) {
    $conditions[] = "r.request_id != ?";
    $params[]     = $exclude_id;
    $types       .= 'i';
}

// ถ้าไม่ใช่ Staff/Admin (เป็น User ทั่วไป) ให้เห็นเฉพาะประวัติการแจ้งซ่อมของตนเอง
$user_role = $_SESSION['role'] ?? 'user';
if (!is_staff_role($user_role)) {
    $conditions[] = "r.user_id = ?";
    $params[]     = $_SESSION['user_id'];
    $types       .= 'i';
}

$where = implode(' AND ', $conditions);

// นับจำนวนครั้ง
$count_sql    = "SELECT COUNT(*) as total FROM repair_requests r WHERE $where";
$count_result = db_select($count_sql, $types, $params);
$count_row    = mysqli_fetch_assoc($count_result);
$total        = intval($count_row['total'] ?? 0);

// นับตามสถานะ
$stat_sql = "SELECT
    COUNT(*) as total,
    COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN r.status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN r.status = 'in_progress' THEN 1 END) as in_progress,
    COUNT(CASE WHEN r.status = 'rejected' THEN 1 END) as rejected
FROM repair_requests r WHERE $where";
$stat_result = db_select($stat_sql, $types, $params);
$stats       = mysqli_fetch_assoc($stat_result);

// ดึงรายละเอียด (สูงสุด 20 รายการล่าสุด)
$detail_sql    = "SELECT r.request_id, r.title, r.location, r.status, r.priority,
                  r.created_at, r.completed_date, c.category_name, u.fullname as requester_name
                  FROM repair_requests r
                  JOIN categories c ON r.category_id = c.category_id
                  JOIN users u ON r.user_id = u.user_id
                  WHERE $where
                  ORDER BY r.created_at DESC
                  LIMIT 20";
$detail_result = db_select($detail_sql, $types, $params);

$records = [];
if ($detail_result) {
    while ($row = mysqli_fetch_assoc($detail_result)) {
        // แปลงสถานะเป็นภาษาไทย
        $status_map = [
            'pending'     => 'รอดำเนินการ',
            'in_progress' => 'กำลังดำเนินการ',
            'completed'   => 'เสร็จสิ้น',
            'rejected'    => 'ยกเลิก',
        ];
        $priority_map = [
            'low'    => 'ต่ำ',
            'medium' => 'ปานกลาง',
            'high'   => 'สูง',
            'urgent' => 'เร่งด่วน',
        ];
        $records[] = [
            'request_id'     => $row['request_id'],
            'title'          => $row['title'],
            'location'       => $row['location'],
            'status'         => $row['status'],
            'status_th'      => $status_map[$row['status']] ?? $row['status'],
            'priority'       => $row['priority'],
            'priority_th'    => $priority_map[$row['priority']] ?? $row['priority'],
            'category_name'  => $row['category_name'],
            'requester_name' => $row['requester_name'],
            'created_at'     => $row['created_at'],
            'completed_date' => $row['completed_date'],
        ];
    }
}

echo json_encode([
    'count'   => $total,
    'stats'   => $stats,
    'records' => $records,
    'building'=> $building,
    'room'    => $room,
], JSON_UNESCAPED_UNICODE);
