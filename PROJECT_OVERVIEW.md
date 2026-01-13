# 📋 Project Overview - ระบบแจ้งซ่อมออนไลน์

## 🎯 บทบาทและวัตถุประสงค์

**ชื่อโปรเจค:** ระบบแจ้งซ่อมออนไลน์ (Online Maintenance Request System)

**วัตถุประสงค์:**
- จัดการการแจ้งซ่อมบำรุงออนไลน์ในองค์กร
- ติดตามสถานะของการซ่อมแซมแบบเรียลไทม์
- แจ้งเตือนผู้ดูแลระบบผ่าน Telegram
- จัดการข้อมูลผู้ใช้และบทบาท
- ตรวจสอบและวิเคราะห์รายงาน

---

## 🏗️ สถาปัตยกรรมระบบ

### Technology Stack

| ส่วนประกอบ | เทคโนโลยี | เวอร์ชัน |
|---|---|---|
| **Backend** | PHP | 7.4.33 |
| **Database** | MariaDB | 10.6.17 |
| **Frontend** | HTML5, CSS3, JavaScript | Bootstrap 5 |
| **UI Framework** | Bootstrap | 5.x |
| **Icons** | Boxicons (bx) | - |
| **API Integration** | Telegram Bot API | - |

### Environment

```
Platform: XAMPP
Database: MariaDB/MySQL
Web Server: Apache
Root Path: c:\xampp\htdocs\rp
Database Name: nakaiact_testrp
```

---

## 📁 โครงสร้างไฟล์โปรเจค

```
rp/
├── index.php                          # หน้าแรกและ redirect logic
├── login.php                          # หน้าเข้าสู่ระบบ
├── logout.php                         # ออกจากระบบ
├── register.php                       # สมัครสมาชิก
│
├── config/
│   └── db_connect.php                # เชื่อมต่อฐานข้อมูลและ Helper Functions
│
├── includes/
│   ├── header.php                    # Header template
│   └── footer.php                    # Footer template
│
├── uploads/                          # โฟลเดอร์เก็บรูปภาพ
│
├── User Pages (บทบาท: User)
│   ├── dashboard.php                 # หน้าแดชบอร์ดผู้ใช้
│   ├── profile.php                   # จัดการโปรไฟล์ผู้ใช้
│   ├── create_request.php            # สร้างแจ้งซ่อมใหม่
│   ├── my_requests.php               # ดูแจ้งซ่อมของผู้ใช้
│   └── view_request.php              # ดูรายละเอียดแจ้งซ่อม
│
├── Admin Pages (บทบาท: admin)
│   ├── admin_dashboard.php           # แดชบอร์ดผู้ดูแล
│   ├── admin_categories.php          # จัดการหมวดหมู่
│   ├── admin_users.php               # จัดการผู้ใช้
│   ├── admin_requests.php            # จัดการแจ้งซ่อมทั้งหมด
│   ├── admin_reports.php             # รายงานและสถิติ
│   └── admin_settings.php            # ตั้งค่าระบบ
│
├── Database & Utilities
│   ├── database_testrp.sql           # Schema ฐานข้อมูล
│   └── update_db.php                 # Script อัพเดตฐานข้อมูล
│
└── Documentation
    ├── PROJECT_OVERVIEW.md           # ไฟล์นี้
    └── ADMIN_SETTINGS_DOCUMENTATION.md
```

---

## 👥 ระบบสิทธิ์และบทบาท

### 1. User (ผู้ใช้ทั่วไป)
**หน้าที่:**
- สร้างแจ้งซ่อมใหม่
- ดูแจ้งซ่อมของตัวเอง
- ติดตามสถานะการซ่อม
- แก้ไขโปรไฟล์ส่วนตัว

**Pages:**
- dashboard.php
- profile.php
- create_request.php
- my_requests.php
- view_request.php

