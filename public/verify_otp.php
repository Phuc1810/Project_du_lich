<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET['id'] ?? 0);
$masked = $_GET['m'] ?? '';

$flash = $_SESSION['flash_vo'] ?? [];
unset($_SESSION['flash_vo']);
$error = $flash['error'] ?? '';
$success = $flash['success'] ?? '';

$cooldown = 20;
$remain = 0;

// Check flow session
$reset = $_SESSION['reset'] ?? null;
if (!$reset || empty($reset['rid'])) {
  header("Location: forgot_password.php");
  exit;
}

// Nếu user tự gõ link id khác -> chặn
if ((int)$reset['rid'] !== $id) {
  header("Location: verify_otp.php?id=" . (int)$reset['rid'] . "&m=" . urlencode($reset['masked'] ?? $masked));
  exit;
}

// ✅ Lấy thông tin OTP record hiện tại để chắc chắn có dest/channel trong session (phục vụ resend)
$stmt = $conn->prepare("SELECT MaTK, channel, destination, created_at FROM password_reset_otp WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$rowOtp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rowOtp) {
  $_SESSION['flash_fp'] = ['error' => 'OTP không hợp lệ hoặc đã bị xoá. Vui lòng gửi OTP lại.'];
  header("Location: forgot_password.php?redirect=" . urlencode($reset['redirect'] ?? 'trangchu.php'));
  exit;
}

// Nếu session reset có matk thì check khớp
if (!empty($reset['matk']) && (int)$reset['matk'] !== (int)$rowOtp['MaTK']) {
  $_SESSION['flash_fp'] = ['error' => 'Phiên đặt lại mật khẩu không hợp lệ. Vui lòng gửi OTP lại.'];
  header("Location: forgot_password.php?redirect=" . urlencode($reset['redirect'] ?? 'trangchu.php'));
  exit;
}

// ✅ Cập nhật session để resend không cần quay lại nhập contact
$_SESSION['reset']['matk']   = (int)$rowOtp['MaTK'];
$_SESSION['reset']['channel'] = $rowOtp['channel'];
$_SESSION['reset']['dest']   = $rowOtp['destination'];
$_SESSION['reset']['masked'] = $masked;

// ✅ Tính cooldown bằng MySQL (tránh lệch timezone PHP/MySQL)
$matk = (int)$rowOtp['MaTK'];
$dest = $rowOtp['destination'];

$stmt = $conn->prepare("
  SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS diff_sec
  FROM password_reset_otp
  WHERE MaTK=? AND destination=?
  ORDER BY id DESC
  LIMIT 1
");
$stmt->bind_param("is", $matk, $dest);
$stmt->execute();
$rowLast = $stmt->get_result()->fetch_assoc();
$stmt->close();

$diff = isset($rowLast['diff_sec']) ? (int)$rowLast['diff_sec'] : 999999;
if ($diff < 0) $diff = 0;
$remain = max(0, $cooldown - $diff);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Xác minh OTP</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">
  <link rel="stylesheet" href="./assets/css/auth.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container auth-shell">
  <div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
      <div class="card auth-card p-4">
        <h4 class="fw-bold mb-2">Xác minh OTP</h4>
        <p class="text-muted mb-3">OTP đã gửi tới: <b><?= htmlspecialchars($masked) ?></b></p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- ✅ FORM XÁC NHẬN OTP -->
        <form method="POST" action="process_verify_otp.php" novalidate>
          <input type="hidden" name="id" value="<?= (int)$id ?>">

          <div class="mb-3">
            <label class="form-label">Mã OTP (6 số)</label>
            <input class="form-control" name="otp" inputmode="numeric" placeholder="VD: 123456" required>
          </div>

          <button class="btn btn-primary w-100 btn-pill">Xác nhận OTP</button>
        </form>

        <!-- ✅ RESEND OTP: không quay lại nhập email/sđt -->
        <div class="mt-3 text-center">
          <div id="resendBox" data-remain="<?= (int)$remain ?>">
            <div id="countdownText" class="text-muted" style="display:none;">
              Bạn có thể gửi lại OTP sau <b><span id="secLeft"></span>s</b>
            </div>

            <form id="resendForm" method="POST" action="process_resend_otp.php" style="display:none;">
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <button class="btn btn-link text-decoration-none" type="submit">
                Gửi lại OTP
              </button>
            </form>
          </div>

          <div class="mt-2">
            <a class="text-decoration-none"
              href="auth.php?tab=login&redirect=<?= urlencode($reset['redirect'] ?? 'trangchu.php') ?>">
              Quay về đăng nhập
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/otp_resend.js"></script>
</body>
</html>
