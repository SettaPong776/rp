<?php
require_once 'config/db_connect.php';

$msg = '';

// อัปเดต role ของ user ที่เลือก
if (isset($_POST['fix_user_id']) && isset($_POST['fix_role'])) {
    $fix_id   = (int)$_POST['fix_user_id'];
    $fix_role = $_POST['fix_role'];
    $allowed  = ['admin','user','building_staff','electrical_staff','plumbing_staff','ac_staff',
                 'head_building','head_electrical','head_plumbing','head_ac','computer_staff'];
    if (in_array($fix_role, $allowed)) {
        if (mysqli_query($conn, "UPDATE users SET role='$fix_role' WHERE user_id=$fix_id")) {
            $msg = "<div class='alert alert-success'>✅ อัปเดต role สำเร็จ</div>";
        } else {
            $msg = "<div class='alert alert-danger'>❌ Error: " . mysqli_error($conn) . "</div>";
        }
    }
}

// อัปเดต ENUM
$enum_ok = false;
$q = "ALTER TABLE users MODIFY COLUMN role ENUM(
    'admin','user',
    'building_staff','electrical_staff','plumbing_staff','ac_staff',
    'head_building','head_electrical','head_plumbing','head_ac',
    'computer_staff'
) NOT NULL DEFAULT 'user'";
if (mysqli_query($conn, $q)) {
    $enum_ok = true;
}

// ดึง users ทั้งหมด
$users = mysqli_query($conn, "SELECT user_id, username, fullname, email, role FROM users ORDER BY role, fullname");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>DB Update Tool</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4" style="font-family:sans-serif;background:#f8f9fa;">
<div class="container" style="max-width:900px;">
    <h4 class="mb-3">🔧 Database Update Tool</h4>

    <?php if ($enum_ok): ?>
    <div class="alert alert-success">✅ ENUM อัปเดตสำเร็จ: <code>computer_staff</code> พร้อมใช้งาน</div>
    <?php else: ?>
    <div class="alert alert-warning">⚠️ ENUM อาจอัปเดตแล้วก่อนหน้านี้ (ปกติ)</div>
    <?php endif; ?>

    <?php echo $msg; ?>

    <h5 class="mt-4 mb-3">รายชื่อผู้ใช้ทั้งหมด (แก้ไข Role ได้)</h5>
    <table class="table table-bordered table-hover bg-white">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>ชื่อ</th>
                <th>Role ปัจจุบัน</th>
                <th>แก้ไข Role</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($u = mysqli_fetch_assoc($users)): ?>
            <tr class="<?php echo empty($u['role']) ? 'table-danger' : ''; ?>">
                <td><?php echo $u['user_id']; ?></td>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td><?php echo htmlspecialchars($u['fullname']); ?></td>
                <td>
                    <?php if (empty($u['role'])): ?>
                        <span class="badge bg-danger">⚠️ ว่าง (ผิดปกติ)</span>
                    <?php else: ?>
                        <code><?php echo htmlspecialchars($u['role']); ?></code>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="fix_user_id" value="<?php echo $u['user_id']; ?>">
                        <select name="fix_role" class="form-select form-select-sm" style="width:220px;">
                            <option value="user"                <?php echo $u['role']=='user'?'selected':''; ?>>user</option>
                            <option value="building_staff"      <?php echo $u['role']=='building_staff'?'selected':''; ?>>building_staff</option>
                            <option value="electrical_staff"    <?php echo $u['role']=='electrical_staff'?'selected':''; ?>>electrical_staff</option>
                            <option value="plumbing_staff"      <?php echo $u['role']=='plumbing_staff'?'selected':''; ?>>plumbing_staff</option>
                            <option value="ac_staff"            <?php echo $u['role']=='ac_staff'?'selected':''; ?>>ac_staff</option>
                            <option value="head_building"       <?php echo $u['role']=='head_building'?'selected':''; ?>>head_building</option>
                            <option value="head_electrical"     <?php echo $u['role']=='head_electrical'?'selected':''; ?>>head_electrical</option>
                            <option value="head_plumbing"       <?php echo $u['role']=='head_plumbing'?'selected':''; ?>>head_plumbing</option>
                            <option value="head_ac"             <?php echo $u['role']=='head_ac'?'selected':''; ?>>head_ac</option>
                            <option value="computer_staff"      <?php echo $u['role']=='computer_staff'?'selected':''; ?>>computer_staff ⭐</option>
                            <option value="admin"               <?php echo $u['role']=='admin'?'selected':''; ?>>admin</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">บันทึก</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <div class="alert alert-warning mt-3">
        ⚠️ ลบหรือปิดใช้งานไฟล์นี้หลังจากใช้งานเสร็จแล้ว เพื่อความปลอดภัย
    </div>
</div>
</body>
</html>