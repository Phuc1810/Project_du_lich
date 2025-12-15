<?php
require_once __DIR__ . "/../app/config/config.php";

// === HÀM BỎ DẤU (giống bên timkiemnhanh.php) ===
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

// LẤY FILTER TỪ FORM
$dia_diem   = isset($_GET['dia_diem'])   ? trim($_GET['dia_diem'])   : "";
$ngay       = isset($_GET['ngay_khoi_hanh']) ? trim($_GET['ngay_khoi_hanh']) : "";
$thoi_luong = isset($_GET['thoi_luong']) ? trim($_GET['thoi_luong']) : "";
$gia        = isset($_GET['gia'])        ? trim($_GET['gia'])        : "";

// BỎ DẤU ĐỊA ĐIỂM (cho tìm không phân biệt dấu)
$dia_khong_dau = boDau($dia_diem);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả lọc tour</title>

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
  require_once __DIR__ . "/../app/includes/social-bar.php";
?>

<div class="container search-result-wrapper">

  <h2 class="fw-bold text-center mb-4">
    KẾT QUẢ LỌC TOUR
  </h2>

  <div class="row g-4">
  <?php
    // ========================
    // BUILD SQL ĐỘNG
    // ========================
    $sql = "
      SELECT t.MaTour, t.TenTour, t.ThoiLuong, t.GiaGiam, t.GiaGoc, t.DiaDiem, t.NgayKhoiHanh,
             h.DuongDan, t.PhanTramGiam
      FROM Tour t
      LEFT JOIN HinhAnhTour h 
             ON t.MaTour = h.MaTour AND h.LaAnhChinh = 1
      WHERE t.TrangThai = 'Hoạt động'
    ";

    $conditions = [];

    // ĐỊA ĐIỂM (so sánh không phân biệt hoa thường + gần như không phân biệt dấu)
    if ($dia_diem !== "") {
        $diaEsc = $conn->real_escape_string(mb_strtolower($dia_diem, 'UTF-8'));

        $conditions[] = "
        CONVERT(LOWER(t.DiaDiem) USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
        LIKE '%$diaEsc%'";
    }

    // NGÀY KHỞI HÀNH (>= ngày chọn, hoặc = tuỳ bạn)
    if ($ngay !== "") {
        $conditions[] = "t.NgayKhoiHanh >= '" . $conn->real_escape_string($ngay) . "'";
    }

    // THỜI LƯỢNG
    if ($thoi_luong !== "") {
        $conditions[] = "t.ThoiLuong = '" . $conn->real_escape_string($thoi_luong) . "'";
    }

    // GIÁ – map theo khoảng
    if ($gia !== "") {
        switch ($gia) {
          case "1": // dưới 1 triệu
            $conditions[] = "t.GiaGiam < 1000000";
            break;
          case "2": // 1–2 triệu
            $conditions[] = "t.GiaGiam >= 1000000 AND t.GiaGiam < 2000000";
            break;
          case "3": // 2–3 triệu
            $conditions[] = "t.GiaGiam >= 2000000 AND t.GiaGiam < 3000000";
            break;
          case "4": // trên 3 triệu
            $conditions[] = "t.GiaGiam >= 3000000";
            break;
        }
    }

    // GỘP CONDITIONS
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    // THỬ THÊM SẮP XẾP CHO ĐẸP
    $sql .= " ORDER BY t.NgayKhoiHanh ASC";

    $res = $conn->query($sql);

    if (!$res) {
        echo "<div class='text-danger'>Lỗi SQL: ".$conn->error."</div>";
    } elseif ($res->num_rows == 0) {
        echo "<h5 class='text-center text-muted'>Không có tour phù hợp...</h5>";
    } else {
        while ($row = $res->fetch_assoc()):
            // tính % giảm nếu cần
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
                    class="btn btn-book w-100 mt-3">ĐẶT TOUR
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
