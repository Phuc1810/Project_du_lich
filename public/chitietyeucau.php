<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

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

function fmtMoney($n)
{
  if ($n === null || $n === '') return '—';
  return number_format((float)$n, 0, ',', '.') . ' VNĐ';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) {
  header("Location: auth.php?tab=login&redirect=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

$maYC = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($maYC <= 0) {
  header("Location: donyeucau.php");
  exit;
}

// lấy yêu cầu đúng chủ tài khoản
$sql = "
  SELECT
    y.MaYC, y.TenCongTy, y.NguoiLienHe, y.SDT, y.SoNguoi,
    y.ThoiGianKhoiHanh, y.GiaTriHopDong,y.NgayThanhToan,y.TrangThai,
    y.MaTour, y.MaNV,
    kh.HoTen AS HoTenKH, kh.Email AS EmailKH, kh.SoDienThoai AS SDTKH,
    t.TenTour, t.DiaDiem, t.ThoiLuong,
    t.GiaGoc, t.GiaGiam, t.PhanTramGiam,
    h.DuongDan AS AnhChinh
  FROM yeucaudoanhnghiep y
  JOIN khachhang kh ON kh.MaKH = y.MaKH
  LEFT JOIN tour t ON t.MaTour = y.MaTour
  LEFT JOIN hinhanhtour h ON h.MaTour=t.MaTour AND h.LaAnhChinh=1
  WHERE y.MaYC=? AND kh.MaTK=?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $maYC, $matk);
$stmt->execute();
$yc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$yc) {
  header("Location: donyeucau.php");
  exit;
}

// badge class
function badgeClassYC($st)
{
 $st = trim((string)$st); // Bỏ mb_strtolower để so sánh chính xác tên
  if ($st === 'Chờ xử lý') return 'text-bg-warning'; // Vàng
  if ($st === 'Đã liên hệ') return 'text-bg-info text-white'; // Xanh dương (thêm text-white cho rõ)
  if ($st === 'Hoàn thành') return 'text-bg-success'; // Xanh lá
  if ($st === 'Hủy tour') return 'text-bg-danger'; // Đỏ
  return 'text-bg-secondary';
}
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Chi tiết yêu cầu #<?= (int)$maYC ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">

  <style>
    body {
      background: #f6f8fb;
    }

    .wrap {
      padding-top: 150px;
      padding-bottom: 50px;
    }

    .cardx {
      border: 0;
      border-radius: 20px;
      background: #fff;
      box-shadow: 0 14px 40px rgba(16, 24, 40, .10);
    }

    .title {
      font-size: 26px;
      font-weight: 1000;
    }

    .muted {
      color: #64748b;
    }

    .divider {
      height: 1px;
      background: rgba(15, 23, 42, .08);
      margin: 16px 0;
    }

    .tour-mini {
      display: flex;
      gap: 14px;
      align-items: center;
      padding: 14px;
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 16px;
      background: #fff;
    }

    .tour-mini img {
      width: 120px;
      height: 90px;
      object-fit: cover;
      border-radius: 12px;
      background: #e2e8f0;
    }

    .kv {
      display: grid;
      grid-template-columns: 200px 1fr;
      gap: 10px 14px;
    }

    .k {
      color: #64748b;
      font-weight: 700;
    }

    .v {
      font-weight: 900;
      color: #0f172a;
    }

    @media (max-width: 768px) {
      .kv {
        grid-template-columns: 1fr;
      }

      .v {
        font-weight: 800;
      }
    }
  </style>
</head>

