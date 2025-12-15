<?php
require_once "../config.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $madon = isset($_GET['madon']) ? (int)$_GET['madon'] : 0;
  if ($madon <= 0) {
    echo json_encode(['status' => 'invalid'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $conn->prepare("SELECT TrangThai FROM DonDatTour WHERE MaDon=? LIMIT 1");
  $stmt->bind_param("i", $madon);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    echo json_encode(['status' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $st = trim((string)$row['TrangThai']);
  $stLower = mb_strtolower($st, 'UTF-8');

  if ($stLower === mb_strtolower('Đã thanh toán', 'UTF-8')) {
    echo json_encode(['status' => 'paid'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($stLower === mb_strtolower('Hết chỗ', 'UTF-8')) {
    echo json_encode(['status' => 'soldout'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode(['status' => 'pending', 'trangthai' => $st], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
