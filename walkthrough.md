# SQL Injection Protection - Walkthrough

## Summary

ได้ทำการป้องกัน SQL injection โดยใช้ **Prepared Statements** สำหรับทุก SQL query ที่มีการรับ user input ในระบบแจ้งซ่อม

## Changes Made

### 1. Core Helper Functions

#### db_connect.php

เพิ่ม 3 helper functions ใหม่:

| Function | Purpose |
|----------|---------|
| `db_select()` | สำหรับ SELECT queries, return mysqli_result |
| `db_execute()` | สำหรับ INSERT/UPDATE/DELETE, return bool |
| `db_insert()` | สำหรับ INSERT, return insert_id |

ปรับปรุงฟังก์ชัน:
- `clean_input()` - ใช้สำหรับ display เท่านั้น (ไม่ใช้ mysqli_real_escape_string อีกต่อไป)
- `add_request_history()` - ใช้ prepared statement
- `insert_request_history()` - ใช้ prepared statement

---

### 2. ไฟล์ที่แก้ไข (8 ไฟล์)

| File | Changes |
|------|---------|
| login.php | User lookup query |
| register.php | Username/email check, INSERT user |
| create_request.php | User lookup, INSERT request, category lookup |
| view_request.php | Request lookup, UPDATE status, history queries |
| profile.php | User data, UPDATE profile/password, statistics |
| admin_users.php | All CRUD operations for users |
| admin_requests.php | Status update, request listing with filters |

---

## ตัวอย่างการเปลี่ยนแปลง

### Before (ไม่ปลอดภัย)
```php
$username = clean_input($_POST['username']);
$query = "SELECT * FROM users WHERE username = '$username'";
$result = mysqli_query($conn, $query);
```

### After (ปลอดภัย)
```php
$username = $_POST['username'];
$result = db_select("SELECT * FROM users WHERE username = ?", "s", [$username]);
```

---

## Testing Recommendations

1. **Login** - ทดสอบเข้าสู่ระบบปกติ
2. **Register** - ทดสอบสมัครสมาชิกใหม่
3. **Create Request** - ทดสอบสร้างรายการแจ้งซ่อม
4. **View/Update Request** - ทดสอบดู/อัพเดตสถานะ
5. **Profile** - ทดสอบแก้ไขข้อมูลส่วนตัว
6. **Admin Users** - ทดสอบจัดการผู้ใช้
