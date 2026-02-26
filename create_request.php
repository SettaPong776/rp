<?php
// กำหนดชื่อหน้า
$page_title = "แจ้งซ่อมใหม่";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ดึงข้อมูลผู้ใช้
$user_id = $_SESSION['user_id'];
$result = db_select("SELECT * FROM users WHERE user_id = ?", "i", [$user_id]);
$user = mysqli_fetch_assoc($result);

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $category_id = intval($_POST['category_id']);
    $location = trim($_POST['location']);
    $asset_number = trim($_POST['asset_number']);
    $description = trim($_POST['description']);
    $priority = 'medium'; // ค่า default - admin/building_staff จะกำหนดความสำคัญเองในภายหลัง
    $image = '';

    // ตรวจสอบว่ามีข้อมูลครบหรือไม่
    if (empty($title) || empty($category_id) || empty($description)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } else {
        // จัดการการอัพโหลดรูปภาพ (ถ้ามี)
        if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
            $upload_dir = 'uploads/';
            $file_name = basename($_FILES['image']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            // ตรวจสอบนามสกุลไฟล์
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_ext, $allowed_exts)) {
                $error = 'อัพโหลดได้เฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif) เท่านั้น';
            } else {
                // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
                if ($_FILES['image']['size'] > 5000000) {
                    $error = 'ขนาดไฟล์ต้องไม่เกิน 5MB';
                } else {
                    // สร้างโฟลเดอร์ uploads ถ้ายังไม่มี
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    // อัพโหลดไฟล์
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                        $image = $upload_path;
                    } else {
                        $error = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
                    }
                }
            }
        }

        if (!isset($error)) {
            // บันทึกข้อมูลลงในฐานข้อมูล
            $request_id = db_insert(
                "INSERT INTO repair_requests (user_id, category_id, title, description, location, asset_number, priority, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                "iissssss",
                [$user_id, $category_id, $title, $description, $location, $asset_number, $priority, $image]
            );

            if ($request_id) {
                // บันทึกประวัติการอัพเดท
                add_request_history($request_id, $user_id, 'pending', 'สร้างรายการแจ้งซ่อมใหม่');

                // ดึงข้อมูลหมวดหมู่
                $result = db_select("SELECT category_name FROM categories WHERE category_id = ?", "i", [$category_id]);
                $category = mysqli_fetch_assoc($result);

                // ส่งการแจ้งเตือนไปยัง Telegram
                $priority_text = "";
                switch ($priority) {
                    case 'low':
                        $priority_text = "ต่ำ";
                        break;
                    case 'medium':
                        $priority_text = "ปานกลาง";
                        break;
                    case 'high':
                        $priority_text = "สูง";
                        break;
                    case 'urgent':
                        $priority_text = "เร่งด่วน";
                        break;
                }

                send_telegram_notification("<b>มีรายการแจ้งซ่อมใหม่</b>\n\nหมายเลข: #" . $request_id .
                    "\nเรื่อง: " . $title .
                    "\nผู้แจ้ง: " . $user['fullname'] .
                    "\nหมวดหมู่: " . $category['category_name'] .
                    "\nความสำคัญ: " . $priority_text .
                    "\nสถานที่: " . ($location ?: 'ไม่ระบุ') .
                    "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));

                // Redirect ไปยังหน้าดูรายละเอียด
                header('Location: view_request.php?id=' . $request_id . '&success=1');
                exit();
            } else {
                $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            }
        }
    }
}

// ดึงข้อมูลหมวดหมู่
$query = "SELECT * FROM categories ORDER BY category_name";
$categories = mysqli_query($conn, $query);

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-plus-circle me-2"></i>แจ้งซ่อมใหม่
    </h1>
</div>

