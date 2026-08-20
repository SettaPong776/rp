<?php
$page_title = "ประวัติการซ่อมแยกตามสถานที่";
require_once 'config/db_connect.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$current_role = $_SESSION['role'];
$is_staff     = is_staff_role($current_role);

$bld_query = mysqli_query($conn, "SELECT name FROM buildings ORDER BY sort_order ASC, name ASC");
$buildings_list = [];
while ($b = mysqli_fetch_assoc($bld_query)) $buildings_list[] = $b['name'];

$dept_query = mysqli_query($conn, "SELECT name FROM departments ORDER BY sort_order ASC, name ASC");
$departments_list = [];
while ($d = mysqli_fetch_assoc($dept_query)) $departments_list[] = $d['name'];

$search_building = trim($_GET['building']   ?? '');
$search_room     = trim($_GET['room']       ?? '');
$search_dept     = trim($_GET['department'] ?? '');
$search_status   = trim($_GET['status']     ?? '');

$wc = ['1=1']; $params = []; $types = '';
if (!empty($search_building)) { $wc[] = "REPLACE(r.location, ' ', '') LIKE ?"; $params[] = '%'.preg_replace('/\s+/', '', $search_building).'%'; $types .= 's'; }
if (!empty($search_dept))     { $wc[] = "REPLACE(r.location, ' ', '') LIKE ?"; $params[] = '%'.preg_replace('/\s+/', '', $search_dept).'%';     $types .= 's'; }
if (!empty($search_room))     { $wc[] = "REPLACE(r.location, ' ', '') LIKE ?"; $params[] = '%'.preg_replace('/\s+/', '', $search_room).'%';     $types .= 's'; }
if (!empty($search_status))   { $wc[] = "r.status = ?";      $params[] = $search_status;           $types .= 's'; }
if (!$is_staff)               { $wc[] = "r.user_id = ?";     $params[] = $_SESSION['user_id'];     $types .= 'i'; }
$w = implode(' AND ', $wc);

$sel = fn($sql) => !empty($types) ? db_select($sql, $types, $params) : mysqli_query($conn, $sql);

