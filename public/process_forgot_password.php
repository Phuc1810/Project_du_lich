<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// Composer autoload
require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;

function is_email($s){ return filter_var($s, FILTER_VALIDATE_EMAIL); }
function is_phone10($s){ return preg_match('/^\d{10}$/', $s); }

function mask_email($email){
  $parts = explode('@', $email);
  if (count($parts) !== 2) return $email;
  $name = $parts[0];
  $dom  = $parts[1];
  $keep = substr($name, 0, 2);
  return $keep . str_repeat('*', max(0, strlen($name)-2)) . '@' . $dom;
}
function mask_phone($p){
  return substr($p,0,2) . str_repeat('*',6) . substr($p,-2);
}

function send_otp_email($toEmail, $otp){
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = SMTP_USER;
  $mail->Password = SMTP_APP_PASS;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;
  $mail->CharSet = 'UTF-8';

  $mail->setFrom(SMTP_USER, 'TourDuLich');
  $mail->addAddress($toEmail);
  $mail->isHTML(true);
  $mail->Subject = 'Mã OTP đặt lại mật khẩu';
  $mail->Body = "Mã OTP của bạn là: <b>$otp</b><br>Mã có hiệu lực 5 phút.";
  $mail->send();
}

function vn_to_e164($phone10){
  // 0xxxxxxxxx -> +84xxxxxxxxx
  return '+84' . substr($phone10, 1);
}

/**
 * ✅ Infobip SMS
 * Config cần có:
 * INFOBIP_BASE_URL, INFOBIP_API_KEY, INFOBIP_FROM (E.164, ví dụ +447491163443)
 */
function send_otp_sms($toPhone10, $otp){
  if (!defined('INFOBIP_BASE_URL') || !defined('INFOBIP_API_KEY') || !defined('INFOBIP_FROM')) {
    throw new Exception("Chưa cấu hình SMS (Infobip).");
  }

  $to = vn_to_e164($toPhone10);
  $text = "Ma OTP TourDuLich: $otp (hieu luc 5 phut)";

  $payload = [
    "messages" => [[
      "from" => INFOBIP_FROM,
      "destinations" => [
        ["to" => $to]
      ],
      "text" => $text
    ]]
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => rtrim(INFOBIP_BASE_URL, '/') . "/sms/2/text/advanced",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      "Authorization: App " . INFOBIP_API_KEY,
      "Content-Type: application/json",
      "Accept: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30,
  ]);

  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($res === false) {
    $err = curl_error($ch);
    curl_close($ch);
    throw new Exception("cURL error: " . $err);
  }
  curl_close($ch);

  if ($http < 200 || $http >= 300) {
    throw new Exception("Infobip HTTP $http: " . $res);
  }
}

$redirect = $_POST['redirect'] ?? 'trangchu.php';
$contact  = trim($_POST['contact'] ?? '');

if ($contact === '') {
  $_SESSION['flash_fp'] = ['error' => 'Vui lòng nhập email hoặc số điện thoại.', 'old' => ['contact'=>$contact]];
  header("Location: forgot_password.php?redirect=".urlencode($redirect));
  exit;
}

$channel = null;
if (is_email($contact)) $channel = 'email';
else if (is_phone10($contact)) $channel = 'sms';
else {
  $_SESSION['flash_fp'] = ['error' => 'Email/SĐT không hợp lệ.', 'old' => ['contact'=>$contact]];
  header("Location: forgot_password.php?redirect=".urlencode($redirect));
  exit;
}

$sql = "
  SELECT tk.MaTK, tk.Provider,
         kh.Email AS user_email,
         kh.SoDienThoai AS user_phone
  FROM taikhoan tk
  JOIN khachhang kh ON kh.MaTK = tk.MaTK
  WHERE (kh.Email=? OR kh.SoDienThoai=? OR tk.TenDangNhap=?)
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $contact, $contact, $contact);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
  $_SESSION['flash_fp'] = ['success' => 'Nếu thông tin đúng, hệ thống đã gửi OTP.'];
  header("Location: forgot_password.php?redirect=".urlencode($redirect));
  exit;
}

if (($user['Provider'] ?? '') !== 'local') {
  $_SESSION['flash_fp'] = ['error' => 'Tài khoản này đăng nhập bằng Google. Vui lòng dùng nút “Đăng nhập Google”.'];
  header("Location: forgot_password.php?redirect=".urlencode($redirect));
  exit;
}

$otp = (string)random_int(100000, 999999);
$otpHash = password_hash($otp, PASSWORD_BCRYPT);
$expiresAt = date('Y-m-d H:i:s', time() + 300);

$dest = ($channel === 'email') ? ($user['user_email'] ?? '') : ($user['user_phone'] ?? '');

// ✅ tạo mask TRƯỚC khi set session
$mask = ($channel === 'email') ? mask_email($dest) : mask_phone($dest);

$stmt = $conn->prepare("INSERT INTO password_reset_otp (MaTK, channel, destination, otp_hash, expires_at) VALUES (?,?,?,?,?)");
$stmt->bind_param("issss", $user['MaTK'], $channel, $dest, $otpHash, $expiresAt);
$stmt->execute();
$rid = $conn->insert_id;
$stmt->close();

try {
  if ($channel === 'email') send_otp_email($dest, $otp);
  else send_otp_sms($dest, $otp);

  $_SESSION['reset'] = [
    'rid' => $rid,
    'matk' => (int)$user['MaTK'],
    'redirect' => $redirect,
    'prefill' => $user['user_email'] ?? '',
    'channel' => $channel,
    'dest' => $dest,
    'masked' => $mask
  ];

  header("Location: verify_otp.php?id=".$rid."&m=".urlencode($mask));
  exit;

} catch (Throwable $e) {
  $_SESSION['flash_fp'] = ['error' => 'Gửi OTP thất bại: '.$e->getMessage()];
  header("Location: forgot_password.php?redirect=".urlencode($redirect));
  exit;
}
