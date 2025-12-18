<?php
// public/nhanvien/tintuc_toggle.php

require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php"); exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: tintuc.php"); exit; }

try {
    $stmt = $conn->prepare("SELECT TrangThai FROM tintuc WHERE MaTin=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $cur = trim((string)$row['TrangThai']);
        // Đảo trạng thái
        $new = ($cur === 'Hiển thị') ? 'Ẩn' : 'Hiển thị';

        $stmt = $conn->prepare("UPDATE tintuc SET TrangThai=? WHERE MaTin=? LIMIT 1");
        $stmt->bind_param("si", $new, $id);
        $stmt->execute();
        $stmt->close();

        // --- SỬA ĐOẠN NÀY ---
        // Kiểm tra trạng thái MỚI để gửi thông báo phù hợp
        if ($new === 'Hiển thị') {
            $msgType = 'shown'; // Đã hiện
        } else {
            $msgType = 'hidden'; // Đã ẩn
        }
        
        header("Location: tintuc.php?msg=" . $msgType);
        // --------------------
    } else {
        header("Location: tintuc.php");
    }
    exit;

} catch (Exception $e) {
    header("Location: tintuc.php");
    exit;
}