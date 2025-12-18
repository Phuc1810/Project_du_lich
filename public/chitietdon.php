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

/**
 * Lấy thông tin đơn + tour + check đúng chủ đơn
 * Lưu ý: CTKM chỉ hiển thị nếu DonDatTour.MaCTKM có giá trị
 */
$sql = "
  SELECT
    d.MaDon, d.NgayDat, d.TrangThai,
    d.SoLuongNguoiLon, d.SoLuongTreEm, d.SoLuongTreNho,
    d.GiaNguoiLonApDung, d.GiaTreEmApDung,
    d.TongTienGoc, d.TongTienPhaiTra,
    d.MaTour, d.MaCTKM,

    t.TenTour, t.DiaDiem, t.NgayKhoiHanh, t.ThoiLuong,
    t.SoCho, t.SoChoDaDat,

    h.DuongDan AS AnhChinh,

    c.TenKM
  FROM dondattour d
  JOIN khachhang kh ON kh.MaKH = d.MaKH
  JOIN tour t ON t.MaTour = d.MaTour
  LEFT JOIN hinhanhtour h ON h.MaTour=t.MaTour AND h.LaAnhChinh=1
  LEFT JOIN chuongtrinhkhuyenmai c ON c.MaCTKM = d.MaCTKM
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
$stLower = mb_strtolower($st, 'UTF-8');

$isPaid    = ($stLower === mb_strtolower('Đã thanh toán','UTF-8'));
$isPending = ($stLower === mb_strtolower('Chờ thanh toán','UTF-8'));
$isSoldout = ($stLower === mb_strtolower('Hết chỗ','UTF-8'));

$soCho     = (int)($don['SoCho'] ?? 0);
$soChoDaDat= (int)($don['SoChoDaDat'] ?? 0);
$conLai    = max(0, $soCho - $soChoDaDat);
$tourFullNow = ($soCho > 0 && $soChoDaDat >= $soCho);

