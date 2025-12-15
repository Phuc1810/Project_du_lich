<?php
require_once __DIR__ . "/../app/config/config.php";

$key = isset($_GET['key']) ? trim($_GET['key']) : "";
$key = mb_strtolower($key, 'UTF-8');

$sql = "SELECT DISTINCT DiaDiem 
        FROM Tour
        WHERE TrangThai = 'Hoạt động'";

if ($key !== "") {
    // lọc theo từ khoá người dùng gõ
    $like = "%".$conn->real_escape_string($key)."%";
    $sql .= " AND LOWER(DiaDiem) LIKE LOWER('$like')";
}

$sql .= " ORDER BY DiaDiem LIMIT 30";

$res = $conn->query($sql);

if (!$res || $res->num_rows == 0) {
    echo "<div class='suggest-item text-muted'>Không có địa điểm phù hợp</div>";
    exit;
}

while ($row = $res->fetch_assoc()) {
    $dia = htmlspecialchars($row['DiaDiem']);
    echo "<div class='suggest-item' data-value='$dia'>$dia</div>";
}
