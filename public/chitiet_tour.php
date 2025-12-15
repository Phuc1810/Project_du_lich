<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// CHECK LOGIN
$isLoggedIn = !empty($_SESSION['user']['MaTK']);

// LẤY ID TOUR
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header("Location: trangchu.php");
  exit;
}

// (MỚI) LẤY ID CTKM (nếu đi từ trang khuyến mãi)
$ctkm_id = isset($_GET['ctkm']) ? (int)$_GET['ctkm'] : 0;

// LẤY THÔNG TIN TOUR + ẢNH CHÍNH
$sqlTour = "
  SELECT  t.*,
          h.DuongDan AS AnhChinh
  FROM Tour t
  LEFT JOIN HinhAnhTour h 
         ON t.MaTour = h.MaTour AND h.LaAnhChinh = 1
  WHERE t.MaTour = $id
    AND t.TrangThai = 'Hoạt động'
  LIMIT 1
";
$tourRes = $conn->query($sqlTour);
$tour = $tourRes ? $tourRes->fetch_assoc() : null;

if (!$tour) {
  header("Location: trangchu.php");
  exit;
}

/* ==========================================================
   (MỚI) KIỂM TRA CTKM CÓ ÁP DỤNG CHO TOUR NÀY KHÔNG
========================================================== */
$km_ap_dung = false;
$pt_km = 0;
$ten_km = "";

if ($ctkm_id > 0) {
  $sqlKM = "
    SELECT tk.PhanTramGiamKM, c.TenKM
    FROM Tour_KhuyenMai tk
    JOIN ChuongTrinhKhuyenMai c ON c.MaCTKM = tk.MaCTKM
    WHERE tk.MaTour = $id
      AND tk.MaCTKM = $ctkm_id
      AND c.TrangThai = 'Hoạt động'
      AND c.NgayBatDau <= CURDATE()
      AND c.NgayKetThuc >= CURDATE()
    LIMIT 1
  ";
  $kmRes = $conn->query($sqlKM);
  if ($kmRes && $kmRes->num_rows > 0) {
    $kmRow = $kmRes->fetch_assoc();
    $pt_km = (float)$kmRow['PhanTramGiamKM'];
    $ten_km = $kmRow['TenKM'];
    if ($pt_km > 0) $km_ap_dung = true;
  }
}

/* ==========================================================
   TÍNH % GIẢM + GIÁ HIỂN THỊ
========================================================== */
$gia_goc_db  = (float)$tour['GiaGoc'];
$gia_giam_db = (float)$tour['GiaGiam'];

if ($km_ap_dung) {
  $pt_hien_thi = (int)round($pt_km);
  $gia_cu_hien_thi  = $gia_goc_db;
  $gia_moi_hien_thi = $gia_goc_db * (100 - $pt_km) / 100;
} else {
  $pt_hien_thi = (int)($tour['PhanTramGiam'] ?? 0);
  if ($pt_hien_thi <= 0 && $gia_goc_db > 0 && $gia_giam_db > 0) {
    $pt_hien_thi = (int)round(100 - $gia_giam_db / $gia_goc_db * 100);
  }
  $gia_cu_hien_thi  = $gia_goc_db;
  $gia_moi_hien_thi = $gia_giam_db;
}

// LẤY DANH SÁCH ẢNH (GALLERY)
$sqlImg = "
  SELECT DuongDan, LaAnhChinh
  FROM HinhAnhTour
  WHERE MaTour = $id
  ORDER BY LaAnhChinh DESC, MaAnh ASC
";
$imgsRes = $conn->query($sqlImg);

// LẤY LỊCH TRÌNH TOUR
$sqlLT = "
  SELECT NgayThu, TieuDe, NoiDung
  FROM LichTrinhTour
  WHERE MaTour = $id
  ORDER BY NgayThu ASC
";
$ltRes = $conn->query($sqlLT);

// ✅ URL đặt tour (dùng cho cả logged-in và modal redirect)
$bookUrl = "dattour.php?id=".(int)$tour['MaTour'] . ($km_ap_dung ? "&ctkm=".(int)$ctkm_id : "");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($tour['TenTour']) ?></title>

  <!-- Bootstrap + Font Awesome -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- CSS chung + CSS chi tiết tour -->
  <link rel="stylesheet" href="./assets/css/chung.css">
  <link rel="stylesheet" href="./assets/css/chitiet-tour.css">
</head>

<body>

<?php
require_once __DIR__ . "/../app/includes/header.php";
require_once __DIR__ . "/../app/includes/social-bar.php";
?>