### 2. Admin (ผู้ดูแลระบบ)
**หน้าที่:**
- จัดการแจ้งซ่อมทั้งหมด
- อัพเดตสถานะการซ่อม
- จัดการหมวดหมู่
- จัดการผู้ใช้
- ดูรายงานสถิติ
- ตั้งค่าระบบ
- ส่งการแจ้งเตือน

**Pages:**
- admin_dashboard.php
- admin_requests.php
- admin_categories.php
- admin_users.php
- admin_reports.php
- admin_settings.php

---

## 🗄️ โครงสร้างฐานข้อมูล

### 1. ตาราง `users`
**วัตถุประสงค์:** เก็บข้อมูลผู้ใช้ระบบ

| คอลัมน์ | ชนิด | คำอธิบาย |
|---|---|---|
| `user_id` | INT | รหัสผู้ใช้ (Primary Key) |
| `fullname` | VARCHAR(255) | ชื่อเต็ม |
| `email` | VARCHAR(255) | อีเมล (Unique) |
| `phone` | VARCHAR(20) | เบอร์โทรศัพท์ |
| `password` | VARCHAR(255) | รหัสผ่าน (Hashed) |
| `role` | ENUM | บทบาท: user, admin, building_staff |
| `status` | VARCHAR(20) | สถานะ: active, inactive |
| `created_at` | TIMESTAMP | วันที่สร้าง |
| `updated_at` | TIMESTAMP | วันที่อัพเดตล่าสุด |

### 2. ตาราง `categories`
**วัตถุประสงค์:** เก็บหมวดหมู่ของการซ่อม

| คอลัมน์ | ชนิด | คำอธิบาย |
|---|---|---|
| `category_id` | INT | รหัสหมวดหมู่ |
| `category_name` | VARCHAR(100) | ชื่อหมวดหมู่ |
| `description` | TEXT | คำอธิบาย |
| `created_at` | TIMESTAMP | วันที่สร้าง |
| `updated_at` | TIMESTAMP | วันที่อัพเดต |

**หมวดหมู่ตัวอย่าง:**
- คอมพิวเตอร์
- เครือข่าย
- ระบบไฟฟ้า
- เครื่องปรับอากาศ
- เฟอร์นิเจอร์
- อื่นๆ

### 3. ตาราง `repair_requests`
**วัตถุประสงค์:** เก็บข้อมูลแจ้งซ่อม

| คอลัมน์ | ชนิด | ค่าที่เป็นไปได้ | คำอธิบาย |
|---|---|---|---|
| `request_id` | INT | - | รหัสแจ้งซ่อม (Primary Key) |
| `user_id` | INT | - | รหัสผู้ใช้ (Foreign Key) |
| `category_id` | INT | - | รหัสหมวดหมู่ (Foreign Key) |
| `title` | VARCHAR(255) | - | หัวเรื่อง |
| `description` | TEXT | - | รายละเอียด |
| `location` | VARCHAR(255) | - | สถานที่ |
| `priority` | ENUM | low, medium, high, urgent | ระดับความสำคัญ |
| `status` | ENUM | pending, in_progress, completed, rejected | สถานะ |
| `image` | VARCHAR(255) | - | เส้นทางไฟล์รูปภาพ |
| `admin_remark` | TEXT | - | หมายเหตุจากผู้ดูแล |
| `completed_date` | DATETIME | - | วันที่เสร็จสิ้น |
| `created_at` | TIMESTAMP | - | วันที่สร้าง |
| `updated_at` | TIMESTAMP | - | วันที่อัพเดต |

### 4. ตาราง `request_history`
**วัตถุประสงค์:** เก็บประวัติการเปลี่ยนสถานะ

