<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$redirect = $_GET['redirect'] ?? 'trangchu.php';

// xoá dữ liệu session
$_SESSION = [];

// xoá cookie session
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}

session_destroy();

// chặn redirect absolute
$redirect = trim((string)$redirect);
if ($redirect === '' || preg_match('#^(https?:)?//#i', $redirect)) $redirect = 'trangchu.php';

header("Location: " . $redirect);
exit;
