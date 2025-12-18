<?php
// app/includes/staff_guard.php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_staff_login(): void {
  $ok = !empty($_SESSION['staff']['MaTK']) && (strtoupper((string)($_SESSION['staff']['VaiTro'] ?? '')) === 'NV');
  if (!$ok) {
    header("Location: /nhanvien/login.php");
    exit;
  }
}
