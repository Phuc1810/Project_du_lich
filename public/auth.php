<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'trangchu.php';
$tab = $_GET['tab'] ?? 'login';

// lấy flash (errors + old)
$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$errors = $flash['errors'] ?? [];
$old    = $flash['old'] ?? [];

$view = $_GET['view'] ?? ($flash['view'] ?? null);

// Prefill login_key khi bấm "Về trang đăng nhập"
if (empty($old['login_key']) && !empty($_GET['prefill'])) {
  $old['login_key'] = $_GET['prefill'];
}
if (empty($old['login_key']) && !empty($_GET['email'])) {
  $old['login_key'] = $_GET['email'];
}

if (!empty($flash['tab'])) $tab = $flash['tab'];
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Đăng nhập</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">
  <link rel="stylesheet" href="./assets/css/auth.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>

<body>

  <?php require_once __DIR__ . "/../app/includes/header.php"; ?>

  <div class="container auth-shell">
    <div class="row justify-content-center">
      <div class="col-lg-9 col-xl-8">
        <div class="card auth-card">
          <div class="row g-0">

            <!-- LEFT -->
            <div class="col-md-5 d-none d-md-block">
              <div class="auth-left h-100">
                <div class="brand">
                  <i class="fa-solid fa-plane-departure"></i> TourDuLich
                </div>

                <div class="slogan">
                  Đăng nhập để đặt tour nhanh hơn, theo dõi đơn đặt tour và nhận ưu đãi.
                </div>

                <div class="mt-4">
                  <div class="bullet">
                    <i class="fa-solid fa-check"></i>
                    <div>Đặt tour & quản lý đơn</div>
                  </div>
                  <div class="bullet">
                    <i class="fa-solid fa-check"></i>
                    <div>Nhận khuyến mãi theo tài khoản</div>
                  </div>
                  <div class="bullet">
                    <i class="fa-solid fa-check"></i>
                    <div>Đăng nhập Google 1 chạm</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- RIGHT -->
            <div class="col-md-7">
              <div class="auth-right">

                <?php if ($view === 'register_success' && !empty($flash['success'])): ?>

                  <!-- ✅ CHỈ HIỆN SUCCESS SCREEN -->
                  <div class="auth-success text-center">
                    <div class="auth-success-icon">
                      <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <h4 class="fw-bold mb-2">Đăng ký thành công!</h4>
                    <p class="text-muted mb-4"><?= htmlspecialchars($flash['success']) ?></p>

                    <a class="btn btn-primary btn-pill w-100"
                      href="auth.php?tab=login&redirect=<?= urlencode($redirect) ?>&prefill=<?= urlencode($flash['email'] ?? ($old['login_key'] ?? '')) ?>">
                      Về trang đăng nhập
                    </a>
                  </div>

                <?php else: ?>

                  <!-- ✅ CHỖ NÀY MỚI HIỆN FORM -->
                  <?php if (!empty($flash['success'])): ?>
                    <div class="alert alert-success d-flex align-items-center justify-content-between gap-3 mb-3">
                      <div>
                        <i class="fa-solid fa-circle-check me-2"></i>
                        <?= htmlspecialchars($flash['success']) ?>
                      </div>
                      <?php if (!empty($flash['success_btn_text']) && !empty($flash['success_btn_link'])): ?>
                        <a class="btn btn-success btn-sm"
                          href="<?= htmlspecialchars($flash['success_btn_link']) ?>">
                          <?= htmlspecialchars($flash['success_btn_text']) ?>
                        </a>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert-danger mb-3"><?= htmlspecialchars($errors['form']) ?></div>
                  <?php endif; ?>


                  <!-- Tabs -->
                  <ul class="nav nav-pills seg-tabs mb-3" role="tablist">
                    <li class="nav-item">
                      <button class="nav-link <?= ($tab === 'login') ? 'active' : '' ?>"
                        data-bs-toggle="pill" data-bs-target="#tab-login" type="button">
                        Đăng nhập
                      </button>
                    </li>
                    <li class="nav-item">
                      <button class="nav-link <?= ($tab === 'register') ? 'active' : '' ?>"
                        data-bs-toggle="pill" data-bs-target="#tab-register" type="button">
                        Đăng ký
                      </button>
                    </li>
                  </ul>

                  <div class="tab-content">

                    <!-- LOGIN -->
                    <div class="tab-pane fade <?= ($tab === 'login') ? 'show active' : '' ?>" id="tab-login">
                      <form method="POST" action="process_login.php" novalidate>
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                        <div class="mb-3">
                          <label class="form-label">Email hoặc SĐT</label>
                          <input class="form-control <?= isset($errors['login_key']) ? 'is-invalid' : '' ?>"
                            name="login_key"
                            value="<?= htmlspecialchars($old['login_key'] ?? '') ?>"
                            placeholder="vd: ten@gmail.com hoặc 0123456789">
                          <?php if (isset($errors['login_key'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['login_key']) ?></div>
                          <?php endif; ?>
                        </div>

                        <div class="mb-2">
                          <label class="form-label">Mật khẩu</label>

                          <div class="input-group">
                            <input id="login_password"
                              type="password"
                              class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                              name="password"
                              placeholder="Nhập mật khẩu">
                            <button class="btn btn-outline-secondary btn-pass" type="button"
                              data-toggle="password" data-target="login_password" aria-label="Hiện/ẩn mật khẩu">
                              <i class="fa-regular fa-eye"></i>
                            </button>
                          </div>

                          <?php if (isset($errors['password'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['password']) ?></div>
                          <?php endif; ?>

                          <div class="text-end mt-2">
                            <a href="forgot_password.php?redirect=<?= urlencode($redirect) ?>" class="text-decoration-none">
                              Quên mật khẩu?
                            </a>
                          </div>
                        </div>

                        <button class="btn btn-primary w-100 btn-pill mt-3">ĐĂNG NHẬP</button>

                        <div class="divider my-4">hoặc</div>

                        <!-- GOOGLE SIGN IN -->
                        <div class="text-center">
                          <div id="g_id_onload"
                            data-client_id="305921031732-soi97ruuh46nualpefhtpmpv0bmnab9s.apps.googleusercontent.com"
                            data-callback="onGoogleLogin"
                            data-auto_prompt="false"></div>

                          <div class="google-wrap">
                            <div class="g_id_signin"
                              data-type="standard"
                              data-shape="pill"
                              data-theme="outline"
                              data-text="signin_with"
                              data-size="large"
                              data-width="520">
                            </div>
                          </div>
                        </div>
                      </form>
                    </div>

                    <!-- REGISTER -->
                    <?php
                    $maxDob = date('Y-m-d', strtotime('-1 day')); // không cho chọn hôm nay/tương lai
                    $gt = $old['gioitinh'] ?? '';
                    ?>
                    <div class="tab-pane fade <?= ($tab === 'register') ? 'show active' : '' ?>" id="tab-register">
                      <form method="POST" action="process_register.php" novalidate>
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                        <!-- Họ tên -->
                        <div class="mb-3">
                          <label class="form-label">Họ tên</label>
                          <input class="form-control <?= isset($errors['hoten']) ? 'is-invalid' : '' ?>"
                            name="hoten"
                            value="<?= htmlspecialchars($old['hoten'] ?? '') ?>"
                            placeholder="VD: Nguyễn Văn A">
                          <?php if (isset($errors['hoten'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['hoten']) ?></div>
                          <?php endif; ?>
                        </div>

                        <!-- Email hoặc Số điện thoại -->
                        <div class="mb-3">
                          <label class="form-label">Email hoặc Số điện thoại</label>
                          <input class="form-control <?= isset($errors['contact']) ? 'is-invalid' : '' ?>"
                            name="contact"
                            value="<?= htmlspecialchars($old['contact'] ?? '') ?>"
                            placeholder="VD: ten@gmail.com hoặc 0123456789">
                          <?php if (isset($errors['contact'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['contact']) ?></div>
                          <?php endif; ?>
                        </div>

                        <!-- Địa chỉ -->
                        <div class="mb-3">
                          <label class="form-label">Địa chỉ</label>
                          <input class="form-control <?= isset($errors['diachi']) ? 'is-invalid' : '' ?>"
                            name="diachi"
                            value="<?= htmlspecialchars($old['diachi'] ?? '') ?>"
                            placeholder="VD: 123 Lê Lợi, Q1, TP.HCM">
                          <?php if (isset($errors['diachi'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['diachi']) ?></div>
                          <?php endif; ?>
                        </div>

                        <!-- Ngày sinh -->
                        <div class="mb-3">
                          <label class="form-label">Ngày sinh</label>
                          <input type="date"
                            class="form-control <?= isset($errors['ngaysinh']) ? 'is-invalid' : '' ?>"
                            name="ngaysinh"
                            value="<?= htmlspecialchars($old['ngaysinh'] ?? '') ?>"
                            max="<?= $maxDob ?>">
                          <?php if (isset($errors['ngaysinh'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['ngaysinh']) ?></div>
                          <?php endif; ?>
                        </div>

                        <!-- Giới tính -->
                        <div class="mb-3">
                          <label class="form-label d-block">Giới tính</label>

                          <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gt_nam" value="Nam"
                              <?= ($gt === 'Nam') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="gt_nam">Nam</label>
                          </div>

                          <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gt_nu" value="Nữ"
                              <?= ($gt === 'Nữ') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="gt_nu">Nữ</label>
                          </div>

                          <?php if (isset($errors['gioitinh'])): ?>
                            <div class="field-error mt-1"><?= htmlspecialchars($errors['gioitinh']) ?></div>
                          <?php endif; ?>
                        </div>

                        <!-- Mật khẩu -->
                        <div class="mb-2">
                          <label class="form-label">Mật khẩu</label>

                          <div class="input-group">
                            <input id="register_password"
                              type="password"
                              class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                              name="password"
                              placeholder="Ít nhất 8 ký tự, đủ hoa/thường/số/ký tự đặc biệt">
                            <button class="btn btn-outline-secondary btn-pass" type="button"
                              data-toggle="password" data-target="register_password" aria-label="Hiện/ẩn mật khẩu">
                              <i class="fa-regular fa-eye"></i>
                            </button>
                          </div>

                          <?php if (isset($errors['password'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['password']) ?></div>
                          <?php endif; ?>

                          <div class="hint mt-1">Gợi ý: Abc@1234</div>
                        </div>

                        <button class="btn btn-success w-100 btn-pill mt-3">ĐĂNG KÝ</button>
                      </form>
                    </div>

                  </div><!-- tab content -->

                <?php endif; ?>

              </div>
            </div>

          </div><!-- row -->
        </div><!-- card -->
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

  <script>
    window.AUTH_REDIRECT = <?= json_encode($redirect) ?>;
  </script>

  <script src="./assets/js/auth.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>