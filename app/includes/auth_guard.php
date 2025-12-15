<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function is_logged_in(): bool {
  return isset($_SESSION['user']) && !empty($_SESSION['user']['MaTK']);
}

function require_login(string $redirect_to) {
  if (!is_logged_in()) {
    header("Location: auth.php?redirect=" . urlencode($redirect_to));
    exit;
  }
}
