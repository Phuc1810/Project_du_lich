<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$id  = (int)($_POST['id'] ?? 0);
$otp = trim($_POST['otp'] ?? '');

$reset = $_SESSION['reset'] ?? null;
if (!$reset || (int)$reset['rid'] !== $id) {
  header("Location: forgot_password.php");
  exit;
}

if ($otp === '' || !preg_match('/^\d{6}$/', $otp)) {
  $_SESSION['flash_vo'] = ['error' => 'Vui lòng nhập OTP gồm 6 chữ số.'];
  header("Location: verify_otp.php?id=".$id);
  exit;
}

// Lấy OTP record
$stmt = $conn->prepare("SELECT id, MaTK, otp_hash, expires_at, attempts, used_at
                        FROM password_reset_otp WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  $_SESSION['flash_vo'] = ['error' => 'Yêu cầu OTP không tồn tại.'];
  header("Location: forgot_password.php");
  exit;
}

if (!empty($row['used_at'])) {
  $_SESSION['flash_vo'] = ['error' => 'OTP đã được sử dụng. Vui lòng tạo yêu cầu mới.'];
  header("Location: forgot_password.php");
  exit;
}

if (strtotime($row['expires_at']) < time()) {
  $_SESSION['flash_vo'] = ['error' => 'OTP đã hết hạn. Vui lòng tạo yêu cầu mới.'];
  header("Location: forgot_password.php");
  exit;
}

$attempts = (int)($row['attempts'] ?? 0);
if ($attempts >= 5) {
  $_SESSION['flash_vo'] = ['error' => 'Bạn nhập sai quá nhiều lần. Vui lòng tạo yêu cầu mới.'];
  header("Location: forgot_password.php");
  exit;
}

if (!password_verify($otp, $row['otp_hash'])) {
  $attempts++;
  $u = $conn->prepare("UPDATE password_reset_otp SET attempts=? WHERE id=?");
  $u->bind_param("ii", $attempts, $id);
  $u->execute();
  $u->close();

  $_SESSION['flash_vo'] = ['error' => 'OTP không đúng. Vui lòng thử lại.'];
  header("Location: verify_otp.php?id=".$id);
  exit;
}

// ✅ OTP đúng → cho phép mở form đổi mật khẩu
$_SESSION['reset_verified'] = [
  'rid' => (int)$row['id'],
  'matk' => (int)$row['MaTK'],
  'redirect' => $reset['redirect'] ?? 'trangchu.php',
  'prefill' => $reset['prefill'] ?? ''
];

header("Location: reset_password.php");
exit;
