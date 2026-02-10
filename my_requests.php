<?php
// กำหนดชื่อหน้า
$page_title = "รายการแจ้งซ่อมของฉัน";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบว่าเป็นผู้ใช้ทั่วไป (ถ้าเป็นแอดมินให้ไปที่หน้า admin_requests.php)
if (in_array($_SESSION['role'], ['admin', 'building_staff'])) {
    header('Location: admin_requests.php');
    exit();
}

// ตัวแปรสำหรับกรองข้อมูล
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$category_filter = isset($_GET['category_id']) ? clean_input($_GET['category_id']) : '';
$date_filter = isset($_GET['date']) ? clean_input($_GET['date']) : '';

// ดึงข้อมูลผู้ใช้
$user_id = $_SESSION['user_id'];

// ดึงข้อมูลรายการแจ้งซ่อมของผู้ใช้
$query = "SELECT r.*, c.category_name 
          FROM repair_requests r 
          JOIN categories c ON r.category_id = c.category_id 
          WHERE r.user_id = '$user_id'";

// เพิ่มเงื่อนไขการกรอง
if ($status_filter) {
    $query .= " AND r.status = '$status_filter'";
}

if ($category_filter) {
    $query .= " AND r.category_id = '$category_filter'";
}

if ($date_filter) {
    $query .= " AND DATE(r.created_at) = '$date_filter'";
}

$query .= " ORDER BY r.created_at DESC";
$requests = mysqli_query($conn, $query);

// ดึงข้อมูลหมวดหมู่
$query = "SELECT * FROM categories ORDER BY category_name";
$categories = mysqli_query($conn, $query);

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-list-ul me-2"></i>รายการแจ้งซ่อมของฉัน
    </h1>
    <a href="create_request.php" class="btn btn-primary">
        <i class="bx bx-plus-circle me-1"></i>แจ้งซ่อมใหม่
    </a>
</div>

<!-- ส่วนกรองข้อมูล -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-filter-alt me-2"></i>ตัวกรองข้อมูล
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">สถานะ</label>
                <select class="form-select" id="status" name="status">
                    <option value="">ทั้งหมด</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ
                    </option>
                    <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>
                        กำลังดำเนินการ</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น
                    </option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>ยกเลิก</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="category_id" class="form-label">หมวดหมู่</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">ทั้งหมด</option>
                    <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?php echo $category['category_id']; ?>" <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                            <?php echo $category['category_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date" class="form-label">วันที่แจ้ง</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="d-grid gap-2 w-100">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-filter me-1"></i>กรองข้อมูล
                    </button>
                    <a href="my_requests.php" class="btn btn-outline-secondary">
                        <i class="bx bx-reset me-1"></i>ล้างตัวกรอง
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- รายการแจ้งซ่อม -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-list-ul me-2"></i>รายการแจ้งซ่อมทั้งหมดของคุณ
        </h6>
    </div>
    <div class="card-body">
        <?php if (mysqli_num_rows($requests) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable">
                    <thead class="bg-light">
                        <tr>
                            <th>หมายเลข</th>
                            <th>เรื่อง</th>
                            <th>หมวดหมู่</th>
                            <th>สถานที่</th>
                            <th>สถานะ</th>
                            <th>ความสำคัญ</th>
                            <th>วันที่แจ้ง</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = mysqli_fetch_assoc($requests)): ?>
                            <tr>
                                <td>#<?php echo $request['request_id']; ?></td>
                                <td><?php echo $request['title']; ?></td>
                                <td><?php echo $request['category_name']; ?></td>
                                <td><?php echo $request['location'] ?: '-'; ?></td>
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
                                <td><?php echo thai_date($request['created_at'], 'j M Y'); ?></td>
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

<!-- คำแนะนำการติดตามสถานะ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row">
            <div class="col-md-6 mb-4 mb-md-0">
                <h5 class="mb-3">สถานะและความหมาย</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <span class="badge bg-warning text-dark me-2">รอดำเนินการ</span>
                        รายการแจ้งซ่อมที่ยังไม่ได้เริ่มดำเนินการ
                    </li>
                    <li class="mb-2">
                        <span class="badge bg-info text-white me-2">กำลังดำเนินการ</span>
                        รายการแจ้งซ่อมที่อยู่ระหว่างดำเนินการแก้ไข
                    </li>
                    <li class="mb-2">
                        <span class="badge bg-success me-2">เสร็จสิ้น</span>
                        รายการแจ้งซ่อมที่ดำเนินการเสร็จสิ้นแล้ว
                    </li>
                    <li>
                        <span class="badge bg-danger me-2">ยกเลิก</span>
                        รายการแจ้งซ่อมที่ถูกยกเลิกหรือไม่สามารถดำเนินการได้
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h5 class="mb-3">ความสำคัญและการตอบสนอง</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <span class="badge bg-success me-2">ต่ำ</span>
                        ปัญหาที่ไม่เร่งด่วน จะได้รับการแก้ไขตามลำดับ
                    </li>
                    <li class="mb-2">
                        <span class="badge bg-warning text-dark me-2">ปานกลาง</span>
                        ปัญหาทั่วไป จะได้รับการแก้ไขภายใน 1-3 วันทำการ
                    </li>
                    <li class="mb-2">
                        <span class="badge bg-danger me-2">สูง</span>
                        ปัญหาสำคัญ จะได้รับการแก้ไขภายใน 24 ชั่วโมง
                    </li>
                    <li>
                        <span class="badge bg-danger me-2"><i class="bx bx-error-circle"></i> เร่งด่วน</span>
                        ปัญหาฉุกเฉิน จะได้รับการแก้ไขทันที
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>