<?php
// public/nhanvien/donhang.php

require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// 1. SỬA: Guard dùng SESSION STAFF
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


// ===== AUTO UPDATE trạng thái theo ngày (chạy cho toàn bộ đơn) =====
try {
    // 1) Đang diễn ra: từ "Đã thanh toán" -> "Đang diễn ra"
    $sqlRun = "
        UPDATE dondattour d
        JOIN tour t ON t.MaTour = d.MaTour
        SET d.TrangThai = 'Đang diễn ra'
        WHERE d.TrangThai = 'Đã thanh toán'
          AND t.NgayKhoiHanh IS NOT NULL
          AND t.NgayKetThuc IS NOT NULL
          AND CURDATE() >= t.NgayKhoiHanh
          AND CURDATE() <= t.NgayKetThuc
    ";
    $conn->query($sqlRun);

    // 2) Đã hoàn tất: từ 'Đã thanh toán' hoặc 'Đang diễn ra' -> 'Đã hoàn tất'
    $sqlDone = "
        UPDATE dondattour d
        JOIN tour t ON t.MaTour = d.MaTour
        SET d.TrangThai = 'Đã hoàn tất'
        WHERE d.TrangThai IN ('Đã thanh toán','Đang diễn ra')
          AND t.NgayKetThuc IS NOT NULL
          AND CURDATE() > t.NgayKetThuc
    ";
    $conn->query($sqlDone);
} catch (Throwable $e) {
    // bỏ qua để trang vẫn chạy
}

// ===== Filters =====
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// ===== Build WHERE =====
$where = "1=1";
$params = [];
$types = "";

if ($status !== '') {
    $where .= " AND d.TrangThai = ?";
    $types .= "s";
    $params[] = $status;
}

if ($q !== '') {
    $where .= " AND (d.MaDon = ? OR t.TenTour LIKE ?)";
    $types .= "is";
    $params[] = (int)$q;
    $params[] = "%" . $q . "%";
}

// ===== Total =====
$sqlCount = "SELECT COUNT(*) AS c FROM dondattour d JOIN Tour t ON t.MaTour = d.MaTour WHERE $where";
$stmt = $conn->prepare($sqlCount);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $limit));

// ===== List =====
$sqlList = "
  SELECT 
    d.MaDon, d.NgayDat, d.TrangThai, 
    (d.SoLuongNguoiLon + d.SoLuongTreEm + d.SoLuongTreNho) AS SoNguoi,
    d.TongTienPhaiTra,
    t.MaTour, t.TenTour, t.DiaDiem
  FROM dondattour d
  JOIN tour t ON t.MaTour = d.MaTour
  WHERE $where
  ORDER BY d.MaDon DESC
  LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($sqlList);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$rs = $stmt->get_result();
$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$stmt->close();

// Dropdown options
$statusList = [
    "" => "Tất cả trạng thái",
    "Chờ thanh toán" => "Chờ thanh toán",
    "Đã thanh toán" => "Đã thanh toán",
    "Đang diễn ra" => "Đang diễn ra",
    "Đã hoàn tất" => "Đã hoàn tất",
];
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Quản lý Đơn tour | VietJourney</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/nhanvien.css">
</head>

