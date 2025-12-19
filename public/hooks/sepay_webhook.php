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
           tk.TenDangNhap
    FROM dondattour d
    JOIN khachhang kh ON kh.MaKH = d.MaKH
    LEFT JOIN taikhoan tk ON tk.MaTK = kh.MaTK
    JOIN tour t ON t.MaTour = d.MaTour
    WHERE d.MaDon=?
    LIMIT 1
  ");
  $stmt->bind_param("i", $madon);
  $stmt->execute();
  $info = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // --- LOGIC LẤY EMAIL / SĐT (Ưu tiên thông tin khách hàng) ---
  $email = '';
  $phone = '';

  // 1. Ưu tiên lấy Email từ thông tin khách hàng (chính xác nhất)
  if (!empty($info['Email']) && filter_var($info['Email'], FILTER_VALIDATE_EMAIL)) {
      $email = $info['Email'];
  }
  // 2. Nếu không có, thử lấy từ Tên đăng nhập (nếu là email)
  elseif (!empty($info['TenDangNhap']) && filter_var($info['TenDangNhap'], FILTER_VALIDATE_EMAIL)) {
      $email = $info['TenDangNhap'];
  }

  // 3. Lấy SĐT từ thông tin khách hàng
  if (!empty($info['SoDienThoai'])) {
      $phone = $info['SoDienThoai'];
  }
  // 4. Nếu không có, thử lấy từ Tên đăng nhập (nếu là số)
  elseif (!empty($info['TenDangNhap']) && preg_match('/^\d{9,12}$/', $info['TenDangNhap'])) {
      $phone = $info['TenDangNhap'];
  }

  $ngayKH = !empty($info['NgayKhoiHanh']) ? date('d/m/Y', strtotime($info['NgayKhoiHanh'])) : 'Đang cập nhật';
  $money = number_format((float)$info['TongTienPhaiTra'], 0, ',', '.');

  $subject = "Xác nhận thanh toán đơn tour #DH{$madon}";
  $html = "
    <div style='font-family:Arial,sans-serif;line-height:1.6;color:#333;'>
      <h2 style='margin:0 0 16px;color:#0d6efd;'>Thanh toán thành công ✅</h2>
      <p>Xin chào <b>".htmlspecialchars($info['HoTen'] ?? 'Quý khách', ENT_QUOTES, 'UTF-8')."</b>,</p>
      <p>Hệ thống VietJourney đã nhận được thanh toán cho đơn hàng <b>#DH{$madon}</b>.</p>
      <div style='background:#f8f9fa;padding:15px;border-radius:8px;margin:15px 0;'>
        <ul style='list-style:none;padding:0;margin:0;'>
          <li style='margin-bottom:8px;'>📦 <b>Tour:</b> ".htmlspecialchars($info['TenTour'] ?? '', ENT_QUOTES, 'UTF-8')."</li>
          <li style='margin-bottom:8px;'>📍 <b>Địa điểm:</b> ".htmlspecialchars($info['DiaDiem'] ?? '', ENT_QUOTES, 'UTF-8')."</li>
          <li style='margin-bottom:8px;'>📅 <b>Khởi hành:</b> {$ngayKH}</li>
          <li style='margin-bottom:8px;'>👥 <b>Khách:</b> {$info['SoLuongNguoiLon']} người lớn, {$info['SoLuongTreEm']} trẻ em, {$info['SoLuongTreNho']} trẻ nhỏ</li>
          <li style='margin-bottom:8px;'>💰 <b>Số tiền:</b> <b style='color:#dc3545;'>{$money} VNĐ</b></li>
        </ul>
      </div>
      <p>Cảm ơn bạn đã tin tưởng VietJourney!</p>
      <hr style='border:0;border-top:1px solid #eee;margin:20px 0;'>
      <p style='color:#6c757d;font-size:12px'>Đây là email tự động, vui lòng không trả lời.</p>
    </div>
  ";

  $smsText = "VietJourney: DH{$madon} thanh toan thanh cong. Tour: ".($info['TenTour'] ?? '').". Khoi hanh: {$ngayKH}.";

  $sent = false;
  // Gửi Email trước
  if ($email !== '') {
    $sent = send_email_smtp($email, $subject, $html);
    wlog("SEND_EMAIL to={$email} ok=".($sent?'1':'0'));
  }
  
  // Nếu gửi email thất bại HOẶC không có email -> Gửi SMS
  if (!$sent && $phone !== '') {
    $sentSMS = send_sms_infobip($phone, $smsText);
    wlog("SEND_SMS to={$phone} ok=".($sentSMS?'1':'0'));
  }
  
  if ($email === '' && $phone === '') {
    wlog("NO_CONTACT_INFO madon={$madon}");
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
