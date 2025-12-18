<?php
require_once __DIR__ . "/../app/config/config.php";

// LẤY THAM SỐ LOẠI TIN
$loai = isset($_GET['loai']) ? trim($_GET['loai']) : 'tintuc';
$loai = strtolower($loai);

// Map loại tin -> tiêu đề hiển thị
if ($loai === 'kinhnghiem') {
    $loai_db   = 'kinhnghiem';
    $titlePage = "KINH NGHIỆM DU LỊCH";
} else {
    // mặc định là tin tức
    $loai_db   = 'tintuc';
    $titlePage = "TIN TỨC DU LỊCH";
}

// QUERY DANH SÁCH TIN
$sql = "
    SELECT MaTin, TieuDe, MoTa, AnhDaiDien, NgayDang
    FROM tintuc
    WHERE TrangThai = 'Hiển thị'
      AND LoaiTin   = '".$conn->real_escape_string($loai_db)."'
    ORDER BY NgayDang DESC
";
$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titlePage) ?></title>

    <!-- Bootstrap + Font Awesome -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- CSS chung + CSS riêng cho trang tin tức -->
    <link rel="stylesheet" href="./assets/css/chung.css">
    <link rel="stylesheet" href="./assets/css/tintuc.css">
</head>
<body>

<?php
  require_once __DIR__ . "/../app/includes/header.php";
  require_once __DIR__ . "/../app/includes/social-bar.php";
?>

<div class="container blog-list-wrapper">

  <!-- TIÊU ĐỀ TRANG -->
  <h2 class="fw-bold text-center mb-4 blog-list-title">
    <?= htmlspecialchars($titlePage) ?>
  </h2>

  <!-- DANH SÁCH TIN -->
  <div class="row g-4">
    <?php
      if (!$res) {
        echo "<div class='text-danger'>Lỗi SQL: ".$conn->error."</div>";
      } elseif ($res->num_rows == 0) {
        echo "<h5 class='text-center text-muted mb-5'>
                Hiện chưa có bài viết nào cho mục này.
              </h5>";
      } else {
        while ($tin = $res->fetch_assoc()):
          $mo_ta_ngan = mb_substr($tin['MoTa'], 0, 150, 'UTF-8') . '...';
    ?>
      <div class="col-md-4">
        <div class="blog-card">
          <div class="blog-card-img-box">
            <img src="assets/<?= $tin['AnhDaiDien'] ?>" alt=""
                 class="blog-card-img">
          </div>

          <div class="blog-card-body">
            <h5 class="blog-card-title">
              <?= htmlspecialchars($tin['TieuDe']) ?>
            </h5>

            <p class="blog-card-date">
              <i class="fa-regular fa-calendar-days me-1"></i>
              <?= date('d/m/Y', strtotime($tin['NgayDang'])) ?>
            </p>

            <p class="blog-card-desc">
              <?= htmlspecialchars($mo_ta_ngan) ?>
            </p>

            <a href="chitiettin.php?id=<?= $tin['MaTin'] ?>"
               class="blog-card-btn">
              XEM CHI TIẾT
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

<?php require_once __DIR__ . "/../app/includes/footer.php";; ?>

</body>
</html>
