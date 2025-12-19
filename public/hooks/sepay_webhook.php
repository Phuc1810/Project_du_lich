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
    echo json_encode(['ok' => true, 'message' => 'sepay_webhook alive']);
    exit;
}

/** ========= AUTH ========= */
if (defined('SEPAY_WEBHOOK_TOKEN') && trim((string)SEPAY_WEBHOOK_TOKEN) !== '') {
    $token = trim((string)SEPAY_WEBHOOK_TOKEN);
    $auth = trim($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $xApi = trim($_SERVER['HTTP_X_API_KEY'] ?? ($_SERVER['HTTP_X_APIKEY'] ?? ($_SERVER['HTTP_API_KEY'] ?? '')));

    $ok = false;
    if ($auth === ('Bearer ' . $token)) $ok = true;
    if ($auth === $token) $ok = true;
    if ($xApi === $token) $ok = true;

    if (!$ok) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

/** ========= Utility Functions ========= */
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

function normalize_phone_to_e164($phone) {
    $p = preg_replace('/\D+/', '', (string)$phone);
    if ($p === '') return '';
    if (strpos($p, '0') === 0) return '+84' . substr($p, 1);
    if (strpos($p, '84') === 0) return '+' . $p;
    return '+' . $p;
}

/** ========= GỬI MAIL BẰNG BREVO API (Thay thế SMTP Gmail) ========= */
function send_email_brevo($to, $subject, $html) {
    try {
        // Kiểm tra cấu hình Brevo trong config.php
        if (!defined('BREVO_API_KEY') || empty(BREVO_API_KEY)) {
            wlog("BREVO_ERR=Chưa cấu hình BREVO_API_KEY");
            return false;
        }

        $senderEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'tranhoaiphuc1810@gmail.com';
        $senderName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Admin Tour Du Lich';

        $url = "https://api.brevo.com/v3/smtp/email";
        
        $data = [
            "sender" => [
                "name" => $senderName,
                "email" => $senderEmail
            ],
            "to" => [
                [
                    "email" => $to
                ]
            ],
            "subject" => $subject,
            "htmlContent" => $html
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "api-key: " . BREVO_API_KEY,
                "content-type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            wlog("BREVO_CURL_ERR=" . $err);
            return false;
        }

        // Brevo trả về 201 Created là thành công
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            wlog("BREVO_API_FAIL HTTP=$httpCode RES=$response");
            return false;
        }

    } catch (Throwable $e) {
        wlog("BREVO_EXCEPTION=" . $e->getMessage());
        return false;
    }
}

/** ========= GỬI SMS INFOBIP ========= */
function send_sms_infobip($toPhone, $text) {
    try {
        if (!defined('INFOBIP_BASE_URL') || !defined('INFOBIP_API_KEY')) return false;
        
        $to = normalize_phone_to_e164($toPhone);
        if ($to === '') return false;

        $url = rtrim(INFOBIP_BASE_URL, '/') . "/sms/2/text/advanced";
        $payload = json_encode([
            "messages" => [[
                "from" => defined('INFOBIP_FROM') ? INFOBIP_FROM : 'ServiceSMS',
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
        curl_close($ch);

        return ($code >= 200 && $code < 300);
    } catch (Throwable $e) {
        wlog("SMS_ERR=" . $e->getMessage());
        return false;
    }
}

/** ========= Parse Webhook ========= */
$raw = file_get_contents("php://input");
wlog("RAW=" . $raw);

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$transferType = (string)pick_value($payload, ['transferType', 'type']);
if ($transferType !== '' && strtolower($transferType) !== 'in') {
    echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'not_incoming']);
    exit;
}

$text = pick_value($payload, ['content', 'description', 'transactionContent', 'transferContent']);
$amountRaw = pick_value($payload, ['transferAmount', 'amount', 'money', 'value']);
$text = is_string($text) ? trim($text) : '';
$amount = is_numeric($amountRaw) ? (int)round((float)$amountRaw) : 0;

if ($text === '' || !preg_match('/\bDH\s*([0-9]+)\b/i', $text, $m)) {
    echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'no_DH_code']);
    exit;
}
$madon = (int)$m[1];

if ($amount <= 0) {
    echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'no_amount', 'madon' => $madon]);
    exit;
}

/** ========= Transaction xử lý đơn ========= */
$conn->begin_transaction();

