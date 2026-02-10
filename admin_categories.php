<?php
// กำหนดชื่อหน้า
$page_title = "จัดการหมวดหมู่";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินและเป็นแอดมินหรือไม่
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'building_staff'])) {
    header('Location: login.php');
    exit();
}

// จัดการการเพิ่มหมวดหมู่ใหม่
if (isset($_POST['add_category'])) {
    $category_name = clean_input($_POST['category_name']);
    $description = clean_input($_POST['description']);

    // ตรวจสอบว่าชื่อหมวดหมู่ซ้ำหรือไม่
    $query = "SELECT * FROM categories WHERE category_name = '$category_name'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $error = 'ชื่อหมวดหมู่นี้มีในระบบแล้ว กรุณาใช้ชื่อหมวดหมู่อื่น';
    } else {
        // บันทึกข้อมูลลงในฐานข้อมูล
        $query = "INSERT INTO categories (category_name, description) VALUES ('$category_name', '$description')";

        if (mysqli_query($conn, $query)) {
            $success = 'เพิ่มหมวดหมู่ใหม่เรียบร้อยแล้ว';

            // ส่งการแจ้งเตือนไปยัง Telegram
            send_telegram_notification("<b>มีหมวดหมู่ใหม่ในระบบ</b>\n\nชื่อหมวดหมู่: $category_name\nเพิ่มโดย: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
        } else {
            $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . mysqli_error($conn);
        }
    }
}

// จัดการการแก้ไขหมวดหมู่
if (isset($_POST['edit_category'])) {
    $category_id = clean_input($_POST['category_id']);
    $category_name = clean_input($_POST['category_name']);
    $description = clean_input($_POST['description']);

    // ตรวจสอบว่าชื่อหมวดหมู่ซ้ำหรือไม่ (ยกเว้นหมวดหมู่ปัจจุบัน)
    $query = "SELECT * FROM categories WHERE category_name = '$category_name' AND category_id != '$category_id'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $error = 'ชื่อหมวดหมู่นี้มีในระบบแล้ว กรุณาใช้ชื่อหมวดหมู่อื่น';
    } else {
        // อัพเดตข้อมูลหมวดหมู่
        $query = "UPDATE categories SET 
                  category_name = '$category_name', 
                  description = '$description' 
                  WHERE category_id = '$category_id'";

        if (mysqli_query($conn, $query)) {
            $success = 'อัพเดตข้อมูลหมวดหมู่เรียบร้อยแล้ว';

            // ส่งการแจ้งเตือนไปยัง Telegram
            send_telegram_notification("<b>มีการอัพเดตข้อมูลหมวดหมู่</b>\n\nรหัส: $category_id\nชื่อหมวดหมู่: $category_name\nอัพเดตโดย: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
        } else {
            $error = 'เกิดข้อผิดพลาดในการอัพเดตข้อมูล: ' . mysqli_error($conn);
        }
    }
}

// จัดการการลบหมวดหมู่
if (isset($_POST['delete_category'])) {
    $category_id = clean_input($_POST['category_id']);

    // ตรวจสอบว่ามีรายการแจ้งซ่อมที่ใช้หมวดหมู่นี้หรือไม่
    $query = "SELECT COUNT(*) as count FROM repair_requests WHERE category_id = '$category_id'";
    $result = mysqli_query($conn, $query);
    $count = mysqli_fetch_assoc($result)['count'];

    if ($count > 0) {
        $error = 'ไม่สามารถลบหมวดหมู่นี้ได้ เนื่องจากมีรายการแจ้งซ่อมที่ใช้หมวดหมู่นี้อยู่ (' . $count . ' รายการ)';
    } else {
        // ดึงข้อมูลหมวดหมู่ก่อนลบ
        $query = "SELECT category_name FROM categories WHERE category_id = '$category_id'";
        $result = mysqli_query($conn, $query);
        $category = mysqli_fetch_assoc($result);

        // ลบหมวดหมู่
        $query = "DELETE FROM categories WHERE category_id = '$category_id'";

        if (mysqli_query($conn, $query)) {
            $success = 'ลบหมวดหมู่เรียบร้อยแล้ว';

            // ส่งการแจ้งเตือนไปยัง Telegram
            send_telegram_notification("<b>มีการลบหมวดหมู่</b>\n\nรหัส: $category_id\nชื่อหมวดหมู่: " . $category['category_name'] . "\nลบโดย: " . $_SESSION['fullname'] . "\nเวลา: " . thai_date(date('Y-m-d H:i:s')));
        } else {
            $error = 'เกิดข้อผิดพลาดในการลบหมวดหมู่: ' . mysqli_error($conn);
        }
    }
}

// ดึงข้อมูลหมวดหมู่ทั้งหมด
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM repair_requests WHERE category_id = c.category_id) as request_count 
          FROM categories c 
          ORDER BY c.category_name";
$categories = mysqli_query($conn, $query);

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-category me-2"></i>จัดการหมวดหมู่
    </h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="bx bx-plus-circle me-1"></i>เพิ่มหมวดหมู่ใหม่
    </button>
</div>

