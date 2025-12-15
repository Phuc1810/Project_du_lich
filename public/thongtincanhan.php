<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) {
  header("Location: auth.php?tab=login&redirect=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

$errors = [];
$success = false;

/**
 * Lấy KhachHang theo MaTK, nếu chưa có thì tạo tối thiểu
 */
$stmt = $conn->prepare("SELECT MaKH, HoTen, Email, SoDienThoai, DiaChi, NgaySinh, GioiTinh FROM KhachHang WHERE MaTK=? LIMIT 1");
$stmt->bind_param("i", $matk);
$stmt->execute();
$kh = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kh) {
  $hoten = (string)($_SESSION['user']['HoTen'] ?? '');
  $email = (string)($_SESSION['user']['Email'] ?? '');
  $sdt   = (string)($_SESSION['user']['SoDienThoai'] ?? '');
  $diachi = '';

  $stmt = $conn->prepare("INSERT INTO KhachHang (HoTen, Email, SoDienThoai, DiaChi, MaTK) VALUES (?,?,?,?,?)");
  $stmt->bind_param("ssssi", $hoten, $email, $sdt, $diachi, $matk);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("SELECT MaKH, HoTen, Email, SoDienThoai, DiaChi, NgaySinh, GioiTinh FROM KhachHang WHERE MaTK=? LIMIT 1");
  $stmt->bind_param("i", $matk);
  $stmt->execute();
  $kh = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$makh = (int)($kh['MaKH'] ?? 0);
if ($makh <= 0) die("Không lấy được MaKH.");

/**
 * Dữ liệu hiện tại (DB) + dữ liệu hiển thị form
 */
$cur = [
  'HoTen'       => (string)($kh['HoTen'] ?? ''),
  'Email'       => (string)($kh['Email'] ?? ''),
  'SoDienThoai' => (string)($kh['SoDienThoai'] ?? ''),
  'DiaChi'      => (string)($kh['DiaChi'] ?? ''),
  'NgaySinh'    => (string)($kh['NgaySinh'] ?? ''),
  'GioiTinh'    => (string)($kh['GioiTinh'] ?? ''),
];

$old = $cur;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $old['HoTen']       = trim($_POST['HoTen'] ?? '');
  $old['Email']       = trim($_POST['Email'] ?? '');
  $old['SoDienThoai'] = trim($_POST['SoDienThoai'] ?? '');
  $old['DiaChi']      = trim($_POST['DiaChi'] ?? '');
  $old['NgaySinh']    = trim($_POST['NgaySinh'] ?? '');
  $old['GioiTinh']    = trim($_POST['GioiTinh'] ?? '');

  $isChanged = function(string $k) use ($old, $cur) {
    return (string)$old[$k] !== (string)$cur[$k];
  };

  // ✅ Validate CHỈ khi user thay đổi field đó

  if ($isChanged('Email')) {
    if ($old['Email'] !== '' && !filter_var($old['Email'], FILTER_VALIDATE_EMAIL)) {
      $errors[] = "Email không hợp lệ.";
    }
  }

  if ($isChanged('SoDienThoai')) {
    if ($old['SoDienThoai'] !== '' && !preg_match('/^\d{10}$/', $old['SoDienThoai'])) {
      $errors[] = "SĐT phải đúng 10 số (vd: 0xxxxxxxxx).";
    }
  }

  if ($isChanged('NgaySinh')) {
    if ($old['NgaySinh'] !== '') {
      $today = date('Y-m-d');
      if ($old['NgaySinh'] >= $today) {
        $errors[] = "Ngày sinh không được là ngày hiện tại hoặc tương lai.";
      }
    }
  }

  if ($isChanged('GioiTinh')) {
    $validGenders = ['Nam','Nữ','Khác',''];
    if (!in_array($old['GioiTinh'], $validGenders, true)) {
      $errors[] = "Giới tính không hợp lệ.";
    }
  }

  // HoTen / DiaChi: bạn không yêu cầu ràng buộc => không validate

  if (empty($errors)) {
    try {
      $sqlUp = "
        UPDATE KhachHang
        SET
          HoTen       = NULLIF(?, ''),
          Email       = NULLIF(?, ''),
          SoDienThoai = NULLIF(?, ''),
          DiaChi      = NULLIF(?, ''),
          NgaySinh    = NULLIF(?, ''),
          GioiTinh    = NULLIF(?, '')
        WHERE MaKH=? LIMIT 1
      ";
      $stmt = $conn->prepare($sqlUp);
      $stmt->bind_param(
        "ssssssi",
        $old['HoTen'],
        $old['Email'],
        $old['SoDienThoai'],
        $old['DiaChi'],
        $old['NgaySinh'],
        $old['GioiTinh'],
        $makh
      );
      $stmt->execute();
      $stmt->close();

      // reload để hiển thị đúng dữ liệu mới
      $stmt = $conn->prepare("SELECT HoTen, Email, SoDienThoai, DiaChi, NgaySinh, GioiTinh FROM KhachHang WHERE MaKH=? LIMIT 1");
      $stmt->bind_param("i", $makh);
      $stmt->execute();
      $kh = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      $old = [
        'HoTen'       => (string)($kh['HoTen'] ?? ''),
        'Email'       => (string)($kh['Email'] ?? ''),
        'SoDienThoai' => (string)($kh['SoDienThoai'] ?? ''),
        'DiaChi'      => (string)($kh['DiaChi'] ?? ''),
        'NgaySinh'    => (string)($kh['NgaySinh'] ?? ''),
        'GioiTinh'    => (string)($kh['GioiTinh'] ?? ''),
      ];

      if ($old['HoTen'] !== '') $_SESSION['user']['HoTen'] = $old['HoTen'];

      $success = true;

    } catch (Throwable $e) {
      $errors[] = "Lỗi cập nhật hồ sơ: " . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Thông tin cá nhân</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">

  <style>
    body{ background:#f6f8fb; }
    .wrap{ padding-top:150px; padding-bottom:50px; }
    .cardx{ border:0; border-radius:20px; background:#fff; box-shadow:0 14px 40px rgba(16,24,40,.10); }
    .title{ font-size:26px; font-weight:1000; }
    .muted{ color:#64748b; }
    .divider{ height:1px; background:rgba(15,23,42,.08); margin:16px 0; }
    .form-control, .form-select{ border-radius:12px; }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container wrap">
  <div class="cardx p-4 p-lg-5">

    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
      <div>
        <div class="title"><i class="fa-regular fa-id-card me-2"></i>Thông tin cá nhân</div>
        <div class="muted mt-1">Bạn có thể cập nhật hồ sơ. Nhấn lưu dù không thay đổi gì vẫn được.</div>
      </div>
      <a class="btn btn-outline-secondary" href="trangchu.php">
        <i class="fa-solid fa-house me-1"></i> Trang chủ
      </a>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success mt-3 mb-0">
        <i class="fa-solid fa-circle-check me-2"></i> Lưu thông tin thành công!
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
      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label fw-semibold">Họ tên</label>
          <input class="form-control" name="HoTen" value="<?= h($old['HoTen']) ?>" placeholder="Nhập họ tên">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Email</label>
          <input class="form-control" name="Email" value="<?= h($old['Email']) ?>" placeholder="vd: abc@gmail.com">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Số điện thoại</label>
          <input class="form-control" name="SoDienThoai" value="<?= h($old['SoDienThoai']) ?>" placeholder="vd: 0xxxxxxxxx" inputmode="numeric">
          <div class="form-text">Nếu bạn nhập/đổi SĐT thì phải đủ 10 số.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Giới tính</label>
          <select class="form-select" name="GioiTinh">
            <option value="" <?= ($old['GioiTinh']===''?'selected':'') ?>>-- Chọn --</option>
            <option value="Nam" <?= ($old['GioiTinh']==='Nam'?'selected':'') ?>>Nam</option>
            <option value="Nữ" <?= ($old['GioiTinh']==='Nữ'?'selected':'') ?>>Nữ</option>
            <option value="Khác" <?= ($old['GioiTinh']==='Khác'?'selected':'') ?>>Khác</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Ngày sinh</label>
          <input class="form-control" type="date" name="NgaySinh"
                 max="<?= date('Y-m-d', strtotime('-1 day')) ?>"
                 value="<?= h($old['NgaySinh']) ?>">
          <div class="form-text">Nếu bạn chọn/đổi ngày sinh thì không được là hôm nay hoặc tương lai.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Địa chỉ</label>
          <input class="form-control" name="DiaChi" value="<?= h($old['DiaChi']) ?>" placeholder="Nhập địa chỉ">
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button class="btn btn-primary btn-lg px-4" type="submit">
            <i class="fa-solid fa-floppy-disk me-2"></i> Lưu thay đổi
          </button>
        </div>

      </div>
    </form>

    <div class="divider"></div>
    <div class="muted small">
      Hệ thống chỉ kiểm tra ràng buộc ở những trường bạn thay đổi.
    </div>

  </div>
</div>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
