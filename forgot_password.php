<?php
$page_title = "ลืมรหัสผ่าน";
require_once 'config/db_connect.php';

// ถ้าล็อกอินอยู่แล้วให้ redirect
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$step = isset($_SESSION['fp_step']) ? $_SESSION['fp_step'] : 1;
$error = '';
$success = '';

// ---- STEP 1: กรอกอีเมล ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'กรุณากรอกอีเมลให้ถูกต้อง';
    } else {
        // ตรวจสอบอีเมลในระบบ
        $result = db_select("SELECT user_id, fullname, email FROM users WHERE email = ?", "s", [$email]);
        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            // ลบ token เก่าของ email นี้ที่ยังไม่ได้ใช้
            db_execute("DELETE FROM password_resets WHERE email = ?", "s", [$email]);

            // สร้าง OTP 6 หลัก
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', time() + 600); // 10 นาที

            // บันทึก OTP ลงฐานข้อมูล
            $inserted = db_insert(
                "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)",
                "sss",
                [$email, $otp, $expires_at]
            );

            if ($inserted) {
                // ส่งอีเมล
                $sent = send_password_reset_email($email, $user['fullname'], $otp);

                if ($sent) {
                    $_SESSION['fp_email'] = $email;
                    $_SESSION['fp_step'] = 2;
                    header('Location: forgot_password.php');
                    exit();
                } else {
                    // ถ้าส่งอีเมลไม่ได้ (ยังไม่ config SMTP) ให้แสดง OTP บนหน้าจอสำหรับทดสอบ
                    $_SESSION['fp_email'] = $email;
                    $_SESSION['fp_step'] = 2;
                    $_SESSION['fp_otp_debug'] = $otp; // debug เท่านั้น ลบออกตอน production
                    header('Location: forgot_password.php?debug=1');
                    exit();
                }
            } else {
                $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            }
        } else {
            $error = 'ไม่พบอีเมลนี้ในระบบ กรุณาตรวจสอบอีเมลที่ใช้ลงทะเบียน';
        }
    }
}

// ---- STEP 2: ยืนยัน OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp_input = trim(implode('', $_POST['otp'] ?? []));
    $email = $_SESSION['fp_email'] ?? '';

    if (empty($otp_input) || strlen($otp_input) !== 6) {
        $error = 'กรุณากรอกรหัส OTP 6 หลักให้ครบถ้วน';
        $step = 2;
    } else {
        $result = db_select(
            "SELECT * FROM password_resets WHERE email = ? AND token = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
            "ss",
            [$email, $otp_input]
        );

        if ($result && mysqli_num_rows($result) > 0) {
            $reset = mysqli_fetch_assoc($result);
            $_SESSION['fp_step'] = 3;
            $_SESSION['fp_reset_id'] = $reset['id'];
            header('Location: forgot_password.php');
            exit();
        } else {
            $error = 'รหัส OTP ไม่ถูกต้องหรือหมดอายุแล้ว กรุณาลองใหม่';
            $step = 2;
        }
    }
}

// ---- STEP 3: ตั้งรหัสผ่านใหม่ ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['fp_email'] ?? '';
    $reset_id = $_SESSION['fp_reset_id'] ?? 0;

    if (strlen($new_password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        $step = 3;
    } elseif ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
        $step = 3;
    } else {
        // ตรวจสอบ reset record อีกครั้ง
        $result = db_select(
            "SELECT * FROM password_resets WHERE id = ? AND email = ? AND used = 0 AND expires_at > NOW()",
            "is",
            [$reset_id, $email]
        );

        if ($result && mysqli_num_rows($result) > 0) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);

            // อัปเดตรหัสผ่าน
            $updated = db_execute("UPDATE users SET password = ? WHERE email = ?", "ss", [$hashed, $email]);

            if ($updated) {
                // Mark token as used
                db_execute("UPDATE password_resets SET used = 1 WHERE id = ?", "i", [$reset_id]);

                // เคลียร์ session
                unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_reset_id'], $_SESSION['fp_otp_debug']);

                // Redirect พร้อม success message
                header('Location: login.php?reset=success');
                exit();
            } else {
                $error = 'เกิดข้อผิดพลาดในการอัปเดตรหัสผ่าน กรุณาลองใหม่';
                $step = 3;
            }
        } else {
            $error = 'เซสชันหมดอายุ กรุณาเริ่มใหม่อีกครั้ง';
            unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_reset_id']);
            $step = 1;
        }
    }
}

