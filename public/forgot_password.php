<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$redirect = $_GET['redirect'] ?? 'trangchu.php';

$flash = $_SESSION['flash_fp'] ?? [];
unset($_SESSION['flash_fp']);

$error = $flash['error'] ?? '';
$success = $flash['success'] ?? '';
$old = $flash['old'] ?? ['contact' => ''];
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Quên mật khẩu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">
  <link rel="stylesheet" href="./assets/css/auth.css">
</head>
<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container auth-shell">
  <div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
      <div class="card auth-card p-4">
        <h4 class="fw-bold mb-2">Quên mật khẩu</h4>
        <p class="text-muted mb-3">Nhập Email (@gmail.com) hoặc SĐT (10 số) để nhận mã OTP.</p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="process_forgot_password.php" novalidate>
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
          <div class="mb-3">
            <label class="form-label">Email hoặc SĐT</label>
            <input class="form-control" name="contact"
                   value="<?= htmlspecialchars($old['contact'] ?? '') ?>"
                   placeholder="vd: ten@gmail.com hoặc 0123456789" required>
          </div>

          <button class="btn btn-primary w-100 btn-pill">Gửi OTP</button>

          <div class="text-center mt-3">
            <a class="text-decoration-none" href="auth.php?tab=login&redirect=<?= urlencode($redirect) ?>">
              Quay về đăng nhập
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