try {
    // 1) Lock đơn
    $stmt = $conn->prepare("SELECT MaDon, TrangThai, TongTienPhaiTra, SoLuongNguoiLon, SoLuongTreEm, SoLuongTreNho, MaTour FROM dondattour WHERE MaDon=? FOR UPDATE");
    $stmt->bind_param("i", $madon);
    $stmt->execute();
    $don = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$don) {
        $conn->rollback();
        echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'order_not_found', 'madon' => $madon]);
        exit;
    }

    $stDon = trim((string)$don['TrangThai']);
    $stLower = mb_strtolower($stDon, 'UTF-8');

    if ($stLower === mb_strtolower('Đã thanh toán', 'UTF-8') || $stLower === mb_strtolower('Hết chỗ', 'UTF-8')) {
        $conn->commit();
        echo json_encode(['ok' => true, 'already' => true, 'status' => $stDon, 'madon' => $madon]);
        exit;
    }

    $expected = (int)round((float)$don['TongTienPhaiTra']);
    if ($amount < $expected) {
        $conn->rollback();
        echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'amount_less_than_expected', 'expected' => $expected, 'amount' => $amount, 'madon' => $madon]);
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
        echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'tour_not_found', 'madon' => $madon]);
        exit;
    }

    $soCho = (int)$tour['SoCho'];
    $soChoDaDat = (int)$tour['SoChoDaDat'];

    // 3) Xử lý hết chỗ
    if ($soChoDaDat + $needSeats > $soCho) {
        $conn->query("UPDATE dondattour SET TrangThai='Hết chỗ' WHERE MaDon=$madon");
        $stmt = $conn->prepare("INSERT INTO thanhtoan (NgayTT, SoTien, PhuongThuc, TrangThaiTT, MaDon) VALUES (CURDATE(), ?, 'Chuyển khoản', 'Nhận tiền - Hết chỗ', ?)");
        $stmt->bind_param("di", $amount, $madon);
        $stmt->execute();
        $conn->commit();
        $status_final = 'soldout';
        goto SEND_NOTIFY;
    }

    // 4) Cập nhật chỗ & trạng thái
    $newSoChoDaDat = $soChoDaDat + $needSeats;
    $conn->query("UPDATE tour SET SoChoDaDat=$newSoChoDaDat WHERE MaTour=$matour");
    if ($newSoChoDaDat >= $soCho) {
        $conn->query("UPDATE tour SET TrangThai='Hết chỗ' WHERE MaTour=$matour");
    }

    $conn->query("UPDATE dondattour SET TrangThai='Đã thanh toán' WHERE MaDon=$madon");
    
    $stmt = $conn->prepare("INSERT INTO thanhtoan (NgayTT, SoTien, PhuongThuc, TrangThaiTT, MaDon) VALUES (CURDATE(), ?, 'Chuyển khoản', 'Thành công', ?)");
    $stmt->bind_param("di", $amount, $madon);
    $stmt->execute();
    $conn->commit();
    $status_final = 'paid';

    SEND_NOTIFY:

    // Lấy thông tin chi tiết để gửi mail
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

    // Logic lấy Email/Phone tối ưu
    $email = '';
    if (!empty($info['Email']) && filter_var($info['Email'], FILTER_VALIDATE_EMAIL)) $email = $info['Email'];
    elseif (!empty($info['TenDangNhap']) && filter_var($info['TenDangNhap'], FILTER_VALIDATE_EMAIL)) $email = $info['TenDangNhap'];

    $phone = '';
    if (!empty($info['SoDienThoai'])) $phone = $info['SoDienThoai'];
    elseif (!empty($info['TenDangNhap']) && preg_match('/^\d{9,12}$/', $info['TenDangNhap'])) $phone = $info['TenDangNhap'];

    $ngayKH = !empty($info['NgayKhoiHanh']) ? date('d/m/Y', strtotime($info['NgayKhoiHanh'])) : 'Đang cập nhật';
    $money = number_format((float)$info['TongTienPhaiTra'], 0, ',', '.');

    if ($status_final === 'paid') {
        $subject = "Xác nhận thanh toán đơn tour #DH{$madon}";
        $html = "
            <div style='font-family:Arial,sans-serif;line-height:1.6;color:#333;'>
                <h2 style='color:#0d6efd;'>Thanh toán thành công ✅</h2>
                <p>Xin chào <b>".htmlspecialchars($info['HoTen']??'', ENT_QUOTES)."</b>,</p>
                <p>Đơn hàng <b>#DH{$madon}</b> đã thanh toán thành công.</p>
                <ul>
                    <li><b>Tour:</b> ".htmlspecialchars($info['TenTour']??'')."</li>
                    <li><b>Khởi hành:</b> {$ngayKH}</li>
                    <li><b>Tổng tiền:</b> {$money} VNĐ</li>
                </ul>
                <p>Cảm ơn quý khách!</p>
            </div>";
        $smsText = "VietJourney: DH{$madon} thanh toan thanh cong. Tour: ".($info['TenTour']??'').".";
    } else {
        $subject = "Thông báo: Tour đã hết chỗ cho đơn #DH{$madon}";
        $html = "
            <div style='font-family:Arial,sans-serif;line-height:1.6;color:#333;'>
                <h2 style='color:#dc3545;'>Tour đã hết chỗ ⚠️</h2>
                <p>Xin chào <b>".htmlspecialchars($info['HoTen']??'', ENT_QUOTES)."</b>,</p>
                <p>Chúng tôi đã nhận được thanh toán cho đơn <b>#DH{$madon}</b> nhưng tour hiện đã hết chỗ.</p>
                <p>Vui lòng liên hệ hotline để được hỗ trợ hoàn tiền hoặc đổi tour.</p>
            </div>";
        $smsText = "VietJourney: Don DH{$madon} da nhan tien nhung tour da het cho. Vui long lien he ho tro.";
    }

    $sent = false;
    // GỬI MAIL QUA BREVO
    if ($email !== '') {
        $sent = send_email_brevo($email, $subject, $html);
        wlog("SEND_EMAIL_BREVO to={$email} ok=".($sent?'1':'0'));
    }
    
    // NẾU GỬI MAIL LỖI -> GỬI SMS
    if (!$sent && $phone !== '') {
        $sentSMS = send_sms_infobip($phone, $smsText);
        wlog("SEND_SMS to={$phone} ok=".($sentSMS?'1':'0'));
    }

    echo json_encode(['ok'=>true, 'status'=>$status_final, 'madon'=>$madon, 'amount'=>$amount]);

} catch (Throwable $e) {
    if (isset($conn) && $conn->errno) $conn->rollback();
    wlog("ERROR=" . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false, 'message'=>$e->getMessage()]);
    exit;
}