// ---- Cancel / Back ----
if (isset($_GET['cancel'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_reset_id'], $_SESSION['fp_otp_debug']);
    header('Location: forgot_password.php');
    exit();
}

// sync step from session
if ($step === 1 && isset($_SESSION['fp_step'])) {
    $step = $_SESSION['fp_step'];
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card border-0 shadow-lg">
            <div class="card-body p-5">

                <!-- Header -->
                <div class="text-center mb-4">
                    <div class="position-relative d-inline-block mb-3">
                        <div
                            style="width:80px;height:80px;background:linear-gradient(135deg,#4e73df,#224abe);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                            <i class="bx bx-key text-white" style="font-size:2.2rem;"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1">ลืมรหัสผ่าน</h4>
                    <p class="text-muted small mb-0">
                        <?php if ($step == 1): ?>กรอกอีเมลที่ลงทะเบียนไว้
                        <?php elseif ($step == 2): ?>กรอกรหัส OTP ที่ส่งไปยังอีเมล
                        <?php else: ?>ตั้งรหัสผ่านใหม่
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Step Indicator -->
                <div class="d-flex align-items-center justify-content-center mb-4 gap-2">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="d-flex align-items-center">
                            <div
                                style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;
                                <?php echo $step >= $i ? 'background:linear-gradient(135deg,#4e73df,#224abe);color:#fff;' : 'background:#e9ecef;color:#adb5bd;'; ?>">
                                <?php if ($step > $i): ?>
                                    <i class="bx bx-check" style="font-size:1rem;"></i>
                                <?php else: ?>
                                    <?php echo $i; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($i < 3): ?>
                                <div
                                    style="width:40px;height:2px;<?php echo $step > $i ? 'background:#4e73df;' : 'background:#e9ecef;'; ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Alert -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                        <i class="bx bx-error-circle me-1"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2" role="alert">
                        <i class="bx bx-check-circle me-1"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Debug OTP (ลบออกตอน production) -->
                <?php if (isset($_GET['debug']) && isset($_SESSION['fp_otp_debug'])): ?>
                    <div class="alert alert-warning py-2 text-center">
                        <small class="d-block text-muted">⚠️ ยังไม่ได้ config SMTP — OTP สำหรับทดสอบ:</small>
                        <strong class="fs-4 text-primary letter-spacing-2">
                            <?php echo $_SESSION['fp_otp_debug']; ?>
                        </strong>
                    </div>
                <?php endif; ?>

                <!-- ===== STEP 1: กรอกอีเมล ===== -->
                <?php if ($step == 1): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">อีเมลที่ลงทะเบียน</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bx bx-envelope"></i></span>
                                <input type="email" class="form-control" name="email" placeholder="example@email.com"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    required autofocus>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="request_otp" class="btn btn-primary">
                                <i class="bx bx-send me-1"></i>ส่งรหัส OTP
                            </button>
                            <a href="login.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bx bx-arrow-back me-1"></i>กลับสู่หน้าเข้าสู่ระบบ
                            </a>
                        </div>
                    </form>

                    <!-- ===== STEP 2: กรอก OTP ===== -->
                <?php elseif ($step == 2): ?>
                    <p class="text-center text-muted small mb-3">
                        ส่ง OTP ไปยัง <strong>
                            <?php echo htmlspecialchars($_SESSION['fp_email'] ?? ''); ?>
                        </strong><br>
                        <span class="text-danger" id="countdown"></span>
                    </p>
                    <form method="POST" id="otpForm">
                        <div class="d-flex gap-2 justify-content-center mb-4" id="otpInputs">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <input type="text" name="otp[]" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                    class="form-control text-center fw-bold otp-box"
                                    style="width:46px;height:54px;font-size:1.4rem;" autocomplete="off">
                            <?php endfor; ?>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="verify_otp" class="btn btn-primary">
                                <i class="bx bx-shield-check me-1"></i>ยืนยัน OTP
                            </button>
                            <a href="forgot_password.php?cancel=1" class="btn btn-outline-secondary btn-sm">
                                <i class="bx bx-refresh me-1"></i>ขอรหัสใหม่ / ยกเลิก
                            </a>
                        </div>
                    </form>
                    <script>
                        // OTP input auto-advance
                        const boxes = document.querySelectorAll('.otp-box');
                        boxes.forEach((box, idx) => {
                            box.addEventListener('input', e => {
                                const val = e.target.value.replace(/\D/g, '');
                                e.target.value = val.slice(-1);
                                if (val && idx < boxes.length - 1) boxes[idx + 1].focus();
                            });
                            box.addEventListener('keydown', e => {
                                if (e.key === 'Backspace' && !box.value && idx > 0) boxes[idx - 1].focus();
                                if (e.key === 'ArrowRight' && idx < boxes.length - 1) boxes[idx + 1].focus();
                                if (e.key === 'ArrowLeft' && idx > 0) boxes[idx - 1].focus();
                            });
                            box.addEventListener('paste', e => {
                                e.preventDefault();
                                const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                                paste.split('').forEach((ch, i) => { if (boxes[idx + i]) boxes[idx + i].value = ch; });
                                const next = Math.min(idx + paste.length, boxes.length - 1);
                                boxes[next].focus();
                            });
                        });
                        boxes[0].focus();

                        // Countdown 10 minutes
                        let secs = 600;
                        const cd = document.getElementById('countdown');
                        const timer = setInterval(() => {
                            secs--;
                            const m = Math.floor(secs / 60);
                            const s = secs % 60;
                            cd.textContent = `หมดอายุใน ${m}:${String(s).padStart(2, '0')} นาที`;
                            if (secs <= 0) { clearInterval(timer); cd.textContent = 'รหัส OTP หมดอายุแล้ว'; }
                        }, 1000);
                    </script>

                    <!-- ===== STEP 3: ตั้งรหัสผ่านใหม่ ===== -->
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">รหัสผ่านใหม่</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bx bx-lock-alt"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                    placeholder="อย่างน้อย 6 ตัวอักษร" required autofocus>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNew" tabindex="-1">
                                    <i class="bx bx-show" id="eyeNew"></i>
                                </button>
                            </div>
                            <!-- Strength bar -->
                            <div class="mt-2">
                                <div class="progress" style="height:4px;">
                                    <div class="progress-bar" id="strengthBar" style="width:0%;transition:all .3s;"></div>
                                </div>
                                <small id="strengthText" class="text-muted"></small>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bx bx-lock-open-alt"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                    placeholder="กรอกรหัสผ่านซ้ำ" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirm" tabindex="-1">
                                    <i class="bx bx-show" id="eyeConfirm"></i>
                                </button>
                            </div>
                            <small id="matchText" class="text-muted"></small>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="reset_password" class="btn btn-success btn-lg">
                                <i class="bx bx-check-circle me-1"></i>บันทึกรหัสผ่านใหม่
                            </button>
                        </div>
                    </form>
                    <script>
                        // Toggle password visibility
                        ['New', 'Confirm'].forEach(t => {
                            document.getElementById('toggle' + t).addEventListener('click', function () {
                                const inp = document.getElementById(t === 'New' ? 'new_password' : 'confirm_password');
                                const eye = document.getElementById('eye' + t);
                                const show = inp.type === 'password';
                                inp.type = show ? 'text' : 'password';
                                eye.className = show ? 'bx bx-hide' : 'bx bx-show';
                            });
                        });

                        // Password strength
                        const pw = document.getElementById('new_password');
                        const bar = document.getElementById('strengthBar');
                        const txt = document.getElementById('strengthText');
                        pw.addEventListener('input', function () {
                            const v = pw.value;
                            let score = 0;
                            if (v.length >= 6) score++;
                            if (v.length >= 10) score++;
                            if (/[A-Z]/.test(v)) score++;
                            if (/[0-9]/.test(v)) score++;
                            if (/[^A-Za-z0-9]/.test(v)) score++;
                            const levels = ['', 'อ่อนมาก', 'อ่อน', 'พอใช้', 'ดี', 'แข็งแกร่ง'];
                            const colors = ['', '#dc3545', '#fd7e14', '#ffc107', '#20c997', '#198754'];
                            bar.style.width = (score * 20) + '%';
                            bar.style.background = colors[score] || '#dee2e6';
                            txt.textContent = v.length > 0 ? 'ความแข็งแกร่ง: ' + (levels[score] || '') : '';
                        });

                        // Match check
                        const cp = document.getElementById('confirm_password');
                        const mt = document.getElementById('matchText');
                        cp.addEventListener('input', function () {
                            if (!cp.value) { mt.textContent = ''; return; }
                            const match = pw.value === cp.value;
                            mt.textContent = match ? '✓ รหัสผ่านตรงกัน' : '✗ รหัสผ่านไม่ตรงกัน';
                            mt.className = match ? 'text-success small' : 'text-danger small';
                        });
                    </script>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>