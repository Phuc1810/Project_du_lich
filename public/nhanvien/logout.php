<?php
// public/nhanvien/logout.php

// 1. Khởi động session để tìm thấy dữ liệu đang lưu
if (session_status() === PHP_SESSION_NONE) session_start();

// 2. Xóa session của nhân viên
if (isset($_SESSION['staff'])) {
    unset($_SESSION['staff']);
}

// 3. Chuyển hướng về trang đăng nhập
header("Location: login.php");
exit;
?>