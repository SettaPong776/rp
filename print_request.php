<?php
// รายงาน PDF - หน้าสำหรับพิมพ์/บันทึกเป็น PDF (แบบทางการ สีสว่าง เหมาะกับพิมพ์ขาว-ดำ)
date_default_timezone_set('Asia/Bangkok');
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$request_id = intval($_GET['id']);

// ดึงข้อมูลรายการแจ้งซ่อม
$result = db_select(
    "SELECT r.*, c.category_name, u.fullname as requester_name, u.email as requester_email, u.department as requester_department, u.phone as requester_phone 
     FROM repair_requests r 
     JOIN categories c ON r.category_id = c.category_id 
     JOIN users u ON r.user_id = u.user_id 
     WHERE r.request_id = ?",
    "i",
    [$request_id]
);

if (!$result || mysqli_num_rows($result) == 0) {
    header('Location: dashboard.php');
    exit();
}

$request = mysqli_fetch_assoc($result);

// ตรวจสอบสิทธิ์การเข้าถึง
if (!in_array($_SESSION['role'], ['admin', 'building_staff']) && $request['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php');
    exit();
}

// ดึงข้อมูลประวัติการอัพเดท (รวมรูปภาพ)
$history_result = db_select(
    "SELECT h.*, u.fullname 
     FROM request_history h 
     JOIN users u ON h.user_id = u.user_id 
     WHERE h.request_id = ? 
     ORDER BY h.created_at DESC LIMIT 4",
    "i",
    [$request_id]
);

// ดึงชื่อผู้ดำเนินการ
$completed_by_result = db_select(
    "SELECT u.fullname 
     FROM request_history h 
     JOIN users u ON h.user_id = u.user_id 
     WHERE h.request_id = ? AND h.status = 'completed' 
     ORDER BY h.created_at DESC LIMIT 1",
    "i",
    [$request_id]
);
$completed_by = $completed_by_result ? mysqli_fetch_assoc($completed_by_result) : null;

// ดึงการตั้งค่าระบบ
$settings_result = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('site_name', 'site_logo')");
$settings = [];
while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

// แปลงสถานะและความสำคัญ
$status_texts = ['pending' => 'รอดำเนินการ', 'in_progress' => 'กำลังดำเนินการ', 'completed' => 'เสร็จสิ้น', 'rejected' => 'ยกเลิก'];
$priority_texts = ['low' => 'ต่ำ', 'medium' => 'ปานกลาง', 'high' => 'สูง', 'urgent' => 'เร่งด่วน'];