$stats = mysqli_fetch_assoc($sel("SELECT COUNT(*) as total,
    COUNT(CASE WHEN r.status='completed'   THEN 1 END) as completed,
    COUNT(CASE WHEN r.status='in_progress' THEN 1 END) as in_progress,
    COUNT(CASE WHEN r.status='pending'     THEN 1 END) as pending,
    COUNT(CASE WHEN r.status='rejected'    THEN 1 END) as rejected
FROM repair_requests r WHERE $w"));

$top_res = $sel("SELECT MAX(r.location) as location,
    COUNT(*) as total,
    COUNT(CASE WHEN r.status='completed'   THEN 1 END) as completed,
    COUNT(CASE WHEN r.status='in_progress' THEN 1 END) as in_progress,
    COUNT(CASE WHEN r.status='pending'     THEN 1 END) as pending
FROM repair_requests r WHERE $w AND r.location != ''
GROUP BY REPLACE(r.location, ' ', '') ORDER BY total DESC LIMIT 10");
$top_locations = [];
while ($row = mysqli_fetch_assoc($top_res)) $top_locations[] = $row;

$list_res = $sel("SELECT r.request_id, r.title, r.location, r.status, r.priority,
    r.created_at, r.completed_date, c.category_name, u.fullname as requester_name
    FROM repair_requests r
    JOIN categories c ON r.category_id = c.category_id
    JOIN users u ON r.user_id = u.user_id
    WHERE $w ORDER BY r.created_at DESC");
$rows = [];
while ($row = mysqli_fetch_assoc($list_res)) $rows[] = $row;

$has_filter = !empty($search_building)||!empty($search_room)||!empty($search_dept)||!empty($search_status);

include 'includes/header.php';
?>
<style>
.rh-hero{background:linear-gradient(135deg,#1a73e8,#0d47a1);border-radius:16px;padding:28px 32px 24px;margin-bottom:28px;position:relative;overflow:hidden;box-shadow:0 8px 32px rgba(26,115,232,.28);}
.rh-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.07);}
.rh-hero::after{content:'';position:absolute;bottom:-60px;left:-20px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.04);}
.rh-hero h1{color:#fff;font-size:1.5rem;font-weight:700;margin-bottom:4px;}
.rh-hero p{color:rgba(255,255,255,.75);margin-bottom:20px;font-size:.92rem;}
.rh-sb{background:rgba(255,255,255,.13);border-radius:12px;padding:18px 20px;backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.15);}
.rh-sb .form-label{color:rgba(255,255,255,.9);font-size:.79rem;font-weight:600;margin-bottom:5px;letter-spacing:.3px;}
.rh-sb .form-select,.rh-sb .form-control{border:none;box-shadow:0 2px 8px rgba(0,0,0,.12);font-size:.9rem;}
.rh-sb .input-group-text{border:none;background:#f8f9fa;}
.rh-stat{border-radius:14px;border:none;box-shadow:0 6px 20px rgba(0,0,0,.1);transition:transform .2s,box-shadow .2s;overflow:hidden;position:relative;}
.rh-stat:hover{transform:translateY(-5px);box-shadow:0 12px 32px rgba(0,0,0,.15);}
.rh-stat .sico{position:absolute;right:-10px;bottom:-10px;font-size:5.5rem;opacity:.1;line-height:1;}
.rh-stat .slbl{font-size:.72rem;font-weight:700;letter-spacing:.6px;text-transform:uppercase;opacity:.85;}
.rh-stat .sval{font-size:2.2rem;font-weight:800;line-height:1.1;margin-top:4px;}
.s-blue{background:linear-gradient(135deg,#1a73e8,#1565c0);color:#fff;}
.s-green{background:linear-gradient(135deg,#2e7d32,#388e3c);color:#fff;}
.s-teal{background:linear-gradient(135deg,#006064,#00838f);color:#fff;}
.s-orange{background:linear-gradient(135deg,#e65100,#ef6c00);color:#fff;}
.rh-rank{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:.82rem;flex-shrink:0;}
.r1{background:linear-gradient(135deg,#f9a825,#ffd600);color:#5d4037;box-shadow:0 3px 10px rgba(249,168,37,.45);}
.r2{background:linear-gradient(135deg,#78909c,#b0bec5);color:#fff;box-shadow:0 3px 10px rgba(120,144,156,.4);}
.r3{background:linear-gradient(135deg,#bf360c,#e64a19);color:#fff;box-shadow:0 3px 10px rgba(191,54,12,.4);}
.rn{background:#f1f3f4;color:#9e9e9e;}
.bar-wrap{background:#eee;border-radius:20px;height:5px;width:100%;max-width:130px;overflow:hidden;margin-top:5px;}
.bar-fill{height:100%;border-radius:20px;transition:width .8s cubic-bezier(.4,0,.2,1);}
.rate-pill{display:inline-block;padding:3px 11px;border-radius:20px;font-weight:700;font-size:.82rem;}
.rh{background:#e8f5e9;color:#2e7d32;}.rm{background:#fff8e1;color:#e65100;}.rl{background:#ffebee;color:#c62828;}
.ftag{display:inline-flex;align-items:center;gap:5px;background:#e8f0fe;color:#1a73e8;border-radius:20px;padding:4px 12px;font-size:.8rem;font-weight:600;border:1px solid #c5d8fd;}
.ftag a{color:#c62828;text-decoration:none;margin-left:2px;font-weight:700;}
.sec-hd{background:linear-gradient(90deg,#f8faff,#fff);border-bottom:1px solid #e8eaf6;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;}
.sec-hd h6{margin:0;font-weight:700;color:#3f51b5;}
.rh-th thead th{background:#f4f6ff;color:#5c6bc0;font-size:.76rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;border-bottom:2px solid #e8eaf6;white-space:nowrap;padding-top:12px;padding-bottom:12px;}
.rh-th tbody tr{transition:background .12s;}
.rh-th tbody tr:hover{background:#f0f4ff;}
.ttl-cell{max-width:210px;}
.ttl-cell .ttl{font-weight:600;color:#333;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;}
.loc-pill{display:inline-flex;align-items:center;gap:4px;background:#f1f3f4;border-radius:20px;padding:3px 10px;font-size:.77rem;color:#555;max-width:200px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
.rh-empty{padding:65px 20px;text-align:center;}
.rh-empty .eico{font-size:5rem;color:#c5cae9;}
.rh-empty h5{color:#5c6bc0;font-weight:700;margin-top:12px;}
.rh-empty p{color:#9e9e9e;font-size:.88rem;}
</style>

<!-- HERO -->
<div class="rh-hero">
  <div style="position:relative;z-index:1;">
    <h1><i class="bx bx-history me-2"></i>ประวัติการซ่อมแยกตามสถานที่</h1>
    <p>ค้นหาและดูสถิติการซ่อมตามอาคาร คณะ/หน่วยงาน หรือหมายเลขห้อง</p>
    <form method="GET" id="searchForm">
      <div class="rh-sb">
        <div class="row g-3">
          <div class="col-md-3 col-sm-6">
            <label class="form-label"><i class="bx bx-building-house me-1"></i>อาคาร/เลขอาคาร</label>
            <select class="form-select select2" name="building" id="sh_building">
              <option value="">— ทั้งหมด —</option>
              <?php foreach ($buildings_list as $b): ?>
              <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $search_building==$b?'selected':''; ?>><?php echo htmlspecialchars($b); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 col-sm-6">
            <label class="form-label"><i class="bx bx-buildings me-1"></i>คณะ/หน่วยงาน</label>
            <select class="form-select select2" name="department" id="sh_department">
              <option value="">— ทั้งหมด —</option>
              <?php foreach ($departments_list as $d): ?>
              <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $search_dept==$d?'selected':''; ?>><?php echo htmlspecialchars($d); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 col-sm-6">
            <label class="form-label"><i class="bx bx-map-pin me-1"></i>หมายเลขห้อง / คำค้นหา</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-search text-muted"></i></span>
              <input type="text" class="form-control" name="room" id="sh_room"
                placeholder="เช่น 20104, ชั้น 1" value="<?php echo htmlspecialchars($search_room); ?>">
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <label class="form-label"><i class="bx bx-loader-circle me-1"></i>สถานะ</label>
            <select class="form-select" name="status" id="sh_status">
              <option value="">— ทั้งหมด —</option>
              <option value="pending"     <?php echo $search_status=='pending'?'selected':''; ?>>รอดำเนินการ</option>
              <option value="in_progress" <?php echo $search_status=='in_progress'?'selected':''; ?>>กำลังดำเนินการ</option>
              <option value="completed"   <?php echo $search_status=='completed'?'selected':''; ?>>เสร็จสิ้น</option>
              <option value="rejected"    <?php echo $search_status=='rejected'?'selected':''; ?>>ยกเลิก</option>
            </select>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="submit" class="btn btn-light fw-bold px-4"><i class="bx bx-search me-1"></i>ค้นหา</button>
          <?php if ($has_filter): ?>
          <a href="room_history.php" class="btn btn-outline-light"><i class="bx bx-x me-1"></i>ล้างตัวกรอง</a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- FILTER TAGS -->
<?php if ($has_filter):
$stmap = ['pending'=>'รอดำเนินการ','in_progress'=>'กำลังดำเนินการ','completed'=>'เสร็จสิ้น','rejected'=>'ยกเลิก'];
$clr = fn($k) => http_build_query(array_filter([
  'building'=>$k=='building'?'':$search_building,'room'=>$k=='room'?'':$search_room,
  'department'=>$k=='department'?'':$search_dept,'status'=>$k=='status'?'':$search_status,
])); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
  <span class="text-muted small fw-semibold">กรองด้วย:</span>
  <?php if ($search_building): ?><span class="ftag"><i class="bx bx-building-house"></i><?php echo htmlspecialchars($search_building); ?> <a href="?<?php echo $clr('building'); ?>">✕</a></span><?php endif; ?>
  <?php if ($search_dept):     ?><span class="ftag"><i class="bx bx-buildings"></i><?php echo htmlspecialchars($search_dept); ?> <a href="?<?php echo $clr('department'); ?>">✕</a></span><?php endif; ?>
  <?php if ($search_room):     ?><span class="ftag"><i class="bx bx-map-pin"></i><?php echo htmlspecialchars($search_room); ?> <a href="?<?php echo $clr('room'); ?>">✕</a></span><?php endif; ?>
  <?php if ($search_status):   ?><span class="ftag"><i class="bx bx-badge-check"></i><?php echo $stmap[$search_status]??$search_status; ?> <a href="?<?php echo $clr('status'); ?>">✕</a></span><?php endif; ?>
</div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
  <?php
  foreach ([
    ['val'=>$stats['total'],       'lbl'=>'รายการทั้งหมด',    'ico'=>'bx-package',      'cls'=>'s-blue'],
    ['val'=>$stats['completed'],   'lbl'=>'เสร็จสิ้น',        'ico'=>'bx-check-circle',  'cls'=>'s-green'],
    ['val'=>$stats['in_progress'], 'lbl'=>'กำลังดำเนินการ',  'ico'=>'bx-loader-alt',    'cls'=>'s-teal'],
    ['val'=>$stats['pending'],     'lbl'=>'รอดำเนินการ',      'ico'=>'bx-time-five',     'cls'=>'s-orange'],
  ] as $c): ?>
  <div class="col-6 col-md-3">
    <div class="card rh-stat <?php echo $c['cls']; ?> h-100">
      <div class="card-body p-4 position-relative">
        <i class="bx <?php echo $c['ico']; ?> sico"></i>
        <div class="slbl"><?php echo $c['lbl']; ?></div>
        <div class="sval"><?php echo intval($c['val']); ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- TOP LOCATIONS -->
<?php if (!empty($top_locations)): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="sec-hd">
    <h6><i class="bx bx-trophy me-2 text-warning"></i>สถานที่ที่แจ้งซ่อมบ่อยที่สุด
      <small class="fw-normal text-muted ms-2"><?php echo $has_filter?'(ตามเงื่อนไขที่กรอง)':'Top 10'; ?></small>
    </h6>
    <?php if ($has_filter): ?><span class="badge text-bg-primary rounded-pill" style="font-size:.73rem;">กรองแล้ว</span><?php endif; ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead style="background:#f4f6ff;">
          <tr style="font-size:.75rem;font-weight:700;color:#5c6bc0;letter-spacing:.4px;text-transform:uppercase;">
            <th class="ps-4" style="width:60px;">อันดับ</th>
            <th>สถานที่</th>
            <th class="text-center" style="width:80px;">ทั้งหมด</th>
            <th class="text-center" style="width:80px;">เสร็จ</th>
            <th class="text-center" style="width:90px;">กำลังซ่อม</th>
            <th class="text-center" style="width:70px;">รอ</th>
            <th class="text-center" style="width:110px;">อัตราสำเร็จ</th>
            <th class="pe-4" style="width:90px;"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($top_locations as $i => $loc):
          $rate  = $loc['total']>0 ? round(($loc['completed']/$loc['total'])*100) : 0;
          $pts   = explode(' | ', $loc['location']);
          $bp    = trim($pts[0]??'');
          $rp    = trim($pts[2]??(count($pts)==2?trim($pts[1]):''));
          $rkcls = $i===0?'r1':($i===1?'r2':($i===2?'r3':'rn'));
          $bc    = $rate>=70?'#2e7d32':($rate>=40?'#ef6c00':'#c62828');
          $rc    = $rate>=70?'rh':($rate>=40?'rm':'rl');
        ?>
        <tr>
          <td class="ps-4"><span class="rh-rank <?php echo $rkcls; ?>"><?php echo $i+1; ?></span></td>
          <td>
            <div style="font-size:.9rem;font-weight:600;color:#333;">
            <?php if (count($pts)>=2):
              echo '<span class="text-primary">'.htmlspecialchars(trim($pts[0])).'</span>';
              echo ' <span class="text-muted mx-1" style="font-weight:400;">›</span> ';
              if (count($pts)>=3) {
                echo '<span class="text-secondary">'.htmlspecialchars(trim($pts[1])).'</span>';
                echo ' <span class="text-muted mx-1" style="font-weight:400;">›</span> ';
                echo '<strong>'.htmlspecialchars(trim($pts[2])).'</strong>';
              } else {
                echo '<strong>'.htmlspecialchars(trim($pts[1])).'</strong>';
              }
            else: echo htmlspecialchars($loc['location']); endif; ?>
            </div>
            <div class="bar-wrap"><div class="bar-fill" style="width:<?php echo $rate; ?>%;background:<?php echo $bc; ?>;"></div></div>
          </td>
          <td class="text-center"><span class="fw-bold text-primary"><?php echo $loc['total']; ?></span></td>
          <td class="text-center"><span class="fw-bold text-success"><?php echo $loc['completed']; ?></span></td>
          <td class="text-center"><span class="fw-bold text-info"><?php echo $loc['in_progress']; ?></span></td>
          <td class="text-center"><span class="fw-bold text-warning"><?php echo $loc['pending']; ?></span></td>
          <td class="text-center"><span class="rate-pill <?php echo $rc; ?>"><?php echo $rate; ?>%</span></td>
          <td class="pe-4">
            <a href="room_history.php?<?php echo http_build_query(array_filter(['building'=>$bp,'room'=>$rp])); ?>"
               class="btn btn-sm btn-outline-primary rounded-pill px-3">
              <i class="bx bx-filter-alt me-1"></i>กรอง
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- REQUEST LIST -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="sec-hd">
    <h6><i class="bx bx-list-ul me-2"></i>รายการแจ้งซ่อม</h6>
    <span class="badge rounded-pill bg-primary px-3"><?php echo count($rows); ?> รายการ</span>
  </div>
  <div class="card-body p-0">
  <?php if (!empty($rows)): ?>
    <div class="table-responsive">
      <table class="table rh-th align-middle mb-0 datatable">
        <thead>
          <tr>
            <th class="ps-4">เลขที่</th>
            <th>เรื่อง</th>
            <th>สถานที่</th>
            <th>หมวดหมู่</th>
            <?php if ($is_staff): ?><th>ผู้แจ้ง</th><?php endif; ?>
            <th class="text-center">สถานะ</th>
            <th>วันที่แจ้ง</th>
            <th>วันที่เสร็จ</th>
            <th class="pe-4"></th>
          </tr>
        </thead>
        <tbody>
        <?php
        $sb = [
          'pending'     => '<span class="badge rounded-pill bg-warning text-dark">รอดำเนินการ</span>',
          'in_progress' => '<span class="badge rounded-pill bg-info text-white">กำลังดำเนินการ</span>',
          'completed'   => '<span class="badge rounded-pill bg-success">เสร็จสิ้น</span>',
          'rejected'    => '<span class="badge rounded-pill bg-danger">ยกเลิก</span>',
        ];
        foreach ($rows as $r): ?>
        <tr>
          <td class="ps-4"><span class="text-muted" style="font-size:.79rem;font-family:monospace;">#<?php echo $r['request_id']; ?></span></td>
          <td class="ttl-cell"><span class="ttl" title="<?php echo htmlspecialchars($r['title']); ?>"><?php echo htmlspecialchars($r['title']); ?></span></td>
          <td><span class="loc-pill" title="<?php echo htmlspecialchars($r['location']); ?>"><i class="bx bx-map-pin" style="color:#1a73e8;flex-shrink:0;"></i><?php echo htmlspecialchars($r['location']); ?></span></td>
          <td><span class="badge rounded-pill bg-light text-dark border" style="font-weight:500;font-size:.78rem;"><?php echo htmlspecialchars($r['category_name']); ?></span></td>
          <?php if ($is_staff): ?><td><small class="text-secondary"><?php echo htmlspecialchars($r['requester_name']); ?></small></td><?php endif; ?>
          <td class="text-center"><?php echo $sb[$r['status']] ?? htmlspecialchars($r['status']); ?></td>
          <td><small class="text-muted"><?php echo thai_date($r['created_at'],'j M Y'); ?></small></td>
          <td><?php if ($r['completed_date']): ?><small class="text-success"><i class="bx bx-check me-1"></i><?php echo thai_date($r['completed_date'],'j M Y'); ?></small><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
          <td class="pe-4"><a href="view_request.php?id=<?php echo $r['request_id']; ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bx bx-show-alt me-1"></i>ดู</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="rh-empty">
      <i class="bx bx-search-alt eico d-block"></i>
      <h5>ไม่พบรายการแจ้งซ่อม</h5>
      <p>ลองปรับเงื่อนไขการค้นหา หรือล้างตัวกรองเพื่อดูข้อมูลทั้งหมด</p>
      <a href="room_history.php" class="btn btn-primary rounded-pill px-4 mt-1"><i class="bx bx-reset me-1"></i>ล้างตัวกรองทั้งหมด</a>
    </div>
  <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
