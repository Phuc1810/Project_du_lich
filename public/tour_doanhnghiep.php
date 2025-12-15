<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Lấy danh sách tour doanh nghiệp + ảnh chính
$sql = "
  SELECT
    t.MaTour AS MaTour,
    t.TenTour,
    t.DiaDiem,
    t.GiaGoc,
    t.GiaGiam,
    t.PhanTramGiam,
    h.DuongDan AS AnhChinh
  FROM Tour t
  LEFT JOIN HinhAnhTour h
    ON h.MaTour = t.MaTour AND h.LaAnhChinh = 1
  WHERE t.LoaiTour = 'Doanh nghiệp'
  ORDER BY t.MaTour DESC
";
$rs = $conn->query($sql);

$tours = [];
if ($rs) {
  while ($row = $rs->fetch_assoc()) $tours[] = $row;
}

// helper tính % giảm
function calc_discount_percent($giaGoc, $giaGiam, $phanTramGiam){
  $giaGoc = (float)$giaGoc;
  $giaGiam = (float)$giaGiam;
  $phanTramGiam = (float)$phanTramGiam;

  if ($phanTramGiam > 0) return (int)round($phanTramGiam);
  if ($giaGoc > 0 && $giaGiam > 0 && $giaGiam < $giaGoc) {
    return (int)round(100 - ($giaGiam / $giaGoc * 100));
  }
  return 0;
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Tour Doanh Nghiệp</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">
  <link rel="stylesheet" href="./assets/css/tour_doanhnghiep.css">
</head>

<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container wrap">
  <h2 class="page-title">TOUR DOANH NGHIỆP</h2>

  <?php if (empty($tours)): ?>
    <div class="alert alert-info">Hiện chưa có tour doanh nghiệp.</div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($tours as $t):
        $maTour  = (int)($t['MaTour'] ?? 0);
        $giaGoc  = (float)($t['GiaGoc'] ?? 0);
        $giaGiam = (float)($t['GiaGiam'] ?? 0);

        $pt = calc_discount_percent($giaGoc, $giaGiam, $t['PhanTramGiam'] ?? 0);

        $hasSale = ($giaGiam > 0 && $giaGoc > 0 && $giaGiam < $giaGoc) || ($pt > 0);
        $displayNew = $hasSale ? $giaGiam : $giaGoc;

        $img = trim((string)($t['AnhChinh'] ?? ''));
      ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="tour-card">
          <div class="thumb-wrap">
            <?php if ($img !== ''): ?>
              <img class="thumb" src="assets/<?= h($img) ?>" alt="">
            <?php else: ?>
              <div class="thumb thumb-placeholder"></div>
            <?php endif; ?>

            <?php if ($pt > 0): ?>
              <div class="badge-sale">-<?= (int)$pt ?>%</div>
            <?php endif; ?>
          </div>

          <div class="card-bodyx">
            <div class="tour-name"><?= h($t['TenTour'] ?? '') ?></div>

            <div class="tour-loc">
              <i class="fa-solid fa-location-dot loc-ic"></i>
              <span><?= h($t['DiaDiem'] ?? '') ?></span>
            </div>

            <div class="price-row">
              <div class="price-new"><?= number_format($displayNew, 0, ',', '.') ?> VNĐ</div>
              <?php if ($hasSale): ?>
                <div class="price-old"><?= number_format($giaGoc, 0, ',', '.') ?> VNĐ</div>
              <?php endif; ?>
            </div>

            <!-- ✅ FIX: dùng $t['MaTour'] (không dùng $row nữa) -->
            <a class="btn btn-view" href="xemtour_doanhnghiep.php?id=<?= $maTour ?>">XEM TOUR</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
