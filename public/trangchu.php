<?php require_once __DIR__ . "/../app/config/config.php";; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trang chủ - TourDuLich</title>

  <!-- CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;700&family=Montserrat:wght@600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/trangchu.css">
</head>
<body class="page-home">

<!-- =======================================================
                        HEADER
======================================================== -->
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<!-- =======================================================
                        BANNER (ĐỘNG)
======================================================== -->
<div id="anh_truot" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-inner">

  <?php
    $sql = "SELECT t.MaTour, t.TenTour, t.GiaGiam, h.DuongDan
            FROM tour t 
            JOIN hinhanhtour h ON t.MaTour = h.MaTour
            WHERE h.LoaiAnh = 'banner'
            ORDER BY h.MaAnh ASC
            LIMIT 4";

    $banner = $conn->query($sql);
    $first = true;

    while ($row = $banner->fetch_assoc()):
    
  ?>

    <div class="carousel-item <?= $first ? 'active' : '' ?>"
         style="background-image:url('assets/<?= $row['DuongDan'] ?>'); 
                background-size:cover; background-position:center; height:100vh;">
      <div class="lop_mo">
        <div class="noi_dung_banner text-white">
          <h1 class="fw-bold"><?= $row['TenTour'] ?></h1>
          <h4 class="fw-bold"><?= number_format($row['GiaGiam']) ?> VNĐ</h4>
          <a href="chitiet_tour.php?id=<?= $row['MaTour'] ?>" class="btn btn-outline-light mt-3">ĐẶT NGAY</a>
        </div>
      </div>
    </div>

  <?php 
    $first = false;
    endwhile;
  ?>

  </div>
</div>

<!-- =======================================================
                THANH MẠNG XÃ HỘI
======================================================== -->
<div class="thanh_mxh">
  <a href="#" class="nut_mxh"><i class="fa-solid fa-calendar-days"></i></a>
  <a href="#" class="nut_mxh"><i class="fa-solid fa-phone"></i></a>
  <a href="#" class="nut_mxh"><i class="fa-brands fa-facebook-messenger"></i></a>
  <a href="#" class="nut_mxh"><i class="fa-brands fa-zalo"></i></a>
  <a href="#" class="nut_mxh"><i class="fa-solid fa-map-location-dot"></i></a>
  <a href="#" id="ve_dau_trang" class="nut_mxh"><i class="fa-solid fa-arrow-up"></i></a>
</div>

<!-- =======================================================
                SEARCH BOX NÂNG CAO
======================================================== -->
<?php
  // LẤY DANH SÁCH ĐỊA ĐIỂM KHÁC NHAU
  $sql_dd = "SELECT DISTINCT DiaDiem FROM tour WHERE TrangThai = 'Hoạt động' ORDER BY DiaDiem";
  $dsDiaDiem = $conn->query($sql_dd);
?>