// สีสำหรับสถานะ (สีสว่าง)
$status_colors = [
    'pending' => '#F59E0B',      // Amber
    'in_progress' => '#3B82F6',  // Blue  
    'completed' => '#10B981',    // Emerald
    'rejected' => '#EF4444'      // Red
];
$status_bg = [
    'pending' => '#FEF3C7',
    'in_progress' => '#DBEAFE',
    'completed' => '#D1FAE5',
    'rejected' => '#FEE2E2'
];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน #<?php echo $request_id; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563EB;
            /* Bright Blue */
            --primary-light: #3B82F6;
            --primary-dark: #1D4ED8;
            --accent: #06B6D4;
            /* Cyan */
            --text-dark: #1e293b;
            --text-medium: #475569;
            --text-light: #64748b;
            --border: #e2e8f0;
            --bg-light: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: var(--text-dark);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* Header - Bright Style */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #fff;
            padding: 12px 25px;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #06B6D4, #10B981, #F59E0B);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-text h1 {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 1px;
        }

        .header-text .subtitle {
            font-size: 11px;
            opacity: 0.9;
        }

        .header-right {
            text-align: right;
        }

        .request-number {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .print-date {
            font-size: 11px;
            opacity: 0.85;
        }

        /* Content */
        .content {
            padding: 15px 25px;
            flex: 1;
        }

        /* Document Title */
        .document-title {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary);
        }

        /* Title Bar */
        .title-bar {
            background:
                <?php echo $status_bg[$request['status']]; ?>
            ;
            border: 1px solid
                <?php echo $status_colors[$request['status']]; ?>
                40;
            border-left: 5px solid
                <?php echo $status_colors[$request['status']]; ?>
            ;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .title-bar h2 {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            max-width: 60%;
        }

        .badges {
            display: flex;
            gap: 8px;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-status {
            background:
                <?php echo $status_colors[$request['status']]; ?>
            ;
            color: #fff;
        }

        .badge-priority {
            background: #fff;
            color: var(--text-dark);
            border: 1px solid var(--border);
        }

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        /* Card */
        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #fff;
            padding: 7px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-icon {
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
        }

        .card-title {
            font-size: 12px;
            font-weight: 600;
        }

        .card-body {
            padding: 10px 12px;
        }

        /* Info List */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            padding-bottom: 5px;
            border-bottom: 1px dotted #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-label {
            width: 85px;
            font-size: 11px;
            color: var(--text-light);
            font-weight: 500;
            flex-shrink: 0;
        }

        .info-value {
            font-size: 11px;
            color: var(--text-dark);
            font-weight: 600;
        }

        /* Requester */
        .requester-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .requester-details h3 {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .requester-details p {
            font-size: 11px;
            color: var(--text-medium);
            margin: 2px 0;
        }

        /* Description */
        .description-box {
            background: var(--bg-light);
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            font-size: 11px;
            line-height: 1.6;
        }

        /* Admin Remark */
        .admin-remark {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid #fbbf24;
            border-left: 4px solid #F59E0B;
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }

        .admin-remark-header {
            font-size: 11px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 6px;
        }

        .admin-remark-content {
            font-size: 11px;
            color: #78350f;
            line-height: 1.5;
        }

        /* Timeline */
        .timeline {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .timeline-item {
            display: flex;
            gap: 8px;
            padding: 7px 10px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            position: relative;
        }

        .timeline-dot {
            width: 8px;
            height: 8px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 50%;
            margin-top: 4px;
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
            min-width: 0;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }

        .timeline-status {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .timeline-date {
            font-size: 10px;
            color: var(--text-light);
        }

        .timeline-user {
            font-size: 11px;
            color: var(--text-medium);
        }

        .timeline-remark {
            font-size: 10px;
            color: var(--text-light);
            margin-top: 3px;
            font-style: italic;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #fff;
            padding: 8px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }

        .footer-left {
            font-size: 10px;
            opacity: 0.95;
        }

        .footer-right {
            font-size: 10px;
            opacity: 0.85;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 15px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .signature-box {
            text-align: center;
            padding-top: 30px;
        }

        .signature-line {
            border-top: 1.5px solid var(--text-dark);
            width: 160px;
            margin: 0 auto 6px;
        }

        .signature-label {
            font-size: 11px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .signature-sublabel {
            font-size: 10px;
            color: var(--text-light);
            margin-top: 2px;
        }

        /* Action Buttons */
        .action-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 8px;
            z-index: 1000;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: #fff;
            color: var(--text-dark);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
        }

        /* Print Styles */
        @page {
            size: A4;
            margin: 5mm;
        }

        @media print {
            * {
                box-sizing: border-box !important;
            }

            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 12px !important;
                line-height: 1.45 !important;
                width: 100% !important;
            }

            .page {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                display: flex !important;
                flex-direction: column !important;
            }

            .action-buttons {
                display: none !important;
            }

            /* Reset responsive overrides for print */
            .header {
                padding: 10px 20px !important;
            }

            .header-content {
                flex-direction: row !important;
                align-items: center !important;
            }

            .header-left {
                flex-direction: row !important;
                align-items: center !important;
                gap: 12px !important;
            }

            .header-left img {
                width: 45px !important;
                height: 45px !important;
            }

            .header-right {
                text-align: right !important;
            }

            .header-text h1 {
                font-size: 15px !important;
            }

            .header-text .subtitle {
                font-size: 10px !important;
            }

            .header::after {
                height: 2px !important;
            }

            .request-number {
                font-size: 20px !important;
            }

            .print-date {
                font-size: 9px !important;
            }

            .content {
                padding: 12px 20px !important;
                flex: 1 !important;
            }

            .document-title {
                font-size: 14px !important;
                margin-bottom: 10px !important;
                padding-bottom: 6px !important;
            }

            .title-bar {
                padding: 8px 12px !important;
                margin-bottom: 10px !important;
                flex-direction: row !important;
                align-items: center !important;
            }

            .title-bar h2 {
                font-size: 12px !important;
                max-width: 60% !important;
            }

            .badge {
                padding: 3px 10px !important;
                font-size: 9px !important;
            }

            .grid-2 {
                grid-template-columns: 1fr 1fr !important;
                gap: 10px !important;
                margin-bottom: 10px !important;
            }

            .card-header {
                padding: 6px 10px !important;
                gap: 7px !important;
            }

            .card-icon {
                width: 18px !important;
                height: 18px !important;
                font-size: 9px !important;
            }

            .card-title {
                font-size: 11px !important;
            }

            .card-body {
                padding: 8px 10px !important;
            }

            .info-list {
                gap: 5px !important;
            }

            .info-row {
                padding-bottom: 4px !important;
            }

            .info-label {
                width: 75px !important;
                font-size: 10px !important;
            }

            .info-value {
                font-size: 10px !important;
            }

            .avatar {
                width: 30px !important;
                height: 30px !important;
                font-size: 12px !important;
            }

            .requester-info {
                gap: 10px !important;
            }

            .requester-details h3 {
                font-size: 11px !important;
                margin-bottom: 2px !important;
            }

            .requester-details p {
                font-size: 10px !important;
                margin: 1px 0 !important;
            }

            .description-box {
                padding: 8px 10px !important;
                font-size: 11px !important;
                line-height: 1.5 !important;
            }

            .admin-remark {
                padding: 6px 10px !important;
                margin-bottom: 10px !important;
            }

            .admin-remark-header {
                font-size: 10px !important;
                margin-bottom: 4px !important;
            }

            .admin-remark-content {
                font-size: 10px !important;
            }

            .timeline {
                gap: 4px !important;
            }

            .timeline-item {
                padding: 5px 8px !important;
                gap: 7px !important;
            }

            .timeline-dot {
                width: 7px !important;
                height: 7px !important;
                margin-top: 3px !important;
            }

            .timeline-header {
                flex-direction: row !important;
                align-items: center !important;
            }

            .timeline-status {
                font-size: 11px !important;
            }

            .timeline-date {
                font-size: 9px !important;
            }

            .timeline-user {
                font-size: 10px !important;
            }

            .timeline-remark {
                font-size: 9px !important;
            }

            .signature-section {
                margin-top: 12px !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 20px !important;
            }

            .signature-box {
                padding-top: 25px !important;
            }

            .signature-line {
                width: 140px !important;
                margin-bottom: 4px !important;
            }

            .signature-label {
                font-size: 10px !important;
            }

            .signature-sublabel {
                font-size: 9px !important;
            }

            .footer {
                padding: 6px 20px !important;
                flex-direction: row !important;
                margin-top: auto !important;
            }

            .footer-left,
            .footer-right {
                font-size: 9px !important;
            }

            .header,
            .footer,
            .card-header,
            .badge-status,
            .avatar,
            .timeline-dot {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        @media screen and (min-width: 821px) {
            body {
                padding: 25px;
            }

            .page {
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                border-radius: 12px;
            }
        }

        @media screen and (max-width: 820px) {
            body {
                padding: 0 !important;
                background: #fff;
            }

            .page {
                width: 100% !important;
                min-height: auto;
                border-radius: 0 !important;
                box-shadow: none !important;
            }

            .content {
                padding: 12px 15px;
            }

            .header {
                padding: 10px 15px;
            }

            .header-text h1 {
                font-size: 15px;
            }

            .header-text .subtitle {
                font-size: 10px;
            }

            .request-number {
                font-size: 18px;
            }

            .document-title {
                font-size: 14px;
                margin-bottom: 10px;
                padding-bottom: 6px;
            }

            .title-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 8px 12px;
                margin-bottom: 10px;
            }

            .title-bar h2 {
                max-width: 100%;
                font-size: 13px;
            }

            .badges {
                flex-wrap: wrap;
            }

            .grid-2 {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .card-header {
                padding: 6px 10px;
            }

            .card-body {
                padding: 8px 10px;
            }

            .info-label {
                width: 75px;
                font-size: 10px;
            }

            .info-value {
                font-size: 10px;
            }

            .avatar {
                width: 32px;
                height: 32px;
                font-size: 13px;
            }

            .requester-details h3 {
                font-size: 11px;
            }

            .requester-details p {
                font-size: 10px;
            }

            .description-box {
                padding: 8px;
                font-size: 11px;
            }

            .admin-remark {
                padding: 8px 10px;
                margin-bottom: 10px;
            }

            .admin-remark-header {
                font-size: 10px;
            }

            .admin-remark-content {
                font-size: 10px;
            }

            .timeline-item {
                padding: 6px 8px;
            }

            .timeline-status {
                font-size: 11px;
            }

            .timeline-date {
                font-size: 9px;
            }

            .timeline-user {
                font-size: 10px;
            }

            .signature-section {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-top: 12px;
            }

            .signature-box {
                padding-top: 20px;
            }

            .footer {
                padding: 6px 15px;
                flex-direction: column;
                gap: 2px;
                text-align: center;
            }

            .action-buttons {
                position: fixed;
                top: auto;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 10px 15px;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
                border-radius: 0;
                justify-content: center;
                z-index: 1000;
            }

            .btn {
                padding: 8px 16px;
                font-size: 12px;
                flex: 1;
                justify-content: center;
            }
        }

        @media screen and (max-width: 480px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .header-right {
                text-align: left;
            }

            .request-number {
                font-size: 16px;
            }

            .header-left img {
                width: 40px !important;
                height: 40px !important;
            }

            .timeline-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }

            .badge {
                padding: 3px 8px;
                font-size: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="action-buttons">
        <a href="view_request.php?id=<?php echo $request_id; ?>" class="btn btn-secondary">← กลับ</a>
        <button onclick="window.print();" class="btn btn-primary">พิมพ์ / บันทึก PDF</button>
    </div>

    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <img src="assets/images/favicon.png" alt="Logo"
                        style="width: 55px; height: 55px; object-fit: contain;">
                    <div class="header-text">
                        <h1><?php echo $settings['site_name'] ?? 'ระบบแจ้งซ่อมออนไลน์'; ?></h1>
                        <div class="subtitle">เอกสารใบแจ้งซ่อม</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="request-number">เลขที่ <?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?></div>
                    <div class="print-date">วันที่พิมพ์: <?php echo thai_date(date('Y-m-d H:i:s'), 'j F Y'); ?></div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Document Title -->
            <div class="document-title">ใบแจ้งซ่อม / Repair Request Form</div>

            <!-- Title Bar -->
            <div class="title-bar">
                <h2><?php echo htmlspecialchars($request['title'] ?? ''); ?></h2>
                <div class="badges">
                    <span class="badge badge-status">
                        <?php echo $status_texts[$request['status']]; ?>
                    </span>
                    <span class="badge badge-priority">
                        ความสำคัญ: <?php echo $priority_texts[$request['priority']]; ?>
                    </span>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="grid-2">
                <!-- Request Info -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">1</div>
                        <div class="card-title">ข้อมูลการแจ้งซ่อม</div>
                    </div>
                    <div class="card-body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-label">หมวดหมู่</span>
                                <span
                                    class="info-value"><?php echo htmlspecialchars($request['category_name'] ?? ''); ?></span>
                            </div>
                            <?php if ($request['location']): ?>
                                <div class="info-row">
                                    <span class="info-label">สถานที่</span>
                                    <span
                                        class="info-value"><?php echo htmlspecialchars($request['location'] ?? ''); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">วันที่แจ้ง</span>
                                <span
                                    class="info-value"><?php echo thai_date($request['created_at'], 'j F Y H:i น.'); ?></span>
                            </div>
                            <?php if ($request['status'] == 'completed' && $request['completed_date']): ?>
                                <div class="info-row">
                                    <span class="info-label">วันที่เสร็จ</span>
                                    <span
                                        class="info-value"><?php echo thai_date($request['completed_date'], 'j F Y H:i น.'); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($completed_by): ?>
                                <div class="info-row">
                                    <span class="info-label">ผู้ดำเนินการ</span>
                                    <span
                                        class="info-value"><?php echo htmlspecialchars($completed_by['fullname'] ?? ''); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Requester Info -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">2</div>
                        <div class="card-title">ข้อมูลผู้แจ้ง</div>
                    </div>
                    <div class="card-body">
                        <div class="requester-info">
                            <div class="avatar"><?php
                            $name = $request['requester_name'] ?? '';
                            if (function_exists('mb_substr')) {
                                echo mb_substr($name, 0, 1);
                            } else {
                                preg_match('/./u', $name, $m);
                                echo $m[0] ?? substr($name, 0, 1);
                            }
                            ?></div>
                            <div class="requester-details">
                                <h3><?php echo htmlspecialchars($request['requester_name'] ?? ''); ?></h3>
                                <p>อีเมล: <?php echo htmlspecialchars($request['requester_email'] ?? ''); ?></p>
                                <?php if ($request['requester_department']): ?>
                                    <p>แผนก: <?php echo htmlspecialchars($request['requester_department'] ?? ''); ?></p>
                                <?php endif; ?>
                                <?php if ($request['requester_phone']): ?>
                                    <p>โทร: <?php echo htmlspecialchars($request['requester_phone'] ?? ''); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="card" style="margin-bottom: 16px;">
                <div class="card-header">
                    <div class="card-icon">3</div>
                    <div class="card-title">รายละเอียดการแจ้งซ่อม</div>
                </div>
                <div class="card-body">
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($request['description'] ?? '')); ?>
                    </div>
                </div>
            </div>

            <!-- Admin Remark -->
            <?php if ($request['admin_remark']): ?>
                <div class="admin-remark">
                    <div class="admin-remark-header">หมายเหตุจากผู้ดูแลระบบ</div>
                    <div class="admin-remark-content"><?php echo nl2br(htmlspecialchars($request['admin_remark'] ?? '')); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">4</div>
                    <div class="card-title">ประวัติการดำเนินการ</div>
                </div>
                <div class="card-body">
                    <?php if ($history_result && mysqli_num_rows($history_result) > 0): ?>
                        <div class="timeline">
                            <?php while ($history = mysqli_fetch_assoc($history_result)): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <span
                                                class="timeline-status"><?php echo $status_texts[$history['status']] ?? $history['status']; ?></span>
                                            <span
                                                class="timeline-date"><?php echo thai_date($history['created_at'], 'j F Y H:i น.'); ?></span>
                                        </div>
                                        <div class="timeline-user">โดย:
                                            <?php echo htmlspecialchars($history['fullname'] ?? ''); ?>
                                        </div>
                                        <?php if ($history['remark']): ?>
                                            <div class="timeline-remark">
                                                "<?php
                                                $remark = $history['remark'] ?? '';
                                                if (function_exists('mb_substr')) {
                                                    echo mb_substr($remark, 0, 80);
                                                    echo mb_strlen($remark) > 80 ? '...' : '';
                                                } else {
                                                    echo substr($remark, 0, 80);
                                                    echo strlen($remark) > 80 ? '...' : '';
                                                }
                                                ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 12px; color: var(--text-light); font-size: 11px;">
                            ยังไม่มีประวัติการดำเนินการ
                        </div>
                    <?php endif; ?>
                </div>
            </div>


        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-left">
                <strong><?php echo $settings['site_name'] ?? 'ระบบแจ้งซ่อมออนไลน์'; ?> มหาวิทยาลัยราชภัฏเลย</strong> |
                ใบแจ้งซ่อมเลขที่
                <?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?>
            </div>
            <div class="footer-right">
                หน้า 1
            </div>
        </div>
    </div>


    <?php
    // ดึงรูปภาพจากประวัติทั้งหมด
    $all_history_images = db_select(
        "SELECT h.image, h.status, h.created_at, u.fullname 
         FROM request_history h 
         JOIN users u ON h.user_id = u.user_id 
         WHERE h.request_id = ? AND h.image IS NOT NULL AND h.image != ''
         ORDER BY h.created_at ASC",
        "i",
        [$request_id]
    );

    $has_main_image = !empty($request['image']);
    $has_history_images = $all_history_images && mysqli_num_rows($all_history_images) > 0;

    if ($has_main_image || $has_history_images):
        ?>
        <!-- หน้าที่ 2: รูปภาพ -->
        <div class="page" style="page-break-before: always; margin-top: 30px;">
            <!-- Header หน้า 2 -->
            <div class="header">
                <div class="header-content">
                    <div class="header-left">
                        <img src="assets/images/favicon.png" alt="Logo"
                            style="width: 55px; height: 55px; object-fit: contain;">
                        <div class="header-text">
                            <h1>รูปภาพประกอบ</h1>
                            <div class="subtitle">ใบแจ้งซ่อมเลขที่ <?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?>
                            </div>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="request-number">เลขที่ <?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?></div>
                        <div class="print-date">วันที่พิมพ์: <?php echo thai_date(date('Y-m-d H:i:s'), 'j F Y'); ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <?php if ($has_main_image): ?>
                    <!-- รูปภาพหลัก -->
                    <div class="card" style="margin-bottom: 16px;">
                        <div class="card-header">
                            <div class="card-icon">A</div>
                            <div class="card-title">รูปภาพประกอบการแจ้งซ่อม</div>
                        </div>
                        <div class="card-body" style="text-align: center;">
                            <img src="<?php echo htmlspecialchars($request['image'] ?? ''); ?>" alt="รูปภาพประกอบ"
                                style="max-width: 100%; max-height: 280px; border-radius: 8px; border: 1px solid var(--border);">
                            <p style="margin-top: 8px; color: var(--text-light); font-size: 11px;">
                                รูปภาพที่แนบมาพร้อมการแจ้งซ่อม</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($has_history_images): ?>
                    <!-- รูปภาพจากประวัติการดำเนินการ -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon">B</div>
                            <div class="card-title">รูปภาพจากการดำเนินการ</div>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <?php while ($img = mysqli_fetch_assoc($all_history_images)): ?>
                                    <div
                                        style="background: var(--bg-light); padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; text-align: center;">
                                        <img src="<?php echo htmlspecialchars($img['image'] ?? ''); ?>" alt="รูปภาพ"
                                            style="max-width: 100%; max-height: 280px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 8px;">
                                        <div style="font-size: 11px; color: var(--text-dark);">
                                            <strong><?php echo $status_texts[$img['status']] ?? $img['status']; ?></strong>
                                            <span style="color: var(--text-light);"> - โดย
                                                <?php echo htmlspecialchars($img['fullname'] ?? ''); ?></span>
                                        </div>
                                        <div style="font-size: 10px; color: var(--text-light);">
                                            <?php echo thai_date($img['created_at'], 'j F Y H:i น.'); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer หน้า 2 -->
            <div class="footer">
                <div class="footer-left">
                    <strong><?php echo $settings['site_name'] ?? 'ระบบแจ้งซ่อมออนไลน์'; ?> มหาวิทยาลัยราชภัฏเลย</strong> |
                    ใบแจ้งซ่อมเลขที่
                    <?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="footer-right">
                    หน้า 2
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>