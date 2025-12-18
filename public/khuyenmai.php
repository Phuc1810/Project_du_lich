<?php
require_once __DIR__ . "/../app/config/config.php";

// ====== CẬP NHẬT TRẠNG THÁI CTKM THEO NGÀY HIỆN TẠI ======

// Hết hạn
$conn->query("
  UPDATE chuongtrinhkhuyenmai
  SET TrangThai = 'Hết hạn'
  WHERE NgayKetThuc < CURDATE()
");

// Sắp diễn ra
$conn->query("
  UPDATE chuongtrinhkhuyenmai
  SET TrangThai = 'Sắp diễn ra'
  WHERE NgayBatDau > CURDATE()
    AND NgayKetThuc >= CURDATE()
");

// Hoạt động
$conn->query("
  UPDATE chuongtrinhkhuyenmai
  SET TrangThai = 'Hoạt động'
  WHERE NgayBatDau <= CURDATE()
    AND NgayKetThuc >= CURDATE()
");

// ====== LẤY DANH SÁCH CTKM ======
// Mẹo nhỏ: Dùng ORDER BY FIELD để đưa 'Hoạt động' lên trước 'Sắp diễn ra'
$sql = "
    SELECT MaCTKM, TenKM, NoiDung, AnhDaiDien,
           PhanTramGiam, NgayBatDau, NgayKetThuc, TrangThai
    FROM chuongtrinhkhuyenmai
    WHERE TrangThai IN ('Hoạt động', 'Sắp diễn ra')
    ORDER BY FIELD(TrangThai, 'Hoạt động', 'Sắp diễn ra'), NgayBatDau DESC
";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Chương trình khuyến mãi</title>

  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link rel="stylesheet" href="./assets/css/chung.css">
  <link rel="stylesheet" href="./assets/css/khuyenmai.css">
  <style>
      /* Thêm style cho badge trạng thái */
      .status-badge {
          position: absolute;
          top: 10px;
          left: 10px;
          padding: 5px 10px;
          border-radius: 4px;
          font-size: 12px;
          font-weight: bold;
          text-transform: uppercase;
          color: #fff;
          z-index: 2;
      }
      .bg-sap-dien-ra { background-color: #fd7e14; } /* Màu cam */
      .bg-hoat-dong { background-color: #198754; }   /* Màu xanh lá */
  </style>
</head>
<body>

<?php
  require_once __DIR__ . "/../app/includes/header.php";
  require_once __DIR__ . "/../app/includes/social-bar.php";
?>

<div class="container km-wrapper">
  <h2 class="fw-bold text-center mb-4 km-title">
    CHƯƠNG TRÌNH KHUYẾN MÃI
  </h2>

  <div class="row g-4">
    <?php
      if (!$res) {
        echo "<div class='text-danger'>Lỗi SQL: ".$conn->error."</div>";
      } elseif ($res->num_rows == 0) {
        echo "<h5 class='text-center text-muted mb-5'>
                Hiện chưa có chương trình khuyến mãi nào.
              </h5>";
      } else {
        while ($km = $res->fetch_assoc()):
          $tu_ngay  = date('d/m/Y', strtotime($km['NgayBatDau']));
          $den_ngay = date('d/m/Y', strtotime($km['NgayKetThuc']));
          $pt_giam  = (int)$km['PhanTramGiam']; // Lấy % giảm
    ?>
      <div class="col-md-4">
        <div class="km-card h-100">
          <div class="km-img-box position-relative">
            <img src="assets/<?= $km['AnhDaiDien'] ?>" class="km-img" alt="" onerror="this.src='assets/img/no-image.jpg'">
            
            <?php if($km['TrangThai'] === 'Sắp diễn ra'): ?>
                <span class="status-badge bg-sap-dien-ra">Sắp diễn ra</span>
            <?php endif; ?>

            <?php if ($pt_giam > 0): ?>
                <span class="km-badge">
                  -<?= $pt_giam ?>%
                </span>
            <?php endif; ?>
            
          </div>

          <div class="km-body">
            <h5 class="km-name text-truncate" title="<?= htmlspecialchars($km['TenKM']) ?>">
                <?= htmlspecialchars($km['TenKM']) ?>
            </h5>

            <p class="km-time mb-1 text-muted small">
              <i class="fa-regular fa-calendar-days me-1"></i>
              <?= $tu_ngay ?> - <?= $den_ngay ?>
            </p>

            <p class="km-desc text-secondary" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
              <?= nl2br(htmlspecialchars($km['NoiDung'])) ?>
            </p>

            <a href="khuyenmai_chitiet.php?id=<?= $km['MaCTKM'] ?>"
               class="btn btn-km-view w-100 mt-2">
               XEM CÁC TOUR ÁP DỤNG
            </a>
          </div>
        </div>
      </div>
    <?php
        endwhile;
      }
    ?>
  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>