<!-- แสดงข้อความแจ้งเตือน -->
<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-1"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-1"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- รายการหมวดหมู่ -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-list-ul me-2"></i>รายการหมวดหมู่ทั้งหมด
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead class="bg-light">
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อหมวดหมู่</th>
                        <th>คำอธิบาย</th>
                        <th>จำนวนรายการแจ้งซ่อม</th>
                        <th>วันที่สร้าง</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                        <tr>
                            <td><?php echo $category['category_id']; ?></td>
                            <td><?php echo $category['category_name']; ?></td>
                            <td><?php echo $category['description'] ?: '-'; ?></td>
                            <td>
                                <?php if ($category['request_count'] > 0): ?>
                                    <span class="badge bg-info"><?php echo $category['request_count']; ?> รายการ</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0 รายการ</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo thai_date($category['created_at'], 'j M Y'); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#editCategoryModal"
                                        data-category-id="<?php echo $category['category_id']; ?>"
                                        data-category-name="<?php echo $category['category_name']; ?>"
                                        data-description="<?php echo $category['description']; ?>">
                                        <i class="bx bx-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                        data-bs-target="#deleteCategoryModal"
                                        data-category-id="<?php echo $category['category_id']; ?>"
                                        data-category-name="<?php echo $category['category_name']; ?>"
                                        data-request-count="<?php echo $category['request_count']; ?>">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- สรุปการใช้งานหมวดหมู่ -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-pie-chart me-2"></i>สถิติการใช้งานหมวดหมู่
        </h6>
    </div>
    <div class="card-body">
        <div class="chart-container" style="position: relative; height:400px;">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- Modal เพิ่มหมวดหมู่ใหม่ -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">เพิ่มหมวดหมู่ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">ชื่อหมวดหมู่ <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-category"></i>
                            </span>
                            <input type="text" class="form-control" id="category_name" name="category_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">คำอธิบาย</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-text"></i>
                            </span>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="add_category" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal แก้ไขหมวดหมู่ -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">แก้ไขข้อมูลหมวดหมู่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">ชื่อหมวดหมู่ <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-category"></i>
                            </span>
                            <input type="text" class="form-control" id="edit_category_name" name="category_name"
                                required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">คำอธิบาย</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bx bx-text"></i>
                            </span>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ลบหมวดหมู่ -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">ยืนยันการลบหมวดหมู่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete_category_id" name="category_id">
                    <div id="delete_message">
                        <p>คุณแน่ใจหรือไม่ที่ต้องการลบหมวดหมู่: <strong id="delete_category_name"></strong>?</p>
                    </div>
                    <div id="delete_warning" class="alert alert-danger d-none">
                        <i class="bx bx-error-circle me-1"></i>ไม่สามารถลบหมวดหมู่นี้ได้
                        เนื่องจากมีรายการแจ้งซ่อมที่ใช้หมวดหมู่นี้อยู่ (<span id="delete_request_count"></span> รายการ)
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="delete_category" class="btn btn-danger" id="delete_button">
                        <i class="bx bx-trash me-1"></i>ลบหมวดหมู่
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // เปิด modal แก้ไขหมวดหมู่
        const editCategoryModal = document.getElementById('editCategoryModal');
        if (editCategoryModal) {
            editCategoryModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const categoryId = button.getAttribute('data-category-id');
                const categoryName = button.getAttribute('data-category-name');
                const description = button.getAttribute('data-description');

                const modalCategoryId = editCategoryModal.querySelector('#edit_category_id');
                const modalCategoryName = editCategoryModal.querySelector('#edit_category_name');
                const modalDescription = editCategoryModal.querySelector('#edit_description');

                modalCategoryId.value = categoryId;
                modalCategoryName.value = categoryName;
                modalDescription.value = description;
            });
        }

        // เปิด modal ลบหมวดหมู่
        const deleteCategoryModal = document.getElementById('deleteCategoryModal');
        if (deleteCategoryModal) {
            deleteCategoryModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const categoryId = button.getAttribute('data-category-id');
                const categoryName = button.getAttribute('data-category-name');
                const requestCount = button.getAttribute('data-request-count');

                const modalCategoryId = deleteCategoryModal.querySelector('#delete_category_id');
                const modalCategoryName = deleteCategoryModal.querySelector('#delete_category_name');
                const modalRequestCount = deleteCategoryModal.querySelector('#delete_request_count');
                const deleteWarning = deleteCategoryModal.querySelector('#delete_warning');
                const deleteButton = deleteCategoryModal.querySelector('#delete_button');

                modalCategoryId.value = categoryId;
                modalCategoryName.textContent = categoryName;
                modalRequestCount.textContent = requestCount;

                // ตรวจสอบว่ามีรายการแจ้งซ่อมที่ใช้หมวดหมู่นี้หรือไม่
                if (requestCount > 0) {
                    deleteWarning.classList.remove('d-none');
                    deleteButton.disabled = true;
                } else {
                    deleteWarning.classList.add('d-none');
                    deleteButton.disabled = false;
                }
            });
        }

        // สร้างแผนภูมิสถิติการใช้งานหมวดหมู่
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = [];
        const categoryLabels = [];
        const categoryColors = [
            '#4E73DF',
            '#1CC88A',
            '#36B9CC',
            '#F6C23E',
            '#E74A3B',
            '#858796',
            '#6563FF',
            '#20C997',
            '#0DCAF0',
            '#FFC107',
            '#DC3545'
        ];

        <?php
        mysqli_data_seek($categories, 0);
        while ($category = mysqli_fetch_assoc($categories)):
            ?>
            categoryLabels.push('<?php echo $category['category_name']; ?>');
            categoryData.push(<?php echo $category['request_count']; ?>);
        <?php endwhile; ?>

        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: 'จำนวนรายการแจ้งซ่อม',
                    data: categoryData,
                    backgroundColor: categoryColors,
                    borderWidth: 0,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'จำนวนรายการแจ้งซ่อมแยกตามหมวดหมู่',
                        font: {
                            size: 16,
                            family: 'Prompt'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                family: 'Prompt'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Prompt'
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    });
</script>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>