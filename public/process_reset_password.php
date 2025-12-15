<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$rv = $_SESSION['reset_verified'] ?? null;
if (!$rv) { header("Location: forgot_password.php"); exit; }

$new = (string)($_POST['new_password'] ?? '');
$cf  = (string)($_POST['confirm_password'] ?? '');

function pass_valid($p){
  if (strlen($p) < 8) return "Mật khẩu phải >= 8 ký tự.";
  if (!preg_match('/[a-z]/', $p)) return "Mật khẩu phải có chữ thường.";
  if (!preg_match('/[A-Z]/', $p)) return "Mật khẩu phải có chữ hoa.";
  if (!preg_match('/\d/', $p)) return "Mật khẩu phải có số.";
  if (!preg_match('/[^a-zA-Z0-9]/', $p)) return "Mật khẩu phải có ký tự đặc biệt.";
  return '';
}

if ($new === '' || $cf === '') {
  $_SESSION['flash_rp'] = ['error' => 'Vui lòng nhập đầy đủ mật khẩu mới.'];
  header("Location: reset_password.php");
  exit;
}

$err = pass_valid($new);
if ($err !== '') {
  $_SESSION['flash_rp'] = ['error' => $err];
  header("Location: reset_password.php");
  exit;
}

if ($new !== $cf) {
  $_SESSION['flash_rp'] = ['error' => 'Nhập lại mật khẩu không khớp.'];
  header("Location: reset_password.php");
  exit;
}

$rid  = (int)$rv['rid'];
$matk = (int)$rv['matk'];

$stmt = $conn->prepare("SELECT id, expires_at, used_at FROM password_reset_otp WHERE id=? AND MaTK=? LIMIT 1");
$stmt->bind_param("ii", $rid, $matk);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !empty($row['used_at']) || strtotime($row['expires_at']) < time()) {
  unset($_SESSION['reset_verified'], $_SESSION['reset']);
  $_SESSION['flash_fp'] = ['error' => 'OTP không hợp lệ hoặc đã hết hạn. Vui lòng tạo yêu cầu mới.'];
  header("Location: forgot_password.php");
  exit;
}

$hash = password_hash($new, PASSWORD_BCRYPT);

$conn->begin_transaction();
try {
  $u1 = $conn->prepare("UPDATE taikhoan SET MatKhau=? WHERE MaTK=? LIMIT 1");
  $u1->bind_param("si", $hash, $matk);
  $u1->execute();
  $u1->close();

  $now = date('Y-m-d H:i:s');
  $u2 = $conn->prepare("UPDATE password_reset_otp SET used_at=? WHERE id=?");
  $u2->bind_param("si", $now, $rid);
  $u2->execute();
  $u2->close();

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  $_SESSION['flash_rp'] = ['error' => 'Đổi mật khẩu thất bại. Vui lòng thử lại.'];
  header("Location: reset_password.php");
  exit;
}

$prefill  = $rv['prefill'] ?? '';
$redirect = $rv['redirect'] ?? 'trangchu.php';

unset($_SESSION['reset_verified'], $_SESSION['reset']);

header("Location: reset_success.php?prefill=".urlencode($prefill)."&redirect=".urlencode($redirect));
exit;
