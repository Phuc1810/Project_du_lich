<?php
// public/nhanvien/khuyenmai.php
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

// =================================================================================
// 1. TỰ ĐỘNG CẬP NHẬT TRẠNG THÁI (Đồng bộ logic với bên Khách hàng)
// =================================================================================
try {
  // A. Hết hạn: Ngày kết thúc < Hôm nay
  $conn->query("UPDATE chuongtrinhkhuyenmai SET TrangThai = 'Hết hạn' WHERE NgayKetThuc < CURDATE()");

  // B. Sắp diễn ra: Ngày bắt đầu > Hôm nay
  $conn->query("UPDATE chuongtrinhkhuyenmai SET TrangThai = 'Sắp diễn ra' WHERE NgayBatDau > CURDATE()");

  // C. Hoạt động: Trong khoảng thời gian (Từ ngày bắt đầu -> Đến ngày kết thúc)
  $conn->query("UPDATE chuongtrinhkhuyenmai SET TrangThai = 'Hoạt động' WHERE NgayBatDau <= CURDATE() AND NgayKetThuc >= CURDATE()");
} catch (Exception $e) {
  // Bỏ qua lỗi update để không chặn trang web
}
// =================================================================================

$q  = trim($_GET['q'] ?? '');
$tt = trim($_GET['tt'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = "1=1";
$params = [];
$types = "";

if ($tt !== '') {
  $where .= " AND c.TrangThai=?";
  $types .= "s";
  $params[] = $tt;
}
if ($q !== '') {
  $where .= " AND (c.MaCTKM=? OR c.TenKM LIKE ?)";
  $types .= "is";
  $params[] = (int)$q;
  $params[] = "%" . $q . "%";
}

// Count
$sqlCount = "SELECT COUNT(*) c FROM chuongtrinhkhuyenmai c WHERE $where";
$stmt = $conn->prepare($sqlCount);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$totalPages = max(1, (int)ceil($total / $perPage));

// List (Ưu tiên Hoạt động lên đầu)
$sql = "
  SELECT
    c.MaCTKM, c.TenKM, c.NoiDung, c.AnhDaiDien, c.PhanTramGiam,
    c.NgayBatDau, c.NgayKetThuc, c.TrangThai
  FROM chuongtrinhkhuyenmai c
  WHERE $where
  ORDER BY FIELD(c.TrangThai, 'Hoạt động', 'Sắp diễn ra', 'Hết hạn'), c.NgayBatDau DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
if ($types !== "") {
  $types2 = $types . "ii";
  $params2 = array_merge($params, [$perPage, $offset]);
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param("ii", $perPage, $offset);
}
$stmt->execute();
$rs = $stmt->get_result();
$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$stmt->close();

$ttOpts = ['Hoạt động', 'Sắp diễn ra', 'Hết hạn'];
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Quản lý Khuyến mãi | VietJourney</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/nhanvien.css">
  <style>
    .thumb {
      width: 70px;
      height: 52px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
    }

    .cell-truncate {
      max-width: 380px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
    }

    .btn-detail-custom {
      background-color: #fff;
      color: #0d6efd;
      border: 1px solid #0d6efd;
      font-weight: 600;
      font-size: 13px;
      padding: 5px 15px;
      transition: all 0.2s ease-in-out;
    }

    .btn-detail-custom:hover {
      background-color: #0d6efd;
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2);
    }
  </style>
</head>

<body>
  <div class="dashboard-wrapper">
    <aside class="sidebar">
      <div class="brand-section">
        <a href="#" class="brand-logo"><i class="fa-solid fa-compass"></i> VietJourney</a>
      </div>
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
      <header class="page-header">
        <div>
          <h1 class="page-title">Quản lý Khuyến mãi</h1>
          <div class="current-date">Tổng: <b><?= number_format($total) ?></b> chương trình</div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-primary" href="khuyenmai_them.php">
            <i class="fa-solid fa-plus me-1"></i> Thêm khuyến mãi
          </a>
        </div>
      </header>

      <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
          <i class="fa-solid fa-circle-check me-2"></i>
          <?php
          $msg = $_GET['msg'];
          if ($msg == 'added')        echo "Thêm chương trình khuyến mãi thành công!";
          elseif ($msg == 'updated')  echo "Cập nhật khuyến mãi thành công!";
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div class="toolbar-card">
        <form method="GET" class="search-form" action="khuyenmai.php">
          <div class="search-group" style="flex:2;">
            <i class="fa-solid fa-magnifying-glass text-secondary"></i>
            <input class="search-input" name="q" value="<?= h($q) ?>" placeholder="Nhập mã CTKM hoặc tên...">
          </div>

          <div class="search-group">
            <i class="fa-solid fa-toggle-on text-secondary"></i>
            <select class="search-select" name="tt">
              <option value="" <?= $tt === '' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
              <?php foreach ($ttOpts as $op): ?>
                <option value="<?= h($op) ?>" <?= $tt === $op ? 'selected' : ''; ?>><?= h($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button class="search-btn" type="submit">Lọc</button>
          <a class="reset-btn" href="khuyenmai.php"><i class="fa-solid fa-rotate-left"></i></a>
        </form>
      </div>

      <div class="table-card">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width:90px;">Ảnh</th>
                <th>Chương trình</th>
                <th style="width:100px;">% giảm</th>
                <th style="width:200px;">Thời gian</th>
                <th style="width:130px;">Trạng thái</th>
                <th style="width:150px;" class="text-end">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-5">
                    <i class="fa-regular fa-folder-open fa-2x mb-3 d-block"></i>Không có dữ liệu.
                  </td>
                </tr>
                <?php else: foreach ($rows as $r): ?>
                  <?php
                  $st = (string)($r['TrangThai'] ?? '');

                  // Màu sắc badge trạng thái
                  $badge = 'badge-soft-secondary'; // Mặc định (Hết hạn)
                  if ($st === 'Hoạt động') $badge = 'badge-soft-success';
                  else if ($st === 'Sắp diễn ra') $badge = 'badge-soft-warning'; // Màu cam
                  ?>
                  <tr>
                    <td>
                      <?php if (!empty($r['AnhDaiDien'])): ?>
                        <img class="thumb" src="<?= h(asset_url($r['AnhDaiDien'])) ?>" alt="" onerror="this.src='../assets/img/no-image.jpg'">
                      <?php else: ?>
                        <div class="thumb d-flex align-items-center justify-content-center bg-light text-muted"><i class="fa-regular fa-image"></i></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="fw-bold cell-truncate text-primary" title="<?= h($r['TenKM'] ?? '') ?>"><?= h($r['TenKM'] ?? '') ?></div>
                      <div class="text-muted small cell-truncate mt-1"><?= h($r['NoiDung'] ?? '') ?></div>
                      <div class="text-muted x-small">Mã: #<?= (int)$r['MaCTKM'] ?></div>
                    </td>
                    <td class="fw-bold text-secondary">
                      <?php
                      $pt = (int)($r['PhanTramGiam'] ?? 0);
                      echo ($pt > 0) ? $pt . '%' : '—';
                      ?>
                    </td>
                    <td>
                      <div class="small"><b><?= h(fmtDate($r['NgayBatDau'] ?? '')) ?></b> → <b><?= h(fmtDate($r['NgayKetThuc'] ?? '')) ?></b></div>
                    </td>
                    <td>
                      <span class="badge-soft <?= $badge ?>"><?= h($st ?: '—') ?></span>
                    </td>

                    <td class="text-end">
                      <div class="d-flex align-items-center justify-content-end gap-2">
                        <a class="btn btn-detail-custom rounded-pill text-decoration-none"
                          href="khuyenmai_chitiet.php?id=<?= (int)$r['MaCTKM'] ?>"
                          title="Xem chi tiết">
                          Chi tiết
                        </a>

                        <a class="btn btn-sm btn-outline-primary border-0 bg-light rounded-circle"
                          href="khuyenmai_sua.php?id=<?= (int)$r['MaCTKM'] ?>"
                          style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"
                          title="Sửa">
                          <i class="fa-solid fa-pen"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="mt-4">
            <ul class="pagination justify-content-center">
              <?php
              $mk = function ($p) use ($q, $tt) {
                $qs = ['page' => $p];
                if ($q !== '') $qs['q'] = $q;
                if ($tt !== '') $qs['tt'] = $tt;
                return 'khuyenmai.php?' . http_build_query($qs);
              };
              $prev = max(1, $page - 1);
              $next = min($totalPages, $page + 1);
              ?>
              <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="<?= h($mk($prev)) ?>"><i class="fa-solid fa-chevron-left"></i></a></li>
              <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?= h($mk($i)) ?>"><?= $i ?></a></li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>"><a class="page-link" href="<?= h($mk($next)) ?>"><i class="fa-solid fa-chevron-right"></i></a></li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>