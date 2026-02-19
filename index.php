<?php
// กำหนดชื่อหน้า
$page_title = "หน้าหลัก";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (isset($_SESSION['user_id'])) {
    // ถ้าล็อกอินแล้ว ให้ redirect ไปยังหน้าที่เหมาะสม
    if (in_array($_SESSION['role'], ['admin', 'building_staff'])) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// ดึงข้อมูลการตั้งค่าของระบบ
$query = "SELECT * FROM settings";
$settings_result = mysqli_query($conn, $query);
$settings = [];

while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="card border-0 shadow-lg mb-5">
    <div class="card-body p-0">
        <div class="row g-0">
            <div class="col-lg-6">
                <div class="p-5">
                    <h1 class="display-4 fw-bold text-primary mb-3">ระบบแจ้งซ่อมออนไลน์</h1>
                    <p class="lead mb-4">
                        ระบบแจ้งซ่อมออนไลน์ที่ช่วยให้คุณแจ้งซ่อมและติดตามการซ่อมบำรุงได้อย่างสะดวกและรวดเร็ว</p>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="login.php" class="btn btn-primary btn-lg px-4 me-md-2">
                            <i class="bx bx-log-in me-1"></i>เข้าสู่ระบบ
                        </a>
                        <a href="register.php" class="btn btn-outline-primary btn-lg px-4">
                            <i class="bx bx-user-plus me-1"></i>สมัครสมาชิก
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="bg-primary h-100 d-flex align-items-center justify-content-center p-5">
                    <i class="bx bx-wrench text-white" style="font-size: 15rem; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="row mb-5">
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow h-100">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-primary d-inline-flex p-3 mb-3">
                    <i class="bx bx-plus-circle text-white" style="font-size: 2rem;"></i>
                </div>
                <h4>แจ้งซ่อมง่าย</h4>
                <p class="text-muted">แจ้งซ่อมได้ง่ายและรวดเร็วผ่านระบบออนไลน์ เพียงกรอกข้อมูลและรายละเอียดการแจ้งซ่อม
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow h-100">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-info d-inline-flex p-3 mb-3">
                    <i class="bx bx-search-alt text-white" style="font-size: 2rem;"></i>
                </div>
                <h4>ติดตามสถานะ</h4>
                <p class="text-muted">ติดตามสถานะการซ่อมบำรุงได้ตลอดเวลา ดูความคืบหน้าและประวัติการอัพเดท</p>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow h-100">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-success d-inline-flex p-3 mb-3">
                    <i class="bx bx-check-circle text-white" style="font-size: 2rem;"></i>
                </div>
                <h4>ได้รับการซ่อม</h4>
                <p class="text-muted">เมื่อการซ่อมเสร็จสิ้น จะได้รับรายงานการซ่อมและสถานะจะเปลี่ยนเป็นเสร็จสิ้น</p>
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="card border-0 shadow mb-5">
    <div class="card-header bg-white py-3">
        <h4 class="m-0 fw-bold text-primary">
            <i class="bx bx-help-circle me-2"></i>วิธีการใช้งาน
        </h4>
    </div>
    <div class="card-body p-4">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <div class="text-center">
                    <div class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center mb-3"
                        style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">1</div>
                    <h5>สมัครสมาชิก</h5>
                    <p class="text-muted">สร้างบัญชีผู้ใช้ด้วยข้อมูลส่วนตัวของคุณ</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <div class="text-center">
                    <div class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center mb-3"
                        style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">2</div>
                    <h5>แจ้งซ่อม</h5>
                    <p class="text-muted">กรอกรายละเอียดการแจ้งซ่อม เลือกหมวดหมู่และความสำคัญ</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <div class="text-center">
                    <div class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center mb-3"
                        style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">3</div>
                    <h5>ติดตามสถานะ</h5>
                    <p class="text-muted">ตรวจสอบความคืบหน้าของการซ่อมบำรุงและรับการแจ้งเตือน</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <div class="text-center">
                    <div class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center mb-3"
                        style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">4</div>
                    <h5>เสร็จสิ้น</h5>
                    <p class="text-muted">รับทราบเมื่อการซ่อมบำรุงเสร็จสิ้น และพร้อมใช้งาน</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FAQ Section -->
<div class="card border-0 shadow mb-5">
    <div class="card-header bg-white py-3">
        <h4 class="m-0 fw-bold text-primary">
            <i class="bx bx-question-mark me-2"></i>คำถามที่พบบ่อย
        </h4>
    </div>
    <div class="card-body p-4">
        <div class="accordion" id="faqAccordion">
            <div class="accordion-item border-0 mb-3">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        ฉันจะสมัครสมาชิกได้อย่างไร?
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                    data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        คุณสามารถสมัครสมาชิกได้โดยคลิกที่ปุ่ม "สมัครสมาชิก" จากนั้นกรอกข้อมูลส่วนตัวของคุณ เช่น
                        ชื่อผู้ใช้ รหัสผ่าน อีเมล และข้อมูลอื่นๆ ที่จำเป็น หลังจากยืนยันการสมัครสมาชิกแล้ว
                        คุณจะสามารถเข้าสู่ระบบและใช้งานระบบแจ้งซ่อมได้ทันที
                    </div>
                </div>
            </div>

            <div class="accordion-item border-0 mb-3">
                <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        ฉันจะแจ้งซ่อมได้อย่างไร?
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo"
                    data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        หลังจากเข้าสู่ระบบแล้ว คุณสามารถแจ้งซ่อมได้โดยคลิกที่ปุ่ม "แจ้งซ่อมใหม่"
                        จากนั้นกรอกรายละเอียดการแจ้งซ่อม เลือกหมวดหมู่ ระบุความสำคัญ และอัพโหลดรูปภาพประกอบ (ถ้ามี)
                        หลังจากบันทึกรายการแจ้งซ่อมแล้ว ระบบจะส่งการแจ้งเตือนไปยังผู้ดูแลระบบเพื่อดำเนินการต่อไป
                    </div>
                </div>
            </div>

            <div class="accordion-item border-0 mb-3">
                <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        ฉันจะติดตามสถานะการแจ้งซ่อมได้อย่างไร?
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree"
                    data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        คุณสามารถติดตามสถานะการแจ้งซ่อมได้ที่หน้า "รายการแจ้งซ่อมของฉัน"
                        ซึ่งจะแสดงรายการแจ้งซ่อมทั้งหมดของคุณ พร้อมสถานะปัจจุบัน นอกจากนี้ คุณยังสามารถคลิกที่ปุ่ม
                        "ดูรายละเอียด" เพื่อดูรายละเอียดเพิ่มเติมและประวัติการอัพเดต
                    </div>
                </div>
            </div>

            <div class="accordion-item border-0">
                <h2 class="accordion-header" id="headingFour">

                </h2>

            </div>
        </div>
    </div>
</div>

<!-- Contact Section -->
<div class="card border-0 shadow mb-5">
    <div class="card-header bg-white py-3">
        <h4 class="m-0 fw-bold text-primary">
            <i class="bx bx-envelope me-2"></i>ติดต่อเรา
        </h4>
    </div>
    <div class="card-body p-4">
        <div class="row">
            <div class="col-md-6 mb-4 mb-md-0">
                <p>มีคำถามหรือต้องการความช่วยเหลือในการใช้งานระบบ? สามารถติดต่อเราได้ผ่านช่องทางด้านล่างนี้</p>
                <div class="d-flex align-items-center mb-3">
                    <i class="bx bx-envelope me-3 text-primary" style="font-size: 1.5rem;"></i>
                    <div>
                        <h6 class="mb-0">อีเมล</h6>
                        <p class="mb-0">inven@lru.ac.th</p>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <i class="bx bx-phone me-3 text-primary" style="font-size: 1.5rem;"></i>
                    <div>
                        <h6 class="mb-0">โทรศัพท์</h6>
                        <p class="mb-0">4117 (งานพัสดุ กองกลาง สำนักงานอธิการบดี)</p>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <i class="bx bx-time me-3 text-primary" style="font-size: 1.5rem;"></i>
                    <div>
                        <h6 class="mb-0">เวลาทำการ</h6>
                        <p class="mb-0">จันทร์ - ศุกร์: 8:30 - 16:30 น.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-light p-4 rounded">
                    <h5 class="mb-3">เริ่มต้นใช้งานวันนี้</h5>
                    <p>ลงทะเบียนเพื่อใช้งานระบบแจ้งซ่อมออนไลน์ และเพิ่มประสิทธิภาพในการบริหารจัดการการซ่อมบำรุง</p>
                    <div class="d-grid gap-2">
                        <a href="register.php" class="btn btn-primary">
                            <i class="bx bx-user-plus me-1"></i>สมัครสมาชิก
                        </a>
                        <a href="login.php" class="btn btn-outline-primary">
                            <i class="bx bx-log-in me-1"></i>เข้าสู่ระบบ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Hero Section */
    .display-4 {
        font-weight: 700;
    }

    /* Features Section */
    .rounded-circle {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Accordion */
    .accordion-item {
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, .05);
        overflow: hidden;
    }

    .accordion-button {
        box-shadow: none;
        font-weight: 500;
    }

    .accordion-button:not(.collapsed) {
        background-color: #6563ff;
        color: white;
    }

    /* Animation for cards */
    .card {
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
</style>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>