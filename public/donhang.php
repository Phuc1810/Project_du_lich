<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) {
  header("Location: auth.php?tab=login&redirect=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

/**
 * ✅ AUTO CẬP NHẬT TRẠNG THÁI ĐƠN THEO NGÀY (chỉ cho đơn của user này)
 * - Nếu đã thanh toán và hôm nay trong [NgayKhoiHanh, NgayKetThuc] => Đang diễn ra
 * - Nếu đã thanh toán hoặc đang diễn ra và hôm nay > NgayKetThuc => Đã hoàn tất
 */
try {
  // 1) Đang diễn ra
  $sqlRun = "
    UPDATE dondattour d
    JOIN khachhang kh ON kh.MaKH = d.MaKH
    JOIN tour t ON t.MaTour = d.MaTour
    SET d.TrangThai = 'Đang diễn ra'
    WHERE kh.MaTK = ?
      AND d.TrangThai = 'Đã thanh toán'
      AND t.NgayKhoiHanh IS NOT NULL
      AND t.NgayKetThuc IS NOT NULL
      AND CURDATE() >= t.NgayKhoiHanh
      AND CURDATE() <= t.NgayKetThuc
  ";
  $stmt = $conn->prepare($sqlRun);
  $stmt->bind_param("i", $matk);
  $stmt->execute();
  $stmt->close();

  // 2) Đã hoàn tất
  $sqlDone = "
    UPDATE dondattour d
    JOIN khachhang kh ON kh.MaKH = d.MaKH
    JOIN tour t ON t.MaTour = d.MaTour
    SET d.TrangThai = 'Đã hoàn tất'
    WHERE kh.MaTK = ?
      AND d.TrangThai IN ('Đã thanh toán', 'Đang diễn ra')
      AND t.NgayKetThuc IS NOT NULL
      AND CURDATE() > t.NgayKetThuc
  ";
  $stmt = $conn->prepare($sqlDone);
  $stmt->bind_param("i", $matk);
  $stmt->execute();
  $stmt->close();

} catch (Throwable $e) {
  // nếu lỗi thì bỏ qua để trang vẫn chạy
}

// filter
$filter = trim($_GET['st'] ?? ''); // '', 'Chờ thanh toán', 'Đã thanh toán', 'Đang diễn ra', 'Đã hoàn tất'
$validFilters = ['', 'Chờ thanh toán', 'Đã thanh toán', 'Đang diễn ra', 'Đã hoàn tất'];
if (!in_array($filter, $validFilters, true)) $filter = '';

// paging
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

// đếm tổng
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM dondattour d
  JOIN khachhang kh ON kh.MaKH = d.MaKH
  WHERE kh.MaTK=?
" . ($filter !== '' ? " AND d.TrangThai=?" : "");

$stmt = $conn->prepare($sqlCount);
if ($filter !== '') {
  $stmt->bind_param("is", $matk, $filter);
} else {
  $stmt->bind_param("i", $matk);
}
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

// list đơn
$sql = "
  SELECT
    d.MaDon, d.NgayDat, d.TrangThai,
    d.SoLuongNguoiLon, d.SoLuongTreEm, d.SoLuongTreNho,
    d.TongTienPhaiTra,
    t.MaTour, t.TenTour, t.DiaDiem, t.NgayKhoiHanh, t.NgayKetThuc,
    h.DuongDan AS AnhChinh
  FROM dondattour d
  JOIN khachhang kh ON kh.MaKH = d.MaKH
  JOIN tour t ON t.MaTour = d.MaTour
  LEFT JOIN hinhanhtour h ON h.MaTour=t.MaTour AND h.LaAnhChinh=1
  WHERE kh.MaTK=?
" . ($filter !== '' ? " AND d.TrangThai=?" : "") . "
  ORDER BY d.MaDon DESC
  LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if ($filter !== '') {
  $stmt->bind_param("isii", $matk, $filter, $perPage, $offset);
} else {
  $stmt->bind_param("iii", $matk, $perPage, $offset);
}
$stmt->execute();
$rs = $stmt->get_result();
$orders = [];
while ($row = $rs->fetch_assoc()) $orders[] = $row;
$stmt->close();

// helper badge
function badgeClass($st){
  $st = mb_strtolower(trim((string)$st), 'UTF-8');
  if ($st === mb_strtolower('Đã thanh toán','UTF-8')) return 'text-bg-success';
  if ($st === mb_strtolower('Chờ thanh toán','UTF-8')) return 'text-bg-warning';
  if ($st === mb_strtolower('Đang diễn ra','UTF-8')) return 'text-bg-primary';
  if ($st === mb_strtolower('Đã hoàn tất','UTF-8')) return 'text-bg-secondary';
  return 'text-bg-light';
}

function fmtDate($d){
  if (!$d) return '—';
  $ts = strtotime($d);
  return $ts ? date('d/m/Y', $ts) : '—';
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đơn hàng của tôi</title>
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

    .order-item{
      border:1px solid rgba(15,23,42,.08);
      border-radius:16px;
      background:#fff;
      padding:14px;
      display:flex;
      gap:14px;
      align-items:flex-start;
      transition: transform .12s ease, box-shadow .12s ease;
    }
    .order-item:hover{
      transform: translateY(-3px);
      box-shadow:0 14px 30px rgba(16,24,40,.12);
    }
    .thumb{
      width:110px; height:82px;
      border-radius:12px;
      object-fit:cover;
      background:#e2e8f0;
      flex:0 0 auto;
    }
    .name{ font-weight:900; font-size:16px; margin-bottom:4px; line-height:1.25; }
    .meta{ font-size:13px; color:#64748b; font-weight:600; display:flex; flex-wrap:wrap; gap:10px; }
    .money{ font-weight:1000; font-size:16px; color:#e11d48; }
    .right{
      margin-left:auto;
      display:flex;
      flex-direction:column;
      align-items:flex-end;
      gap:8px;
      min-width:170px;
    }
    .btn-detail{
      border-radius:999px;
      font-weight:800;
      padding:8px 14px;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container wrap">
  <div class="cardx p-4 p-lg-5">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
      <div>
        <div class="title"><i class="fa-solid fa-receipt me-2"></i>Đơn hàng của tôi</div>
        <div class="muted mt-1">Danh sách các đơn đặt tour của bạn.</div>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="trangchu.php">
          <i class="fa-solid fa-house me-1"></i> Trang chủ
        </a>
      </div>
    </div>

    <div class="divider"></div>

    <!-- Filter -->
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-4">
        <label class="form-label fw-semibold mb-1">Lọc theo trạng thái</label>
        <select class="form-select" name="st">
          <option value="" <?= $filter===''?'selected':''; ?>>Tất cả</option>
          <option value="Chờ thanh toán" <?= $filter==='Chờ thanh toán'?'selected':''; ?>>Chờ thanh toán</option>
          <option value="Đã thanh toán" <?= $filter==='Đã thanh toán'?'selected':''; ?>>Đã thanh toán</option>
          <option value="Đang diễn ra" <?= $filter==='Đang diễn ra'?'selected':''; ?>>Đang diễn ra</option>
          <option value="Đã hoàn tất" <?= $filter==='Đã hoàn tất'?'selected':''; ?>>Đã hoàn tất</option>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit">
          <i class="fa-solid fa-filter me-1"></i> Lọc
        </button>
      </div>
    </form>

    <div class="divider"></div>

    <?php if (empty($orders)): ?>
      <div class="alert alert-info mb-0">
        Bạn chưa có đơn hàng nào<?= $filter!=='' ? ' với trạng thái đã chọn' : '' ?>.
      </div>
    <?php else: ?>

      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="muted small">Tổng: <b><?= (int)$total ?></b> đơn</div>
        <div class="muted small">Trang <?= (int)$page ?> / <?= (int)$totalPages ?></div>
      </div>

      <div class="d-grid gap-3">
        <?php foreach ($orders as $o): ?>
          <?php
            $qty = (int)$o['SoLuongNguoiLon'] + (int)$o['SoLuongTreEm'] + (int)$o['SoLuongTreNho'];
            $st = (string)$o['TrangThai'];
          ?>
          <div class="order-item">
            <?php if (!empty($o['AnhChinh'])): ?>
              <img class="thumb" src="assets/<?= h($o['AnhChinh']) ?>" alt="">
            <?php else: ?>
              <div class="thumb"></div>
            <?php endif; ?>

            <div class="flex-grow-1">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="name"><?= h($o['TenTour'] ?? '') ?></div>
                <span class="badge <?= badgeClass($st) ?>"><?= h($st) ?></span>
                <span class="badge text-bg-light">Đơn #<?= (int)$o['MaDon'] ?></span>
              </div>

              <div class="meta mt-1">
                <span><i class="fa-solid fa-location-dot me-1"></i><?= h($o['DiaDiem'] ?? '—') ?></span>
                <span><i class="fa-regular fa-calendar-days me-1"></i>Khởi hành: <?= fmtDate($o['NgayKhoiHanh'] ?? '') ?></span>
                <span><i class="fa-regular fa-calendar-check me-1"></i>Kết thúc: <?= fmtDate($o['NgayKetThuc'] ?? '') ?></span>
                <span><i class="fa-regular fa-clock me-1"></i>Ngày đặt: <?= fmtDate($o['NgayDat'] ?? '') ?></span>
                <span><i class="fa-solid fa-users me-1"></i>Số lượng: <?= (int)$qty ?></span>
              </div>
            </div>

            <div class="right">
              <div class="money"><?= number_format((float)$o['TongTienPhaiTra'], 0, ',', '.') ?> VNĐ</div>

              <?php if (mb_strtolower(trim($st),'UTF-8') === mb_strtolower('Chờ thanh toán','UTF-8')): ?>
                <a class="btn btn-warning btn-detail" href="thanhtoan.php?madon=<?= (int)$o['MaDon'] ?>">
                  <i class="fa-solid fa-qrcode me-1"></i> Thanh toán
                </a>
              <?php endif; ?>

              <a class="btn btn-outline-secondary btn-detail" href="chitietdon.php?madon=<?= (int)$o['MaDon'] ?>">
                <i class="fa-solid fa-eye me-1"></i> Xem chi tiết
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
          <ul class="pagination justify-content-center mb-0">
            <?php
              $mkUrl = function($p) use ($filter){
                $qs = ['page'=>$p];
                if ($filter !== '') $qs['st'] = $filter;
                return 'donhang.php?' . http_build_query($qs);
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
