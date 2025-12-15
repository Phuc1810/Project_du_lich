<header id="header">
  <?php
  // đảm bảo có config + session
  if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . "/../config.php";
  }
  if (session_status() === PHP_SESSION_NONE) session_start();

  // Lấy thông tin công ty
  $cty = ['SoDienThoai' => '', 'Logo_1' => ''];
  $rs = $conn->query("SELECT SoDienThoai, Logo_1 FROM CongTy LIMIT 1");
  if ($rs) {
    $row = $rs->fetch_assoc();
    if ($row) $cty = $row;
  }

  // URL hiện tại để redirect sau login
  $currentUrl = $_SERVER['REQUEST_URI'] ?? 'trangchu.php';

  // Check login
  $isLoggedIn = !empty($_SESSION['user']['MaTK']);
  $hoten = '';

  if ($isLoggedIn) {
    // ưu tiên lấy từ session
    if (!empty($_SESSION['user']['HoTen'])) {
      $hoten = $_SESSION['user']['HoTen'];
    } else {
      // lấy từ DB theo MaTK
      $matk = (int)$_SESSION['user']['MaTK'];
      $stmt = $conn->prepare("SELECT HoTen FROM KhachHang WHERE MaTK=? LIMIT 1");
      $stmt->bind_param("i", $matk);
      $stmt->execute();
      $rowKH = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      $hoten = $rowKH['HoTen'] ?? '';
      $_SESSION['user']['HoTen'] = $hoten; // cache
    }
  }
  ?>

  <!-- TOPBAR -->
  <div class="topbar position-fixed w-100 top-0 start-0 z-3 d-flex justify-content-start align-items-center gap-3">
    <span class="fw-bold phone-hotline">
      <?= htmlspecialchars($cty['SoDienThoai'] ?? '') ?>
    </span>

    <div class="search-container d-flex align-items-center">
      <form action="timkiemnhanh.php" method="GET" class="d-flex align-items-center">
        <input type="text" name="key" class="searchbox" placeholder="Nhập địa điểm...">
      </form>
      <button class="btn btn-primary btn-sm ms-2 rounded-pill px-3">Liên hệ</button>
    </div>
  </div>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top thanh_dieu_huong">
    <div class="container d-flex justify-content-between align-items-center">

      <a class="navbar-brand fw-bold text-white" href="trangchu.php">
        <img src="assets/<?= htmlspecialchars($cty['Logo_1'] ?? '') ?>" class="logo_img" alt="Logo">
      </a>

      <div class="collapse navbar-collapse justify-content-end" id="menu_chinh">
        <ul class="navbar-nav align-items-center gap-3">

          <li class="nav-item">
            <a class="nav-link active" href="trangchu.php">TRANG CHỦ</a>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">TOUR</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="tour-mien.php?mien=bac">Tour Miền Bắc</a></li>
              <li><a class="dropdown-item" href="tour-mien.php?mien=trung">Tour Miền Trung</a></li>
              <li><a class="dropdown-item" href="tour-mien.php?mien=nam">Tour Miền Nam</a></li>
              <li><a class="dropdown-item" href="khuyenmai.php">Chương trình khuyến mãi</a></li>
            </ul>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="tour_doanhnghiep.php">ĐẶT TOUR DOANH NGHIỆP</a>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="banggia.php">BẢNG GIÁ</a>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">BLOG</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="tintuc.php?loai=tintuc">Tin tức du lịch</a></li>
              <li><a class="dropdown-item" href="tintuc.php?loai=kinhnghiem">Kinh nghiệm du lịch</a></li>
            </ul>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="#footer-site">LIÊN HỆ</a>
          </li>

          <!-- ✅ LOGIN / GREETING -->
          <?php if (!$isLoggedIn): ?>
            <li class="nav-item login-item">
              <a class="nav-link login-link"
                href="auth.php?tab=login&redirect=<?= urlencode($currentUrl) ?>">
                <i class="fa-regular fa-user me-1"></i> ĐĂNG NHẬP
              </a>
            </li>
          <?php else: ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                <i class="fa-regular fa-user me-1"></i>
                CHÀO <?= htmlspecialchars($hoten !== '' ? $hoten : 'BẠN') ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <a class="dropdown-item" href="thongtincanhan.php">
                    <i class="fa-regular fa-id-card me-2"></i> Thông tin cá nhân
                  </a>
                </li>

                <li>
                  <hr class="dropdown-divider">
                </li>

                <li>
                  <a class="dropdown-item" href="donhang.php">
                    <i class="fa-solid fa-receipt me-2"></i> Đơn hàng (đặt tour)
                  </a>
                </li>

                <li>
                  <a class="dropdown-item" href="donyeucau.php">
                    <i class="fa-solid fa-briefcase me-2"></i> Yêu cầu doanh nghiệp
                  </a>
                </li>
                
                <li>
                  <hr class="dropdown-divider">
                </li>

                <li>
                  <a class="dropdown-item text-danger"
                    href="logout.php?redirect=<?= urlencode($currentUrl) ?>">
                    <i class="fa-solid fa-right-from-bracket me-2"></i> Đăng xuất
                  </a>
                </li>
              </ul>
            </li>
          <?php endif; ?>

        </ul>
      </div>
    </div>
  </nav>
</header>
