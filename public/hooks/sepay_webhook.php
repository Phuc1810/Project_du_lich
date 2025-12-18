<?php
require_once __DIR__ . "/../../app/config/config.php";
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$logFile = __DIR__ . "/sepay_webhook.log";

/** ========= Helper log ========= */
function wlog($msg) {
  global $logFile;
  @file_put_contents($logFile, date('c') . " " . $msg . PHP_EOL, FILE_APPEND);
}

/** ========= GET để test sống ========= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
  echo json_encode(['ok'=>true,'message'=>'sepay_webhook alive']);
  exit;
}

/** ========= AUTH (chỉ check khi token KHÔNG rỗng) =========
 * SePay UI của bạn đang chọn "Không cần chứng thực" => token phải rỗng
 * Nếu token có => accept nhiều kiểu header (đỡ lệch chuẩn SePay)
 */
if (defined('SEPAY_WEBHOOK_TOKEN') && trim((string)SEPAY_WEBHOOK_TOKEN) !== '') {
  $token = trim((string)SEPAY_WEBHOOK_TOKEN);

  $auth = trim($_SERVER['HTTP_AUTHORIZATION'] ?? '');
  $xApi = trim($_SERVER['HTTP_X_API_KEY'] ?? ($_SERVER['HTTP_X_APIKEY'] ?? ($_SERVER['HTTP_API_KEY'] ?? '')));

  $ok = false;

  // Bearer token
  if ($auth === ('Bearer ' . $token)) $ok = true;
  // Một số hệ thống gửi thẳng token vào Authorization
  if ($auth === $token) $ok = true;
  // API Key header
  if ($xApi === $token) $ok = true;

  if (!$ok) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'message'=>'Unauthorized']);
    exit;
  }
}

/** ========= pick value (tìm field trong payload kể cả nested) ========= */
function pick_value(array $arr, array $keys) {
  foreach ($keys as $k) {
    if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') return $arr[$k];
  }
  foreach ($arr as $v) {
    if (is_array($v)) {
      $found = pick_value($v, $keys);
      if ($found !== null && $found !== '') return $found;
    }
  }
  return null;
}

/** ========= Parse JSON ========= */
$raw = file_get_contents("php://input");
wlog("RAW=".$raw);

$payload = json_decode($raw, true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Invalid JSON']);
  exit;
}

/** ========= Chỉ xử lý tiền vào ========= */
$transferType = (string)pick_value($payload, ['transferType','type']);
if ($transferType !== '' && strtolower($transferType) !== 'in') {
  echo json_encode(['ok'=>true,'ignored'=>true,'reason'=>'not_incoming']);
  exit;
}

/** ========= Lấy content + amount ========= */
$text = pick_value($payload, ['content','description','transactionContent','transferContent']);
$amountRaw = pick_value($payload, ['transferAmount','amount','money','value']);

$text = is_string($text) ? trim($text) : '';
$amount = is_numeric($amountRaw) ? (int)round((float)$amountRaw) : 0;

if ($text === '' || !preg_match('/\bDH\s*([0-9]+)\b/i', $text, $m)) {
  echo json_encode(['ok'=>true,'ignored'=>true,'reason'=>'no_DH_code']);
  exit;
}
$madon = (int)$m[1];

if ($amount <= 0) {
  echo json_encode(['ok'=>true,'ignored'=>true,'reason'=>'no_amount','madon'=>$madon]);
  exit;
}

/** ========= Notify: Email (PHPMailer nếu có) ========= */
function send_email_smtp($to, $subject, $html) {
  try {
   $autoload = __DIR__ . "/../../vendor/autoload.php";
    if (!file_exists($autoload)) {
      // Không có PHPMailer -> thử mail() (thường không gửi được Gmail SMTP)
      $headers  = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type:text/html;charset=UTF-8\r\n";
      $headers .= "From: ".SMTP_USER."\r\n";
      return @mail($to, $subject, $html, $headers);
    }

    require_once $autoload;
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_APP_PASS;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom(SMTP_USER, 'VietJourney Tour');
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;

    $mail->send();
    return true;
  } catch (Throwable $e) {
    wlog("EMAIL_ERR=".$e->getMessage());
    return false;
  }
}

/** ========= Notify: SMS Infobip ========= */
function normalize_phone_to_e164($phone) {
  $p = preg_replace('/\D+/', '', (string)$phone);
  if ($p === '') return '';
  // 0xxxxxxxxx -> +84xxxxxxxxx
  if (strpos($p, '0') === 0) return '+84' . substr($p, 1);
  // 84xxxxxxxxx -> +84xxxxxxxxx
  if (strpos($p, '84') === 0) return '+' . $p;
  // nếu đã có dấu + ở đầu (bị strip) thì vẫn đưa về +...
  return '+' . $p;
}

