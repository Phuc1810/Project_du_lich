<?php
require_once __DIR__ . "/../app/config/config.php";

// LẤY ID CTKM
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: khuyenmai.php");
    exit;
}

// LẤY THÔNG TIN CHƯƠNG TRÌNH
$sql_ct = "
    SELECT MaCTKM, TenKM, NoiDung, AnhDaiDien,
           PhanTramGiam, NgayBatDau, NgayKetThuc, TrangThai
    FROM ChuongTrinhKhuyenMai
    WHERE MaCTKM = $id
    LIMIT 1
";
$ct = $conn->query($sql_ct)->fetch_assoc();

if (!$ct) {
    header("Location: khuyenmai.php");
    exit;
}

$tu_ngay  = date('d/m/Y', strtotime($ct['NgayBatDau']));
$den_ngay = date('d/m/Y', strtotime($ct['NgayKetThuc']));

// LẤY CÁC TOUR ÁP DỤNG CTKM NÀY
$sql_tour = "
    SELECT t.MaTour, t.TenTour, t.DiaDiem, t.ThoiLuong,
           t.GiaGoc, t.GiaGiam,
           tk.PhanTramGiamKM AS KMThem,
           h.DuongDan AS AnhChinh
    FROM tour_khuyenmai tk
    JOIN tour t ON t.MaTour = tk.MaTour
    LEFT JOIN hinhanhtour h
           ON h.MaTour = t.MaTour AND h.LaAnhChinh = 1
    WHERE tk.MaCTKM = $id
      AND t.TrangThai = 'Hoạt động'
    ORDER BY t.DiaDiem ASC, t.TenTour ASC
";
$tour_res = $conn->query($sql_tour);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($ct['TenKM']) ?></title>

    <!-- Bootstrap + FontAwesome -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="./assets/css/chung.css">
    <!-- Dùng lại card từ khuyenmai.css (hero/khác vẫn ok) -->
    <link rel="stylesheet" href="./assets/css/khuyenmai.css">
    <!-- CSS riêng cho trang chi tiết -->
    <link rel="stylesheet" href="./assets/css/khuyenmaichitiet.css">
</head>

<body>

    <?php
    require_once __DIR__ . "/../app/includes/header.php";
    require_once __DIR__ . "/../app/includes/social-bar.php";
    ?>

    <div class="container kmct-wrapper">

        <!-- HERO CTKM -->
        <div class="row kmct-hero g-4 align-items-start">
            <div class="col-md-5">
                <div class="kmct-hero-imgbox">
                    <img src="assets/<?= htmlspecialchars($ct['AnhDaiDien']) ?>" class="kmct-hero-img" alt="">
                    <span class="kmct-hero-badge">
                        -<?= (int)$ct['PhanTramGiam'] ?>%
                    </span>
                </div>
            </div>

            <div class="col-md-7">
                <h1 class="kmct-title">
                    <?= htmlspecialchars($ct['TenKM']) ?>
                </h1>

                <p class="kmct-time">
                    <i class="fa-regular fa-calendar-days me-1"></i>
                    Từ <?= $tu_ngay ?> – đến <?= $den_ngay ?>
                </p>

                <p class="kmct-desc mt-3">
                    <?= nl2br(htmlspecialchars($ct['NoiDung'])) ?>
                </p>
            </div>
        </div>

        <!-- DANH SÁCH TOUR ÁP DỤNG -->
        <div class="kmct-tour-section mt-5">
            <h3 class="kmct-tour-title text-center mb-4">
                CÁC TOUR ĐANG ÁP DỤNG KHUYẾN MÃI NÀY
            </h3>

            <?php
            if (!$tour_res) {
                echo "<div class='text-danger'>Lỗi SQL: " . $conn->error . "</div>";
            } elseif ($tour_res->num_rows == 0) {
                echo "<p class='text-center text-muted mb-5'>
                    Hiện chưa có tour nào áp dụng chương trình này.
                  </p>";
            } else {
            ?>
                <div class="row g-4">
                    <?php while ($t = $tour_res->fetch_assoc()):
                        // Giá đang bán (GiaGiam) + giảm thêm từ CTKM
                        $gia_goc = (float)$t['GiaGoc'];
                        $pt_km           = (float)$t['KMThem'];
                        $gia_sau         = $gia_goc * (100 - $pt_km) / 100;

                        $anh = !empty($t['AnhChinh']) ? $t['AnhChinh'] : './img/no-image.jpg';
                    ?>
                        <div class="col-md-4">
                            <!-- CARD TOUR: giống mẫu bạn gửi -->
                            <div class="kmct-tour-card2">
                                <div class="kmct-tour-imgbox2">
                                    <img src="assets/<?= htmlspecialchars($anh) ?>" class="kmct-tour-img2" alt="">

                                    <?php if ($pt_km > 0): ?>
                                        <span class="kmct-tour-badge2">-<?= (int)$pt_km ?>%</span>
                                    <?php endif; ?>
                                </div>

                                <div class="kmct-tour-body2">
                                    <h5 class="kmct-tour-title2">
                                        <?= htmlspecialchars($t['TenTour']) ?>
                                    </h5>

                                    <p class="kmct-tour-place2">
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                        <?= htmlspecialchars($t['DiaDiem']) ?>
                                    </p>

                                    <p class="kmct-tour-price2">
                                        <span class="kmct-price-new2">
                                            <?= number_format($gia_sau, 0, ',', '.') ?> VNĐ
                                        </span>

                                        <?php if ($pt_km > 0): ?>
                                            <span class="kmct-price-old2">
                                                <?= number_format($gia_goc, 0, ',', '.') ?> VNĐ
                                            </span>
                                        <?php endif; ?>
                                    </p>

                                    <!-- Mình thêm &ctkm= để lát bạn xử lý giá theo CTKM khi vào chi tiết -->
                                    <a href="chitiet_tour.php?id=<?= (int)$t['MaTour'] ?>&ctkm=<?= (int)$id ?>"
                                        class="kmct-btn-outline2">
                                        ĐẶT TOUR
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php } ?>
        </div>

    </div>

    <?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

</body>

</html>