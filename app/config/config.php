<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Cấu hình kết nối: Ưu tiên lấy từ biến môi trường Railway, nếu không có thì lấy giá trị mặc định (localhost)
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE');
$port = getenv('MYSQLPORT'); // Railway thường dùng cổng khác 3306, nên cần biến này

// Tạo kết nối (Thêm tham số $port vào cuối)
$conn = new mysqli($host, $user, $pass, $db, $port);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    // Nên ẩn lỗi chi tiết khi lên môi trường production để bảo mật
    die("Kết nối thất bại. Vui lòng kiểm tra lại cấu hình."); 
    // Hoặc để debug thì dùng dòng dưới:
    // die("Lỗi kết nối database: " . $conn->connect_error);
}

// ===== SMTP Gmail (gửi OTP email) =====
define('SMTP_USER', 'tranhoaiphuc1810@gmail.com');        // gmail gửi OTP
define('SMTP_APP_PASS', 'onsp ivht kizu yayf');   // App Password 16 ký tự (không phải mật khẩu gmail thường)

// ===== Twilio SMS (gửi OTP SMS) =====
define('INFOBIP_BASE_URL', 'https://d8l1kl.api.infobip.com');
define('INFOBIP_API_KEY', 'b68699c1e2da8038010b50b56c664984-bff282f5-94e1-42d3-94c4-9154a8ef59ff');
define('INFOBIP_FROM', '+447491163443');

define('VIETQR_BANK_ID', '970423');
define('VIETQR_ACCOUNT_NO', '00000632482');
define('VIETQR_TEMPLATE', 'compact');
define('VIETQR_ACCOUNT_NAME', 'TRAN HOAI PHUC');
// define('SEPAY_WEBHOOK_TOKEN', 'chuoi_token_ban_dat_tren_sepay');
define('SEPAY_WEBHOOK_TOKEN', '');


?>