| คอลัมน์ | ชนิด | คำอธิบาย |
|---|---|---|
| `history_id` | INT | รหัส (Primary Key) |
| `request_id` | INT | รหัสแจ้งซ่อม (Foreign Key) |
| `user_id` | INT | รหัสผู้ใช้ (Foreign Key) |
| `status` | VARCHAR(50) | สถานะที่เปลี่ยน |
| `remark` | TEXT | หมายเหตุ |
| `created_at` | TIMESTAMP | วันที่สร้าง |

### 5. ตาราง `settings`
**วัตถุประสงค์:** เก็บการตั้งค่าระบบ

| คอลัมน์ | ชนิด | คำอธิบาย |
|---|---|---|
| `setting_name` | VARCHAR(100) | ชื่อการตั้งค่า (Primary Key) |
| `setting_value` | TEXT | ค่าของการตั้งค่า |

**ตัวอย่างการตั้งค่า:**
- `site_name` - ชื่อระบบ
- `site_description` - คำอธิบายระบบ
- `telegram_bot_token` - Token สำหรับ Telegram Bot
- `telegram_chat_id` - Chat ID สำหรับส่งการแจ้งเตือน
- `notification_enabled` - เปิด/ปิดการแจ้งเตือน

---

## 🔐 ระบบความปลอดภัย

### Authentication (การพิสูจน์ตัวตน)

**Login Flow:**
1. ผู้ใช้ป้อนอีเมล/รหัสผ่าน
2. ตรวจสอบในฐานข้อมูล
3. ตรวจสอบรหัสผ่าน (hashed password)
4. สร้าง Session

**ตัวแปร Session:**
```
$_SESSION['user_id']     // รหัสผู้ใช้
$_SESSION['fullname']    // ชื่อเต็ม
$_SESSION['email']       // อีเมล
$_SESSION['role']        // บทบาท (user, admin)
$_SESSION['status']      // สถานะ (active/inactive)
```

### Authorization (สิทธิ์การเข้าถึง)

**Pattern ตรวจสอบ:**
```php
// Admin only
if ($_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Logged in users only
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
```

### Data Protection

- **Password:** SHA-256 Hashing (หรือ bcrypt)
- **Input Validation:** `clean_input()` function
- **SQL Injection Prevention:** Prepared Statements (ต้องปรับปรุง)
- **CSRF Protection:** (ยังไม่มี - ต้องเพิ่ม)

---

## 🔄 User Workflow

### สำหรับผู้ใช้ทั่วไป (User)

```
1. เข้าสู่ระบบ (login.php)
   ↓
2. หน้าแดชบอร์ด (dashboard.php)
   ├── ดูแจ้งซ่อมของตัวเอง
   ├── สร้างแจ้งซ่อมใหม่
   └── แก้ไขโปรไฟล์
   ↓
3. สร้างแจ้งซ่อม (create_request.php)
   - เลือกหมวดหมู่
   - กรอกรายละเอียด
   - เลือกลำดับความสำคัญ
   - อัพโหลดรูปภาพ
   ↓
4. ติดตามสถานะ (my_requests.php, view_request.php)
   - ดูสถานะปัจจุบัน
   - ดูหมายเหตุจากผู้ดูแล
   - ดูประวัติการเปลี่ยนแปลง
```

### สำหรับผู้ดูแลระบบ (Admin)

```
1. เข้าสู่ระบบ (login.php)
   ↓
2. แดชบอร์ดผู้ดูแล (admin_dashboard.php)
   ├── ดูสถิติและสรุป
   ├── ดูแจ้งซ่อมปัจจุบัน
   └── เมนูจัดการต่างๆ
   ↓
3. จัดการแจ้งซ่อม (admin_requests.php)
   - อัพเดตสถานะ
   - เพิ่มหมายเหตุ
   - ปฏิเสธการซ่อม
   - เสร็จสิ้นการซ่อม
   ↓
4. จัดการข้อมูล (admin_users.php, admin_categories.php)
   ↓
5. ดูรายงาน (admin_reports.php)
   ↓
6. ตั้งค่าระบบ (admin_settings.php)
   - ตั้งค่า Telegram
   - เปลี่ยนชื่อระบบ
   - ทดสอบการแจ้งเตือน
```

