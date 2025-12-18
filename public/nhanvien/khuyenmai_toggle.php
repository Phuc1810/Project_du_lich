<?php
// public/nhanvien/khuyenmai_toggle.php
require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: khuyenmai.php"); exit; }

try {
    // 1. Lấy trạng thái hiện tại VÀ Ngày kết thúc
    $stmt = $conn->prepare("SELECT TrangThai, NgayKetThuc FROM chuongtrinhkhuyenmai WHERE MaCTKM=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $curStatus = (string)($row['TrangThai'] ?? '');
        $endDate   = (string)($row['NgayKetThuc'] ?? '');
        $today     = date('Y-m-d');

        $newStatus = '';
        $msgType   = '';

        // --- LOGIC XỬ LÝ ---

        // TRƯỜNG HỢP 1: Đang chạy -> Muốn TẮT (Thủ công)
        if ($curStatus === 'Hoạt động') {
            $newStatus = 'Ngừng hoạt động';
            $msgType   = 'hidden'; // Đã tắt
        } 
        // TRƯỜNG HỢP 2: Đang tắt/Hết hạn -> Muốn BẬT lại
        else {
            // Kiểm tra ngày: Nếu ngày kết thúc nhỏ hơn hôm nay -> KHÔNG CHO BẬT
            if ($endDate < $today) {
                // Báo lỗi: Phải sửa ngày kết thúc trước
                header("Location: khuyenmai.php?err=date_expired");
                exit; 
            } else {
                // Còn hạn -> Cho phép bật
                $newStatus = 'Hoạt động';
                $msgType   = 'shown'; // Đã bật
            }
        }

        // 2. Cập nhật DB
        $stmt = $conn->prepare("UPDATE chuongtrinhkhuyenmai SET TrangThai=? WHERE MaCTKM=? LIMIT 1");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        $stmt->close();

        // 3. Về trang danh sách
        header("Location: khuyenmai.php?msg=" . $msgType);
    } else {
        header("Location: khuyenmai.php");
    }
    exit;

} catch (Exception $e) {
    header("Location: khuyenmai.php");
    exit;
}