<section class="advanced-search container mt-5">
  <form id="formAdvanceSearch" class="search-box shadow" method="GET" action="timkiemnangcao.php">

    <!-- ĐỊA ĐIỂM: input + datalist (gợi ý theo CSDL) -->
    <div class="search-item position-relative">
      <label><i class="fa-solid fa-location-dot"></i> Địa điểm</label>

      <input type="text"
             id="dia_diem"
             name="dia_diem"
             class="search-location-input"
             placeholder="Nhập hoặc chọn địa điểm..."
             autocomplete="off">

      <div id="suggest-dia-diem" class="suggest-location-box d-none"></div>
      <datalist id="ds_dia_diem">
        <?php if($dsDiaDiem && $dsDiaDiem->num_rows > 0): ?>
          <?php while($rowDD = $dsDiaDiem->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($rowDD['DiaDiem']) ?>"></option>
          <?php endwhile; ?>
        <?php endif; ?>
      </datalist>
    </div>

    <!-- NGÀY KHỞI HÀNH -->
    <div class="search-item">
      <label><i class="fa-solid fa-calendar-days"></i> Ngày khởi hành</label>
      <input type="date" id="ngay_khoi_hanh" name="ngay_khoi_hanh">
    </div>

    <!-- THỜI LƯỢNG -->
    <div class="search-item">
      <label><i class="fa-solid fa-clock"></i> Thời lượng</label>
      <select id="thoi_luong" name="thoi_luong">
        <option value="">-- Chọn thời lượng --</option>
        <option value="1N">1N</option>
        <option value="2N1Đ">2N1Đ</option>
        <option value="3N2Đ">3N2Đ</option>
        <option value="4N3Đ">4N3Đ</option>
      </select>
    </div>

    <!-- GIÁ -->
    <div class="search-item">
      <label><i class="fa-solid fa-money-bill"></i> Giá</label>
      <select id="gia" name="gia">
        <option value="">-- Chọn giá --</option>
        <option value="1">Dưới 1 triệu</option>
        <option value="2">1–2 triệu</option>
        <option value="3">2–3 triệu</option>
        <option value="4">Trên 3 triệu</option>
      </select>
    </div>

    <!-- NÚT TÌM KIẾM -->
    <div class="search-btn-box">
      <button id="btnTimKiem" type="submit" disabled>
        <i class="fa-solid fa-magnifying-glass"></i> Tìm kiếm
      </button>
    </div>

  </form>
</section>

<!-- =======================================================
              TOUR NỔI BẬT TRONG THÁNG (ĐỘNG)
======================================================== -->
<section class="py-5 bg-light" id="tour_noi_bat">
  <div class="container-fluid custom-padding">

    <h2 class="fw-bold text-center mb-4">TOUR NỔI BẬT TRONG THÁNG</h2>

    <div id="tourSlider" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">

      <?php
        $sql = "SELECT t.MaTour, t.TenTour, t.GiaGiam, h.DuongDan
                FROM tour t
                JOIN hinhanhtour h ON t.MaTour = h.MaTour 
                WHERE h.LoaiAnh = 'noibat'
                ORDER BY t.MaTour ASC
                LIMIT 8";

        $res = $conn->query($sql);
        $tours = $res->fetch_all(MYSQLI_ASSOC);
        $slides = array_chunk($tours, 4);
      ?>

      <?php foreach ($slides as $index => $group): ?>
        <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
          <div class="row g-4">

            <?php foreach ($group as $tour): ?>
            <div class="col-md-3">
              <div class="tour-card">
                <div class="tour-img">
                  <img src="assets/<?= $tour['DuongDan'] ?>" alt="">
                  <div class="tour-overlay">
                    <h5 class="tour-title"><?= $tour['TenTour'] ?></h5>
                    <p class="tour-price">Giá: <?= number_format($tour['GiaGiam']) ?> VNĐ</p>
                    <a href="chitiet_tour.php?id=<?= $tour['MaTour'] ?>" class="btn datngay-btn">ĐẶT NGAY</a>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>

          </div>
        </div>
      <?php endforeach; ?>

      </div>

      <button class="carousel-control-prev" type="button" data-bs-target="#tourSlider" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
      </button>

      <button class="carousel-control-next" type="button" data-bs-target="#tourSlider" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
      </button>

    </div>

  </div>
</section>

<!-- =======================================================
              TOUR KHUYẾN MÃI (ĐỘNG)
======================================================== -->
<section class="py-5 bg-light" id="tour_khuyen_mai">
  <div class="container">
    <h2 class="fw-bold text-center mb-4">TOUR KHUYẾN MÃI NỔI BẬT</h2>

    <div class="row g-4">

    <?php
     // Dùng MAX(h.DuongDan) hoặc ANY_VALUE(h.DuongDan)
    $sql = "SELECT t.MaTour, t.TenTour, t.GiaGoc, t.GiaGiam, MAX(h.DuongDan) as DuongDan
        FROM tour t
        JOIN hinhanhtour h ON t.MaTour = h.MaTour AND h.LaAnhChinh = 1 
        WHERE t.PhanTramGiam >= 20 AND t.TrangThai = 'Hoạt động'
        GROUP BY t.MaTour
        LIMIT 9";

      $km = $conn->query($sql);

      while ($t = $km->fetch_assoc()):
        $discount = 100 - round($t['GiaGiam'] / $t['GiaGoc'] * 100);
    ?>

      <div class="col-md-4">
        <div class="km-card">
          <img src="assets/<?= $t['DuongDan'] ?>" class="km-img">
          <span class="km-discount">-<?= $discount ?>%</span>

          <div class="km-overlay">
            <h5 class="km-title"><?= $t['TenTour'] ?></h5>
            <p class="km-old"><?= number_format($t['GiaGoc']) ?>đ</p>
            <p class="km-new"><?= number_format($t['GiaGiam']) ?>đ</p>
            <a href="chitiet_tour.php?id=<?= $t['MaTour'] ?>" class="btn km-btn">ĐẶT TOUR</a>
          </div>
        </div>
      </div>

    <?php endwhile; ?>

    </div>
  </div>
</section>

<!-- ==========================================================
                Blog
=========================================================== -->
<section class="py-5 bg-white" id="blog">
  <div class="container">

    <h2 class="fw-bold text-center mb-4">BLOG</h2>

    <div class="row g-4 mb-4">

      <?php
        $hero1 = $conn->query("SELECT AnhDaiDien FROM tintuc WHERE LoaiTin='tintuc' AND TrangThai='Hiển thị' ORDER BY NgayDang DESC LIMIT 1")->fetch_assoc();
        $hero2 = $conn->query("SELECT AnhDaiDien FROM tintuc WHERE LoaiTin='kinhnghiem' AND TrangThai='Hiển thị' ORDER BY NgayDang DESC LIMIT 1")->fetch_assoc();
      ?>

      <div class="col-md-6">
        <div class="blog-hero-card">
          <img src="assets/<?= $hero1['AnhDaiDien'] ?>" class="blog-hero-img">
          <div class="blog-hero-overlay">
            <h3 class="blog-hero-title">TIN TỨC DU LỊCH</h3>
            <p class="blog-hero-desc">Cập nhật thông tin mới nhất về xu hướng du lịch.</p>
            <a href="tintuc.php?loai=tintuc" class="blog-hero-btn">Đọc thêm</a>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="blog-hero-card">
          <img src="assets/<?= $hero2['AnhDaiDien'] ?>" class="blog-hero-img">
          <div class="blog-hero-overlay">
            <h3 class="blog-hero-title">KINH NGHIỆM DU LỊCH</h3>
            <p class="blog-hero-desc">Những bí quyết hữu ích cho chuyến đi hoàn hảo.</p>
            <a href="tintuc.php?loai=kinhnghiem" class="blog-hero-btn">Đọc thêm</a>
          </div>
        </div>
      </div>

    </div>


    <!-- Slider tin tức -->
    <div id="blogSlider" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">

        <?php
          $tin = $conn->query("SELECT MaTin, TieuDe, MoTa, AnhDaiDien, LoaiTin
          FROM tintuc 
          WHERE TrangThai='Hiển thị' 
          ORDER BY NgayDang DESC
          LIMIT 6")->fetch_all(MYSQLI_ASSOC);
          $slides = array_chunk($tin, 3);
        ?>

        <?php foreach ($slides as $index => $group): ?>
          <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
            <div class="row g-4">

              <?php foreach ($group as $t): ?>
              <div class="col-md-4">
                <div class="blog-mini-card">
                  <div class="blog-mini-img-box">
                    <img src="assets/<?= $t['AnhDaiDien'] ?>" class="blog-mini-img">
                  </div>

                  <div class="blog-mini-body">
                    <h5 class="blog-mini-title"><?= $t['TieuDe'] ?></h5>

                    <p class="blog-mini-desc">
                      <?= substr($t['MoTa'],0,120) ?>...
                    </p>

                    <a href="chitiettin.php?id=<?= $t['MaTin'] ?>&loai=<?= $t['LoaiTin'] ?>" class="btn-xemthem"> Xem thêm </a>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>

            </div>
          </div>
        <?php endforeach; ?>

      </div>

      <button class="carousel-control-prev" type="button" data-bs-target="#blogSlider" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#blogSlider" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
      </button>

    </div>

  </div>
</section>

<!-- =======================================================
                        FOOTER 
======================================================== -->
<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>



<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/search-filter.js"></script>

<script>
  const heroCarousel = document.querySelector('#anh_truot');
  new bootstrap.Carousel(heroCarousel, {
    interval: 4000,
    ride: 'carousel',
    pause: false,
    wrap: true
  });
</script>

</body>
</html>