---

## 📢 Telegram Integration

### วัตถุประสงค์
ส่งการแจ้งเตือนไปยัง Admin หรือกลุ่มโดยอัตโนมัติเมื่อ:
- มีแจ้งซ่อมใหม่
- อัพเดตสถานะการซ่อม
- เปลี่ยนแปลงการตั้งค่า

### API Endpoint
```
https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage
```

### Implementation
- **Method:** POST via cURL
- **Data:**
  ```json
  {
    "chat_id": "123456789",
    "text": "ข้อความแจ้งเตือน",
    "parse_mode": "HTML"
  }
  ```

### Configuration
ตั้งค่าใน `admin_settings.php`:
1. Telegram Bot Token
2. Telegram Chat ID
3. เปิด/ปิดการแจ้งเตือน

---

## 📊 สถิติและรายงาน

### ข้อมูลที่ติดตามได้

**Dashboard Statistics:**
- จำนวนแจ้งซ่อมทั้งหมด
- แจ้งซ่อมรอดำเนินการ
- แจ้งซ่อมในกระบวนการ
- แจ้งซ่อมเสร็จสิ้น
- แจ้งซ่อมปฏิเสธ

**Reports (admin_reports.php):**
- สถิติตามหมวดหมู่
- สถิติตามลำดับความสำคัญ
- สถิติตามผู้ใช้
- เวลาเฉลี่ยในการซ่อม
- อัตราการเสร็จสิ้น

---

## 🔧 Helper Functions

จากไฟล์ `config/db_connect.php`:

| ฟังก์ชัน | วัตถุประสงค์ |
|---|---|
| `clean_input($data)` | ทำความสะอาด Input ป้องกัน SQL Injection |
| `send_telegram_notification($message)` | ส่งข้อความไปยัง Telegram |
| `thai_date($datetime)` | แปลงวันที่เป็นรูปแบบภาษาไทย |
| `mysqli_query()` | ส่วนของ MySQL |
| `mysqli_fetch_assoc()` | ดึงข้อมูลจำหน่ายจากผลลัพธ์ |

---

## 🎨 UI/UX Features

### Components
- **Navigation:** Bootstrap Navbar with responsive menu
- **Cards:** Shadow cards with consistent styling
- **Tables:** Responsive data tables with icons
- **Forms:** Input groups with icons
- **Alerts:** Bootstrap alerts (success, error, warning, info)
- **Modals:** For confirmations and detailed views
- **Icons:** Boxicons (bx-*) throughout

### Design System
- **Color Scheme:** Bootstrap primary/secondary/danger/success
- **Typography:** Responsive text sizing
- **Spacing:** Consistent padding/margin (Bootstrap utilities)
- **Layout:** Mobile-first responsive design

---

## 🚀 Key Features

### Implemented ✅
- ✅ User authentication & authorization
- ✅ Create, Read, Update repair requests
- ✅ Category management
- ✅ Priority levels (low, medium, high, urgent)
- ✅ Status tracking (pending, in_progress, completed, rejected)
- ✅ User role management (user, admin, building_staff)
- ✅ Request history tracking
- ✅ Image upload support
- ✅ Admin dashboard with statistics
- ✅ Telegram bot integration
- ✅ System settings management
- ✅ Admin remarks/notes
- ✅ User profile management

### Future Enhancements 🔮
- 📱 Mobile app (React Native/Flutter)
- 📧 Email notifications
- 📄 PDF export reports
- 🔐 Two-factor authentication
- 🗺️ Map integration for locations
- 💬 Live chat support
- 📊 Advanced analytics
- 🔄 Workflow automation
- 🎯 Performance metrics dashboard
- 🔔 SMS notifications

---

## 📝 Development Guidelines

