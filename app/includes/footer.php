<footer id="footer-site" class="footer-section bg-white pt-5 pb-3 border-top">
  <div class="container">

    <?php  
      // LẤY THÔNG TIN CÔNG TY
      $sql_cty = "SELECT * FROM congty LIMIT 1";
      $cty = $conn->query($sql_cty)->fetch_assoc();

      // LẤY DANH SÁCH CHI NHÁNH
      $sql_cn = "SELECT * FROM chinhanh WHERE MaCTY = " . $cty['MaCTY'];
      $chinhanh = $conn->query($sql_cn);
    ?>

    <div class="row g-4">

      <!-- ==== CỘT 1: LOGO + TÊN CTY + ICON ==== -->
      <div class="col-lg-3 col-md-6 text-center">

        <img src="assets/<?= $cty['Logo_2'] ?>" alt="Logo" class="footer-logo">

        <h6 class="footer-company-name">
          <?= $cty['TenCongTy'] ?>
        </h6>

        <div class="social-icons">
          <a href="#" class="social-btn"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="mailto:<?= $cty['Email'] ?>" class="social-btn"><i class="fa-solid fa-envelope"></i></a>
          <a href="#" class="social-btn"><i class="fa-solid fa-location-dot"></i></a>
        </div>

      </div>

      <!-- ==== CỘT 2: THÔNG TIN LIÊN HỆ ==== -->
      <div class="col-lg-4 col-md-6">
        <h5 class="footer-title">THÔNG TIN LIÊN HỆ</h5>

        <ul class="list-unstyled footer-contact">
          <li><strong>Địa chỉ :</strong> <?= $cty['DiaChi'] ?></li>
          <li><strong>Điện thoại :</strong> <?= $cty['SoDienThoai'] ?></li>
          <li><strong>Email :</strong> <?= $cty['Email'] ?></li>
        </ul>

        <h6 class="fw-bold mt-3">Chi nhánh:</h6>
        <ul class="list-unstyled">
          <?php while ($cn = $chinhanh->fetch_assoc()): ?>
            <li>
              <i class="fa-solid fa-location-dot"></i>
              <strong><?= $cn['TenChiNhanh'] ?>:</strong> <?= $cn['DiaChi'] ?> — <?= $cn['SDT'] ?>
            </li>
          <?php endwhile; ?>
        </ul>
      </div>

      <!-- ==== CỘT 3: GIỚI THIỆU ==== -->
      <div class="col-lg-2 col-md-6">
        <h5 class="footer-title">GIỚI THIỆU</h5>
        <ul class="list-unstyled footer-links">
          <li><a href="#"><i class="fa-solid fa-angle-right"></i> Hướng dẫn thanh toán</a></li>
          <li><a href="#"><i class="fa-solid fa-angle-right"></i> Hướng dẫn đặt tour</a></li>
          <li><a href="../banggia.php"><i class="fa-solid fa-angle-right"></i> Bảng giá</a></li>
          <li><a href="../khuyenmai.php"><i class="fa-solid fa-angle-right"></i> Chương trình khuyến mãi</a></li>
        </ul>
      </div>

      <!-- ==== CỘT 4: CHÍNH SÁCH ==== -->
      <div class="col-lg-3 col-md-6">
        <h5 class="footer-title">CHÍNH SÁCH</h5>
        <ul class="list-unstyled footer-links">
          <li><a href="#"><i class="fa-solid fa-angle-right"></i> Điều khoản chung</a></li>
        </ul>

        <div class="mt-3">
          <img src="assets/./img/bo-cong-thuong.png" alt="Bộ công thương" width="180">
        </div>
      </div>

    </div>
  </div>
</footer>