<body>
  <?php require_once __DIR__ . "/../app/includes/header.php"; ?>

  <div class="container wrap">
    <div class="cardx p-4 p-lg-5">
      <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
        <div>
          <div class="title"><i class="fa-solid fa-clipboard-list me-2"></i>Chi tiết yêu cầu</div>
          <div class="muted mt-1">
            Mã yêu cầu: <b>#<?= (int)$yc['MaYC'] ?></b>
            • <span class="badge <?= badgeClassYC($yc['TrangThai'] ?? '') ?>"><?= h($yc['TrangThai'] ?? '—') ?></span>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary" href="donyeucau.php">
            <i class="fa-solid fa-arrow-left me-1"></i> Danh sách yêu cầu
          </a>
          <a class="btn btn-outline-secondary" href="trangchu.php">
            <i class="fa-solid fa-house me-1"></i> Trang chủ
          </a>
        </div>
      </div>

      <div class="divider"></div>

      <!-- Tour -->
      <div class="tour-mini">
        <?php if (!empty($yc['AnhChinh'])): ?>
          <img src="assets/<?= h($yc['AnhChinh']) ?>" alt="">
        <?php else: ?>
          <img src="" alt="">
        <?php endif; ?>
        <div class="flex-grow-1">
          <div style="font-weight:1000; font-size:18px; margin-bottom:2px;">
            <?= h($yc['TenTour'] ?? 'Tour doanh nghiệp') ?>
          </div>
          <div class="muted" style="font-weight:700;">
            <?php if (!empty($yc['DiaDiem'])): ?>
              <i class="fa-solid fa-location-dot me-1" style="color:#e11d48;"></i><?= h($yc['DiaDiem']) ?>
            <?php endif; ?>
            <?php if (!empty($yc['ThoiLuong'])): ?>
              &nbsp; • &nbsp;<i class="fa-regular fa-clock me-1" style="color:#2563eb;"></i><?= h($yc['ThoiLuong']) ?>
            <?php endif; ?>
            <?php
            $giaGoc = (float)($yc['GiaGoc'] ?? 0);
            $giaGiam = (float)($yc['GiaGiam'] ?? 0);
            $pt = (int)($yc['PhanTramGiam'] ?? 0);

            $hasSale = ($giaGoc > 0 && $giaGiam > 0 && $giaGiam < $giaGoc) || ($pt > 0);
            if ($pt <= 0 && $hasSale && $giaGoc > 0) {
              $pt = (int)round(100 - ($giaGiam / $giaGoc * 100));
            }
            $displayPrice = $hasSale ? $giaGiam : $giaGoc;
            ?>
            <div class="mt-2">
              <span class="fw-bold" style="color:#e11d48;">
                <?= $displayPrice > 0 ? number_format($displayPrice, 0, ',', '.') . " VNĐ" : "—" ?>
              </span>

              <?php if ($hasSale): ?>
                <span class="text-muted text-decoration-line-through ms-2">
                  <?= $giaGoc > 0 ? number_format($giaGoc, 0, ',', '.') . " VNĐ" : "" ?>
                </span>
                <?php if ($pt > 0): ?>
                  <span class="badge text-bg-warning ms-2">-<?= (int)$pt ?>%</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="divider"></div>

      <!-- Thông tin yêu cầu -->
      <div class="kv">
        <div class="k">Tên công ty</div>
        <div class="v"><?= h($yc['TenCongTy'] ?? '—') ?></div>

        <div class="k">Người liên hệ</div>
        <div class="v"><?= h($yc['NguoiLienHe'] ?? '—') ?></div>

        <div class="k">Số điện thoại liên hệ</div>
        <div class="v"><?= h($yc['SDT'] ?? '—') ?></div>

        <div class="k">Số người</div>
        <div class="v"><?= (int)($yc['SoNguoi'] ?? 0) ?></div>

        <div class="k">Thời gian khởi hành</div>
        <div class="v"><?= fmtDate($yc['ThoiGianKhoiHanh'] ?? '') ?></div>

        <div class="k">Giá trị hợp đồng</div>
        <div class="v"><?= fmtMoney($yc['GiaTriHopDong'] ?? null) ?></div>

        <div class="k">Ngày thanh toán</div>
        <div class="v"><?= fmtDate($yc['NgayThanhToan'] ?? '') ?></div>

        <div class="k">Trạng thái</div>
        <div class="v"><?= h($yc['TrangThai'] ?? '—') ?></div>

        <div class="k">Mã tour</div>
        <div class="v"><?= !empty($yc['MaTour']) ? (int)$yc['MaTour'] : '—' ?></div>

        <div class="k">Mã nhân viên phụ trách</div>
        <div class="v"><?= !empty($yc['MaNV']) ? (int)$yc['MaNV'] : '—' ?></div>
      </div>

      <div class="divider"></div>

      <div class="muted small">
        Nếu trạng thái là <b>Chờ xử lý</b> / <b>Đang xử lý</b> thì nhân viên sẽ liên hệ để chốt chi tiết (giá, lịch trình, hợp đồng...).
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>