function send_sms_infobip($toPhone, $text) {
  try {
    $to = normalize_phone_to_e164($toPhone);
    if ($to === '') return false;

    $url = rtrim(INFOBIP_BASE_URL, '/') . "/sms/2/text/advanced";
    $payload = json_encode([
      "messages" => [[
        "from" => INFOBIP_FROM,
        "destinations" => [["to" => $to]],
        "text" => $text
      ]]
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => [
        "Authorization: App " . INFOBIP_API_KEY,
        "Content-Type: application/json",
        "Accept: application/json"
      ],
      CURLOPT_TIMEOUT => 15
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) wlog("SMS_CURL_ERR=".$err);
    wlog("SMS_HTTP=".$code." RES=".$res);

    return ($code >= 200 && $code < 300);
  } catch (Throwable $e) {
    wlog("SMS_ERR=".$e->getMessage());
    return false;
  }
}

/** ========= Transaction xử lý đơn ========= */
$conn->begin_transaction();

try {
  // 1) Lock đơn
  $stmt = $conn->prepare("
    SELECT MaDon, TrangThai, TongTienPhaiTra,
           SoLuongNguoiLon, SoLuongTreEm, SoLuongTreNho,
           MaTour
    FROM dondattour
    WHERE MaDon=?
    FOR UPDATE
  ");
  $stmt->bind_param("i", $madon);
  $stmt->execute();
  $don = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$don) {
    $conn->rollback();
    echo json_encode(['ok'=>true,'ignored'=>true,'reason'=>'order_not_found','madon'=>$madon]);
    exit;
  }

  $stDon = trim((string)$don['TrangThai']);
  $stLower = mb_strtolower($stDon,'UTF-8');

  if ($stLower === mb_strtolower('Đã thanh toán','UTF-8') || $stLower === mb_strtolower('Hết chỗ','UTF-8')) {
    $conn->commit();
    echo json_encode(['ok'=>true,'already'=>true,'status'=>$stDon,'madon'=>$madon]);
    exit;
  }

  $expected = (int)round((float)$don['TongTienPhaiTra']);
  if ($amount < $expected) {
    $conn->rollback();
    echo json_encode([
      'ok'=>true,'ignored'=>true,'reason'=>'amount_less_than_expected',
      'expected'=>$expected,'amount'=>$amount,'madon'=>$madon
    ]);
    exit;
  }

  $matour = (int)$don['MaTour'];
  $needSeats = (int)$don['SoLuongNguoiLon'] + (int)$don['SoLuongTreEm'] + (int)$don['SoLuongTreNho'];

  // 2) Lock tour
  $stmt = $conn->prepare("SELECT SoCho, SoChoDaDat, TrangThai FROM tour WHERE MaTour=? FOR UPDATE");
  $stmt->bind_param("i", $matour);
  $stmt->execute();
  $tour = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$tour) {
    $conn->rollback();
    echo json_encode(['ok'=>true,'ignored'=>true,'reason'=>'tour_not_found','madon'=>$madon]);
    exit;
  }

  $soCho = (int)$tour['SoCho'];
  $soChoDaDat = (int)$tour['SoChoDaDat'];

  // 3) Không đủ chỗ
  if ($soChoDaDat + $needSeats > $soCho) {
    $statusSold = "Hết chỗ";
    $stmt = $conn->prepare("UPDATE dondattour SET TrangThai=? WHERE MaDon=? LIMIT 1");
    $stmt->bind_param("si", $statusSold, $madon);
    $stmt->execute();
    $stmt->close();

    $ttStatus = "Nhận tiền - Hết chỗ";
    $stmt = $conn->prepare("
      INSERT INTO thanhtoan (NgayTT, SoTien, PhuongThuc, TrangThaiTT, MaDon)
      VALUES (CURDATE(), ?, 'Chuyển khoản', ?, ?)
    ");
    $stmt->bind_param("dsi", $amount, $ttStatus, $madon);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // gửi thông báo soldout (sau commit)
    goto SEND_NOTIFY_SOLDOUT;
  }

  // 4) Update tour chỗ
  $newSoChoDaDat = $soChoDaDat + $needSeats;
  $stmt = $conn->prepare("UPDATE tour SET SoChoDaDat=? WHERE MaTour=? LIMIT 1");
  $stmt->bind_param("ii", $newSoChoDaDat, $matour);
  $stmt->execute();
  $stmt->close();

  if ($newSoChoDaDat >= $soCho) {
    $stFull = "Hết chỗ";
    $stmt = $conn->prepare("UPDATE tour SET TrangThai=? WHERE MaTour=? LIMIT 1");
    $stmt->bind_param("si", $stFull, $matour);
    $stmt->execute();
    $stmt->close();
  }

  // 5) Update đơn: Đã thanh toán
  $statusPaid = "Đã thanh toán";
  $stmt = $conn->prepare("UPDATE dondattour SET TrangThai=? WHERE MaDon=? LIMIT 1");
  $stmt->bind_param("si", $statusPaid, $madon);
  $stmt->execute();
  $stmt->close();

  // 6) Insert ThanhToan: Thành công
  $ttStatus = "Thành công";
  $stmt = $conn->prepare("
    INSERT INTO thanhtoan (NgayTT, SoTien, PhuongThuc, TrangThaiTT, MaDon)
    VALUES (CURDATE(), ?, 'Chuyển khoản', ?, ?)
  ");
  $stmt->bind_param("dsi", $amount, $ttStatus, $madon);
  $stmt->execute();
  $stmt->close();

  $conn->commit();

  /** ===== Gửi thông báo PAID ===== */
  SEND_NOTIFY_PAID:

  $stmt = $conn->prepare("
    SELECT d.MaDon, d.TongTienPhaiTra, d.SoLuongNguoiLon, d.SoLuongTreEm, d.SoLuongTreNho,
           t.TenTour, t.DiaDiem, t.NgayKhoiHanh,
           kh.HoTen, kh.Email, kh.SoDienThoai,
           tk.TenDangNhap, tk.Provider
    FROM dondattour d
    JOIN khachhang kh ON kh.MaKH = d.MaKH
    JOIN taikhoan tk ON tk.MaTK = kh.MaTK
    JOIN tour t ON t.MaTour = d.MaTour
    WHERE d.MaDon=?
    LIMIT 1
  ");
  $stmt->bind_param("i", $madon);
  $stmt->execute();
  $info = $stmt->get_result()->fetch_assoc();
  $stmt->close();

//   $email = '';
//   if (!empty($info['Email']) && filter_var($info['Email'], FILTER_VALIDATE_EMAIL)) $email = $info['Email'];
//   if ($email === '' && !empty($info['TenDangNhap']) && filter_var($info['TenDangNhap'], FILTER_VALIDATE_EMAIL)) $email = $info['TenDangNhap'];

//   $phone = '';
//   if (!empty($info['SoDienThoai'])) $phone = $info['SoDienThoai'];
//   if ($phone === '' && !empty($info['TenDangNhap']) && preg_match('/^\d{9,12}$/', $info['TenDangNhap'])) $phone = $info['TenDangNhap'];

$username = trim((string)($info['TenDangNhap'] ?? ''));

$isLoginEmail = ($username !== '' && filter_var($username, FILTER_VALIDATE_EMAIL));
$isLoginPhone = ($username !== '' && preg_match('/^\d{9,12}$/', $username));

$email = '';
$phone = '';

// 1) Nếu đăng nhập bằng EMAIL => gửi EMAIL (ưu tiên email đăng nhập)
if ($isLoginEmail) {
  $email = $username;
  if ($email === '' && !empty($info['Email']) && filter_var($info['Email'], FILTER_VALIDATE_EMAIL)) {
    $email = $info['Email'];
  }
}
// 2) Nếu đăng nhập bằng SĐT => gửi SMS (ưu tiên SĐT đăng nhập)
else if ($isLoginPhone) {
  $phone = $username;
  if ($phone === '' && !empty($info['SoDienThoai'])) {
    $phone = $info['SoDienThoai'];
  }
}
// 3) Fallback: nếu không rõ kiểu đăng nhập => ưu tiên Email rồi mới SMS
else {
  if (!empty($info['Email']) && filter_var($info['Email'], FILTER_VALIDATE_EMAIL)) $email = $info['Email'];
  if ($email === '' && $isLoginEmail) $email = $username;

  if ($email === '') {
    if (!empty($info['SoDienThoai'])) $phone = $info['SoDienThoai'];
    if ($phone === '' && $isLoginPhone) $phone = $username;
  }
}


  $ngayKH = !empty($info['NgayKhoiHanh']) ? date('d/m/Y', strtotime($info['NgayKhoiHanh'])) : 'Đang cập nhật';
  $money = number_format((float)$info['TongTienPhaiTra'], 0, ',', '.');

  $subject = "Xác nhận thanh toán đơn tour #DH{$madon}";
  $html = "
    <div style='font-family:Arial,sans-serif;line-height:1.6'>
      <h2 style='margin:0 0 8px'>Thanh toán thành công ✅</h2>
      <p>Xin chào <b>".htmlspecialchars($info['HoTen'] ?? '', ENT_QUOTES, 'UTF-8')."</b>,</p>
      <p>Đơn tour <b>#DH{$madon}</b> đã được thanh toán thành công.</p>
      <ul>
        <li><b>Tour:</b> ".htmlspecialchars($info['TenTour'] ?? '', ENT_QUOTES, 'UTF-8')."</li>
        <li><b>Địa điểm:</b> ".htmlspecialchars($info['DiaDiem'] ?? '', ENT_QUOTES, 'UTF-8')."</li>
        <li><b>Khởi hành:</b> {$ngayKH}</li>
        <li><b>Số lượng:</b> Người lớn {$info['SoLuongNguoiLon']}, Trẻ em {$info['SoLuongTreEm']}, Trẻ nhỏ {$info['SoLuongTreNho']}</li>
        <li><b>Số tiền:</b> {$money} VNĐ</li>
        <li><b>Trạng thái:</b> Đã thanh toán</li>
      </ul>
      <p>Bạn có thể xem chi tiết đơn tại website.</p>
      <p style='color:#64748b;font-size:12px'>VietJourney Tour</p>
    </div>
  ";

  $smsText = "VietJourney: DH{$madon} thanh toan thanh cong. Tour: ".($info['TenTour'] ?? '').". Khoi hanh: {$ngayKH}. So tien: {$money} VND.";

  $sent = false;
  if ($email !== '') {
    $sent = send_email_smtp($email, $subject, $html);
    wlog("SEND_EMAIL to={$email} ok=".($sent?'1':'0'));
  } elseif ($phone !== '') {
    $sent = send_sms_infobip($phone, $smsText);
    wlog("SEND_SMS to={$phone} ok=".($sent?'1':'0'));
  } else {
    wlog("NO_CONTACT madon={$madon}");
  }

  echo json_encode(['ok'=>true,'status'=>'paid','madon'=>$madon,'amount'=>$amount]);
  exit;

  /** ===== Gửi thông báo SOLDOUT ===== */
  SEND_NOTIFY_SOLDOUT:

  $stmt = $conn->prepare("
    SELECT d.MaDon, d.TongTienPhaiTra,
           t.TenTour,
           kh.HoTen, kh.Email, kh.SoDienThoai,
           tk.TenDangNhap
    FROM dondattour d
    JOIN khachhang kh ON kh.MaKH = d.MaKH
    JOIN taikhoan tk ON tk.MaTK = kh.MaTK
    JOIN tour t ON t.MaTour = d.MaTour
    WHERE d.MaDon=?
    LIMIT 1
  ");
  $stmt->bind_param("i", $madon);
  $stmt->execute();
  $info = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $email = '';
  if (!empty($info['Email']) && filter_var($info['Email'], FILTER_VALIDATE_EMAIL)) $email = $info['Email'];
  if ($email === '' && !empty($info['TenDangNhap']) && filter_var($info['TenDangNhap'], FILTER_VALIDATE_EMAIL)) $email = $info['TenDangNhap'];

  $phone = $info['SoDienThoai'] ?? '';

  $subject = "Thông báo: Tour đã hết chỗ cho đơn #DH{$madon}";
  $html = "
    <div style='font-family:Arial,sans-serif;line-height:1.6'>
      <h2 style='margin:0 0 8px;color:#b45309'>Tour đã hết chỗ ⚠️</h2>
      <p>Xin chào <b>".htmlspecialchars($info['HoTen'] ?? '', ENT_QUOTES, 'UTF-8')."</b>,</p>
      <p>Hệ thống đã nhận tiền cho đơn <b>#DH{$madon}</b> nhưng tour <b>".htmlspecialchars($info['TenTour'] ?? '', ENT_QUOTES, 'UTF-8')."</b> hiện không còn đủ chỗ.</p>
      <p>Vui lòng liên hệ để được hỗ trợ xử lý.</p>
      <p style='color:#64748b;font-size:12px'>VietJourney Tour</p>
    </div>
  ";

  $smsText = "VietJourney: Don DH{$madon} da nhan tien nhung tour da het cho. Vui long lien he ho tro.";

  if ($email !== '') {
    $sent = send_email_smtp($email, $subject, $html);
    wlog("SEND_EMAIL_SOLDOUT to={$email} ok=".($sent?'1':'0'));
  } elseif ($phone !== '') {
    $sent = send_sms_infobip($phone, $smsText);
    wlog("SEND_SMS_SOLDOUT to={$phone} ok=".($sent?'1':'0'));
  } else {
    wlog("NO_CONTACT_SOLDOUT madon={$madon}");
  }

  echo json_encode(['ok'=>true,'status'=>'soldout','madon'=>$madon,'amount'=>$amount]);
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  wlog("ERROR=".$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
  exit;
}
