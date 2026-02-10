<?php
// กำหนดชื่อหน้า
$page_title = "หน้าหลัก";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบว่าเป็นผู้ใช้ทั่วไป (ไม่ใช่แอดมิน)
if (in_array($_SESSION['role'], ['admin', 'building_staff'])) {
    header('Location: admin_dashboard.php');
    exit();
}

// ดึงข้อมูลผู้ใช้
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// ดึงข้อมูลสถิติรายการแจ้งซ่อมของผู้ใช้
$query = "SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
            COUNT(*) as total_count
          FROM repair_requests 
          WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);
$request_stats = mysqli_fetch_assoc($result);

// ดึงรายการแจ้งซ่อมล่าสุดของผู้ใช้
$query = "SELECT r.*, c.category_name 
          FROM repair_requests r
          JOIN categories c ON r.category_id = c.category_id
          WHERE r.user_id = '$user_id'
          ORDER BY r.created_at DESC 
          LIMIT 5";
$recent_requests = mysqli_query($conn, $query);

// ดึงข้อมูลหมวดหมู่
$query = "SELECT * FROM categories ORDER BY category_name";
$categories = mysqli_query($conn, $query);

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-home-alt me-3"></i>หน้าหลัก
    </h1>
    <a href="create_request.php" class="btn btn-primary">
        <i class="bx bx-plus-circle me-1"></i>แจ้งซ่อมใหม่
    </a>
</div>

<!-- ส่วนต้อนรับ -->
<div class="card border-0 shadow mb-4">
    <div class="card-body p-0">
        <div class="row g-0">
            <div class="col-md-8">
                <div class="p-4 p-md-5">
                    <h2 class="text-primary fw-bold">สวัสดี, <?php echo $user['fullname']; ?></h2>
                    <p class="text-muted">ยินดีต้อนรับเข้าสู่ระบบแจ้งซ่อมออนไลน์
                        คุณสามารถแจ้งซ่อมและติดตามสถานะได้ที่นี่</p>
                </div>
            </div>
            <div class="col-md-4 d-none d-md-block text-end">
                <i class="bx bx-wrench text-primary p-4" style="font-size: 8rem; opacity: 0.1;"></i>
            </div>
        </div>
    </div>
</div>

<!-- บัตรสรุปสถิติ -->
<div class="row mb-4">
    <!-- รายการทั้งหมด -->
    <div class="col-md-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #EBF3FE;">
                            <i class="bx bx-package text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-primary"><?php echo $request_stats['total_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">รายการทั้งหมด</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- เสร็จสิ้น -->
    <div class="col-md-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #E6F8F1;">
                            <i class="bx bx-check-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-success"><?php echo $request_stats['completed_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">เสร็จสิ้น</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- กำลังดำเนินการ -->
    <div class="col-md-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #E7F4FF;">
                            <i class="bx bx-loader text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-info"><?php echo $request_stats['in_progress_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">กำลังดำเนินการ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- รอดำเนินการ -->
    <div class="col-md-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #FFF6E9;">
                            <i class="bx bx-time text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-warning"><?php echo $request_stats['pending_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">รอดำเนินการ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- รายการแจ้งซ่อมล่าสุด -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-list-ul me-2"></i>รายการแจ้งซ่อมล่าสุดของคุณ
        </h6>
        <a href="my_requests.php" class="btn btn-sm btn-primary">
            ดูทั้งหมด <i class="bx bx-right-arrow-alt"></i>
        </a>
    </div>
    <div class="card-body">
        <?php if (mysqli_num_rows($recent_requests) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th>หมายเลข</th>
                            <th>เรื่อง</th>
                            <th>หมวดหมู่</th>
                            <th>สถานะ</th>
                            <th>ความสำคัญ</th>
                            <th>วันที่แจ้ง</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = mysqli_fetch_assoc($recent_requests)): ?>
                            <tr>
                                <td>#<?php echo $request['request_id']; ?></td>
                                <td><?php echo $request['title']; ?></td>
                                <td><?php echo $request['category_name']; ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'pending' => '<span class="badge bg-warning text-dark">รอดำเนินการ</span>',
                                        'in_progress' => '<span class="badge bg-info text-white">กำลังดำเนินการ</span>',
                                        'completed' => '<span class="badge bg-success">เสร็จสิ้น</span>',
                                        'rejected' => '<span class="badge bg-danger">ยกเลิก</span>'
                                    ];
                                    echo $status_badges[$request['status']];
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $priority_badges = [
                                        'low' => '<span class="badge bg-success">ต่ำ</span>',
                                        'medium' => '<span class="badge bg-warning text-dark">ปานกลาง</span>',
                                        'high' => '<span class="badge bg-danger">สูง</span>',
                                        'urgent' => '<span class="badge bg-danger"><i class="bx bx-error-circle me-1"></i>เร่งด่วน</span>'
                                    ];
                                    echo $priority_badges[$request['priority']];
                                    ?>
                                </td>
                                <td><?php echo thai_date($request['created_at']); ?></td>
                                <td>
                                    <a href="view_request.php?id=<?php echo $request['request_id']; ?>"
                                        class="btn btn-sm btn-primary">
                                        <i class="bx bx-show-alt"></i> ดูรายละเอียด
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bx bx-clipboard-x text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">ไม่มีรายการแจ้งซ่อม</h5>
                <p class="text-muted">คุณยังไม่มีรายการแจ้งซ่อมในระบบ</p>
                <a href="create_request.php" class="btn btn-primary mt-3">
                    <i class="bx bx-plus-circle me-1"></i>แจ้งซ่อมใหม่
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- คำแนะนำการใช้งาน -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="text-center">
                    <div class="rounded-circle bg-primary d-inline-flex p-3 mb-3">
                        <i class="bx bx-plus-circle text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h5>แจ้งซ่อม</h5>
                    <p class="text-muted">กรอกรายละเอียดการแจ้งซ่อม เลือกหมวดหมู่และความสำคัญ</p>
                </div>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="text-center">
                    <div class="rounded-circle bg-info d-inline-flex p-3 mb-3">
                        <i class="bx bx-time text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h5>ติดตามสถานะ</h5>
                    <p class="text-muted">ตรวจสอบสถานะการดำเนินการได้จากรายการแจ้งซ่อมของคุณ</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <div class="rounded-circle bg-success d-inline-flex p-3 mb-3">
                        <i class="bx bx-check-circle text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h5>ได้รับการซ่อม</h5>
                    <p class="text-muted">เมื่อการซ่อมเสร็จสิ้น สถานะจะเปลี่ยนเป็นเสร็จสิ้น</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        border-radius: 10px;
        overflow: hidden;
    }

    .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, .05);
    }

    /* Animation for cards */
    .card {
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .rounded-circle {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>