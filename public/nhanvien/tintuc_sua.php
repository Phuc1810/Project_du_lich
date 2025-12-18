<?php
// public/nhanvien/tintuc_sua.php
require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Guard staff
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
  header("Location: login.php");
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location: tintuc.php");
  exit;
}

$errors = [];
$success = false;

// 1. Lấy thông tin bài viết hiện tại
$stmt = $conn->prepare("SELECT * FROM tintuc WHERE MaTin=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$tin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tin) {
  header("Location: tintuc.php");
  exit;
}

$old = [
  'TieuDe'    => (string)($tin['TieuDe'] ?? ''),
  'MoTa'      => (string)($tin['MoTa'] ?? ''),
  'NoiDung'   => (string)($tin['NoiDung'] ?? ''),
  'LoaiTin'   => (string)($tin['LoaiTin'] ?? 'tintuc'),
  'TrangThai' => (string)($tin['TrangThai'] ?? 'Hiển thị'),
];

$myMaNV = (int)($_SESSION['staff']['MaNV'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old['TieuDe']    = trim($_POST['TieuDe'] ?? '');
  $old['MoTa']      = trim($_POST['MoTa'] ?? '');
  $old['NoiDung']   = trim($_POST['NoiDung'] ?? '');
  $old['LoaiTin']   = trim($_POST['LoaiTin'] ?? 'tintuc');
  $old['TrangThai'] = trim($_POST['TrangThai'] ?? 'Hiển thị');

  // ===== 1. Validate Bắt buộc =====
  if ($old['TieuDe'] === '') $errors[] = "Vui lòng nhập Tiêu đề.";
  if ($old['MoTa'] === '') $errors[] = "Vui lòng nhập Mô tả.";
  if ($old['NoiDung'] === '') $errors[] = "Vui lòng nhập Nội dung.";

  // Validate Option
  $loaiAllow = ['tintuc', 'kinhnghiem'];
  if (!in_array($old['LoaiTin'], $loaiAllow)) $errors[] = "Loại tin không hợp lệ.";

  $ttAllow = ['Hiển thị', 'Ẩn'];
  if (!in_array($old['TrangThai'], $ttAllow)) $errors[] = "Trạng thái không hợp lệ.";

  // ===== 2. Validate Ảnh (Nếu có chọn file mới) =====
  $hasNewFile = isset($_FILES['AnhDaiDien']) && $_FILES['AnhDaiDien']['error'] !== UPLOAD_ERR_NO_FILE;
  if ($hasNewFile) {
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

  // ===== 3. Cập nhật DB =====
  if (empty($errors)) {
    $conn->begin_transaction();
    try {
      // a. Update thông tin văn bản
      $stmt = $conn->prepare("
                UPDATE tintuc 
                SET TieuDe=?, MoTa=?, NoiDung=?, LoaiTin=?, TrangThai=?, MaNV=NULLIF(?,0)
                WHERE MaTin=? LIMIT 1
            ");
      $stmt->bind_param(
        "sssssii",
        $old['TieuDe'],
        $old['MoTa'],
        $old['NoiDung'],
        $old['LoaiTin'],
        $old['TrangThai'],
        $myMaNV,
        $id
      );
      $stmt->execute();
      $stmt->close();

      // b. Xử lý Ảnh (Nếu có upload mới)
      if ($hasNewFile) {
        // --- Xóa ảnh cũ ---
        // Lấy lại đường dẫn ảnh cũ từ DB (biến $tin đã lấy ở trên)
        $oldPath = $tin['AnhDaiDien'] ?? '';
        if (!empty($oldPath)) {
          // Đường dẫn vật lý: public/assets/ + img/tenfile.jpg
          $fileToDelete = __DIR__ . "/../assets/" . $oldPath;
          if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
          }
        }

        // --- Upload ảnh mới ---
        $uploadDir = __DIR__ . "/../assets/img/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['AnhDaiDien']['name'], PATHINFO_EXTENSION));
        $safeName = 'tin_' . $id . '_' . time() . '.' . $ext;
        $destAbs = $uploadDir . $safeName;

        if (!move_uploaded_file($_FILES['AnhDaiDien']['tmp_name'], $destAbs)) {
          throw new Exception("Lỗi lưu ảnh mới.");
        }

        $dbPath = "img/" . $safeName;

        // --- Update cột AnhDaiDien ---
        $stmtImg = $conn->prepare("UPDATE tintuc SET AnhDaiDien=? WHERE MaTin=?");
        $stmtImg->bind_param("si", $dbPath, $id);
        $stmtImg->execute();
        $stmtImg->close();
      }

      $conn->commit();
      header("Location: tintuc.php?msg=updated");
      exit;
    } catch (Throwable $e) {
      $conn->rollback();
      $errors[] = "Lỗi cập nhật: " . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Sửa tin tức #<?= $id ?> | VietJourney</title>
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

    .thumb-preview {
      width: 100%;
      max-width: 200px;
      height: auto;
      border-radius: 8px;
      border: 1px solid #eee;
      margin-top: 10px;
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
          <h1 class="page-title">Sửa bài viết #<?= $id ?></h1>
          <div class="text-muted">Cập nhật nội dung và hình ảnh</div>
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
          </div>

          <div class="col-12">
            <label class="form-label fw-bold">Nội dung chi tiết <span class="text-danger">*</span></label>
            <textarea class="form-control" name="NoiDung" rows="10" required><?= h($old['NoiDung']) ?></textarea>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-bold">Ảnh đại diện hiện tại</label>
            <div>
              <?php if (!empty($tin['AnhDaiDien'])): ?>
                <img src="<?= h(asset_url($tin['AnhDaiDien'])) ?>" class="thumb-preview" alt="Ảnh hiện tại">
              <?php else: ?>
                <div class="text-muted fst-italic">Chưa có ảnh</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-bold">Thay ảnh mới (Tùy chọn)</label>
            <input type="file" class="form-control" name="AnhDaiDien" accept=".jpg,.jpeg,.png,.webp">
            <div class="form-text">Để trống nếu muốn giữ nguyên ảnh cũ.</div>

            <label class="form-label fw-bold mt-3">Trạng thái</label>
            <select class="form-select" name="TrangThai">
              <option value="Hiển thị" <?= $old['TrangThai'] === 'Hiển thị' ? 'selected' : ''; ?>>Hiển thị</option>
              <option value="Ẩn" <?= $old['TrangThai'] === 'Ẩn' ? 'selected' : ''; ?>>Ẩn</option>
            </select>
          </div>

          <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top mt-3">
            <a href="tintuc.php" class="btn btn-light border px-4">Hủy bỏ</a>
            <button class="btn btn-primary px-4 fw-bold" type="submit">
              <i class="fa-solid fa-floppy-disk me-1"></i> Lưu thay đổi
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>