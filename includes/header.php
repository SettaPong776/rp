<?php
// ตรวจสอบว่ามีการเชื่อมต่อกับฐานข้อมูลหรือไม่
if (!isset($conn)) {
    require_once dirname(__FILE__) . '/../config/db_connect.php';
}

// ดึงข้อมูลการตั้งค่าของระบบ
$query = "SELECT * FROM settings";
$settings_result = mysqli_query($conn, $query);
$settings = [];

while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

// ตรวจสอบว่ามีการล็อกอินหรือไม่
$is_logged_in = isset($_SESSION['user_id']);
$current_user = null;

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE user_id = '$user_id'";
    $user_result = mysqli_query($conn, $query);
    $current_user = mysqli_fetch_assoc($user_result);
}

// ดึง URL ปัจจุบันเพื่อไฮไลท์เมนูที่กำลังใช้งาน
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo $settings['site_name'] ?? 'ระบบแจ้งซ่อมออนไลน์'; ?>
    </title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.png" type="image/png">
    <link rel="icon" href="assets/images/favicon.png" type="image/png">

    <!-- Google Fonts - Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Boxicons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <!-- Datatables CSS -->
    <link href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #6563ff;
            --primary-dark: #5452d8;
            --secondary-color: #fd7e14;
            --success-color: #20c997;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background: linear-gradient(to bottom, var(--primary-color), var(--primary-dark));
            color: white;
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .sidebar .logo-container {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo-container .logo {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            overflow: visible;
            display: flex;
            align-items: center;
        }

        .sidebar .logo-container .toggle-btn {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.5rem;
        }

        .sidebar .menu {
            padding: 20px 0;
        }

        .sidebar .menu-item {
            padding: 10px 20px;
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar .menu-item:hover,
        .sidebar .menu-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .menu-item i {
            font-size: 1.5rem;
            margin-right: 15px;
            min-width: 24px;
            text-align: center;
        }

        .sidebar.collapsed .menu-item span,
        .sidebar.collapsed .logo-text {
            display: none;
        }

        .content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

        .content.expanded {
            margin-left: 60px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .topbar .user-info {
            display: flex;
            align-items: center;
        }

        .topbar .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .mobile-toggle {
            display: none;
            background: transparent;
            border: none;
            color: var(--primary-color);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* กำหนด Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }

            .sidebar.mobile-show {
                left: 0;
            }

            .content {
                margin-left: 0;
            }

            .content.expanded {
                margin-left: 0;
            }

            .mobile-toggle {
                display: block;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <a href="index.php" class="logo">
                <img src="assets/images/favicon.png" alt="Logo"
                    style="width: 35px; height: 35px; object-fit: contain; margin-right: 8px;">
                <span class="logo-text">ระบบแจ้งซ่อม</span>
            </a>
            <button class="toggle-btn" id="sidebarToggle">
                <i class="bx bx-menu"></i>
            </button>
        </div>

        <div class="menu">
            <?php if ($is_logged_in): ?>
                <?php if ($current_user['role'] == 'admin' || $current_user['role'] == 'building_staff'): ?>
                    <!-- Admin Menu -->
                    <a href="admin_dashboard.php"
                        class="menu-item <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                        <i class="bx bx-tachometer"></i>
                        <span>แดชบอร์ด</span>
                    </a>
                    <a href="admin_requests.php"
                        class="menu-item <?php echo ($current_page == 'admin_requests.php') ? 'active' : ''; ?>">
                        <i class="bx bx-list-ul"></i>
                        <span>รายการแจ้งซ่อม</span>
                    </a>
                    <a href="admin_categories.php"
                        class="menu-item <?php echo ($current_page == 'admin_categories.php') ? 'active' : ''; ?>">
                        <i class="bx bx-category"></i>
                        <span>หมวดหมู่</span>
                    </a>
                    <a href="admin_users.php"
                        class="menu-item <?php echo ($current_page == 'admin_users.php') ? 'active' : ''; ?>">
                        <i class="bx bx-user"></i>
                        <span>ผู้ใช้งาน</span>
                    </a>
                    <a href="admin_reports.php"
                        class="menu-item <?php echo ($current_page == 'admin_reports.php') ? 'active' : ''; ?>">
                        <i class="bx bx-bar-chart-alt-2"></i>
                        <span>รายงาน</span>
                    </a>
                    <?php if ($current_user['role'] == 'admin'): ?>
                        <a href="admin_settings.php"
                            class="menu-item <?php echo ($current_page == 'admin_settings.php') ? 'active' : ''; ?>">
                            <i class="bx bx-cog"></i>
                            <span>ตั้งค่า</span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- User Menu -->
                    <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                        <i class="bx bx-home-alt"></i>
                        <span>หน้าหลัก</span>
                    </a>
                    <a href="create_request.php"
                        class="menu-item <?php echo ($current_page == 'create_request.php') ? 'active' : ''; ?>">
                        <i class="bx bx-plus-circle"></i>
                        <span>แจ้งซ่อมใหม่</span>
                    </a>
                    <a href="my_requests.php"
                        class="menu-item <?php echo ($current_page == 'my_requests.php') ? 'active' : ''; ?>">
                        <i class="bx bx-list-ul"></i>
                        <span>รายการแจ้งซ่อมของฉัน</span>
                    </a>
                    <a href="profile.php" class="menu-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                        <i class="bx bx-user"></i>
                        <span>ข้อมูลส่วนตัว</span>
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="menu-item">
                    <i class="bx bx-log-out"></i>
                    <span>ออกจากระบบ</span>
                </a>
            <?php else: ?>
                <!-- Unregistered User Menu -->
                <a href="index.php" class="menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="bx bx-home-alt"></i>
                    <span>หน้าหลัก</span>
                </a>
                <a href="login.php" class="menu-item <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>">
                    <i class="bx bx-log-in"></i>
                    <span>เข้าสู่ระบบ</span>
                </a>
                <a href="register.php" class="menu-item <?php echo ($current_page == 'register.php') ? 'active' : ''; ?>">
                    <i class="bx bx-user-plus"></i>
                    <span>สมัครสมาชิก</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="topbar">
            <button class="mobile-toggle" id="mobileToggle">
                <i class="bx bx-menu"></i>
            </button>

            <?php if ($is_logged_in): ?>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_user['fullname']); ?>&background=random"
                        alt="User Avatar">
                    <div>
                        <h6 class="mb-0"><?php echo $current_user['fullname']; ?></h6>
                        <small class="text-muted"><?php
                        if ($current_user['role'] == 'admin')
                            echo 'ผู้ดูแลระบบ';
                        elseif ($current_user['role'] == 'building_staff')
                            echo 'งานอาคาร';
                        else
                            echo 'ผู้ใช้งาน';
                        ?></small>
                    </div>
                </div>
            <?php else: ?>
                <div>
                    <a href="login.php" class="btn btn-primary btn-sm me-2">เข้าสู่ระบบ</a>
                    <a href="register.php" class="btn btn-outline-primary btn-sm">สมัครสมาชิก</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ส่วนเนื้อหาหลัก -->
        <div class="container-fluid">