<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pick_value(array $arr, array $keys) {
  foreach ($keys as $k) {
    if (isset($arr[$k]) && $arr[$k] !== null && $arr[$k] !== '') return $arr[$k];
  }
  return null;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: tour_doanhnghiep.php"); exit; }

// Lấy tour DN + ảnh chính
$sql = "
  SELECT
    t.MaTour, t.TenTour, t.DiaDiem, t.ThoiLuong, t.SoCho, t.SoChoDaDat,
    t.GiaGoc, t.GiaGiam, t.PhanTramGiam, t.TrangThai, t.LoaiTour,
    h.DuongDan AS AnhChinh
  FROM tour t
  LEFT JOIN hinhanhtour h ON h.MaTour = t.MaTour AND h.LaAnhChinh = 1
  WHERE t.MaTour=? AND t.LoaiTour='Doanh nghiệp'
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$tour = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tour) { header("Location: tour_doanhnghiep.php"); exit; }

// Tính % giảm
$giaGoc = (float)($tour['GiaGoc'] ?? 0);
$giaGiam = (float)($tour['GiaGiam'] ?? 0);
$pt = (float)($tour['PhanTramGiam'] ?? 0);

if ($pt <= 0 && $giaGoc > 0 && $giaGiam > 0 && $giaGiam < $giaGoc) {
  $pt = 100 - ($giaGiam / $giaGoc * 100);
}
$pt = (int)round(max(0, $pt));

$hasSale = ($giaGiam > 0 && $giaGoc > 0 && $giaGiam < $giaGoc) || ($pt > 0);
$displayNew = $hasSale ? $giaGiam : $giaGoc;

// Lịch trình (an toàn: SELECT * để không phụ thuộc tên cột)
$lichtrinh = [];
$stmt = $conn->prepare("SELECT * FROM lichtrinhtour WHERE MaTour=? ORDER BY 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$rsLT = $stmt->get_result();
while ($r = $rsLT->fetch_assoc()) $lichtrinh[] = $r;
$stmt->close();

// login?
$isLoggedIn = !empty($_SESSION['user']['MaTK']);
$currentUrl = $_SERVER['REQUEST_URI'] ?? ("xemtour_doanhnghiep.php?id=".(int)$id);

// link gửi yêu cầu (đã login thì đi thẳng)
$linkRequest = "yeucaudoanhnghiep.php?tour=".(int)$id;
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title><?= h($tour['TenTour']) ?> - Tour Doanh Nghiệp</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">
  <link rel="stylesheet" href="./assets/css/xemtour_doanhnghiep.css">
</head>
<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container page-wrap">
  <h1 class="tour-title"><?= h($tour['TenTour']) ?></h1>

  <div class="row g-4 align-items-start">
    <div class="col-lg-7">
      <div class="hero-img-wrap">
        <?php if (!empty($tour['AnhChinh'])): ?>
          <img class="hero-img" src="assets/<?= h($tour['AnhChinh']) ?>" alt="">
        <?php else: ?>
          <div class="hero-img hero-img--placeholder"></div>
        <?php endif; ?>

        <?php if ($pt > 0): ?>
          <div class="badge-sale">-<?= (int)$pt ?>%</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="info-card">
        <ul class="list-unstyled info-list">
          <li>
            <i class="fa-solid fa-location-dot text-danger"></i>
            <strong>Địa điểm:</strong> <?= h($tour['DiaDiem'] ?? '') ?>
          </li>
          <li>
            <i class="fa-regular fa-clock text-primary"></i>
            <strong>Thời lượng:</strong> <?= h($tour['ThoiLuong'] ?? 'Đang cập nhật') ?>
          </li>
          <li>
            <i class="fa-regular fa-calendar text-primary"></i>
            <strong>Khởi hành:</strong> <?= !empty($tour['NgayKhoiHanh']) ? date('d/m/Y', strtotime($tour['NgayKhoiHanh'])) : 'Liên hệ' ?>
          </li>
          <li>
            <i class="fa-solid fa-users text-dark"></i>
            <strong>Số chỗ:</strong> <?= (int)($tour['SoCho'] ?? 0) ?>
            <span class="text-muted small">(Đã đặt: <?= (int)($tour['SoChoDaDat'] ?? 0) ?>)</span>
          </li>
        </ul>

        <hr class="divider">

        <div class="price-section mb-4">
          <?php if ($hasSale): ?>
            <div class="price-old">Giá gốc: <del><?= number_format($giaGoc, 0, ',', '.') ?> VNĐ</del></div>
          <?php endif; ?>
          <div class="price-final">
            Giá chỉ còn: <span class="text-danger"><?= number_format($displayNew, 0, ',', '.') ?> VNĐ</span>
          </div>
        </div>

        <?php if ($isLoggedIn): ?>
          <a class="btn btn-booking" href="yeucaudoanhnghiep.php?tour=<?= (int)$tour['MaTour'] ?>">
            ĐẶT TOUR
          </a>
        <?php else: ?>
          <button class="btn btn-booking" type="button" data-bs-toggle="modal" data-bs-target="#loginModal">
            ĐẶT TOUR
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="mt-5">
    <h3 class="section-title">LỊCH TRÌNH CHI TIẾT</h3>

    <?php if (empty($lichtrinh)): ?>
      <div class="alert alert-light border">Đang cập nhật lịch trình...</div>
    <?php else: ?>
      <div class="accordion tour-accordion" id="ltAccordion">
        <?php foreach ($lichtrinh as $i => $lt):
          $tieuDe = pick_value($lt, ['TieuDe','TieuDeNgay','TenNgay','TenLichTrinh','MoTa','TieuDeLT']) ?? '';
          $noiDung = pick_value($lt, ['NoiDung','ChiTiet','NoiDungLT','NoiDungChiTiet','MoTaChiTiet']) ?? '';

          $dayTitle = trim((string)$tieuDe);
          if ($dayTitle === '') $dayTitle = 'Lịch trình';

          $collapseId = "lt_c_".$i;
          $headingId  = "lt_h_".$i;
          $noiDungSafe = nl2br(h((string)$noiDung));
        ?>
          <div class="accordion-item border-0 mb-2">
            <h2 class="accordion-header" id="<?= h($headingId) ?>">
              <button class="accordion-button fw-bold <?= $i===0 ? '' : 'collapsed' ?>" type="button"
                data-bs-toggle="collapse" data-bs-target="#<?= h($collapseId) ?>">
                Ngày <?= ($i+1) ?>: <?= h($dayTitle) ?>
              </button>
            </h2>
            <div id="<?= h($collapseId) ?>" class="accordion-collapse collapse <?= $i===0 ? 'show' : '' ?>"
              data-bs-parent="#ltAccordion">
              <div class="accordion-body bg-white">
                <?= $noiDungSafe ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

<!-- Modal yêu cầu đăng nhập -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bạn chưa đăng nhập</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Bạn cần đăng nhập/đăng ký để gửi yêu cầu đặt tour doanh nghiệp.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        <a class="btn btn-primary"
           href="auth.php?tab=login&redirect=<?= urlencode($currentUrl) ?>">
           Đăng nhập / Đăng ký
        </a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
