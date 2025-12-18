<?php
// public/nhanvien/tour_toggle.php

require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// Guard
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php"); exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = (int)($_GET['id'] ?? 0);
if($id <= 0){ header("Location: tour.php"); exit; }

try {
    // Lấy trạng thái hiện tại
    $stmt = $conn->prepare("SELECT TrangThai FROM tour WHERE MaTour=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $cur = (string)$row['TrangThai'];
        
        // Logic đổi trạng thái
        if ($cur === 'Ngừng hoạt động') {
            $new = 'Hoạt động';
            $msgType = 'shown'; // Đã kích hoạt lại
        } else {
            // Đang Hoạt động/Hết chỗ -> Chuyển sang Ngừng
            $new = 'Ngừng hoạt động';
            $msgType = 'hidden'; // Đã ngừng hoạt động
        }

        $stmt = $conn->prepare("UPDATE tour SET TrangThai=? WHERE MaTour=? LIMIT 1");
        $stmt->bind_param("si", $new, $id);
        $stmt->execute();
        $stmt->close();

        // Gửi thông báo về
        header("Location: tour.php?msg=" . $msgType);
    } else {
        header("Location: tour.php");
    }
    exit;

} catch (Exception $e) {
    header("Location: tour.php");
    exit;
}