<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){
  if (!$d) return '—';
  $ts = strtotime($d);
  return $ts ? date('d/m/Y', $ts) : '—';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) {
  header("Location: auth.php?tab=login&redirect=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

// filter trạng thái (nếu bạn muốn thêm trạng thái khác thì bổ sung vào đây)
$filter = trim($_GET['st'] ?? '');
$validFilters = ['', 'Chờ xử lý', 'Đang xử lý', 'Đã duyệt', 'Từ chối', 'Hoàn tất'];
if (!in_array($filter, $validFilters, true)) $filter = '';

// paging
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

// count
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM yeucaudoanhnghiep y
  JOIN KhachHang kh ON kh.MaKH = y.MaKH
  WHERE kh.MaTK=?
" . ($filter !== '' ? " AND y.TrangThai=?" : "");

$stmt = $conn->prepare($sqlCount);
if ($filter !== '') $stmt->bind_param("is", $matk, $filter);
else $stmt->bind_param("i", $matk);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

// list
$sql = "
  SELECT
    y.MaYC, y.TenCongTy, y.NguoiLienHe, y.SDT, y.SoNguoi,
    y.ThoiGianKhoiHanh, y.TrangThai,
    y.MaTour,
    t.TenTour, t.DiaDiem, t.ThoiLuong,
    h.DuongDan AS AnhChinh
  FROM yeucaudoanhnghiep y
  JOIN KhachHang kh ON kh.MaKH = y.MaKH
  LEFT JOIN Tour t ON t.MaTour = y.MaTour
  LEFT JOIN HinhAnhTour h ON h.MaTour = t.MaTour AND h.LaAnhChinh = 1
  WHERE kh.MaTK=?
" . ($filter !== '' ? " AND y.TrangThai=?" : "") . "
  ORDER BY y.MaYC DESC
  LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if ($filter !== '') $stmt->bind_param("isii", $matk, $filter, $perPage, $offset);
else $stmt->bind_param("iii", $matk, $perPage, $offset);
$stmt->execute();
$rs = $stmt->get_result();
$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$stmt->close();

function badgeClassYC($st){
  $st = mb_strtolower(trim((string)$st), 'UTF-8');
  if ($st === mb_strtolower('Chờ xử lý','UTF-8')) return 'text-bg-warning';
  if ($st === mb_strtolower('Đang xử lý','UTF-8')) return 'text-bg-primary';
  if ($st === mb_strtolower('Đã duyệt','UTF-8')) return 'text-bg-success';
  if ($st === mb_strtolower('Hoàn tất','UTF-8')) return 'text-bg-success';
  if ($st === mb_strtolower('Từ chối','UTF-8')) return 'text-bg-danger';
  return 'text-bg-secondary';
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Yêu cầu doanh nghiệp của tôi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">

  <style>
    body{ background:#f6f8fb; }
    .wrap{ padding-top:150px; padding-bottom:50px; }
    .cardx{ border:0; border-radius:20px; background:#fff; box-shadow:0 14px 40px rgba(16,24,40,.10); }
    .title{ font-size:26px; font-weight:1000; }
    .muted{ color:#64748b; }
    .divider{ height:1px; background:rgba(15,23,42,.08); margin:16px 0; }

    .item{
      border:1px solid rgba(15,23,42,.08);
      border-radius:16px;
      background:#fff;
      padding:14px;
      display:flex;
      gap:14px;
      align-items:flex-start;
      transition: transform .12s ease, box-shadow .12s ease;
    }
    .item:hover{ transform: translateY(-3px); box-shadow:0 14px 30px rgba(16,24,40,.12); }
    .thumb{
      width:110px; height:82px;
      border-radius:12px;
      object-fit:cover;
      background:#e2e8f0;
      flex:0 0 auto;
    }
    .name{ font-weight:900; font-size:16px; margin-bottom:4px; line-height:1.25; }
    .meta{ font-size:13px; color:#64748b; font-weight:600; display:flex; flex-wrap:wrap; gap:10px; }
    .right{
      margin-left:auto;
      display:flex;
      flex-direction:column;
      align-items:flex-end;
      gap:8px;
      min-width:170px;
    }
    .btn-detail{ border-radius:999px; font-weight:800; padding:8px 14px; }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container wrap">
  <div class="cardx p-4 p-lg-5">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
      <div>
        <div class="title"><i class="fa-solid fa-building me-2"></i>Yêu cầu doanh nghiệp của tôi</div>
        <div class="muted mt-1">Danh sách các yêu cầu đặt tour doanh nghiệp bạn đã gửi.</div>
      </div>
      <a class="btn btn-outline-secondary" href="trangchu.php">
        <i class="fa-solid fa-house me-1"></i> Trang chủ
      </a>
    </div>

    <div class="divider"></div>

    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-4">
        <label class="form-label fw-semibold mb-1">Lọc theo trạng thái</label>
        <select class="form-select" name="st">
          <option value="" <?= $filter===''?'selected':''; ?>>Tất cả</option>
          <?php foreach (['Chờ xử lý','Đang xử lý','Đã duyệt','Từ chối','Hoàn tất'] as $st): ?>
            <option value="<?= h($st) ?>" <?= $filter===$st?'selected':''; ?>><?= h($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit">
          <i class="fa-solid fa-filter me-1"></i> Lọc
        </button>
      </div>
    </form>

    <div class="divider"></div>

    <?php if (empty($rows)): ?>
      <div class="alert alert-info mb-0">
        Bạn chưa có yêu cầu nào<?= $filter!=='' ? ' với trạng thái đã chọn' : '' ?>.
      </div>
    <?php else: ?>

      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="muted small">Tổng: <b><?= (int)$total ?></b> yêu cầu</div>
        <div class="muted small">Trang <?= (int)$page ?> / <?= (int)$totalPages ?></div>
      </div>

      <div class="d-grid gap-3">
        <?php foreach ($rows as $y): ?>
          <div class="item">
            <?php if (!empty($y['AnhChinh'])): ?>
              <img class="thumb" src="assets/<?= h($y['AnhChinh']) ?>" alt="">
            <?php else: ?>
              <div class="thumb"></div>
            <?php endif; ?>

            <div class="flex-grow-1">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="name"><?= h($y['TenTour'] ?? 'Tour doanh nghiệp') ?></div>
                <span class="badge <?= badgeClassYC($y['TrangThai'] ?? '') ?>"><?= h($y['TrangThai'] ?? '—') ?></span>
                <span class="badge text-bg-light">YC #<?= (int)$y['MaYC'] ?></span>
              </div>

              <div class="meta mt-1">
                <span><i class="fa-solid fa-building me-1"></i><?= h($y['TenCongTy'] ?? '—') ?></span>
                <span><i class="fa-solid fa-user me-1"></i><?= h($y['NguoiLienHe'] ?? '—') ?></span>
                <span><i class="fa-solid fa-phone me-1"></i><?= h($y['SDT'] ?? '—') ?></span>
                <span><i class="fa-solid fa-users me-1"></i>Số người: <?= (int)($y['SoNguoi'] ?? 0) ?></span>
                <span><i class="fa-regular fa-calendar-days me-1"></i>Khởi hành: <?= fmtDate($y['ThoiGianKhoiHanh'] ?? '') ?></span>
                <?php if (!empty($y['DiaDiem'])): ?>
                  <span><i class="fa-solid fa-location-dot me-1"></i><?= h($y['DiaDiem']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="right">
              <a class="btn btn-outline-secondary btn-detail" href="chitietyeucau.php?id=<?= (int)$y['MaYC'] ?>">
                <i class="fa-solid fa-eye me-1"></i> Xem chi tiết
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
          <ul class="pagination justify-content-center mb-0">
            <?php
              $mkUrl = function($p) use ($filter){
                $qs = ['page'=>$p];
                if ($filter !== '') $qs['st'] = $filter;
                return 'donyeucau.php?' . http_build_query($qs);
              };
            ?>
            <li class="page-item <?= $page<=1?'disabled':''; ?>">
              <a class="page-link" href="<?= h($mkUrl(max(1,$page-1))) ?>">«</a>
            </li>

            <?php
              $start = max(1, $page - 2);
              $end = min($totalPages, $page + 2);
              for ($p=$start; $p<=$end; $p++):
            ?>
              <li class="page-item <?= $p===$page?'active':''; ?>">
                <a class="page-link" href="<?= h($mkUrl($p)) ?>"><?= (int)$p ?></a>
              </li>
            <?php endfor; ?>

            <li class="page-item <?= $page>=$totalPages?'disabled':''; ?>">
              <a class="page-link" href="<?= h($mkUrl(min($totalPages,$page+1))) ?>">»</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
