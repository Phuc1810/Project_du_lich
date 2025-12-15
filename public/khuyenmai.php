<?php
require_once __DIR__ . "/../app/config/config.php";

// ====== CẬP NHẬT TRẠNG THÁI CTKM THEO NGÀY HIỆN TẠI ======

// Hết hạn
$conn->query("
  UPDATE ChuongTrinhKhuyenMai
  SET TrangThai = 'Hết hạn'
  WHERE NgayKetThuc < CURDATE()
");

// Sắp diễn ra
$conn->query("
  UPDATE ChuongTrinhKhuyenMai
  SET TrangThai = 'Sắp diễn ra'
  WHERE NgayBatDau > CURDATE()
    AND NgayKetThuc >= CURDATE()
");

// Hoạt động
$conn->query("
  UPDATE ChuongTrinhKhuyenMai
  SET TrangThai = 'Hoạt động'
  WHERE NgayBatDau <= CURDATE()
    AND NgayKetThuc >= CURDATE()
");

// ====== LẤY DANH SÁCH CTKM ======
$sql = "
    SELECT MaCTKM, TenKM, NoiDung, AnhDaiDien,
           PhanTramGiam, NgayBatDau, NgayKetThuc, TrangThai
    FROM ChuongTrinhKhuyenMai
    WHERE TrangThai IN ('Hoạt động', 'Sắp diễn ra')
    ORDER BY NgayBatDau DESC
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
    ?>
      <div class="col-md-4">
        <div class="km-card h-100">
          <div class="km-img-box">
            <img src="assets/<?= $km['AnhDaiDien'] ?>" class="km-img" alt="">
            <span class="km-badge">
              -<?= (int)$km['PhanTramGiam'] ?>%
            </span>
          </div>

          <div class="km-body">
            <h5 class="km-name"><?= htmlspecialchars($km['TenKM']) ?></h5>

            <p class="km-time mb-1">
              <i class="fa-regular fa-calendar-days me-1"></i>
              <?= $tu_ngay ?> - <?= $den_ngay ?>
            </p>

            <p class="km-desc">
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

</body>
</html>
