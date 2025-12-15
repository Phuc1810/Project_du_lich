<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function flash_back($tab, $errors, $old){
  $_SESSION['flash'] = [
    'tab' => $tab,
    'errors' => $errors,
    'old' => $old
  ];
  header("Location: auth.php?tab=".$tab);
  exit;
}

function is_email($s){ return filter_var($s, FILTER_VALIDATE_EMAIL); }
function is_phone10($s){ return preg_match('/^\d{10}$/', $s); }

$redirect = $_POST['redirect'] ?? 'trangchu.php';

$hoten    = trim($_POST['hoten'] ?? '');
$contact  = trim($_POST['contact'] ?? '');
$diachi   = trim($_POST['diachi'] ?? '');
$ngaysinh = trim($_POST['ngaysinh'] ?? '');
$gioitinh = trim($_POST['gioitinh'] ?? '');
$pass     = $_POST['password'] ?? '';

$old = [
  'hoten' => $hoten,
  'contact' => $contact,
  'diachi' => $diachi,
  'ngaysinh' => $ngaysinh,
  'gioitinh' => $gioitinh
];

$errors = [];

/* 1) Validate họ tên */
if ($hoten === '') $errors['hoten'] = "Họ tên không được để trống.";
else {
  $words = preg_split('/\s+/', trim($hoten));
  $words = array_values(array_filter($words));
  if (count($words) < 2) $errors['hoten'] = "Họ tên phải có ít nhất 2 từ.";
}

/* 2) Validate contact = email hoặc sdt */
$email = null;
$sdt   = null;

if ($contact === '') {
  $errors['contact'] = "Vui lòng nhập Email hoặc SĐT.";
} else {
  if (is_email($contact)) {
    if (!preg_match('/@gmail\.com$/i', $contact)) {
      $errors['contact'] = "Email phải có đuôi @gmail.com.";
    } else {
      $email = $contact;
    }
  } elseif (is_phone10($contact)) {
    $sdt = $contact;
  } else {
    $errors['contact'] = "Email/SĐT không hợp lệ.";
  }
}

/* 3) Validate địa chỉ */
if ($diachi === '') $errors['diachi'] = "Địa chỉ không được để trống.";

/* 4) Validate ngày sinh: bắt buộc và phải < hôm nay */
if ($ngaysinh === '') {
  $errors['ngaysinh'] = "Vui lòng chọn ngày sinh.";
} else {
  $tDob = strtotime($ngaysinh);
  $tToday = strtotime(date('Y-m-d'));
  if ($tDob === false) $errors['ngaysinh'] = "Ngày sinh không hợp lệ.";
  else if ($tDob >= $tToday) $errors['ngaysinh'] = "Ngày sinh phải nhỏ hơn ngày hiện tại.";
}

/* 5) Validate giới tính */
if ($gioitinh === '') $errors['gioitinh'] = "Vui lòng chọn giới tính.";
else if (!in_array($gioitinh, ['Nam','Nữ'], true)) $errors['gioitinh'] = "Giới tính không hợp lệ.";

/* 6) Validate password */
if ($pass === '') $errors['password'] = "Mật khẩu không được để trống.";
else {
  if (strlen($pass) < 8) $errors['password'] = "Mật khẩu phải >= 8 ký tự.";
  else if (!preg_match('/[a-z]/', $pass)) $errors['password'] = "Mật khẩu phải có chữ thường.";
  else if (!preg_match('/[A-Z]/', $pass)) $errors['password'] = "Mật khẩu phải có chữ hoa.";
  else if (!preg_match('/\d/', $pass)) $errors['password'] = "Mật khẩu phải có số.";
  else if (!preg_match('/[^a-zA-Z0-9]/', $pass)) $errors['password'] = "Mật khẩu phải có ký tự đặc biệt.";
}

if ($errors) flash_back('register', $errors, $old);

/* TenDangNhap: ưu tiên email nếu có, không thì sdt */
$loginKey = $email ?? $sdt;

/* 7) Check trùng TenDangNhap trong TaiKhoan */
$stmt = $conn->prepare("SELECT 1 FROM TaiKhoan WHERE TenDangNhap=? LIMIT 1");
$stmt->bind_param("s", $loginKey);
$stmt->execute();
$existsTK = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existsTK) {
  $errors['contact'] = "Email/SĐT này đã được dùng để đăng ký.";
  flash_back('register', $errors, $old);
}

/* 8) Check trùng Email/SĐT trong KhachHang */
$e = $email ?? '';
$p = $sdt ?? '';
$sqlDup = "
  SELECT Email, SoDienThoai
  FROM KhachHang
  WHERE (Email = ? AND ? <> '')
     OR (SoDienThoai = ? AND ? <> '')
  LIMIT 1
";
$stmt = $conn->prepare($sqlDup);
$stmt->bind_param("ssss", $e, $e, $p, $p);
$stmt->execute();
$dup = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($dup) {
  if (!empty($email) && strcasecmp($dup['Email'] ?? '', $email) === 0) $errors['contact'] = "Email đã tồn tại.";
  if (!empty($sdt) && ($dup['SoDienThoai'] ?? '') === $sdt) $errors['contact'] = "Số điện thoại đã tồn tại.";
  flash_back('register', $errors, $old);
}

/* 9) Insert */
$conn->begin_transaction();

try {
  $hash = password_hash($pass, PASSWORD_BCRYPT);

  // TaiKhoan
  $sqlTK = "INSERT INTO TaiKhoan (TenDangNhap, MatKhau, VaiTro, TrangThai, Provider, GoogleSub)
            VALUES (?, ?, 'KH', 'Hoạt động', 'local', NULL)";
  $stmt = $conn->prepare($sqlTK);
  $stmt->bind_param("ss", $loginKey, $hash);
  $stmt->execute();
  $maTK = $stmt->insert_id;
  $stmt->close();

  // KhachHang (Email hoặc SĐT có thể NULL)
  $sqlKH = "INSERT INTO KhachHang (HoTen, Email, SoDienThoai, DiaChi, NgaySinh, GioiTinh, MaTK)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sqlKH);
  $stmt->bind_param("ssssssi", $hoten, $email, $sdt, $diachi, $ngaysinh, $gioitinh, $maTK);
  $stmt->execute();
  $stmt->close();

  $conn->commit();

  $_SESSION['flash'] = [
    'view' => 'register_success',
    'success' => 'Đăng ký thành công! Vui lòng đăng nhập để tiếp tục.',
    'email' => ($email ?? $loginKey),
  ];

  header("Location: auth.php?view=register_success&redirect=" . urlencode($redirect));
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  $errors['form'] = "Đăng ký thất bại, vui lòng thử lại.";
  flash_back('register', $errors, $old);
}
