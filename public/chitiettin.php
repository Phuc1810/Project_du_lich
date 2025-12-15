<?php
require_once __DIR__ . "/../app/config/config.php";

// LẤY ID BÀI VIẾT
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$loai_param = isset($_GET['loai']) ? trim($_GET['loai']) : "";

// NẾU KHÔNG CÓ ID HỢP LỆ => VỀ TRANG CHỦ
if ($id <= 0) {
    header("Location: trangchu.php");
    exit;
}

// LẤY THÔNG TIN BÀI VIẾT
$sql = "
    SELECT MaTin, TieuDe, MoTa, AnhDaiDien, NgayDang, LoaiTin, NoiDung
    FROM TinTuc
    WHERE MaTin = ".$id." AND TrangThai = 'Hiển thị'
    LIMIT 1
";
$tin = $conn->query($sql)->fetch_assoc();

if (!$tin) {
    // Không tìm thấy bài
    header("Location: trangchu.php");
    exit;
}

// MAP LOẠI TIN -> TEXT
$loai  = $tin['LoaiTin'];
if ($loai === 'kinhnghiem') {
    $loai_text  = "Kinh nghiệm du lịch";
    $title_page = "KINH NGHIỆM DU LỊCH";
} else {
    $loai_text  = "Tin tức du lịch";
    $title_page = "TIN TỨC DU LỊCH";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($tin['TieuDe']) ?></title>

    <!-- Bootstrap + FA -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="./assets/css/chung.css">
    <link rel="stylesheet" href="./assets/css/chitietin.css">
</head>
<body>

<?php
  require_once __DIR__ . "/../app/includes/header.php";
  require_once __DIR__ . "/../app/includes/social-bar.php";
?>

<div class="container article-wrapper" id="article-wrapper">

  <!-- TIÊU ĐỀ + INFO -->
  <h1 class="article-title mb-2">
      <?= htmlspecialchars($tin['TieuDe']) ?>
  </h1>

  <p class="article-meta mb-4">
    <span class="me-3">
      <i class="fa-regular fa-calendar-days me-1"></i>
      <?= date('d/m/Y', strtotime($tin['NgayDang'])) ?>
    </span>
    <span>
      <i class="fa-regular fa-folder-open me-1"></i>
      <?= htmlspecialchars($loai_text) ?>
    </span>
  </p>

  <!-- ẢNH ĐẠI DIỆN -->
  <?php if (!empty($tin['AnhDaiDien'])): ?>
    <div class="article-thumb mb-4">
      <img src="assets/<?= $tin['AnhDaiDien'] ?>" alt="" class="img-fluid rounded-3">
    </div>
  <?php endif; ?>

  <!-- MÔ TẢ NGẮN (nếu muốn) -->
  <?php if (!empty($tin['MoTa'])): ?>
    <p class="article-intro">
      <?= nl2br(htmlspecialchars($tin['MoTa'])) ?>
    </p>
  <?php endif; ?>

  <!-- NỘI DUNG CHÍNH -->
  <div class="article-content">
    <?= $tin['NoiDung'] /* giả sử cột này đã chứa HTML */ ?>
  </div>

  <!-- BÀI VIẾT LIÊN QUAN -->
  <hr class="my-5">

  <h4 class="fw-bold mb-3">Bài viết liên quan</h4>
  <div class="row g-3">
    <?php
      $sql_lienquan = "
        SELECT MaTin, TieuDe, AnhDaiDien
        FROM TinTuc
        WHERE TrangThai='Hiển thị'
          AND LoaiTin='".$conn->real_escape_string($loai)."'
          AND MaTin <> ".$id."
        ORDER BY NgayDang DESC
        LIMIT 3
      ";
      $lq = $conn->query($sql_lienquan);

      if ($lq && $lq->num_rows > 0):
        while ($row = $lq->fetch_assoc()):
    ?>
      <div class="col-md-4">
        <a href="chitiettin.php?id=<?= $row['MaTin'] ?>&loai=<?= $loai ?>"
           class="related-item d-block">
          <div class="related-thumb mb-2">
            <img src="assets/<?= $row['AnhDaiDien'] ?>" class="img-fluid rounded-3">
          </div>
          <p class="related-title mb-0">
            <?= htmlspecialchars($row['TieuDe']) ?>
          </p>
        </a>
      </div>
    <?php
        endwhile;
      else:
        echo "<p class='text-muted'>Chưa có bài viết liên quan.</p>";
      endif;
    ?>
  </div>

</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

</body>
</html>
