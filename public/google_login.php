<?php
require_once __DIR__ . "/../app/config/config.php";
header('Content-Type: application/json; charset=utf-8');

function safe_redirect(string $url, string $fallback = "trangchu.php"): string {
  $url = trim($url);
  if ($url === "") return $fallback;
  if (preg_match('~^https?://~i', $url)) return $fallback;
  if (str_starts_with($url, "//")) return $fallback;
  return $url;
}

function http_get_json(string $url): ?array {
  // ưu tiên curl cho ổn định
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    return is_array($data) ? $data : null;
  }

  $resp = @file_get_contents($url);
  if ($resp === false) return null;
  $data = json_decode($resp, true);
  return is_array($data) ? $data : null;
}

$credential = $_POST['credential'] ?? '';
$redirect   = safe_redirect($_POST['redirect'] ?? 'trangchu.php');

if ($credential === '') {
  echo json_encode(['ok'=>false,'message'=>'Missing credential']);
  exit;
}

$GOOGLE_CLIENT_ID = "305921031732-soi97ruuh46nualpefhtpmpv0bmnab9s.apps.googleusercontent.com";

$tokenInfo = http_get_json("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential));
if (!$tokenInfo) {
  echo json_encode(['ok'=>false,'message'=>'Không verify được token (check cURL/SSL/internet).']);
  exit;
}

if (($tokenInfo['aud'] ?? '') !== $GOOGLE_CLIENT_ID) {
  echo json_encode(['ok'=>false,'message'=>'Token không hợp lệ (aud mismatch).']);
  exit;
}

$email = $tokenInfo['email'] ?? '';
$sub   = $tokenInfo['sub'] ?? '';
$hoten = $tokenInfo['name'] ?? 'Google User';

if ($email === '' || $sub === '') {
  echo json_encode(['ok'=>false,'message'=>'Thiếu email/sub từ Google.']);
  exit;
}

$conn->begin_transaction();

try {
  // 1) Tìm theo GoogleSub trước (chính xác nhất)
  $stmt = $conn->prepare("
    SELECT tk.MaTK, tk.VaiTro, tk.TrangThai, tk.Provider,
           kh.HoTen, kh.Email, kh.SoDienThoai
    FROM TaiKhoan tk
    LEFT JOIN KhachHang kh ON kh.MaTK = tk.MaTK
    WHERE tk.GoogleSub = ?
    LIMIT 1
  ");
  $stmt->bind_param("s", $sub);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // 2) Nếu chưa có, thử theo Email trong KhachHang
  if (!$row) {
    $stmt = $conn->prepare("
      SELECT tk.MaTK, tk.VaiTro, tk.TrangThai, tk.Provider,
             kh.HoTen, kh.Email, kh.SoDienThoai
      FROM KhachHang kh
      JOIN TaiKhoan tk ON tk.MaTK = kh.MaTK
      WHERE kh.Email = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }

  if ($row) {
    if (($row['TrangThai'] ?? '') !== 'Hoạt động') {
      $conn->rollback();
      echo json_encode(['ok'=>false,'message'=>'Tài khoản đang bị khóa.']);
      exit;
    }

    // Nếu account cũ chưa có GoogleSub thì update thêm (để lần sau login chuẩn sub)
    $stmt = $conn->prepare("UPDATE TaiKhoan SET Provider='google', GoogleSub=? WHERE MaTK=? AND (GoogleSub IS NULL OR GoogleSub='')");
    $stmt->bind_param("si", $sub, $row['MaTK']);
    $stmt->execute();
    $stmt->close();

    $_SESSION['user'] = [
      'MaTK' => (int)$row['MaTK'],
      'VaiTro' => $row['VaiTro'] ?? 'KH',
      'HoTen' => $row['HoTen'] ?? $hoten,
      'Email' => $row['Email'] ?? $email,
      'SoDienThoai' => $row['SoDienThoai'] ?? '',
      'Provider' => 'google'
    ];

    $conn->commit();
    echo json_encode(['ok'=>true,'redirect'=>$redirect]);
    exit;
  }

  // 3) Chưa có => tạo mới
  $tenDangNhap = mb_strtolower($email, 'UTF-8');
  $randomPass = bin2hex(random_bytes(16));
  $hash = password_hash($randomPass, PASSWORD_BCRYPT);
  $vaiTro = 'KH';
  $trangThai = 'Hoạt động';
  $provider = 'google';

  $stmt = $conn->prepare("
    INSERT INTO TaiKhoan (TenDangNhap, MatKhau, VaiTro, TrangThai, Provider, GoogleSub)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $stmt->bind_param("ssssss", $tenDangNhap, $hash, $vaiTro, $trangThai, $provider, $sub);
  $stmt->execute();
  $maTK = $stmt->insert_id;
  $stmt->close();

  $sdt = null;
  $stmt = $conn->prepare("INSERT INTO KhachHang (HoTen, Email, SoDienThoai, MaTK) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("sssi", $hoten, $email, $sdt, $maTK);
  $stmt->execute();
  $stmt->close();

  $conn->commit();

  $_SESSION['user'] = [
    'MaTK' => (int)$maTK,
    'VaiTro' => $vaiTro,
    'HoTen' => $hoten,
    'Email' => $email,
    'SoDienThoai' => '',
    'Provider' => 'google'
  ];

  echo json_encode(['ok'=>true,'redirect'=>$redirect]);
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['ok'=>false,'message'=>'Lỗi server khi xử lý Google login.']);
  exit;
}
