<?php
// public/nhanvien/tintuc_them.php
require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
  header("Location: login.php");
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$errors = [];
$old = [
  'TieuDe' => '',
  'MoTa' => '',
  'NoiDung' => '',
  'LoaiTin' => 'tintuc',
  'TrangThai' => 'Hiển thị',
];

$myMaNV = (int)($_SESSION['staff']['MaNV'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old['TieuDe']    = trim($_POST['TieuDe'] ?? '');
  $old['MoTa']      = trim($_POST['MoTa'] ?? '');
  $old['NoiDung']   = trim($_POST['NoiDung'] ?? '');
  $old['LoaiTin']   = trim($_POST['LoaiTin'] ?? 'tintuc');
  $old['TrangThai'] = trim($_POST['TrangThai'] ?? 'Hiển thị');

  // 1. Validate Bắt buộc
  if ($old['TieuDe'] === '') $errors[] = "Vui lòng nhập Tiêu đề.";
  if ($old['MoTa'] === '') $errors[] = "Vui lòng nhập Mô tả.";
  if ($old['NoiDung'] === '') $errors[] = "Vui lòng nhập Nội dung.";

  // Validate Option
  $loaiAllow = ['tintuc', 'kinhnghiem'];
  if (!in_array($old['LoaiTin'], $loaiAllow)) $errors[] = "Loại tin không hợp lệ.";

  // Validate Trạng thái
  $ttAllow = ['Hiển thị', 'Ẩn'];
  if (!in_array($old['TrangThai'], $ttAllow)) $errors[] = "Trạng thái không hợp lệ.";

  // 2. Validate Ảnh (Bắt buộc)
  $hasFile = isset($_FILES['AnhDaiDien']) && $_FILES['AnhDaiDien']['error'] !== UPLOAD_ERR_NO_FILE;
  if (!$hasFile) {
    $errors[] = "Vui lòng chọn ảnh đại diện.";
  } else {
    if ($_FILES['AnhDaiDien']['error'] !== UPLOAD_ERR_OK) {
      $errors[] = "Upload ảnh lỗi (code: " . (int)$_FILES['AnhDaiDien']['error'] . ").";
    } else {
      $ext = strtolower(pathinfo($_FILES['AnhDaiDien']['name'], PATHINFO_EXTENSION));
      $allow = ['jpg', 'jpeg', 'png', 'webp'];
      if (!in_array($ext, $allow, true)) {
        $errors[] = "Ảnh chỉ hỗ trợ: jpg, jpeg, png, webp.";
      }
      if ((int)$_FILES['AnhDaiDien']['size'] > 5 * 1024 * 1024) {
        $errors[] = "Ảnh quá lớn (tối đa 5MB).";
      }
    }
  }

  // 3. Xử lý lưu DB & Upload
  if (empty($errors)) {
    try {
      // a. Upload ảnh
      $uploadDir = __DIR__ . "/../assets/img/";
      if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
          throw new Exception("Không tạo được thư mục upload: public/assets/img/");
        }
      }

      $ext = strtolower(pathinfo($_FILES['AnhDaiDien']['name'], PATHINFO_EXTENSION));
      // Tạo tên file an toàn
      $safeName = 'tin_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
      $destAbs = $uploadDir . $safeName;

      if (!move_uploaded_file($_FILES['AnhDaiDien']['tmp_name'], $destAbs)) {
        throw new Exception("Không lưu được ảnh upload.");
      }

      // Đường dẫn lưu DB
      $dbPath = "img/" . $safeName;

      // b. Insert DB
      $ngay = date('Y-m-d');
      $stmt = $conn->prepare("
        INSERT INTO tintuc (TieuDe, MoTa, NoiDung, LoaiTin, AnhDaiDien, NgayDang, TrangThai, MaNV)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0))
      ");
      $stmt->bind_param(
        "sssssssi",
        $old['TieuDe'],
        $old['MoTa'],
        $old['NoiDung'],
        $old['LoaiTin'],
        $dbPath,
        $ngay,
        $old['TrangThai'],
        $myMaNV
      );
      $stmt->execute();
      $stmt->close();

      // Redirect
      header("Location: tintuc.php?msg=added");
      exit;
    } catch (Throwable $e) {
      $errors[] = "Lỗi thêm bài viết: " . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Thêm tin tức | VietJourney</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/nhanvien.css">
  <style>
    .form-card {
      background: #fff;
      border-radius: 16px;
      padding: 30px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
      border: 1px solid rgba(0, 0, 0, 0.04);
    }
  </style>
</head>

<body>
  <div class="dashboard-wrapper">
    <aside class="sidebar">
      <div class="brand-section"><a href="#" class="brand-logo"><i class="fa-solid fa-compass"></i> VietJourney</a></div>
      <nav class="nav-section">
        <div class="nav-label">Tổng quan</div>
        <a href="index.php" class="nav-link"><i class="fa-solid fa-grid-2"></i> Dashboard</a>
        <div class="nav-label mt-4">Quản lý nghiệp vụ</div>
        <a href="donhang.php" class="nav-link"><i class="fa-solid fa-receipt"></i> Đơn đặt tour</a>
        <a href="donyeucau.php" class="nav-link"><i class="fa-solid fa-building-user"></i> Yêu cầu doanh nghiệp</a>
        <a href="tour.php" class="nav-link"><i class="fa-solid fa-map-location-dot"></i> Quản lý Tour</a>

        <a href="tintuc.php" class="nav-link active"><i class="fa-solid fa-newspaper"></i> Quản lý tin tức</a>
        <a href="khuyenmai.php" class="nav-link"><i class="fa-solid fa-tags"></i> Quản lý khuyến mãi</a>

        <div class="nav-label mt-4">Hệ thống</div>
        <a href="logout.php" class="nav-link text-danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Đăng xuất</a>
      </nav>
      <div class="user-section">
        <div class="user-card">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['staff']['TenDangNhap'] ?? 'NV', 0, 1)) ?></div>
          <div class="user-info">
            <div class="user-name"><?= h($_SESSION['staff']['HoTen'] ?? $_SESSION['staff']['TenDangNhap'] ?? 'Nhân viên') ?></div>
            <div class="user-role">Nhân viên hệ thống</div>
          </div>
        </div>
      </div>
    </aside>

    <main class="main-content">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="page-title">Thêm bài viết</h1>
          <div class="text-muted">Tạo mới tin tức hoặc bài viết chia sẻ kinh nghiệm</div>
        </div>
        <a class="btn btn-outline-secondary" href="tintuc.php"><i class="fa-solid fa-arrow-left me-1"></i> Quay lại danh sách</a>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
          <div class="fw-bold mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i> Vui lòng kiểm tra lại:</div>
          <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="form-card">
        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-bold">Tiêu đề <span class="text-danger">*</span></label>
            <input class="form-control" name="TieuDe" value="<?= h($old['TieuDe']) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-bold">Loại tin</label>
            <select class="form-select" name="LoaiTin" required>
              <option value="tintuc" <?= $old['LoaiTin'] === 'tintuc' ? 'selected' : ''; ?>>Tin tức</option>
              <option value="kinhnghiem" <?= $old['LoaiTin'] === 'kinhnghiem' ? 'selected' : ''; ?>>Kinh nghiệm</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label fw-bold">Mô tả ngắn <span class="text-danger">*</span></label>
            <textarea class="form-control" name="MoTa" rows="3" required><?= h($old['MoTa']) ?></textarea>
            <div class="form-text">Hiển thị tóm tắt trên danh sách tin tức.</div>
          </div>

          <div class="col-12">
            <label class="form-label fw-bold">Nội dung chi tiết <span class="text-danger">*</span></label>
            <textarea class="form-control" name="NoiDung" rows="10" required><?= h($old['NoiDung']) ?></textarea>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-bold">Ảnh đại diện <span class="text-danger">*</span></label>
            <input type="file" class="form-control" name="AnhDaiDien" accept=".jpg,.jpeg,.png,.webp" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-bold">Trạng thái</label>
            <select class="form-select" name="TrangThai">
              <option value="Hiển thị" <?= $old['TrangThai'] === 'Hiển thị' ? 'selected' : ''; ?>>Hiển thị</option>
              <option value="Ẩn" <?= $old['TrangThai'] === 'Ẩn' ? 'selected' : ''; ?>>Ẩn</option>
            </select>
          </div>

          <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top mt-3">
            <a href="tintuc.php" class="btn btn-light border px-4">Hủy bỏ</a>
            <button class="btn btn-primary px-4 fw-bold" type="submit">
              <i class="fa-solid fa-floppy-disk me-1"></i> Lưu bài viết
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>