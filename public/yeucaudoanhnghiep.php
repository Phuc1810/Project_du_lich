<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) {
  header("Location: auth.php?tab=login&redirect=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

/** TOUR từ query ?tour= */
$maTour = isset($_GET['tour']) ? (int)$_GET['tour'] : 0;
if ($maTour <= 0) {
  header("Location: tour_doanhnghiep.php");
  exit;
}

/** 1) Load tour DN + ảnh chính */
$sqlTour = "
  SELECT
    t.MaTour, t.TenTour, t.DiaDiem, t.ThoiLuong,
    t.GiaGoc, t.GiaGiam, t.PhanTramGiam,
    h.DuongDan AS AnhChinh
  FROM tour t
  LEFT JOIN hinhanhtour h ON h.MaTour=t.MaTour AND h.LaAnhChinh=1
  WHERE t.MaTour=? AND t.LoaiTour='Doanh nghiệp'
  LIMIT 1
";
$stmt = $conn->prepare($sqlTour);
$stmt->bind_param("i", $maTour);
$stmt->execute();
$tour = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tour) {
  header("Location: tour_doanhnghiep.php");
  exit;
}

/** 2) Lấy Khách Hàng theo MaTK (nếu chưa có thì tạo nhanh) */
$stmt = $conn->prepare("SELECT MaKH, HoTen, Email, SoDienThoai FROM khachhang WHERE MaTK=? LIMIT 1");
$stmt->bind_param("i", $matk);
$stmt->execute();
$kh = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kh) {
  $hoten = (string)($_SESSION['user']['HoTen'] ?? '');
  $email = (string)($_SESSION['user']['Email'] ?? '');
  $sdt   = (string)($_SESSION['user']['SoDienThoai'] ?? '');

  $stmt = $conn->prepare("INSERT INTO khachhang (HoTen, Email, SoDienThoai, DiaChi, MaTK) VALUES (?,?,?,?,?)");
  $diachi = '';
  $stmt->bind_param("ssssi", $hoten, $email, $sdt, $diachi, $matk);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("SELECT MaKH, HoTen, Email, SoDienThoai FROM khachhang WHERE MaTK=? LIMIT 1");
  $stmt->bind_param("i", $matk);
  $stmt->execute();
  $kh = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$makh = (int)($kh['MaKH'] ?? 0);
if ($makh <= 0) die("Không lấy được MaKH.");

/** 3) Default form */
$errors = [];
$old = [
  'MaTour'       => $maTour,
  'TenCongTy'    => '',
  'NguoiLienHe'  => (string)($kh['HoTen'] ?? ''),
  'SDT'          => (string)($kh['SoDienThoai'] ?? ''),
  'SoNguoi'      => '',
  'ThoiGianKhoiHanh' => '',
];

/** POST submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old['MaTour'] = isset($_POST['MaTour']) ? (int)$_POST['MaTour'] : $maTour;
  $old['TenCongTy'] = trim($_POST['TenCongTy'] ?? '');
  $old['NguoiLienHe'] = trim($_POST['NguoiLienHe'] ?? '');
  $old['SDT'] = trim($_POST['SDT'] ?? '');
  $old['SoNguoi'] = trim($_POST['SoNguoi'] ?? '');
  $old['ThoiGianKhoiHanh'] = trim($_POST['ThoiGianKhoiHanh'] ?? '');

  // Validate bắt buộc
  if ($old['TenCongTy'] === '') $errors[] = "Vui lòng nhập Tên công ty.";
  if ($old['NguoiLienHe'] === '') $errors[] = "Vui lòng nhập Người liên hệ.";

  // SĐT 10 số
  if ($old['SDT'] === '' || !preg_match('/^\d{10}$/', $old['SDT'])) {
    $errors[] = "SĐT phải đủ 10 số (vd: 0xxxxxxxxx).";
  }

  // Số người: >= 20
  if ($old['SoNguoi'] === '' || !ctype_digit($old['SoNguoi'])) {
    $errors[] = "Số người phải là số nguyên.";
  } else {
    $soNguoi = (int)$old['SoNguoi'];
    if ($soNguoi < 20) $errors[] = "Tour doanh nghiệp yêu cầu tối thiểu 20 người.";
  }

  // Ngày khởi hành: bắt buộc, không nhỏ hơn hôm nay
  if ($old['ThoiGianKhoiHanh'] === '') {
    $errors[] = "Vui lòng chọn Thời gian khởi hành.";
  } else {
    $today = date('Y-m-d');
    if ($old['ThoiGianKhoiHanh'] < $today) {
      $errors[] = "Thời gian khởi hành không được nhỏ hơn ngày hiện tại.";
    }
  }

  if (empty($errors)) {
    $conn->begin_transaction();

    try {
      $trangthai = "Chờ xử lý";

      $soNguoi = (int)$old['SoNguoi'];
      $maTour  = (int)$old['MaTour'];

      // 1) Lock tour
      $stmt = $conn->prepare("SELECT SoCho, SoChoDaDat, TrangThai FROM tour WHERE MaTour=? FOR UPDATE");
      $stmt->bind_param("i", $maTour);
      $stmt->execute();
      $tour = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$tour) {
        throw new Exception("Tour không tồn tại.");
      }

      $soCho     = (int)$tour['SoCho'];
      $soChoDaDat = (int)$tour['SoChoDaDat'];

      // 2) Check đủ chỗ (nếu bạn muốn DN vẫn giới hạn chỗ)
      if ($soCho > 0 && ($soChoDaDat + $soNguoi) > $soCho) {
        throw new Exception("Tour không đủ chỗ. Vui lòng chọn tour khác hoặc giảm số người.");
      }

      // 3) INSERT yêu cầu DN
      $sqlIns = "
      INSERT INTO yeucaudoanhnghiep
        (TenCongTy, NguoiLienHe, SDT, SoNguoi, ThoiGianKhoiHanh, TrangThai, MaKH, MaTour)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
    ";
      $stmt = $conn->prepare($sqlIns);
      $stmt->bind_param(
        "sssissii",
        $old['TenCongTy'],
        $old['NguoiLienHe'],
        $old['SDT'],
        $soNguoi,
        $old['ThoiGianKhoiHanh'],
        $trangthai,
        $makh,
        $maTour
      );
      $stmt->execute();
      $newId = $conn->insert_id;
      $stmt->close();

      // 4) Cập nhật số chỗ đã đặt
      $newSoChoDaDat = $soChoDaDat + $soNguoi;
      $stmt = $conn->prepare("UPDATE Tour SET SoChoDaDat=? WHERE MaTour=? LIMIT 1");
      $stmt->bind_param("ii", $newSoChoDaDat, $maTour);
      $stmt->execute();
      $stmt->close();

      // (Tuỳ chọn) nếu đủ chỗ thì chuyển trạng thái tour
      if ($soCho > 0 && $newSoChoDaDat >= $soCho) {
        $stFull = "Hết chỗ";
        $stmt = $conn->prepare("UPDATE Tour SET TrangThai=? WHERE MaTour=? LIMIT 1");
        $stmt->bind_param("si", $stFull, $maTour);
        $stmt->execute();
        $stmt->close();
      }

      $conn->commit();

      header("Location: yeucaudoanhnghiep.php?tour=".(int)$maTour."&success=1&id=".(int)$newId);
      exit;
    } catch (Throwable $e) {
      $conn->rollback();
      $errors[] = "Lỗi lưu yêu cầu: " . $e->getMessage();
    }
  }
}

$success = (isset($_GET['success']) && $_GET['success'] == '1');
$newId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Gửi yêu cầu tour doanh nghiệp</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">

  <style>
    body {
      background: #f6f8fb;
    }

    .wrap {
      padding-top: 150px;
      padding-bottom: 40px;
    }

    .cardx {
      border: 0;
      border-radius: 20px;
      background: #fff;
      box-shadow: 0 14px 40px rgba(16, 24, 40, .10);
    }

    .title {
      font-size: 24px;
      font-weight: 1000;
    }

    .muted {
      color: #64748b;
    }

    .divider {
      height: 1px;
      background: rgba(15, 23, 42, .08);
      margin: 16px 0;
    }

    .form-control {
      border-radius: 12px;
    }

    .tour-mini {
      display: flex;
      gap: 14px;
      align-items: center;
      padding: 14px;
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 16px;
      background: #fff;
    }

    .tour-mini img {
      width: 110px;
      height: 82px;
      object-fit: cover;
      border-radius: 12px;
      background: #e2e8f0;
    }

    .tour-mini .name {
      font-weight: 900;
      font-size: 18px;
      margin-bottom: 2px;
    }

    .tour-mini .meta {
      color: #64748b;
      font-weight: 600;
      font-size: 14px;
    }

    .tour-mini .meta i {
      color: #e11d48;
      margin-right: 6px;
    }
  </style>
</head>

<body>
  <?php require_once __DIR__ . "/../app/includes/header.php"; ?>

  <div class="container wrap">
    <div class="cardx p-4 p-lg-5">

      <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
        <div>
          <div class="title"><i class="fa-solid fa-paper-plane me-2"></i>Gửi yêu cầu tour doanh nghiệp</div>
          <div class="muted mt-1">Tối thiểu <b>20 người</b>. Nhân viên sẽ liên hệ để xác nhận.</div>
        </div>
        <a class="btn btn-outline-secondary" href="tour_doanhnghiep.php">
          <i class="fa-solid fa-arrow-left me-1"></i> Quay lại danh sách
        </a>
      </div>

      <!-- Tour card (không dropdown) -->
      <div class="tour-mini mt-3">
        <?php if (!empty($tour['AnhChinh'])): ?>
          <img src="assets/<?= h($tour['AnhChinh']) ?>" alt="">
        <?php else: ?>
          <img src="" alt="">
        <?php endif; ?>
        <div class="flex-grow-1">
          <div class="name"><?= h($tour['TenTour'] ?? '') ?></div>
          <div class="meta">
            <i class="fa-solid fa-location-dot"></i><?= h($tour['DiaDiem'] ?? '') ?>
            <?php if (!empty($tour['ThoiLuong'])): ?>
              &nbsp; • &nbsp; <i class="fa-regular fa-clock" style="color:#2563eb;"></i><?= h($tour['ThoiLuong']) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success mt-3 mb-0">
          <div class="fw-bold"><i class="fa-solid fa-circle-check me-2"></i>Gửi yêu cầu thành công!</div>
          <?php if ($newId > 0): ?>
            <div class="mt-1">Mã yêu cầu: <strong>#<?= (int)$newId ?></strong></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
          <div class="fw-bold mb-2">Vui lòng kiểm tra lại:</div>
          <ul class="mb-0">
            <?php foreach ($errors as $er): ?>
              <li><?= h($er) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" class="mt-3" novalidate>
        <input type="hidden" name="MaTour" value="<?= (int)$maTour ?>">
        <div class="row g-3">

          <div class="col-md-6">
            <label class="form-label fw-semibold">Tên công ty</label>
            <input class="form-control" name="TenCongTy" value="<?= h($old['TenCongTy']) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Người liên hệ</label>
            <input class="form-control" name="NguoiLienHe" value="<?= h($old['NguoiLienHe']) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Số điện thoại (10 số)</label>
            <input class="form-control" name="SDT" value="<?= h($old['SDT']) ?>" required pattern="^\d{10}$" inputmode="numeric">
            <div class="form-text">Ví dụ: 0xxxxxxxxx</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Số người (tối thiểu 20)</label>
            <input class="form-control" name="SoNguoi" type="number" min="20" value="<?= h($old['SoNguoi']) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Thời gian khởi hành</label>
            <input class="form-control" type="date" name="ThoiGianKhoiHanh" min="<?= date('Y-m-d') ?>" value="<?= h($old['ThoiGianKhoiHanh']) ?>" required>
          </div>

          <div class="col-md-6 d-flex align-items-end justify-content-end">
            <button class="btn btn-primary btn-lg px-4" type="submit">
              <i class="fa-solid fa-paper-plane me-2"></i> Gửi yêu cầu
            </button>
          </div>

        </div>
      </form>

      <div class="divider"></div>
      <div class="muted small">
        Sau khi gửi, hệ thống sẽ chuyển trạng thái <b>Chờ xử lý</b>. Nhân viên sẽ liên hệ lại để chốt chi tiết.
      </div>

    </div>
  </div>

  <?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>