<div class="container tour-detail-wrapper">

  <!-- TIÊU ĐỀ -->
  <h2 class="fw-bold mb-3 tour-detail-title">
    <?= htmlspecialchars($tour['TenTour']) ?>
  </h2>

  <?php if ($km_ap_dung && !empty($ten_km)): ?>
    <div class="mb-3">
      <span class="badge bg-success">
        Đang áp dụng CTKM: <?= htmlspecialchars($ten_km) ?>
      </span>
    </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- ẢNH + GALLERY -->
    <div class="col-lg-8">
      <div class="tour-main-img position-relative mb-3">
        <img src="assets/<?= htmlspecialchars($tour['AnhChinh']) ?>" class="img-fluid w-100 rounded-4" alt="">
        <?php if ($pt_hien_thi > 0): ?>
          <div class="tour-detail-discount">-<?= (int)$pt_hien_thi ?>%</div>
        <?php endif; ?>
      </div>

      <?php if ($imgsRes && $imgsRes->num_rows > 1): ?>
        <div class="tour-gallery d-flex gap-3 flex-wrap">
          <?php while ($img = $imgsRes->fetch_assoc()): ?>
            <?php if ($img['DuongDan'] == $tour['AnhChinh']) continue; ?>
            <div class="tour-gallery-item">
              <img src="assets/<?= htmlspecialchars($img['DuongDan']) ?>" class="img-fluid rounded-3" alt="">
            </div>
          <?php endwhile; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- KHUNG GIÁ + THÔNG TIN -->
    <div class="col-lg-4">
      <div class="tour-info-card shadow-sm rounded-4 p-3">

        <p class="mb-2">
          <i class="fa-solid fa-location-dot text-danger me-1"></i>
          <strong>Địa điểm:</strong> <?= htmlspecialchars($tour['DiaDiem']) ?>
        </p>

        <p class="mb-2">
          <i class="fa-regular fa-clock text-primary me-1"></i>
          <strong>Thời lượng:</strong> <?= htmlspecialchars($tour['ThoiLuong']) ?>
        </p>

        <?php if (!empty($tour['NgayKhoiHanh'])): ?>
          <p class="mb-2">
            <i class="fa-regular fa-calendar-days text-primary me-1"></i>
            <strong>Khởi hành:</strong>
            <?= date('d/m/Y', strtotime($tour['NgayKhoiHanh'])) ?>
          </p>
        <?php endif; ?>

        <p class="mb-2">
          <i class="fa-solid fa-users me-1"></i>
          <strong>Số chỗ:</strong> <?= (int)$tour['SoCho'] ?>
          <?php if (isset($tour['SoChoDaDat'])): ?>
            (Đã đặt: <?= (int)$tour['SoChoDaDat'] ?>)
          <?php endif; ?>
        </p>

        <hr>

        <p class="mb-1">
          <span class="text-muted"><?= $km_ap_dung ? 'Giá trước CTKM:' : 'Giá gốc:' ?></span>
          <span class="text-decoration-line-through ms-1">
            <?= number_format($gia_cu_hien_thi, 0, ',', '.') ?> VNĐ
          </span>
        </p>

        <p class="tour-detail-price mb-3">
          <span><?= $km_ap_dung ? 'Giá sau CTKM:' : 'Giá chỉ còn:' ?></span>
          <span class="ms-2">
            <?= number_format($gia_moi_hien_thi, 0, ',', '.') ?> VNĐ
          </span>
        </p>

        <!-- ✅ NÚT ĐẶT TOUR: có login -> đi thẳng, chưa login -> mở modal -->
        <?php if ($isLoggedIn): ?>
          <a href="<?= htmlspecialchars($bookUrl) ?>" class="btn btn-book-detail w-100">ĐẶT TOUR</a>
        <?php else: ?>
          <button type="button" class="btn btn-book-detail w-100" data-bs-toggle="modal" data-bs-target="#needLoginModal">
            ĐẶT TOUR
          </button>
        <?php endif; ?>

      </div>
    </div>

  </div>

  <!-- LỊCH TRÌNH TOUR -->
  <div class="mt-5">
    <h4 class="fw-bold mb-3">LỊCH TRÌNH CHI TIẾT</h4>

    <?php if ($ltRes && $ltRes->num_rows > 0): ?>
      <div class="accordion" id="lichTrinhAccordion">
        <?php $i = 0; while ($lt = $ltRes->fetch_assoc()): $i++; ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?= $i ?>">
              <button class="accordion-button <?= $i > 1 ? 'collapsed' : '' ?>"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapse<?= $i ?>"
                aria-expanded="<?= $i == 1 ? 'true' : 'false' ?>"
                aria-controls="collapse<?= $i ?>">
                <?= htmlspecialchars($lt['TieuDe']) ?>
              </button>
            </h2>

            <div id="collapse<?= $i ?>"
              class="accordion-collapse collapse <?= $i == 1 ? 'show' : '' ?>"
              aria-labelledby="heading<?= $i ?>"
              data-bs-parent="#lichTrinhAccordion">
              <div class="accordion-body">
                <?php
                  $noiDung = htmlspecialchars($lt['NoiDung']);
                  $noiDung = str_replace('Sáng:', '<strong>Sáng:</strong>', $noiDung);
                  $replaceArr = [
                    'Trưa:'  => '<br><strong>Trưa:</strong>',
                    'Chiều:' => '<br><strong>Chiều:</strong>',
                    'Tối:'   => '<br><strong>Tối:</strong>',
                  ];
                  $noiDung = strtr($noiDung, $replaceArr);
                  echo $noiDung;
                ?>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <p class="text-muted">Lịch trình đang được cập nhật.</p>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

<!-- ✅ MODAL: CHƯA ĐĂNG NHẬP -->
<?php if (!$isLoggedIn): ?>
<div class="modal fade" id="needLoginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bạn chưa đăng nhập</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body">
        Bạn cần đăng nhập/đăng ký để đặt tour.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        <a class="btn btn-primary"
           href="auth.php?tab=login&redirect=<?= urlencode($bookUrl) ?>">
          Đăng nhập / Đăng ký
        </a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