// Lấy lịch sử thanh toán theo đơn
$payments = [];
$stmt = $conn->prepare("
  SELECT MaTT, NgayTT, SoTien, PhuongThuc, TrangThaiTT
  FROM thanhtoan
  WHERE MaDon=?
  ORDER BY MaTT DESC
");
$stmt->bind_param("i", $madon);
$stmt->execute();
$rs = $stmt->get_result();
while ($r = $rs->fetch_assoc()) $payments[] = $r;
$stmt->close();

// Tính giảm (nếu có) dựa trên TongTienGoc và TongTienPhaiTra
$tongGoc = (float)($don['TongTienGoc'] ?? 0);
$tongTra = (float)($don['TongTienPhaiTra'] ?? 0);
$giamTien = max(0, $tongGoc - $tongTra);
$giamPt = ($tongGoc > 0 && $giamTien > 0) ? round($giamTien / $tongGoc * 100) : 0;

// Badge màu theo trạng thái
$badgeClass = 'bg-secondary';
$badgeText  = $st;
if ($isPaid)    { $badgeClass = 'bg-success';  $badgeText = 'Đã thanh toán'; }
if ($isPending) { $badgeClass = 'bg-warning text-dark'; $badgeText = 'Chờ thanh toán'; }
if ($isSoldout) { $badgeClass = 'bg-danger';  $badgeText = 'Hết chỗ'; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Chi tiết đơn #<?= (int)$madon ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">
  <style>
    body{ background:#f6f8fb; }
    .wrap{ padding-top:160px; padding-bottom:60px; }

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
      width:108px; height:82px; object-fit:cover; border-radius:16px; background:#eee;
      box-shadow:0 10px 24px rgba(16,24,40,.12);
    }
    .tourTitle{ font-size:22px; font-weight:1100; line-height:1.15; }
    .muted{ color:#64748b; font-weight:600; font-size:14px; }

    .chip{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px; border-radius:14px;
      border:1px solid rgba(15,23,42,.08);
      background:#fff;
      font-weight:800; color:#0f172a;
      box-shadow:0 8px 22px rgba(16,24,40,.06);
      font-size:13px;
    }
    .divider{ height:1px; background:rgba(15,23,42,.08); margin:0; }

    .box{
      border:1px solid rgba(15,23,42,.08);
      border-radius:18px;
      background:#fff;
      box-shadow:0 10px 26px rgba(16,24,40,.06);
    }
    .boxHead{
      padding:14px 16px;
      border-bottom:1px solid rgba(15,23,42,.08);
      font-weight:1000;
    }
    .boxBody{ padding:14px 16px; }

    .moneyLabel{ color:#475569; font-weight:800; font-size:13px; text-transform:uppercase; letter-spacing:.3px; }
    .moneyValue{ font-size:40px; font-weight:1300; line-height:1; margin-top:6px; }
    .moneyVnd{ font-size:12px; font-weight:900; color:#334155; margin-top:6px; }

    .tbl td, .tbl th{ vertical-align:middle; }
    .small2{ font-size:13px; color:#475569; font-weight:650; }
  </style>
</head>

<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container wrap">
  <div class="cardx">

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
            <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2" style="font-weight:1000;">
              <?= h($badgeText) ?>
            </span>
          </div>
          <div class="muted mt-1">
            <i class="fa-solid fa-receipt me-1"></i> Đơn #<?= (int)$madon ?>
            &nbsp; • &nbsp;
            <i class="fa-regular fa-calendar-days me-1"></i>
            <?= !empty($don['NgayKhoiHanh']) ? date('d/m/Y', strtotime($don['NgayKhoiHanh'])) : 'Đang cập nhật' ?>
            &nbsp; • &nbsp;
            <i class="fa-solid fa-location-dot me-1"></i><?= h($don['DiaDiem']) ?>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 mt-3">
        <div class="chip"><i class="fa-solid fa-user-group"></i> Người lớn: <strong><?= (int)$don['SoLuongNguoiLon'] ?></strong></div>
        <div class="chip"><i class="fa-solid fa-child"></i> Trẻ em: <strong><?= (int)$don['SoLuongTreEm'] ?></strong></div>
        <div class="chip"><i class="fa-solid fa-baby"></i> Trẻ nhỏ: <strong><?= (int)$don['SoLuongTreNho'] ?></strong></div>

        <?php if ($soCho > 0): ?>
          <div class="chip">
            <i class="fa-solid fa-chair"></i>
            Chỗ còn lại: <strong><?= (int)$conLai ?></strong> / <?= (int)$soCho ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Alerts -->
    <div class="p-3 p-md-4">
      <?php if ($isSoldout): ?>
        <div class="alert alert-danger mb-3" style="border-radius:18px;">
          <div class="fw-bold" style="font-size:18px;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>Tour đã hết chỗ
          </div>
          <div class="mt-1">
            Hệ thống ghi nhận đơn ở trạng thái <strong>Hết chỗ</strong>. Nếu bạn đã chuyển khoản, vui lòng liên hệ để được hỗ trợ xử lý.
          </div>
        </div>
      <?php elseif ($isPaid && $tourFullNow): ?>
        <div class="alert alert-warning mb-3" style="border-radius:18px;">
          <strong>Lưu ý:</strong> Tour hiện đã <strong>đủ chỗ</strong>. Bạn vẫn giữ chỗ thành công theo đơn này.
        </div>
      <?php endif; ?>

      <div class="row g-3">
        <!-- Chi phí -->
        <div class="col-lg-6">
          <div class="box">
            <div class="boxHead"><i class="fa-solid fa-coins me-2"></i>Chi phí đơn hàng</div>
            <div class="boxBody">
              <div class="d-flex justify-content-between align-items-center">
                <div class="small2">Tổng tiền gốc</div>
                <div class="fw-bold"><?= number_format($tongGoc, 0, ',', '.') ?> VNĐ</div>
              </div>

              <?php if ($giamTien > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-2">
                  <div class="small2">Giảm giá (<?= (int)$giamPt ?>%)</div>
                  <div class="fw-bold text-danger">-<?= number_format($giamTien, 0, ',', '.') ?> VNĐ</div>
                </div>
                <?php if (!empty($don['TenKM'])): ?>
                  <div class="small2 mt-1">CTKM áp dụng: <strong><?= h($don['TenKM']) ?></strong></div>
                <?php endif; ?>
              <?php endif; ?>

              <hr style="opacity:.12">

              <div class="moneyLabel">Tổng phải trả</div>
              <div class="moneyValue"><?= number_format($tongTra, 0, ',', '.') ?></div>
              <div class="moneyVnd">VNĐ</div>

              <?php if ($isPending): ?>
                <div class="mt-3">
                  <a class="btn btn-warning btn-lg w-100" href="thanhtoan.php?madon=<?= (int)$madon ?>">
                    <i class="fa-solid fa-qrcode me-2"></i> Thanh toán ngay
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Lịch sử thanh toán -->
        <div class="col-lg-6">
          <div class="box">
            <div class="boxHead"><i class="fa-solid fa-clock-rotate-left me-2"></i>Lịch sử thanh toán</div>
            <div class="boxBody">
              <?php if (empty($payments)): ?>
                <div class="text-muted">Chưa có bản ghi thanh toán.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm tbl mb-0">
                    <thead>
                      <tr>
                        <th>Ngày</th>
                        <th>Số tiền</th>
                        <th>Phương thức</th>
                        <th>Trạng thái</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($payments as $p): ?>
                        <tr>
                          <td><?= !empty($p['NgayTT']) ? date('d/m/Y', strtotime($p['NgayTT'])) : '-' ?></td>
                          <td class="fw-bold"><?= number_format((float)$p['SoTien'], 0, ',', '.') ?> VNĐ</td>
                          <td><?= h($p['PhuongThuc'] ?? '-') ?></td>
                          <td><?= h($p['TrangThaiTT'] ?? '-') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Thông tin đơn chi tiết -->
        <div class="col-12">
          <div class="box">
            <div class="boxHead"><i class="fa-solid fa-file-lines me-2"></i>Thông tin đơn</div>
            <div class="boxBody">
              <div class="row g-3">
                <div class="col-md-4">
                  <div class="small2">Mã đơn</div>
                  <div class="fw-bold">#<?= (int)$don['MaDon'] ?></div>
                </div>
                <div class="col-md-4">
                  <div class="small2">Ngày đặt</div>
                  <div class="fw-bold"><?= !empty($don['NgayDat']) ? date('d/m/Y', strtotime($don['NgayDat'])) : '-' ?></div>
                </div>
                <div class="col-md-4">
                  <div class="small2">Trạng thái</div>
                  <div class="fw-bold"><?= h($don['TrangThai']) ?></div>
                </div>

                <div class="col-md-4">
                  <div class="small2">Giá người lớn áp dụng</div>
                  <div class="fw-bold"><?= number_format((float)$don['GiaNguoiLonApDung'], 0, ',', '.') ?> VNĐ</div>
                </div>
                <div class="col-md-4">
                  <div class="small2">Giá trẻ em áp dụng</div>
                  <div class="fw-bold"><?= number_format((float)$don['GiaTreEmApDung'], 0, ',', '.') ?> VNĐ</div>
                </div>
                <div class="col-md-4">
                  <div class="small2">Tour</div>
                  <div class="fw-bold"><?= h($don['TenTour']) ?></div>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="trangchu.php">
                  <i class="fa-solid fa-house me-1"></i> Về trang chủ
                </a>

                <?php if ($isPaid || $isSoldout): ?>
                  <a class="btn btn-outline-secondary" href="dattour_thanhcong.php?madon=<?= (int)$madon ?>">
                    <i class="fa-solid fa-circle-info me-1"></i> Xem trang kết quả thanh toán
                  </a>
                <?php endif; ?>

                <?php if ($isPending): ?>
                  <a class="btn btn-outline-warning" href="thanhtoan.php?madon=<?= (int)$madon ?>">
                    <i class="fa-solid fa-qrcode me-1"></i> Quay lại thanh toán
                  </a>
                <?php endif; ?>
              </div>

            </div>
          </div>
        </div>

      </div> <!-- row -->
    </div> <!-- padding -->
  </div> <!-- cardx -->
</div> <!-- container -->

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