### File Naming Convention
- **PHP Pages:** `snake_case.php` (e.g., `admin_dashboard.php`)
- **Functions:** `snake_case()` (e.g., `clean_input()`)
- **Variables:** `$snake_case` (e.g., `$user_id`)
- **Classes:** `PascalCase` (e.g., `DatabaseConnection`)

### Code Structure
```
1. Set page title
2. Include database connection
3. Check authentication/authorization
4. Handle POST requests (logic)
5. Handle GET requests (fetch data)
6. Include header template
7. Display HTML content
8. Include footer template
```

### Database Queries Pattern
```php
// Fetch data
$query = "SELECT * FROM table WHERE condition";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    // Process data
}

// Update data
$query = "UPDATE table SET column = '$value' WHERE id = $id";
mysqli_query($conn, $query);

// Insert data
$query = "INSERT INTO table (col1, col2) VALUES ('val1', 'val2')";
mysqli_query($conn, $query);
```

---

## 🔍 File Descriptions

### Core Files

| ไฟล์ | คำอธิบาย |
|---|---|
| `index.php` | หน้าแรก + redirect logic |
| `login.php` | หน้าเข้าสู่ระบบ |
| `logout.php` | ออกจากระบบ |
| `register.php` | สมัครสมาชิกใหม่ |
| `config/db_connect.php` | เชื่อมต่อ DB + Helper functions |

### User Pages

| ไฟล์ | คำอธิบาย |
|---|---|
| `dashboard.php` | หน้าแดชบอร์ดผู้ใช้ |
| `profile.php` | จัดการโปรไฟล์ |
| `create_request.php` | สร้างแจ้งซ่อมใหม่ |
| `my_requests.php` | ดูแจ้งซ่อมของตัวเอง |
| `view_request.php` | ดูรายละเอียดแจ้งซ่อม |

### Admin Pages

| ไฟล์ | คำอธิบาย |
|---|---|
| `admin_dashboard.php` | แดชบอร์ดผู้ดูแล |
| `admin_requests.php` | จัดการแจ้งซ่อมทั้งหมด |
| `admin_categories.php` | จัดการหมวดหมู่ |
| `admin_users.php` | จัดการผู้ใช้ |
| `admin_reports.php` | รายงานและสถิติ |
| `admin_settings.php` | ตั้งค่าระบบ |

---

## 🛠️ Installation & Setup

### Prerequisites
- XAMPP or similar local server
- PHP 7.4+
- MariaDB/MySQL
- Modern web browser

### Steps

1. **Clone/Extract Project**
   ```bash
   cd c:\xampp\htdocs\rp
   ```

2. **Create Database**
   ```bash
   Import database_testrp.sql to MariaDB
   ```

3. **Configure Database Connection**
   Edit `config/db_connect.php`:
   ```php
   $host = 'localhost';
   $db_user = 'root';
   $db_pass = '';
   $db_name = 'nakaiact_testrp';
   ```

4. **Start Services**
   - Start XAMPP Apache & MySQL
   - Open `http://localhost/rp/`

5. **First Login**
   - Default admin credentials (check database)
   - Or register new user

---

## 📚 Additional Documentation

- [ADMIN_SETTINGS_DOCUMENTATION.md](ADMIN_SETTINGS_DOCUMENTATION.md) - Detailed admin settings documentation

---

## 🔗 Project Links & Resources

- **Local URL:** `http://localhost/rp/`
- **Database:** MariaDB/MySQL on localhost:3306
- **Telegram API Docs:** https://core.telegram.org/bots/api
- **Bootstrap Documentation:** https://getbootstrap.com/docs/5.0/
- **Boxicons:** https://boxicons.com/

---

## 📞 Support & Contact

For issues, questions, or contributions:
1. Check the documentation files
2. Review the code comments
3. Check error logs in browser console

---

**Last Updated:** January 13, 2026  
**Project Status:** In Development  
**Version:** 1.0.0
