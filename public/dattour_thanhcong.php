<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$madon = isset($_GET['madon']) ? (int)$_GET['madon'] : 0;
if ($madon <= 0) { header("Location: trangchu.php"); exit; }

$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) { header("Location: auth.php?tab=login"); exit; }

$sql = "
  SELECT d.MaDon, d.NgayDat, d.TrangThai, d.TongTienPhaiTra,
         d.SoLuongNguoiLon, d.SoLuongTreEm, d.SoLuongTreNho,
         d.MaTour,
         t.TenTour, t.DiaDiem, t.NgayKhoiHanh, t.SoCho, t.SoChoDaDat,
         h.DuongDan AS AnhChinh
  FROM dondattour d
  JOIN khachhang kh ON kh.MaKH = d.MaKH
  JOIN tour t ON t.MaTour = d.MaTour
  LEFT JOIN hinhanhtour h ON h.MaTour=t.MaTour AND h.LaAnhChinh=1
  WHERE d.MaDon=? AND kh.MaTK=?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $madon, $matk);
$stmt->execute();
$don = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$don) { header("Location: trangchu.php"); exit; }

$st = trim((string)$don['TrangThai']);
$stLower = mb_strtolower($st,'UTF-8');

$isPaid = ($stLower === mb_strtolower('Đã thanh toán','UTF-8'));
$isSoldout = ($stLower === mb_strtolower('Hết chỗ','UTF-8'));

// Nếu chưa xử lý xong -> quay lại trang thanh toán
if (!$isPaid && !$isSoldout) {
  header("Location: thanhtoan.php?madon=".$madon);
  exit;
}

