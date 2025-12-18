<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$madon = isset($_GET['madon']) ? (int)$_GET['madon'] : 0;
if ($madon <= 0) { header("Location: trangchu.php"); exit; }

$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) { header("Location: trangchu.php"); exit; }

$sql = "
  SELECT d.*, t.TenTour, t.DiaDiem, t.NgayKhoiHanh, t.SoCho, t.SoChoDaDat, t.TrangThai AS TrangThaiTour
  FROM dondattour d
  JOIN khachhang kh ON kh.MaKH=d.MaKH
  JOIN tour t ON t.MaTour=d.MaTour
  WHERE d.MaDon=? AND kh.MaTK=?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $madon, $matk);
$stmt->execute();
$don = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$don) { header("Location: trangchu.php"); exit; }

$tourFull = ((int)$don['SoChoDaDat'] >= (int)$don['SoCho']);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Đơn #<?= (int)$madon ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">
  <style>
    body{background:#f6f8fb}
    .wrap{padding-top:120px;padding-bottom:40px}
    .cardx{border:0;border-radius:18px;box-shadow:0 12px 34px rgba(16,24,40,.10);background:#fff}
    .title{font-size:22px;font-weight:900}
    .muted{color:#64748b}
    .line{display:flex;justify-content:space-between;gap:10px}
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container wrap">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="cardx p-4">
        <div class="d-flex justify-content-between align-items-center">
          <div class="title">Thông tin đơn hàng</div>
          <div class="fw-bold">#<?= (int)$don['MaDon'] ?></div>
        </div>

        <?php if ($tourFull): ?>
          <div class="alert alert-warning mt-3 mb-0">
            Tour hiện <strong>đã hết chỗ</strong> (<?= (int)$don['SoChoDaDat'] ?>/<?= (int)$don['SoCho'] ?>).
          </div>
        <?php endif; ?>

        <hr>

        <div class="fw-bold"><?= h($don['TenTour']) ?></div>
        <div class="muted">
          <?= h($don['DiaDiem']) ?> •
          <?= !empty($don['NgayKhoiHanh']) ? date('d/m/Y', strtotime($don['NgayKhoiHanh'])) : 'Đang cập nhật' ?>
        </div>

        <hr>

        <div class="line"><span class="muted">Người lớn</span><strong><?= (int)$don['SoLuongNguoiLon'] ?></strong></div>
        <div class="line"><span class="muted">Trẻ em</span><strong><?= (int)$don['SoLuongTreEm'] ?></strong></div>
        <div class="line"><span class="muted">Trẻ nhỏ</span><strong><?= (int)$don['SoLuongTreNho'] ?></strong></div>

        <hr>

        <div class="line"><span class="muted">Tổng tiền gốc</span><strong><?= number_format((float)$don['TongTienGoc'], 0, ',', '.') ?> VNĐ</strong></div>
        <div class="line"><span class="muted">Tổng phải trả</span><strong><?= number_format((float)$don['TongTienPhaiTra'], 0, ',', '.') ?> VNĐ</strong></div>
        <div class="mt-2"><span class="muted">Trạng thái đơn: </span><strong><?= h($don['TrangThai']) ?></strong></div>

        <div class="mt-4 d-flex gap-2">
          <a class="btn btn-outline-secondary" href="trangchu.php">Về trang chủ</a>
          <a class="btn btn-primary" href="thanhtoan.php?madon=<?= (int)$don['MaDon'] ?>">Mở trang thanh toán</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
