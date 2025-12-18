<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function safe_redirect($url, $fallback = 'trangchu.php') {
  $url = trim((string)$url);
  if ($url === '') return $fallback;
  // chặn open redirect
  if (preg_match('#^(https?:)?//#i', $url)) return $fallback;
  if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) return $fallback;
  return $url;
}

function flash_set($tab, $errors, $old) {
  $_SESSION['flash'] = [
    'tab' => $tab,
    'errors' => $errors,
    'old' => $old,
  ];
}

$redirect = safe_redirect($_POST['redirect'] ?? 'trangchu.php');

$old = [
  'login_key' => trim($_POST['login_key'] ?? ''),
];
$pass = (string)($_POST['password'] ?? '');

$errors = [];

if ($old['login_key'] === '') $errors['login_key'] = "Vui lòng nhập Email hoặc SĐT.";
if ($pass === '') $errors['password'] = "Vui lòng nhập mật khẩu.";

if (!empty($errors)) {
  flash_set('login', $errors, $old);
  header("Location: auth.php?tab=login&redirect=" . urlencode($redirect));
  exit;
}

// ✅ lấy thêm HoTen/Email/SoDienThoai để header hiển thị "Chào bạn HoTen"
$sql = "
  SELECT
    tk.MaTK, tk.TenDangNhap, tk.MatKhau, tk.VaiTro, tk.TrangThai, tk.Provider,
    kh.HoTen AS HoTen, kh.Email AS Email, kh.SoDienThoai AS SoDienThoai
  FROM taikhoan tk
  LEFT JOIN khachhang kh ON kh.MaTK = tk.MaTK
  WHERE tk.Provider = 'local'
    AND (
      tk.TenDangNhap = ?
      OR kh.Email = ?
      OR kh.SoDienThoai = ?
    )
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $old['login_key'], $old['login_key'], $old['login_key']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
  $errors['login_key'] = "Không tìm thấy tài khoản với Email/SĐT này.";
  flash_set('login', $errors, $old);
  header("Location: auth.php?tab=login&err=1&redirect=" . urlencode($redirect));
  exit;
}

if (($user['VaiTro'] ?? '') !== 'KH') {
    $errors['login_key'] = "Tài khoản này là Nhân viên/Admin, vui lòng đăng nhập tại trang quản trị.";
    flash_set('login', $errors, $old);
    header("Location: auth.php?tab=login&err=1&redirect=" . urlencode($redirect));
    exit;
}

if (!empty($user['TrangThai']) && $user['TrangThai'] !== 'Hoạt động') {
  $errors['login_key'] = "Tài khoản đang bị khóa hoặc không hoạt động.";
  flash_set('login', $errors, $old);
  header("Location: auth.php?tab=login&err=1&redirect=" . urlencode($redirect));
  exit;
}

if (!password_verify($pass, $user['MatKhau'])) {
  $errors['password'] = "Mật khẩu không đúng.";
  flash_set('login', $errors, $old);
  header("Location: auth.php?tab=login&err=1&redirect=" . urlencode($redirect));
  exit;
}

// ✅ set session đầy đủ
$_SESSION['user'] = [
  'MaTK' => (int)$user['MaTK'],
  'TenDangNhap' => $user['TenDangNhap'],
  'VaiTro' => $user['VaiTro'] ?? 'KH',
  'Provider' => $user['Provider'] ?? 'local',
  'HoTen' => $user['HoTen'] ?? '',
  'Email' => $user['Email'] ?? '',
  'SoDienThoai' => $user['SoDienThoai'] ?? ''
];

header("Location: " . $redirect);
exit;
