<?php
require_once __DIR__ . "/../app/config/config.php";
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Bảng giá tour</title>

  <!-- Bootstrap + Font Awesome -->
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- CSS chung + CSS riêng cho bảng giá -->
  <link rel="stylesheet" href="./assets/css/chung.css">
  <link rel="stylesheet" href="./assets/css/banggia.css">
</head>

<body>

  <?php
  require_once __DIR__ . "/../app/includes/header.php";
  require_once __DIR__ . "/../app/includes/header.php";
  ?>

  <div class="container price-wrapper">

    <div class="price-header text-center mb-4">
      <h2 class="fw-bold price-title mb-1">
        BẢNG GIÁ TOUR ĐANG HOẠT ĐỘNG
      </h2>
    </div>

    <div class="table-responsive">
      <table class="table price-table align-middle">
        <thead>
          <tr>
            <th style="width: 5%;">STT</th>
            <th style="width: 35%;">Tên tour</th>
            <th style="width: 20%;">Địa điểm</th>
            <th style="width: 10%;">Thời lượng</th>
            <th style="width: 20%;">Giá</th>
            <th style="width: 10%;" class="text-center">Xem tour</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sql = "
            SELECT MaTour, TenTour, DiaDiem, ThoiLuong, GiaGoc, GiaGiam
            FROM tour
            WHERE TrangThai = 'Hoạt động' and LoaiTour = 'Cá Nhân'
            
          ";

          $res = $conn->query($sql);

          if (!$res) {
            echo "<tr><td colspan='6' class='text-danger'>Lỗi SQL: "
              . $conn->error . "</td></tr>";
          } elseif ($res->num_rows == 0) {
            echo "<tr><td colspan='6' class='text-center text-muted'>
                    Hiện chưa có tour nào đang hoạt động.
                  </td></tr>";
          } else {
            $stt = 1;
            while ($row = $res->fetch_assoc()):
              // Tính phần trăm giảm giá
              $discount = 0;
              if ($row['GiaGoc'] > 0 && $row['GiaGiam'] < $row['GiaGoc']) {
                $discount = round(100 - $row['GiaGiam'] / $row['GiaGoc'] * 100);
              }
          ?>
              <tr class="price-row">
                <td><?= $stt++ ?></td>

                <td class="tour-name-cell">
                  <div class="tour-name">
                    <?= htmlspecialchars($row['TenTour']) ?>
                  </div>
                  <div class="tour-sub">
                    <i class="fa-solid fa-location-dot me-1 text-danger"></i>
                    <?= htmlspecialchars($row['DiaDiem']) ?>
                  </div>
                </td>

                <td class="d-none d-md-table-cell">
                  <span class="badge bg-light text-dark border">
                    <i class="fa-solid fa-map-pin me-1 text-primary"></i>
                    <?= htmlspecialchars($row['DiaDiem']) ?>
                  </span>
                </td>

                <td>
                  <span class="badge duration-badge">
                    <i class="fa-regular fa-clock me-1"></i>
                    <?= htmlspecialchars($row['ThoiLuong']) ?>
                  </span>
                </td>

                <td>
                  <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-1">
                    <span class="price-new">
                      <?= number_format($row['GiaGiam']) ?> VNĐ
                    </span>

                    <?php
                    // tính % giảm để hiện badge (nếu muốn giữ)
                    $discount = 0;
                    if ($row['GiaGoc'] > 0 && $row['GiaGiam'] < $row['GiaGoc']) {
                      $discount = round(100 - $row['GiaGiam'] / $row['GiaGoc'] * 100);
                    }
                    ?>

                    <?php if ($discount > 0): ?>
                      <span class="price-discount-badge">
                        -<?= $discount ?>%
                      </span>
                    <?php endif; ?>
                  </div>
                </td>


                <td class="text-center">
                  <a href="chitiet_tour.php?id=<?= $row['MaTour'] ?>"
                    class="btn btn-view">
                    XEM TOUR
                  </a>
                </td>
              </tr>
          <?php
            endwhile;
          }
          ?>
        </tbody>
      </table>
    </div>

  </div>

  <?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

</body>

</html>