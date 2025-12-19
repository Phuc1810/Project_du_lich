<?php
// public/nhanvien/khuyenmai_sua.php
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location: khuyenmai.php");
  exit;
}

$errors = [];

// 1. Load CTKM
$stmt = $conn->prepare("SELECT * FROM chuongtrinhkhuyenmai WHERE MaCTKM=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$km = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$km) {
  header("Location: khuyenmai.php");
  exit;
}

// 2. Load Mapping (Tour đang áp dụng)
$map = [];
$stmt = $conn->prepare("SELECT MaTour, PhanTramGiamKM FROM tour_khuyenmai WHERE MaCTKM=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$rs = $stmt->get_result();
while ($r = $rs->fetch_assoc()) $map[(int)$r['MaTour']] = (int)$r['PhanTramGiamKM'];
$stmt->close();

// 3. Load All Tours (Có LoaiTour)
$tours = [];
$rs = $conn->query("SELECT MaTour, TenTour, DiaDiem, LoaiTour, TrangThai FROM tour ORDER BY MaTour DESC");
if ($rs) while ($r = $rs->fetch_assoc()) $tours[] = $r;

// --- LOGIC SẮP XẾP: Đưa tour đã chọn lên đầu ---
usort($tours, function ($a, $b) use ($map) {
  $aChecked = array_key_exists($a['MaTour'], $map);
  $bChecked = array_key_exists($b['MaTour'], $map);

  // Nếu a có tick, b không tick -> a lên trước (-1)
  if ($aChecked && !$bChecked) return -1;
  // Nếu a không tick, b có tick -> b lên trước (1)
  if (!$aChecked && $bChecked) return 1;
  // Nếu cùng trạng thái -> Sắp xếp theo ID giảm dần
  return $b['MaTour'] - $a['MaTour'];
});
// -----------------------------------------------

$today = date('Y-m-d');
$old = [
  'TenKM'        => (string)($km['TenKM'] ?? ''),
  'NoiDung'      => (string)($km['NoiDung'] ?? ''),
  'PhanTramGiam' => (string)($km['PhanTramGiam'] ?? '0'),
  'NgayBatDau'   => (string)($km['NgayBatDau'] ?? ''),
  'NgayKetThuc'  => (string)($km['NgayKetThuc'] ?? ''),
  'TrangThai'    => (string)($km['TrangThai'] ?? 'Hoạt động'),
  'AnhDaiDien'   => (string)($km['AnhDaiDien'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach (['TenKM', 'NoiDung', 'PhanTramGiam', 'NgayBatDau', 'NgayKetThuc', 'TrangThai'] as $k) {
    $old[$k] = trim($_POST[$k] ?? '');
  }

  // Validate Logic
  if ($old['TenKM'] === '') $errors[] = "Vui lòng nhập Tên khuyến mãi.";

  if ($old['PhanTramGiam'] === '' || !is_numeric($old['PhanTramGiam'])) {
    $errors[] = "Phần trăm giảm không hợp lệ.";
  } else {
    $pt = (float)$old['PhanTramGiam'];
    if ($pt < 0 || $pt > 100) $errors[] = "Phần trăm giảm phải từ 0-100.";
  }

  if ($old['NgayBatDau'] === '' || $old['NgayKetThuc'] === '') {
    $errors[] = "Vui lòng chọn Ngày bắt đầu và Ngày kết thúc.";
  } else {
    // Logic chặn ngày quá khứ (Chỉ áp dụng nếu người dùng thay đổi ngày về quá khứ)
    // Lưu ý: Nếu CTKM cũ đang chạy thì không bắt lỗi này, chỉ bắt khi tạo mới hoặc sửa thành ngày sai logic.
    if ($old['NgayKetThuc'] < $old['NgayBatDau']) {
      $errors[] = "Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.";
    }
  }

  // Validate Ảnh
  $hasNewFile = isset($_FILES['AnhDaiDien']) && $_FILES['AnhDaiDien']['error'] !== UPLOAD_ERR_NO_FILE;
  if ($hasNewFile) {
    if ($_FILES['AnhDaiDien']['error'] !== UPLOAD_ERR_OK) {
      $errors[] = "Lỗi upload ảnh (Mã lỗi: " . $_FILES['AnhDaiDien']['error'] . ")";
    } else {
      $ext = strtolower(pathinfo($_FILES['AnhDaiDien']['name'], PATHINFO_EXTENSION));
      $allow = ['jpg', 'jpeg', 'png', 'webp'];
      if (!in_array($ext, $allow, true)) $errors[] = "Ảnh chỉ hỗ trợ: jpg, jpeg, png, webp.";
      if ((int)$_FILES['AnhDaiDien']['size'] > 5 * 1024 * 1024) $errors[] = "Ảnh quá lớn (tối đa 5MB).";
    }
  }

  $selectedTours = $_POST['tours'] ?? [];
  if (!is_array($selectedTours)) $selectedTours = [];
  $percentByTour = $_POST['ptkm'] ?? [];
  if (!is_array($percentByTour)) $percentByTour = [];

  if (empty($errors)) {
    $conn->begin_transaction();
    try {
      // 1. Xử lý Ảnh
      if ($hasNewFile) {
        // Xóa ảnh cũ
        if (!empty($km['AnhDaiDien'])) {
          $fileToDelete = __DIR__ . "/../assets/" . $km['AnhDaiDien'];
          if (file_exists($fileToDelete)) unlink($fileToDelete);
        }

        $uploadDir = __DIR__ . "/../assets/img/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['AnhDaiDien']['name'], PATHINFO_EXTENSION));
        $safeName = 'km_' . $id . '_' . time() . '.' . $ext;
        $destAbs = $uploadDir . $safeName;

        if (!move_uploaded_file($_FILES['AnhDaiDien']['tmp_name'], $destAbs)) {
          throw new Exception("Không lưu được file ảnh.");
        }
        $old['AnhDaiDien'] = "img/" . $safeName;
      }

      // 2. Update CTKM
      $sqlUp = "UPDATE chuongtrinhkhuyenmai
                SET TenKM=?, NoiDung=?, AnhDaiDien=?, PhanTramGiam=?, NgayBatDau=?, NgayKetThuc=?, TrangThai=?
                WHERE MaCTKM=? LIMIT 1";
      $stmt = $conn->prepare($sqlUp);
      $pt = (int)$old['PhanTramGiam'];
      $stmt->bind_param(
        "sssisssi",
        $old['TenKM'],
        $old['NoiDung'],
        $old['AnhDaiDien'],
        $pt,
        $old['NgayBatDau'],
        $old['NgayKetThuc'],
        $old['TrangThai'],
        $id
      );
      $stmt->execute();
      $stmt->close();

      // 3. Rebuild Mapping
      $stmt = $conn->prepare("DELETE FROM tour_khuyenmai WHERE MaCTKM=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();

      if (!empty($selectedTours)) {
        $ins = $conn->prepare("INSERT INTO tour_khuyenmai (MaTour, MaCTKM, PhanTramGiamKM) VALUES (?,?,?)");
        foreach ($selectedTours as $mt) {
          $mt = (int)$mt;
          if ($mt <= 0) continue;

          $ptkm = isset($percentByTour[$mt]) && $percentByTour[$mt] !== '' ? (int)$percentByTour[$mt] : $pt;
          if ($ptkm < 0) $ptkm = 0;
          if ($ptkm > 100) $ptkm = 100;

          $ins->bind_param("iii", $mt, $id, $ptkm);
          $ins->execute();
        }
        $ins->close();
      }

      $conn->commit();
      header("Location: khuyenmai.php?msg=updated");
      exit;
    } catch (Throwable $e) {
      $conn->rollback();
      $errors[] = "Lỗi hệ thống: " . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Sửa Khuyến mãi #<?= (int)$id ?> | VietJourney</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/nhanvien.css">
  <style>
    .cardx {
      background: #fff;
      border-radius: 16px;
      padding: 24px;
      border: 1px solid rgba(0, 0, 0, 0.04);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }

    .thumb {
      width: 100%;
      max-width: 150px;
      height: auto;
      object-fit: cover;
      border-radius: 10px;
      border: 1px solid #e2e8f0;
      margin-bottom: 10px;
    }

    .tour-list {
      max-height: 420px;
      overflow-y: auto;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
    }

    .tour-item {
      border-bottom: 1px dashed #e5e7eb;
      padding: 12px 14px;
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .tour-item:last-child {
      border-bottom: none;
    }

    .tour-item:hover {
      background-color: #f9fafb;
    }

    .tour-meta {
      color: #64748b;
      font-size: 12px;
      font-weight: 600;
    }

    .mini-input {
      width: 90px;
      text-align: center;
    }

    /* Search Box Style */
    .search-tour-box {
      position: relative;
      margin-bottom: 15px;
    }

    .search-tour-box input {
      padding-left: 35px;
      border-radius: 8px;
    }

    .search-tour-box i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
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
        <a href="tintuc.php" class="nav-link"><i class="fa-solid fa-newspaper"></i> Quản lý tin tức</a>
        <a href="khuyenmai.php" class="nav-link active"><i class="fa-solid fa-tags"></i> Quản lý khuyến mãi</a>
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
          <h1 class="page-title">Sửa khuyến mãi #<?= (int)$id ?></h1>
          <div class="text-muted">Cập nhật chương trình và gán tour áp dụng</div>
        </div>
        <a class="btn btn-outline-secondary" href="khuyenmai.php"><i class="fa-solid fa-arrow-left me-1"></i> Quay lại</a>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
          <div class="fw-bold mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i> Vui lòng kiểm tra lại:</div>
          <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="row g-4">
        <div class="col-lg-5">
          <div class="cardx h-100">
            <div class="fw-bold mb-3 text-uppercase text-secondary small"><i class="fa-solid fa-tags me-2"></i>Thông tin chung</div>

            <div class="mb-3">
              <label class="form-label fw-bold">Tên khuyến mãi <span class="text-danger">*</span></label>
              <input class="form-control" name="TenKM" value="<?= h($old['TenKM']) ?>" required>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold">% Giảm chung <span class="text-danger">*</span></label>
                <input class="form-control fw-bold text-primary" type="number" min="0" max="100" name="PhanTramGiam" value="<?= h($old['PhanTramGiam']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">Trạng thái</label>
                <select class="form-select" name="TrangThai">
                  <?php foreach (['Hoạt động', 'Ngừng hoạt động', 'Hết hạn'] as $op): ?>
                    <option value="<?= h($op) ?>" <?= $old['TrangThai'] === $op ? 'selected' : ''; ?>><?= h($op) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label fw-bold">Ngày bắt đầu</label>
                <input class="form-control" type="date" name="NgayBatDau" id="NgayBatDau"
                  value="<?= h($old['NgayBatDau']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">Ngày kết thúc</label>
                <input class="form-control" type="date" name="NgayKetThuc" id="NgayKetThuc"
                  value="<?= h($old['NgayKetThuc']) ?>" min="<?= date('Y-m-d') ?>" required>
              </div>
            </div>

            <div class="mt-3">
              <label class="form-label fw-bold">Ảnh đại diện</label>
              <div>
                <?php if (!empty($old['AnhDaiDien'])): ?>
                  <img class="thumb" src="<?= h(asset_url($old['AnhDaiDien'])) ?>" alt="Ảnh hiện tại">
                <?php else: ?>
                  <div class="text-muted fst-italic mb-2">Chưa có ảnh</div>
                <?php endif; ?>
              </div>
              <input class="form-control" type="file" name="AnhDaiDien" accept=".jpg,.jpeg,.png,.webp">
              <div class="form-text small">Chọn để thay ảnh mới (ảnh cũ sẽ bị xóa).</div>
            </div>

            <div class="mt-3">
              <label class="form-label fw-bold">Nội dung chi tiết</label>
              <textarea class="form-control" name="NoiDung" rows="5"><?= h($old['NoiDung']) ?></textarea>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="cardx h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="fw-bold text-uppercase text-secondary small"><i class="fa-solid fa-list-check me-2"></i>Gán tour áp dụng</div>
              <div class="badge bg-light text-dark border">Tổng: <?= count($tours) ?> tour</div>
            </div>

            <div class="search-tour-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="inputSearchTour" class="form-control form-control-sm"
                placeholder="Gõ mã tour, tên tour hoặc địa điểm để tìm nhanh...">
            </div>

            <div class="alert alert-info py-2 small">
              <i class="fa-solid fa-circle-info me-1"></i> Tích chọn tour để áp dụng. Nếu để trống <b>% giảm riêng</b>, hệ thống sẽ dùng <b>% Giảm chung</b> bên trái.
            </div>

            <div class="tour-list">
              <?php if (empty($tours)): ?>
                <div class="p-4 text-center text-muted">Chưa có tour nào trong hệ thống.</div>
              <?php else: ?>
                <div id="tourContainer">
                  <?php foreach ($tours as $t): ?>
                    <?php
                    $maTour = (int)$t['MaTour'];
                    $checked = array_key_exists($maTour, $map);
                    $ptkm = $checked ? (int)$map[$maTour] : '';

                    // Search String (Giống _them: Mã, Tên, Địa điểm)
                    $searchString = h(strtolower($maTour . ' ' . $t['TenTour'] . ' ' . $t['DiaDiem']));

                    // Style cho item đã chọn (Highlight nhẹ)
                    $bgClass = $checked ? 'bg-light border-start border-3 border-primary ps-2' : '';
                    ?>
                    <div class="tour-item <?= $bgClass ?>" data-search="<?= $searchString ?>">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tours[]" value="<?= $maTour ?>" id="t<?= $maTour ?>" <?= $checked ? 'checked' : ''; ?>>
                      </div>

                      <div class="flex-grow-1 cursor-pointer" onclick="document.getElementById('t<?= $maTour ?>').click()">
                        <div class="fw-bold text-dark"><?= h($t['TenTour'] ?? '') ?></div>
                        <div class="tour-meta">
                          #<?= $maTour ?>
                          <span class="text-primary fw-bold mx-1"><?= h($t['LoaiTour'] ?? '') ?></span>
                          • <?= h($t['DiaDiem'] ?? '-') ?>
                        </div>
                      </div>

                      <div class="text-end">
                        <div class="small text-muted mb-1" style="font-size: 11px;">% Riêng</div>
                        <input class="form-control form-control-sm mini-input" type="number" min="0" max="100"
                          name="ptkm[<?= $maTour ?>]" value="<?= h($ptkm) ?>" placeholder="Mặc định">
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div id="noResult" class="text-center text-muted py-4" style="display: none;">
                  <i class="fa-regular fa-face-frown mb-1"></i><br>Không tìm thấy tour phù hợp
                </div>
              <?php endif; ?>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
              <a href="khuyenmai.php" class="btn btn-light border px-4">Hủy bỏ</a>
              <button class="btn btn-primary px-4 fw-bold" type="submit">
                <i class="fa-solid fa-floppy-disk me-1"></i> Lưu thay đổi
              </button>
            </div>
          </div>
        </div>
      </form>

    </main>
  </div>

  <script>
    // 1. Ràng buộc ngày
    const start = document.getElementById('NgayBatDau');
    const end = document.getElementById('NgayKetThuc');
    if (start && end) {
      start.addEventListener('change', function() {
        if (this.value) end.min = this.value;
      });
    }

    // 2. Bộ lọc tìm kiếm Tour
    const searchInput = document.getElementById('inputSearchTour');
    const tourItems = document.querySelectorAll('.tour-item');
    const noResult = document.getElementById('noResult');

    if (searchInput) {
      searchInput.addEventListener('keyup', function(e) {
        const term = e.target.value.toLowerCase().trim();
        let hasVisible = false;

        tourItems.forEach(item => {
          const textData = item.getAttribute('data-search');
          if (textData.includes(term)) {
            item.style.display = 'flex'; // Dùng flex vì class tour-item có display:flex
            hasVisible = true;
          } else {
            item.style.display = 'none';
          }
        });

        if (noResult) {
          noResult.style.display = hasVisible ? 'none' : 'block';
        }
      });
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
