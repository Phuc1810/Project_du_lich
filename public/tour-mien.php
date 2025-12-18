<?php
require_once __DIR__ . "/../app/config/config.php";

// =======================
// XÁC ĐỊNH MIỀN HOẶC KHUYẾN MÃI
// =======================
$mien_param = isset($_GET['mien']) ? strtolower(trim($_GET['mien'])) : "";
$is_khuyenmai = isset($_GET['khuyenmai']) ? true : false;

// Map tham số -> giá trị trong cột Mien của bảng Tour
$mien_map = [
    'bac'   => 'Bắc',
    'trung' => 'Trung',
    'nam'   => 'Nam'
];

$ten_trang = "TẤT CẢ TOUR";

if ($is_khuyenmai) {
    $ten_trang = "CHƯƠNG TRÌNH KHUYẾN MÃI";
} elseif (isset($mien_map[$mien_param])) {
    $mien_db  = $mien_map[$mien_param];
    $ten_trang = "TOUR MIỀN " . mb_strtoupper($mien_db, 'UTF-8');
} else {
    // nếu không có tham số hợp lệ thì mặc định Miền Bắc (tuỳ bạn)
    $mien_db  = 'Bắc';
    $ten_trang = "TOUR MIỀN BẮC";
}

// =======================
// BUILD SQL
// =======================
$sql = "
    SELECT t.MaTour, t.TenTour, t.GiaGoc, t.GiaGiam,
           t.DiaDiem, t.Mien, t.PhanTramGiam,
           h.DuongDan
    FROM tour t
    LEFT JOIN hinhanhtour h
           ON t.MaTour = h.MaTour AND h.LaAnhChinh = 1
    WHERE t.TrangThai = 'Hoạt động'
";

// Lọc theo miền nếu không phải trang khuyến mãi
if (!$is_khuyenmai) {
    $sql .= " AND t.Mien = '" . $conn->real_escape_string($mien_db) . "'";
}

// Lọc tour khuyến mãi (nếu là trang khuyến mãi)
if ($is_khuyenmai) {
    $sql .= " AND (t.PhanTramGiam > 0 OR t.GiaGiam < t.GiaGoc)";
}

// Sắp xếp cho đẹp – tuỳ bạn chỉnh
$sql .= " ORDER BY t.NgayKhoiHanh ASC, t.GiaGiam ASC";

$res = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= $ten_trang ?></title>

    <!-- CSS chung + CSS card tour (đã dùng ở trang tìm kiếm) -->

    <!-- Bootstrap + FontAwesome -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
          
    <link rel="stylesheet" href="./assets/css/chung.css">
    <link rel="stylesheet" href="./assets/css/timkiem.css">
</head>
<body>

<?php
require_once __DIR__ . "/../app/includes/header.php";
require_once __DIR__ . "/../app/includes/social-bar.php"
?>

<div class="container search-result-wrapper">
    <h2 class="fw-bold text-center mb-4">
        <?= htmlspecialchars($ten_trang) ?>
    </h2>

    <div class="row g-4">
    <?php
    if (!$res) {
        echo "<div class='text-danger'>Lỗi SQL: " . $conn->error . "</div>";
    } elseif ($res->num_rows == 0) {
        echo "<h5 class='text-center text-muted'>Không có tour phù hợp...</h5>";
    } else {
        while ($row = $res->fetch_assoc()):
            // Tính % giảm
            $pt_giam = (int)($row['PhanTramGiam'] ?? 0);
            if ($pt_giam <= 0 && $row['GiaGoc'] > 0 && $row['GiaGiam'] > 0) {
                $pt_giam = round(100 - $row['GiaGiam'] / $row['GiaGoc'] * 100);
            }
    ?>
        <div class="col-md-4">
          <div class="tour-card shadow-sm">

            <div class="tour-img">
              <img src="assets/<?= $row['DuongDan'] ?>" alt="">
            </div>

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
    <?php
        endwhile;
    }
    ?>
    </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
</body>
</html>