<!-- แสดงข้อความแจ้งเตือน -->
<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-1"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- ฟอร์มแจ้งซ่อม -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-edit me-2"></i>กรอกข้อมูลการแจ้งซ่อม
        </h6>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="title" class="form-label">หัวข้อเรื่อง <span class="text-danger">*</span></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light"><i class="bx bx-heading"></i></span>
                        <input type="text" class="form-control" id="title" name="title"
                            placeholder="ระบุหัวข้อเรื่องที่ต้องการแจ้งซ่อม" required
                            value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="category_id" class="form-label">หมวดหมู่ <span class="text-danger">*</span></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light"><i class="bx bx-category"></i></span>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">เลือกหมวดหมู่</option>
                            <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo $category['category_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="location" class="form-label">สถานที่/หมายเลขห้อง/อาคาร</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light"><i class="bx bx-map"></i></span>
                        <input type="text" class="form-control" id="location" name="location"
                            placeholder="ระบุสถานที่ หมายเลขห้อง และ หมายเลขอาคาร"
                            value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="asset_number" class="form-label">หมายเลขครุภัณฑ์ <span
                            class="text-muted small">(ถ้ามี)</span></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light"><i class="bx bx-barcode"></i></span>
                        <input type="text" class="form-control" id="asset_number" name="asset_number"
                            placeholder="เช่น มรล.07.303.02/68"
                            value="<?php echo isset($_POST['asset_number']) ? htmlspecialchars($_POST['asset_number']) : ''; ?>">
                    </div>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">รายละเอียด <span class="text-danger">*</span></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light"><i class="bx bx-text"></i></span>
                        <textarea class="form-control" id="description" name="description" rows="5"
                            placeholder="ระบุรายละเอียดของปัญหาที่ต้องการแจ้งซ่อมและระบุหมายเลขครุภัณฑ์ด้วย(ถ้ามี)"
                            required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                </div>

                <div class="col-12">
                    <label for="image" class="form-label">รูปภาพประกอบ (ถ้ามี)</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light"><i class="bx bx-image"></i></span>
                        <input type="file" class="form-control" id="image" name="image">
                    </div>
                    <div class="form-text">อัพโหลดได้เฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif) ขนาดไม่เกิน 5MB</div>
                </div>

                <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="<?php echo in_array($_SESSION['role'], ['admin', 'building_staff']) ? 'admin_dashboard.php' : 'dashboard.php'; ?>"
                        class="btn btn-secondary">
                        <i class="bx bx-arrow-back me-1"></i>ยกเลิก
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>บันทึกรายการแจ้งซ่อม
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- คำแนะนำการแจ้งซ่อม -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-help-circle me-2"></i>คำแนะนำการแจ้งซ่อม
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-3">การกรอกข้อมูล</h5>
                <ul>
                    <li><strong>หัวข้อเรื่อง:</strong> ระบุหัวข้อที่ตรงประเด็นและเข้าใจง่าย</li>
                    <li><strong>หมวดหมู่:</strong> เลือกหมวดหมู่ที่ตรงกับประเภทของปัญหามากที่สุด</li>
                    
                    <li><strong>สถานที่:</strong> ระบุสถานที่ที่เกิดปัญหาให้ชัดเจน เช่น ห้อง, หมายเลขห้อง, ชั้น, อาคาร </li>
                    <li><strong>หมายเลขครุภัณฑ์:</strong> มรล.07.303.02/68</li>

                    <li><strong>รายละเอียด:</strong> อธิบายปัญหาให้ละเอียดและชัดเจนที่สุดและระบุหมายเลขครุภัณฑ์(ถ้ามี)
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h5 class="mb-3">ตัวอย่างรายละเอียดที่ดี</h5>
                <div class="alert alert-light">
                    <p class="mb-0"><strong>หัวข้อ:</strong> เครื่องปรับอากาศไม่เย็น</p>
                    <p class="mb-0"><strong>หมวดหมู่:</strong> เครื่องปรับอากาศ</p>
                    <p class="mb-0"><strong>หมายเลขครุภัณฑ์:</strong> มรล.07.303.02/68 </p>
                    <p class="mb-0"><strong>สถานที่:</strong> ห้องประชุม 301 ชั้น 3 อาคาร 20 </p>

                    <p class="mb-0"><strong>รายละเอียด:</strong> เครื่องปรับอากาศทำงานปกติแต่ไม่เย็น
                        มีเสียงดังผิดปกติเวลาเปิด และมีน้ำหยดจากเครื่อง ปัญหาเริ่มเกิดขึ้นเมื่อวานนี้ (18 พ.ค. 2566)
                        ช่วงบ่าย หมายเลขครุภัณฑ์ มรล. 07.303.02/68</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .input-group-text {
        min-width: 45px;
        justify-content: center;
    }
</style>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>