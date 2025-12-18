<?php 
require_once __DIR__ . "/../app/config/config.php"; 

// HÀM BỎ DẤU TIẾNG VIỆT
function boDau($str) {
    $str = mb_strtolower($str, 'UTF-8');

    $unicode = [
        'a'=>['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ'],
        'e'=>['è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ'],
        'i'=>['ì','í','ị','ỉ','ĩ'],
        'o'=>['ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ'],
        'u'=>['ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ'],
        'y'=>['ỳ','ý','ỵ','ỷ','ỹ'],
        'd'=>['đ']
    ];

    foreach ($unicode as $non => $uni) {
        $str = str_replace($uni, $non, $str);
    }
    return $str;
}

// KEY TÌM KIẾM
$key = isset($_GET['key']) ? trim($_GET['key']) : "";
$key_khong_dau = boDau($key);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả tìm kiếm</title>

        <!-- Bootstrap -->
    <link rel="stylesheet" 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" 
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    
    <!-- CSS CHUNG + CSS RIÊNG -->
    <link rel="stylesheet" href="./assets/css/chung.css">
    <link rel="stylesheet" href="./assets/css/timkiem.css">
    
</head>

<body>

<?php 
require_once __DIR__ . "/../app/includes/header.php"; 
require_once __DIR__ . "/../app/includes/social-bar.php";
?>

<!-- ============ NỘI DUNG CHÍNH ============ -->
<div class="container search-result-wrapper">

    <h2 class="fw-bold text-center mb-4">
        KẾT QUẢ TÌM KIẾM: "<?= htmlspecialchars($key) ?>"
    </h2>

    <div class="row g-4">

<?php
// -------------------------------------------------
// SQL TÌM THEO ĐỊA ĐIỂM KHÔNG DẤU
// -------------------------------------------------
$sql = "
    SELECT t.MaTour, t.TenTour, t.GiaGiam, t.GiaGoc, t.DiaDiem,
           h.DuongDan, t.PhanTramGiam
    FROM tour t
    LEFT JOIN hinhanhtour h 
           ON t.MaTour = h.MaTour AND h.LaAnhChinh = 1
    WHERE t.TrangThai = 'Hoạt động'
      AND CONVERT(t.DiaDiem USING utf8mb4) COLLATE utf8mb4_0900_ai_ci 
          LIKE '%$key_khong_dau%'
";

$res = $conn->query($sql);

if (!$res) {
    echo "<div class='text-danger'>Lỗi SQL: ".$conn->error."</div>";
    exit;
}

// Không có kết quả
if ($res->num_rows == 0) {
    echo "<h5 class='text-center text-muted'>Không có tour phù hợp...</h5>";
}

while ($row = $res->fetch_assoc()):
?>

        <!-- ==== CARD TOUR ==== -->
<div class="col-md-4">
  <div class="tour-card shadow-sm">

     <div class="tour-img">
      <img src="assets/<?= $row['DuongDan'] ?>" alt="">
    </div>
    <?php
      // Lấy % giảm – ưu tiên cột PhanTramGiam, nếu trống thì tự tính
      $pt_giam = (int)($row['PhanTramGiam'] ?? 0);
      if ($pt_giam <= 0 && $row['GiaGoc'] > 0 && $row['GiaGiam'] > 0) {
          $pt_giam = round(100 - $row['GiaGiam'] / $row['GiaGoc'] * 100);
      }
    ?>

    <?php if ($pt_giam > 0): ?>
      <div class="tour-discount-badge">
        -<?= $pt_giam ?>%
      </div>
    <?php endif; ?>

    <div class="tour-body p-3">
      <h5 class="fw-bold mb-1"><?= $row['TenTour'] ?></h5>

      <p class="text-muted mb-1">
        <i class="fa-solid fa-location-dot text-danger"></i>
        <?= $row['DiaDiem'] ?>
      </p>

      <p class="fw-bold text-danger mb-2 mb-0">
        <?= number_format($row['GiaGiam']) ?> VNĐ
        <span class="text-muted text-decoration-line-through ms-1" style="font-size:14px;">
          <?= number_format($row['GiaGoc']) ?> VNĐ
        </span>
      </p>

      <a href="chitiet_tour.php?id=<?= $row['MaTour'] ?>" 
         class="btn btn-book w-100 mt-3">
        ĐẶT TOUR
      </a>
    </div>

  </div>
</div>


<?php endwhile; ?>

    </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

</body>
</html>