<body>

    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="brand-section">
                <a href="#" class="brand-logo"><i class="fa-solid fa-compass"></i> VietJourney</a>
            </div>
            <nav class="nav-section">
                <div class="nav-label">Tổng quan</div>
                <a href="index.php" class="nav-link">
                    <i class="fa-solid fa-grid-2"></i> Dashboard
                </a>
                <div class="nav-label mt-4">Quản lý nghiệp vụ</div>
                <a href="donhang.php" class="nav-link active">
                    <i class="fa-solid fa-receipt"></i> Đơn đặt tour
                </a>
                <a href="donyeucau.php" class="nav-link">
                    <i class="fa-solid fa-building-user"></i> Yêu cầu doanh nghiệp
                </a>
                <a href="tour.php" class="nav-link">
                    <i class="fa-solid fa-map-location-dot"></i> Quản lý Tour
                </a>
                <a href="tintuc.php" class="nav-link"><i class="fa-solid fa-newspaper"></i> Quản lý tin tức</a>
                <a href="khuyenmai.php" class="nav-link"><i class="fa-solid fa-tags"></i> Quản lý khuyến mãi</a>
                <div class="nav-label mt-4">Hệ thống</div>
                <a href="logout.php" class="nav-link text-danger">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Đăng xuất
                </a>
            </nav>
            <div class="user-section">
                <div class="user-card">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['staff']['TenDangNhap'] ?? 'NV', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= h($_SESSION['staff']['HoTen'] ?? $_SESSION['staff']['TenDangNhap'] ?? 'Nhân viên') ?></div>
                        <div class="user-role">Nhân viên hệ thống</div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1 class="page-title">Đơn đặt tour (Khách lẻ)</h1>
                    <div class="current-date">Tổng cộng: <b><?= number_format($total) ?></b> đơn hàng</div>
                </div>
            </header>

            <div class="toolbar-card">
                <form method="GET" action="donhang.php" class="search-form">
                    <div class="search-group">
                        <i class="fa-solid fa-magnifying-glass text-secondary"></i>
                        <input type="text" name="q" class="search-input"
                            value="<?= h($q) ?>" placeholder="Nhập mã đơn, tên tour...">
                    </div>

                    <div class="search-group">
                        <i class="fa-solid fa-filter text-secondary"></i>
                        <select name="status" class="search-select">
                            <?php foreach ($statusList as $k => $label): ?>
                                <option value="<?= h($k) ?>" <?= ($status === $k ? 'selected' : '') ?>>
                                    <?= h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="search-btn" type="submit">
                        Lọc dữ liệu
                    </button>

                    <a class="reset-btn" href="donhang.php"><i class="fa-solid fa-rotate-left"></i></a>
                </form>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:100px;">Mã đơn</th>
                                <th>Thông tin Tour</th>
                                <th style="width:120px;">Ngày đặt</th>
                                <th style="width:100px;">Số khách</th>
                                <th style="width:150px;">Trạng thái</th>
                                <th style="width:150px;" class="text-end">Tổng tiền</th>
                                <th style="width:120px;" class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fa-regular fa-folder-open fa-2x mb-3 d-block"></i>
                                        Không tìm thấy đơn hàng nào phù hợp.
                                    </td>
                                </tr>
                                <?php else: foreach ($rows as $d): ?>
                                    <tr>
                                        <td><span class="fw-bold">#<?= (int)$d['MaDon'] ?></span></td>
                                        <td>
                                            <div class="fw-bold cell-truncate" title="<?= h($d['TenTour']) ?>">
                                                <?= h($d['TenTour'] ?? '') ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fa-solid fa-location-dot me-1"></i><?= h($d['DiaDiem'] ?? '') ?>
                                            </small>
                                        </td>
                                        <td><?= !empty($d['NgayDat']) ? date('d/m/Y', strtotime($d['NgayDat'])) : '-' ?></td>
                                        <td><i class="fa-solid fa-users me-1 text-secondary"></i><?= (int)$d['SoNguoi'] ?></td>
                                        <td>
                                            <?php
                                            $st = (string)($d['TrangThai'] ?? '');
                                            $badgeClass = 'badge-soft-secondary';
                                            if ($st === 'Đã thanh toán') $badgeClass = 'badge-soft-success';
                                            else if ($st === 'Chờ thanh toán') $badgeClass = 'badge-soft-warning';
                                            else if ($st === 'Đang diễn ra') $badgeClass = 'badge-soft-primary';
                                            else if ($st === 'Đã hoàn tất') $badgeClass = 'badge-soft-secondary'; // có thể tạo class danger nếu muốn
                                            ?>
                                            <span class="badge-soft <?= $badgeClass ?>"><?= h($st) ?></span>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            <?= number_format((float)($d['TongTienPhaiTra'] ?? 0), 0, ',', '.') ?>đ
                                        </td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary rounded-pill"
                                                href="chitietdon.php?id=<?= (int)$d['MaDon'] ?>">
                                                Chi tiết
                                            </a>
                                        </td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php
                            $base = "donhang.php?status=" . urlencode($status) . "&q=" . urlencode($q) . "&page=";
                            $prev = max(1, $page - 1);
                            $next = min($totalPages, $page + 1);
                            ?>

                            <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
                                <a class="page-link" href="<?= h($base . $prev) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                            </li>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?= ($i === $page ? 'active' : '') ?>">
                                    <a class="page-link" href="<?= h($base . $i) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>">
                                <a class="page-link" href="<?= h($base . $next) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>