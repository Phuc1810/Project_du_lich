<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;

function vn_to_e164($phone10){
  return '+84' . substr($phone10, 1);
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

$cooldown = 20;

if (empty($_SESSION['reset']['matk']) || empty($_SESSION['reset']['channel']) || empty($_SESSION['reset']['dest'])) {
  header("Location: forgot_password.php");
  exit;
}

$matk = (int)$_SESSION['reset']['matk'];
$channel = $_SESSION['reset']['channel']; // 'email' | 'sms'
$dest = $_SESSION['reset']['dest'];
$masked = $_SESSION['reset']['masked'] ?? '';

/**
 * ✅ Check cooldown bằng MySQL TIMESTAMPDIFF(SECOND,...)
 */
$stmt = $conn->prepare("
  SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS diff_sec
  FROM password_reset_otp
  WHERE MaTK=? AND destination=?
  ORDER BY id DESC
  LIMIT 1
");
$stmt->bind_param("is", $matk, $dest);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$diff = isset($row['diff_sec']) ? (int)$row['diff_sec'] : 999999;
if ($diff < 0) $diff = 0;

$remain = max(0, $cooldown - $diff);
if ($remain > 0) {
  $_SESSION['flash_vo'] = ['error' => "Vui lòng đợi {$remain} giây để gửi lại OTP."];
  header("Location: verify_otp.php?id=".(int)($_SESSION['reset']['rid'] ?? 0)."&m=".urlencode($masked));
  exit;
}

// Tạo OTP mới
$otp = (string)random_int(100000, 999999);
$otpHash = password_hash($otp, PASSWORD_BCRYPT);
$expiresAt = date('Y-m-d H:i:s', time() + 300);

$stmt = $conn->prepare("INSERT INTO password_reset_otp (MaTK, channel, destination, otp_hash, expires_at) VALUES (?,?,?,?,?)");
$stmt->bind_param("issss", $matk, $channel, $dest, $otpHash, $expiresAt);
$stmt->execute();
$newRid = $conn->insert_id;
$stmt->close();

try {
  if ($channel === 'email') send_otp_email($dest, $otp);
  else send_otp_sms($dest, $otp);

  $_SESSION['reset']['rid'] = $newRid;

  $_SESSION['flash_vo'] = ['success' => 'Đã gửi lại OTP. Vui lòng kiểm tra.'];
  header("Location: verify_otp.php?id=".$newRid."&m=".urlencode($masked));
  exit;

} catch (Throwable $e) {
  $_SESSION['flash_vo'] = ['error' => 'Gửi lại OTP thất bại: '.$e->getMessage()];
  header("Location: verify_otp.php?id=".(int)($_SESSION['reset']['rid'] ?? 0)."&m=".urlencode($masked));
  exit;
}
