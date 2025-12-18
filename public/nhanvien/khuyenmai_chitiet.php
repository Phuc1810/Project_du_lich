<?php
// public/nhanvien/khuyenmai_chitiet.php
require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function fmtDate($d)
{
  if (!$d) return '—';
  $ts = strtotime($d);
  return $ts ? date('d/m/Y', $ts) : '—';
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

// ===== 1. LOAD THÔNG TIN CTKM =====
$stmt = $conn->prepare("SELECT * FROM chuongtrinhkhuyenmai WHERE MaCTKM=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$km = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$km) {
  header("Location: khuyenmai.php");
  exit;
}

// ===== 2. XỬ LÝ FORM (POST) - CHỈ CÒN SỬA % VÀ XÓA =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // A. Cập nhật % giảm của 1 tour (Giữ lại để sửa nhanh)
  if ($action === 'update_pt') {
    $maTour = (int)($_POST['MaTour'] ?? 0);
    $ptkm   = (int)($_POST['PhanTramGiamKM'] ?? 0);

    if ($maTour <= 0) $errors[] = "Tour không hợp lệ.";
    if ($ptkm < 0 || $ptkm > 100) $errors[] = "% giảm phải từ 0-100.";

    if (empty($errors)) {
      try {
        $stmt = $conn->prepare("UPDATE tour_khuyenmai SET PhanTramGiamKM=? WHERE MaTour=? AND MaCTKM=? LIMIT 1");
        $stmt->bind_param("iii", $ptkm, $maTour, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: khuyenmai_chitiet.php?id=" . $id . "&msg=updated");
        exit;
      } catch (Throwable $e) {
        $errors[] = "Lỗi cập nhật: " . $e->getMessage();
      }
    }
  }

  // B. Xóa tour khỏi CTKM
  if ($action === 'remove_tour') {
    $maTour = (int)($_POST['MaTour'] ?? 0);
    if ($maTour <= 0) $errors[] = "Tour không hợp lệ.";

    if (empty($errors)) {
      try {
        $stmt = $conn->prepare("DELETE FROM tour_khuyenmai WHERE MaTour=? AND MaCTKM=? LIMIT 1");
        $stmt->bind_param("ii", $maTour, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: khuyenmai_chitiet.php?id=" . $id . "&msg=removed");
        exit;
      } catch (Throwable $e) {
        $errors[] = "Lỗi xóa: " . $e->getMessage();
      }
    }
  }
}

// ===== 3. LOAD DATA HIỂN THỊ =====

// Danh sách tour ĐÃ áp dụng
$sqlApplied = "
  SELECT
    t.MaTour, t.TenTour, t.DiaDiem, t.GiaGoc, t.GiaGiam, t.TrangThai,
    tk.PhanTramGiamKM
  FROM tour_khuyenmai tk
  JOIN tour t ON t.MaTour = tk.MaTour
  WHERE tk.MaCTKM=?
  ORDER BY t.MaTour DESC
";
$stmt = $conn->prepare($sqlApplied);
$stmt->bind_param("i", $id);
$stmt->execute();
$rs = $stmt->get_result();
$applied = [];
while ($r = $rs->fetch_assoc()) $applied[] = $r;
$stmt->close();

// UI Helper
$st = (string)($km['TrangThai'] ?? '');
$badge = 'badge-soft-secondary';
if ($st === 'Hoạt động' || $st === 'Hiển thị') $badge = 'badge-soft-success';
else if ($st === 'Hết hạn') $badge = 'badge-soft-warning';
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Chi tiết khuyến mãi #<?= (int)$id ?> | VietJourney</title>
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
      height: auto;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      margin-bottom: 15px;
    }

    .cell-truncate {
      max-width: 350px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
    }

    .info-label {
      font-size: 0.85rem;
      color: #6c757d;
      font-weight: 600;
      text-transform: uppercase;
      margin-bottom: 4px;
    }

    .info-value {
      font-size: 1rem;
      color: #212529;
      font-weight: 500;
      margin-bottom: 16px;
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
          <h1 class="page-title">Chi tiết chương trình #<?= (int)$id ?></h1>
          <div class="text-muted mt-1">Quản lý thông tin và danh sách tour áp dụng</div>
        </div>

        <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary" href="khuyenmai.php">
            <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
          </a>
          <a class="btn btn-primary" href="khuyenmai_sua.php?id=<?= (int)$id ?>">
            <i class="fa-solid fa-pen me-1"></i> Sửa CTKM
          </a>
          <a class="btn btn-success" href="khuyenmai_them.php">
            <i class="fa-solid fa-plus me-1"></i> Thêm Khuyến mãi
          </a>
        </div>
      </div>

      <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
          <i class="fa-solid fa-circle-check me-2"></i>
          <?php
          $msg = $_GET['msg'];
          if ($msg == 'added')        echo "Đã thêm tour vào chương trình thành công!";
          elseif ($msg == 'updated')  echo "Đã cập nhật % giảm thành công!";
          elseif ($msg == 'removed')  echo "Đã gỡ tour khỏi chương trình!";
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
          <div class="fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i> Có lỗi xảy ra:</div>
          <ul class="mb-0 ps-3 mt-1"><?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-4">
          <div class="cardx h-100">
            <div class="fw-bold mb-3 text-uppercase small text-secondary"><i class="fa-solid fa-circle-info me-2"></i>Thông tin chung</div>

            <?php if (!empty($km['AnhDaiDien'])): ?>
              <img class="thumb" src="<?= h(asset_url($km['AnhDaiDien'])) ?>" alt="" onerror="this.src='../assets/img/no-image.jpg'">
            <?php endif; ?>

            <div class="info-label">Tên chương trình</div>
            <div class="info-value text-primary"><?= h($km['TenKM'] ?? '') ?></div>

            <div class="row">
              <div class="col-6">
                <div class="info-label">Giảm mặc định</div>
                <div class="info-value"><span class="badge bg-danger rounded-pill"><?= (int)($km['PhanTramGiam'] ?? 0) ?>%</span></div>
              </div>
              <div class="col-6">
                <div class="info-label">Trạng thái</div>
                <div class="info-value"><span class="badge-soft <?= $badge ?>"><?= h($st ?: '—') ?></span></div>
              </div>
            </div>

            <div class="info-label">Thời gian áp dụng</div>
            <div class="info-value">
              <i class="fa-regular fa-calendar me-1 text-muted"></i> <?= h(fmtDate($km['NgayBatDau'] ?? '')) ?> <br>
              <i class="fa-solid fa-arrow-down me-1 text-muted ms-1"></i> <br>
              <i class="fa-regular fa-calendar-check me-1 text-muted"></i> <?= h(fmtDate($km['NgayKetThuc'] ?? '')) ?>
            </div>

            <div class="info-label">Nội dung / Mô tả</div>
            <div class="info-value text-muted small" style="white-space: pre-line;"><?= h($km['NoiDung'] ?? 'Không có mô tả') ?></div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="cardx h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="fw-bold text-uppercase small text-secondary"><i class="fa-solid fa-list-ul me-2"></i>Danh sách tour áp dụng (<?= count($applied) ?>)</div>

              <a href="khuyenmai_sua.php?id=<?= (int)$id ?>" class="btn btn-sm btn-light border text-primary fw-bold">
                <i class="fa-solid fa-plus me-1"></i> Gán thêm tour
              </a>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:70px;">Mã</th>
                    <th>Tên Tour</th>
                    <th style="width:130px;">% Giảm</th>
                    <th style="width:50px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($applied)): ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted py-5">
                        <i class="fa-regular fa-folder-open fa-2x mb-3 d-block"></i>
                        Chưa có tour nào áp dụng chương trình này.
                      </td>
                    </tr>
                    <?php else: foreach ($applied as $r): ?>
                      <tr>
                        <td class="fw-bold text-secondary">#<?= (int)$r['MaTour'] ?></td>
                        <td>
                          <div class="fw-bold cell-truncate text-dark" title="<?= h($r['TenTour'] ?? '') ?>"><?= h($r['TenTour'] ?? '') ?></div>
                          <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i><?= h($r['DiaDiem'] ?? '—') ?></div>
                        </td>
                        <td>
                          <form method="POST" class="d-flex gap-1">
                            <input type="hidden" name="action" value="update_pt">
                            <input type="hidden" name="MaTour" value="<?= (int)$r['MaTour'] ?>">
                            <input class="form-control form-control-sm text-center fw-bold text-primary" style="width:60px;" type="number" min="0" max="100"
                              name="PhanTramGiamKM" value="<?= (int)($r['PhanTramGiamKM'] ?? 0) ?>">
                            <button class="btn btn-sm btn-light border" type="submit" title="Lưu % riêng">
                              <i class="fa-solid fa-check text-success"></i>
                            </button>
                          </form>
                        </td>
                        <td class="text-end">
                          <form method="POST" onsubmit="return confirm('Bạn chắc chắn muốn gỡ tour này khỏi chương trình khuyến mãi?');">
                            <input type="hidden" name="action" value="remove_tour">
                            <input type="hidden" name="MaTour" value="<?= (int)$r['MaTour'] ?>">
                            <button class="btn btn-sm btn-light text-danger border-0" type="submit" title="Gỡ bỏ">
                              <i class="fa-solid fa-trash-can"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                  <?php endforeach;
                  endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>

    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>