<?php
// public/nhanvien/tintuc.php
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

$q = trim($_GET['q'] ?? '');
$loai = trim($_GET['loai'] ?? '');
$tt = trim($_GET['tt'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$loaiOpts = ['', 'tintuc', 'kinhnghiem'];
$ttOpts = ['', 'Hiển thị', 'Ẩn'];
if (!in_array($loai, $loaiOpts, true)) $loai = '';
if (!in_array($tt, $ttOpts, true)) $tt = '';

$errors = [];

// WHERE logic
$where = "1=1";
$params = [];
$types = "";

if ($loai !== '') {
  $where .= " AND t.LoaiTin=?";
  $types .= "s";
  $params[] = $loai;
}
if ($tt !== '') {
  $where .= " AND t.TrangThai=?";
  $types .= "s";
  $params[] = $tt;
}
if ($q !== '') {
  $where .= " AND (t.MaTin=? OR t.TieuDe LIKE ?)";
  $types .= "is";
  $params[] = (int)$q;
  $params[] = "%" . $q . "%";
}

// Count total
$sqlCount = "SELECT COUNT(*) c FROM tintuc t WHERE $where";
$stmt = $conn->prepare($sqlCount);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

// List items
$sql = "
  SELECT
    t.MaTin, t.TieuDe, t.MoTa, t.LoaiTin, t.AnhDaiDien, t.NgayDang, t.TrangThai,
    t.MaNV, nv.HoTen AS NV_HoTen
  FROM tintuc t
  LEFT JOIN nhanvien nv ON nv.MaNV = t.MaNV
  WHERE $where
  ORDER BY t.MaTin DESC
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

function fmtDate($d)
{
  if (!$d) return '—';
  $ts = strtotime($d);
  return $ts ? date('d/m/Y', $ts) : '—';
}
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Quản lý Tin tức | VietJourney</title>
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
      max-width: 300px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
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
      <header class="page-header">
        <div>
          <h1 class="page-title">Quản lý Tin tức</h1>
          <div class="current-date">Tổng: <b><?= number_format($total) ?></b> bài viết</div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-primary" href="tintuc_them.php">
            <i class="fa-solid fa-plus me-1"></i> Thêm bài viết
          </a>
        </div>
      </header>

      <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
          <i class="fa-solid fa-circle-check me-2"></i>
          <?php
          $msg = $_GET['msg'];
          if ($msg == 'added')        echo "Thêm bài viết mới thành công!";
          elseif ($msg == 'updated')  echo "Cập nhật bài viết thành công!";
          elseif ($msg == 'deleted')  echo "Đã xóa bài viết thành công!";

          // Thêm 2 dòng này:
          elseif ($msg == 'shown')    echo "Đã công khai (hiển thị) bài viết!";
          elseif ($msg == 'hidden')   echo "Đã ẩn bài viết thành công!";
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="toolbar-card">
        <form method="GET" class="search-form" action="tintuc.php">
          <div class="search-group" style="flex:2;">
            <i class="fa-solid fa-magnifying-glass text-secondary"></i>
            <input class="search-input" name="q" value="<?= h($q) ?>" placeholder="Nhập mã tin hoặc tiêu đề...">
          </div>

          <div class="search-group">
            <i class="fa-solid fa-layer-group text-secondary"></i>
            <select class="search-select" name="loai">
              <option value="" <?= $loai === '' ? 'selected' : ''; ?>>Tất cả loại</option>
              <option value="tintuc" <?= $loai === 'tintuc' ? 'selected' : ''; ?>>Tin tức</option>
              <option value="kinhnghiem" <?= $loai === 'kinhnghiem' ? 'selected' : ''; ?>>Kinh nghiệm</option>
            </select>
          </div>

          <div class="search-group">
            <i class="fa-solid fa-toggle-on text-secondary"></i>
            <select class="search-select" name="tt">
              <option value="" <?= $tt === '' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
              <option value="Hiển thị" <?= $tt === 'Hiển thị' ? 'selected' : ''; ?>>Hiển thị</option>
              <option value="Ẩn" <?= $tt === 'Ẩn' ? 'selected' : ''; ?>>Ẩn</option>
            </select>
          </div>

          <button class="search-btn" type="submit">Lọc</button>
          <a class="reset-btn" href="tintuc.php"><i class="fa-solid fa-rotate-left"></i></a>
        </form>
      </div>

      <div class="table-card">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width:90px;">Ảnh</th>
                <th>Tiêu đề / Mô tả</th>
                <th style="width:120px;">Loại</th>
                <th style="width:110px;">Ngày đăng</th>
                <th style="width:110px;">Trạng thái</th>
                <th style="width:140px;">Người đăng</th>
                <th style="width:150px;" class="text-end">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-5">
                    <i class="fa-regular fa-folder-open fa-2x mb-3 d-block"></i>Không tìm thấy bài viết nào.
                  </td>
                </tr>
                <?php else: foreach ($rows as $r): ?>
                  <?php
                  $isShow = (($r['TrangThai'] ?? '') === 'Hiển thị');
                  $lo = (string)($r['LoaiTin'] ?? '');

                  // Badge màu cho Loại tin
                  $badgeLoai = ($lo === 'kinhnghiem') ? 'badge-soft-warning' : 'badge-soft-info';
                  $textLoai  = ($lo === 'kinhnghiem') ? 'Kinh nghiệm' : 'Tin tức';
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
                      <div class="fw-bold cell-truncate text-primary" title="<?= h($r['TieuDe'] ?? '') ?>">
                        <?= h($r['TieuDe'] ?? '') ?>
                      </div>
                      <div class="text-muted small cell-truncate mt-1"><?= h($r['MoTa'] ?? '') ?></div>
                      <div class="text-muted x-small">Mã tin: #<?= (int)$r['MaTin'] ?></div>
                    </td>
                    <td>
                      <span class="badge-soft <?= $badgeLoai ?>">
                        <?= $textLoai ?>
                      </span>
                    </td>
                    <td><?= h(fmtDate($r['NgayDang'] ?? '')) ?></td>
                    <td>
                      <?php if ($isShow): ?>
                        <span class="badge-soft badge-soft-success">Hiển thị</span>
                      <?php else: ?>
                        <span class="badge-soft badge-soft-secondary">Ẩn</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="fw-semibold small"><?= h($r['NV_HoTen'] ?? '—') ?></div>
                      <div class="text-muted x-small">ID: <?= !empty($r['MaNV']) ? (int)$r['MaNV'] : '—' ?></div>
                    </td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary rounded-pill me-1"
                        href="tintuc_sua.php?id=<?= (int)$r['MaTin'] ?>" title="Sửa">
                        <i class="fa-solid fa-pen"></i>
                      </a>

                      <a href="tintuc_toggle.php?id=<?= (int)$r['MaTin'] ?>"
                        class="btn btn-sm <?= $isShow ? 'btn-outline-secondary' : 'btn-outline-success' ?> rounded-pill"
                        onclick="return confirm('Bạn có chắc muốn <?= $isShow ? 'ẨN' : 'HIỆN' ?> bài viết này?');"
                        title="<?= $isShow ? 'Ẩn bài' : 'Hiện bài' ?>">
                        <?= $isShow ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>' ?>
                      </a>
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
              $mk = function ($p) use ($q, $loai, $tt) {
                $qs = ['page' => $p];
                if ($q !== '') $qs['q'] = $q;
                if ($loai !== '') $qs['loai'] = $loai;
                if ($tt !== '') $qs['tt'] = $tt;
                return 'tintuc.php?' . http_build_query($qs);
              };
              $prev = max(1, $page - 1);
              $next = min($totalPages, $page + 1);
              ?>
              <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?= h($mk($prev)) ?>"><i class="fa-solid fa-chevron-left"></i></a>
              </li>
              <?php
              $start = max(1, $page - 2);
              $end = min($totalPages, $page + 2);
              for ($i = $start; $i <= $end; $i++):
              ?>
                <li class="page-item <?= $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?= h($mk($i)) ?>"><?= $i ?></a></li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?= h($mk($next)) ?>"><i class="fa-solid fa-chevron-right"></i></a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>

      </div>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>