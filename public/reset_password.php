<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$rv = $_SESSION['reset_verified'] ?? null;
if (!$rv) { header("Location: forgot_password.php"); exit; }

$flash = $_SESSION['flash_rp'] ?? [];
unset($_SESSION['flash_rp']);
$error = $flash['error'] ?? '';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đặt lại mật khẩu</title>
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
        <h4 class="fw-bold mb-2">Đặt mật khẩu mới</h4>
        <p class="text-muted mb-3">OTP hợp lệ. Vui lòng đặt mật khẩu mới.</p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="process_reset_password.php" novalidate>
          <div class="mb-3">
            <label class="form-label">Mật khẩu mới</label>
            <div class="input-group">
              <input id="new_password" class="form-control" type="password" name="new_password"
                     placeholder=">=8 ký tự, hoa/thường/số/ký tự đặc biệt" required>
              <button class="btn btn-outline-secondary btn-pass" type="button"
                      data-toggle="password" data-target="new_password">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Nhập lại mật khẩu mới</label>
            <div class="input-group">
              <input id="confirm_password" class="form-control" type="password" name="confirm_password" required>
              <button class="btn btn-outline-secondary btn-pass" type="button"
                      data-toggle="password" data-target="confirm_password">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <button class="btn btn-success w-100 btn-pill">Xác nhận đổi mật khẩu</button>
        </form>

      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/auth.js"></script>
</body>
</html>