$tourFullNow = ((int)$don['SoChoDaDat'] >= (int)$don['SoCho']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Kết quả thanh toán - Đơn #<?= (int)$madon ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">
  <style>
    body{ background:#f6f8fb; }

    /* cách header + dễ nhìn hơn */
    .wrap{
      padding-top: 160px; /* tăng khoảng cách */
      padding-bottom: 60px;
    }

    .cardx{
      border:0; border-radius:22px; background:#fff;
      box-shadow:0 16px 46px rgba(16,24,40,.12);
      overflow:hidden;
    }

    .topGrad{
      padding:18px 18px 16px;
      background: linear-gradient(135deg, rgba(99,102,241,.12), rgba(14,165,233,.08), rgba(255,255,255,1));
      border-bottom:1px solid rgba(15,23,42,.06);
    }

    .tourThumb{
      width:108px; height:82px;
      object-fit:cover; border-radius:16px; background:#eee;
      box-shadow:0 10px 24px rgba(16,24,40,.12);
    }

    .tourTitle{ font-size:22px; font-weight:1100; line-height:1.15; }
    .muted{ color:#64748b; font-weight:600; font-size:14px; }

    .badge-soft{
      background:#eef2ff; color:#3730a3;
      font-weight:1000; border-radius:999px;
      padding:7px 12px; border:1px solid rgba(55,48,163,.12);
      font-size:12px;
    }

    .statusBlock{
      padding:22px;
      display:flex; gap:16px; align-items:flex-start;
    }
    .statusIcon{
      width:54px; height:54px; border-radius:16px;
      display:flex; align-items:center; justify-content:center;
      font-size:24px; flex:0 0 auto;
      box-shadow:0 10px 24px rgba(16,24,40,.10);
    }
    .statusTitle{ font-size:28px; font-weight:1200; margin:0; line-height:1.1; }
    .statusDesc{ margin-top:8px; color:#334155; font-weight:600; }

    .moneyBox{
      margin-top:14px;
      padding:14px 16px;
      border-radius:18px;
      background: rgba(15,23,42,.03);
      border:1px dashed rgba(15,23,42,.18);
    }
    .moneyLabel{ color:#475569; font-weight:800; font-size:13px; text-transform:uppercase; letter-spacing:.3px; }
    .moneyValue{
      font-size:44px; font-weight:1300; line-height:1;
      margin-top:6px;
    }
    .moneyVnd{ font-size:12px; font-weight:900; color:#334155; margin-top:6px; }

    .divider{ height:1px; background:rgba(15,23,42,.08); margin:0 22px; }
    .infoRow{ padding:18px 22px 10px; display:flex; gap:14px; flex-wrap:wrap; }
    .chip{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px; border-radius:14px;
      border:1px solid rgba(15,23,42,.08);
      background:#fff;
      font-weight:800; color:#0f172a;
      box-shadow:0 8px 22px rgba(16,24,40,.06);
      font-size:13px;
    }
    .actions{ padding:18px 22px 22px; display:flex; gap:10px; flex-wrap:wrap; }

    /* theme theo trạng thái */
    .ok .statusIcon{ background: rgba(34,197,94,.16); color:#15803d; }
    .ok .statusTitle{ color:#14532d; }
    .ok .moneyValue{ color:#0f766e; }

    .bad .statusIcon{ background: rgba(239,68,68,.14); color:#b91c1c; }
    .bad .statusTitle{ color:#7f1d1d; }
    .bad .moneyValue{ color:#b91c1c; }

    .subAlert{
      margin: 0 22px 18px;
      border-radius:18px;
      border:1px solid rgba(245,158,11,.22);
      background:#fff7ed;
      color:#9a3412;
      padding:14px 16px;
      font-weight:700;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container wrap">
  <div class="cardx <?= $isPaid ? 'ok' : 'bad' ?>">

    <!-- Header tour -->
    <div class="topGrad">
      <div class="d-flex gap-3 align-items-center">
        <?php if (!empty($don['AnhChinh'])): ?>
          <img class="tourThumb" src="assets/<?= h($don['AnhChinh']) ?>" alt="">
        <?php else: ?>
          <div class="tourThumb"></div>
        <?php endif; ?>

        <div class="flex-grow-1">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="tourTitle"><?= h($don['TenTour']) ?></div>
            <span class="badge-soft">Đơn #<?= (int)$madon ?></span>
          </div>
          <div class="muted mt-1">
            <i class="fa-solid fa-location-dot me-1"></i><?= h($don['DiaDiem']) ?>
            &nbsp; • &nbsp;
            <i class="fa-regular fa-calendar-days me-1"></i>
            <?= !empty($don['NgayKhoiHanh']) ? date('d/m/Y', strtotime($don['NgayKhoiHanh'])) : 'Đang cập nhật' ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Status block -->
    <div class="statusBlock">
      <div class="statusIcon">
        <?php if ($isPaid): ?>
          <i class="fa-solid fa-circle-check"></i>
        <?php else: ?>
          <i class="fa-solid fa-triangle-exclamation"></i>
        <?php endif; ?>
      </div>

      <div class="flex-grow-1">
        <?php if ($isPaid): ?>
          <h1 class="statusTitle">Thanh toán thành công</h1>
          <div class="statusDesc">
            Hệ thống đã ghi nhận giao dịch và giữ chỗ cho bạn.
          </div>

          <div class="moneyBox">
            <div class="moneyLabel">Tổng tiền đã thanh toán</div>
            <div class="moneyValue"><?= number_format((float)$don['TongTienPhaiTra'], 0, ',', '.') ?></div>
            <div class="moneyVnd">VNĐ • Trạng thái: <strong><?= h($don['TrangThai']) ?></strong></div>
          </div>

        <?php else: ?>
          <h1 class="statusTitle">Tour đã hết chỗ</h1>
          <div class="statusDesc">
            Hệ thống đã nhận tiền nhưng tour không còn đủ chỗ để xác nhận đơn.
            Vui lòng liên hệ để được hỗ trợ xử lý.
          </div>

          <div class="moneyBox">
            <div class="moneyLabel">Số tiền của đơn</div>
            <div class="moneyValue"><?= number_format((float)$don['TongTienPhaiTra'], 0, ',', '.') ?></div>
            <div class="moneyVnd">VNĐ • Trạng thái: <strong><?= h($don['TrangThai']) ?></strong></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isPaid && $tourFullNow): ?>
      <div class="subAlert">
        <strong>Lưu ý:</strong> Tour hiện đã <strong>hết chỗ</strong>. Bạn vẫn đã giữ chỗ thành công theo đơn này.
      </div>
    <?php endif; ?>

    <div class="divider"></div>

    <!-- Quick info -->
    <div class="infoRow">
      <div class="chip"><i class="fa-solid fa-user-group"></i>
        Người lớn: <strong><?= (int)$don['SoLuongNguoiLon'] ?></strong>
      </div>
      <div class="chip"><i class="fa-solid fa-child"></i>
        Trẻ em: <strong><?= (int)$don['SoLuongTreEm'] ?></strong>
      </div>
      <div class="chip"><i class="fa-solid fa-baby"></i>
        Trẻ nhỏ: <strong><?= (int)$don['SoLuongTreNho'] ?></strong>
      </div>
    </div>

    <!-- Actions -->
    <div class="actions">
      <a class="btn btn-primary btn-lg" href="trangchu.php">
        <i class="fa-solid fa-house me-1"></i> Về trang chủ
      </a>

      <!-- đổi link nếu trang chi tiết đơn của bạn khác -->
      <a class="btn btn-outline-secondary btn-lg" href="chitietdon.php?madon=<?= (int)$madon ?>">
        <i class="fa-solid fa-receipt me-1"></i> Xem thông tin đơn hàng
      </a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
