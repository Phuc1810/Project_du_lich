<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$redirect = $_GET['redirect'] ?? 'trangchu.php';
$prefill  = $_GET['prefill'] ?? ''; // email để điền sẵn vào form đăng nhập
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đổi mật khẩu thành công</title>
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
      <div class="card auth-card p-4 text-center">
        <div class="auth-success-icon mb-3" style="font-size:48px;color:#198754;">
          <i class="fa-solid fa-circle-check"></i>
        </div>
        <h4 class="fw-bold mb-2">Đổi mật khẩu thành công!</h4>
        <p class="text-muted mb-4">Bạn có thể đăng nhập bằng mật khẩu mới ngay bây giờ.</p>

        <a class="btn btn-primary btn-pill w-100"
           href="auth.php?tab=login&redirect=<?= urlencode($redirect) ?>&prefill=<?= urlencode($prefill) ?>">
          Về trang đăng nhập
        </a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
