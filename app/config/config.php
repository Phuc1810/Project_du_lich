<?php
header('Cross-Origin-Opener-Policy: unsafe-none'); 
header('Cross-Origin-Embedder-Policy: unsafe-none');
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (session_status() === PHP_SESSION_NONE) session_start();

// Cấu hình kết nối: Ưu tiên lấy từ biến môi trường Railway, nếu không có thì lấy giá trị mặc định (localhost)
$host = getenv('MYSQLHOST') ?: "localhost";
$user = getenv('MYSQLUSER') ?: "root";
$pass = getenv('MYSQLPASSWORD') ?: "";
$db   = getenv('MYSQLDATABASE') ?: "tourdulich";
$port = getenv('MYSQLPORT') ?: 3306; // Railway thường dùng cổng khác 3306, nên cần biến này

// Tạo kết nối (Thêm tham số $port vào cuối)
$conn = new mysqli($host, $user, $pass, $db, $port);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    // Nên ẩn lỗi chi tiết khi lên môi trường production để bảo mật
    die("Kết nối thất bại. Vui lòng kiểm tra lại cấu hình."); 
    // Hoặc để debug thì dùng dòng dưới:
    // die("Lỗi kết nối database: " . $conn->connect_error);
}

// Cấu hình charset và timezone
$conn->set_charset("utf8mb4");
try {
    $conn->query("SET time_zone = '+07:00'");
} catch (Exception $e) {
    // Bỏ qua lỗi timezone nếu server không hỗ trợ hoặc không có quyền
}
// ===== SMTP Gmail (gửi OTP email) =====
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 587);
define('SMTP_AUTH_USER', '9e5ce0001@smtp-brevo.com'); 
define('SMTP_AUTH_PASS', 'xsmtpsib-54e60b36c75c8cbb647f45d89f9d2dea251a5a6ddde2ebe485761b1ba8cb90a8-fsbDEDTPlLG8HFTn'); 
define('SMTP_FROM_EMAIL', 'tranhoaiphuc1810@gmail.com');

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
require_once __DIR__ . "/../lib/helpers.php";
if (getenv('MYSQLHOST')) {
    define('BASE_URL', 'https://projectdulich-production.up.railway.app'); // Hoặc 'https://ten-du-an.up.railway.app'
} else {
    define('BASE_URL', '/my_project/public'); // Localhost
}
?>

