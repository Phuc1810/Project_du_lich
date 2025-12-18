<?php
// public/nhanvien/donyeucau.php

require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// 1. Guard dùng SESSION STAFF
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ==== Filter/Search ====
$q = trim($_GET['q'] ?? '');
$st = trim($_GET['st'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Xây dựng điều kiện WHERE
$where = "1=1";
$params = [];
$types  = "";

if ($q !== '') {
    $where .= " AND (y.TenCongTy LIKE ? OR y.NguoiLienHe LIKE ? OR y.SDT LIKE ? OR t.TenTour LIKE ?)";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if ($st !== '') {
    $where .= " AND y.TrangThai = ?";
    $params[] = $st;
    $types .= "s";
}

// Đếm tổng số dòng (để phân trang)
$sqlCount = "
  SELECT COUNT(*) as c 
  FROM yeucaudoanhnghiep y 
  LEFT JOIN tour t ON t.MaTour = y.MaTour
  WHERE $where
";
$stmt = $conn->prepare($sqlCount);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRows = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));

// Query dữ liệu chính
$sql = "
  SELECT
    y.MaYC, y.TenCongTy, y.NguoiLienHe, y.SDT, y.SoNguoi, y.ThoiGianKhoiHanh,
    y.TrangThai, y.MaKH, y.MaNV, y.MaTour,
    kh.HoTen AS KH_HoTen,
    t.TenTour AS Tour_TenTour,
    nv.HoTen AS NV_HoTen
  FROM yeucaudoanhnghiep y
  LEFT JOIN khachhang kh ON kh.MaKH = y.MaKH
  LEFT JOIN tour t ON t.MaTour = y.MaTour
  LEFT JOIN nhanvien nv ON nv.MaNV = y.MaNV
  WHERE $where
  ORDER BY y.MaYC DESC
  LIMIT ? OFFSET ?
";

// Thêm limit/offset vào params để bind
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rs = $stmt->get_result();

$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$stmt->close();

// Danh sách trạng thái
$statusOptions = ["Chờ xử lý", "Đã liên hệ", "Hủy tour", "Hoàn thành"];
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Yêu cầu doanh nghiệp | VietJourney</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
                <a href="index.php" class="nav-link"><i class="fa-solid fa-grid-2"></i> Dashboard</a>

                <div class="nav-label mt-4">Quản lý nghiệp vụ</div>
                <a href="donhang.php" class="nav-link"><i class="fa-solid fa-receipt"></i> Đơn đặt tour</a>
                <a href="donyeucau.php" class="nav-link active"><i class="fa-solid fa-building-user"></i> Yêu cầu doanh nghiệp</a>
                <a href="tour.php" class="nav-link"><i class="fa-solid fa-map-location-dot"></i> Quản lý Tour</a>
                <a href="tintuc.php" class="nav-link"><i class="fa-solid fa-newspaper"></i> Quản lý tin tức</a>
                <a href="khuyenmai.php" class="nav-link"><i class="fa-solid fa-tags"></i> Quản lý khuyến mãi</a>

                <div class="nav-label mt-4">Hệ thống</div>
                <a href="logout.php" class="nav-link text-danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Đăng xuất</a>
            </nav>
            <div class="user-section">
                <div class="user-card">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['staff']['TenDangNhap'] ?? 'NV', 0, 1)) ?></div>
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
                    <h1 class="page-title">Yêu cầu Doanh nghiệp</h1>
                    <div class="current-date">Tổng: <b><?= $totalRows ?></b> yêu cầu</div>
                </div>
            </header>

            <div class="toolbar-card">
                <form class="search-form" method="GET">
                    <div class="search-group" style="flex: 2;">
                        <i class="fa-solid fa-magnifying-glass text-secondary"></i>
                        <input class="search-input" name="q" value="<?= h($q) ?>"
                            placeholder="Tìm tên công ty, người liên hệ, SĐT...">
                    </div>

                    <div class="search-group">
                        <i class="fa-solid fa-filter text-secondary"></i>
                        <select class="search-select" name="st">
                            <option value="">Tất cả trạng thái</option>
                            <?php foreach ($statusOptions as $opt): ?>
                                <option value="<?= h($opt) ?>" <?= ($st === $opt ? 'selected' : '') ?>><?= h($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="search-btn" type="submit">Lọc</button>
                    <a class="reset-btn" href="donyeucau.php"><i class="fa-solid fa-rotate-left"></i></a>
                </form>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Mã YC</th>
                                <th>Thông tin Công ty / Liên hệ</th>
                                <th>Thông tin Tour / Đoàn</th>
                                <th>Trạng thái</th>
                                <th>NV Phụ trách</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">Không tìm thấy yêu cầu nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r):
                                    $kh = !empty($r['ThoiGianKhoiHanh']) ? date('d/m/Y', strtotime($r['ThoiGianKhoiHanh'])) : '—';
                                    $stText = (string)($r['TrangThai'] ?? '');

                                    $badgeClass = 'badge-soft-secondary';
                                    switch ($stText) {
                                        case 'Chờ xử lý':
                                            $badgeClass = 'badge-soft-warning';
                                            break;
                                        case 'Đã liên hệ':
                                            $badgeClass = 'badge-soft-info';
                                            break;
                                        case 'Hoàn thành':
                                            $badgeClass = 'badge-soft-success';
                                            break;
                                        case 'Hủy tour':
                                            $badgeClass = 'badge-soft-danger';
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><span class="fw-bold">#<?= (int)$r['MaYC'] ?></span></td>

                                        <td>
                                            <div class="fw-bold text-primary"><?= h($r['TenCongTy'] ?? '—') ?></div>
                                            <div class="small text-muted mt-1">
                                                <i class="fa-regular fa-user me-1"></i><?= h($r['NguoiLienHe'] ?? '') ?>
                                                <span class="mx-1">•</span>
                                                <i class="fa-solid fa-phone me-1"></i><?= h($r['SDT'] ?? '') ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="fw-bold cell-truncate" title="<?= h($r['Tour_TenTour']) ?>">
                                                <?= h($r['Tour_TenTour'] ?? 'Tour theo yêu cầu riêng') ?>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <i class="fa-regular fa-calendar me-1"></i><?= h($kh) ?>
                                                <span class="mx-1">•</span>
                                                <i class="fa-solid fa-users me-1"></i><?= (int)$r['SoNguoi'] ?> khách
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge-soft <?= $badgeClass ?>"><?= h($stText) ?></span>
                                        </td>

                                        <td>
                                            <?php if (!empty($r['NV_HoTen'])): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="user-avatar" style="width:24px; height:24px; font-size:10px;">
                                                        <?= strtoupper(substr($r['NV_HoTen'], 0, 1)) ?>
                                                    </div>
                                                    <span class="small"><?= h($r['NV_HoTen']) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small fs-italic">Chưa gán</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary rounded-pill"
                                                href="chitietyeucau.php?id=<?= (int)$r['MaYC'] ?>">
                                                Chi tiết
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php
                            // SỬA: Dùng đúng biến $st thay vì $status
                            $base = "donyeucau.php?st=" . urlencode($st) . "&q=" . urlencode($q) . "&page=";
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