<?php
// public/nhanvien/tour.php

require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// 1. Guard
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 2. Filters & Pagination Params
$q    = trim($_GET['q'] ?? '');
$loai = trim($_GET['loai'] ?? '');
$tt   = trim($_GET['tt'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// 3. Build Where Clause
$where = "1=1";
$params = [];
$types  = "";

// LOGIC TÌM KIẾM
if ($q !== '') {
    if (substr($q, 0, 1) === '#') {
        // Nếu gõ # ở đầu -> Tìm chính xác Mã Tour
        $searchId = trim(substr($q, 1));
        if (is_numeric($searchId)) {
            $where .= " AND t.MaTour = ? ";
            $params[] = $searchId;
            $types .= "i";
        } else {
            $where .= " AND 0 ";
        }
    } else {
        // Nếu gõ chữ thường -> Tìm Tên hoặc Địa điểm
        $where .= " AND (t.TenTour LIKE ? OR t.DiaDiem LIKE ?) ";
        $like = "%{$q}%";
        $params[] = $like;
        $types .= "s";
        $params[] = $like;
        $types .= "s";
    }
}

if ($loai !== '') {
    $where .= " AND t.LoaiTour = ? ";
    $params[] = $loai;
    $types .= "s";
}

if ($tt !== '') {
    $where .= " AND t.TrangThai = ? ";
    $params[] = $tt;
    $types .= "s";
}

// 4. Count Total
$sqlCount = "SELECT COUNT(*) as c FROM tour t WHERE $where";
$stmt = $conn->prepare($sqlCount);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalTours = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalTours / $limit));

// 5. Query List Data
$sql = "
  SELECT
    t.MaTour, t.TenTour, t.DiaDiem, t.ThoiLuong,
    t.GiaGoc, t.GiaGiam, t.PhanTramGiam,
    t.SoCho, t.SoChoDaDat, t.NgayKhoiHanh,
    t.LoaiTour, t.TrangThai,
    h.DuongDan AS AnhChinh
  FROM tour t
  LEFT JOIN hinhanhtour h
    ON h.MaTour = t.MaTour AND h.LaAnhChinh = 1
  WHERE $where
  ORDER BY t.MaTour DESC
  LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$rs = $stmt->get_result();
$tours = $rs->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 6. Danh sách Filter
$loaiList = [];
$r1 = $conn->query("SELECT DISTINCT LoaiTour FROM tour WHERE LoaiTour IS NOT NULL AND LoaiTour<>'' ORDER BY LoaiTour");
while ($row = $r1->fetch_assoc()) $loaiList[] = $row['LoaiTour'];

$ttList = ['Hoạt động', 'Hết chỗ', 'Ngừng hoạt động'];
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Quản lý Tour | VietJourney</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/nhanvien.css">

    <style>
        .thumb {
            width: 80px;
            height: 55px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .table-card {
            overflow: hidden;
        }

        /* CSS Bảng */
        .table th {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table th,
        .table td {
            white-space: nowrap;
            vertical-align: middle;
            padding: 16px 20px;
        }

        .col-id {
            width: 60px;
            text-align: center;
            font-weight: 700;
            color: #111827;
        }

        /* Giới hạn chiều rộng tên tour */
        .tour-name {
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 15px;
        }
    </style>
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
                <a href="donyeucau.php" class="nav-link"><i class="fa-solid fa-building-user"></i> Yêu cầu doanh nghiệp</a>
                <a href="tour.php" class="nav-link active"><i class="fa-solid fa-map-location-dot"></i> Quản lý Tour</a>
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
                    <h1 class="page-title">Quản lý Tour</h1>
                    <div class="current-date">Tổng: <b><?= $totalTours ?></b> tour</div>
                </div>
                <div>
                    <a href="tour_them.php" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Thêm Mới
                    </a>
                </div>
            </header>
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    <?php
                    $msg = $_GET['msg'];
                    if ($msg == 'added')        echo "Thêm tour mới thành công!";
                    elseif ($msg == 'updated')  echo "Cập nhật thông tin tour thành công!";
                    elseif ($msg == 'deleted')  echo "Đã xóa tour thành công!";

                    // Thêm 2 dòng này cho Toggle
                    elseif ($msg == 'shown')    echo "Đã kích hoạt tour hoạt động trở lại!";
                    elseif ($msg == 'hidden')   echo "Đã chuyển tour sang trạng thái Ngừng hoạt động!";
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="toolbar-card">
                <form class="search-form" method="GET">
                    <div class="search-group" style="flex: 2;">
                        <i class="fa-solid fa-magnifying-glass text-secondary"></i>
                        <input class="search-input" name="q" value="<?= h($q) ?>"
                            placeholder="Nhập tên hoặc địa điểm tour... (Gõ # + Mã số để tìm chính xác)">
                    </div>

                    <div class="search-group">
                        <i class="fa-solid fa-filter text-secondary"></i>
                        <select class="search-select" name="loai">
                            <option value="">-- Tất cả loại --</option>
                            <?php foreach ($loaiList as $x): ?>
                                <option value="<?= h($x) ?>" <?= $loai === $x ? 'selected' : '' ?>><?= h($x) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-group">
                        <i class="fa-solid fa-toggle-on text-secondary"></i>
                        <select class="search-select" name="tt">
                            <option value="">-- Tất cả trạng thái --</option>
                            <?php foreach ($ttList as $x): ?>
                                <option value="<?= h($x) ?>" <?= $tt === $x ? 'selected' : '' ?>><?= h($x) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="search-btn" type="submit">Lọc</button>
                    <a class="reset-btn" href="tour.php"><i class="fa-solid fa-rotate-left"></i></a>
                </form>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="col-id">MÃ</th>
                                <th style="width: 100px;">ẢNH</th>
                                <th>THÔNG TIN TOUR</th>
                                <th>GIÁ BÁN</th>
                                <th>CHỖ</th>
                                <th>LOẠI HÌNH</th>
                                <th>TRẠNG THÁI</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tours)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">Chưa có dữ liệu tour nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tours as $t):
                                    $ttour = (string)($t['TrangThai'] ?? '');
                                    $badgeClass = 'badge-soft-success';
                                    if ($ttour === 'Ngừng hoạt động') $badgeClass = 'badge-soft-secondary';
                                    if ($ttour === 'Hết chỗ') $badgeClass = 'badge-soft-danger';

                                    // Giá
                                    $gg = (float)($t['GiaGiam'] ?? 0);
                                    $g0 = (float)($t['GiaGoc'] ?? 0);
                                    $price = ($gg > 0 && $gg < $g0) ? $gg : $g0;
                                ?>
                                    <tr>
                                        <td class="col-id">#<?= (int)$t['MaTour'] ?></td>

                                        <td>
                                            <?php if (!empty($t['AnhChinh'])): ?>
                                                <img class="thumb" src="<?= h(asset_url($t['AnhChinh'])) ?>" alt="Img" onerror="this.src='https://placehold.co/80x55?text=No+Img'">
                                            <?php else: ?>
                                                <div class="thumb d-flex align-items-center justify-content-center bg-light text-muted small">
                                                    No Img
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="fw-bold text-dark tour-name" title="<?= h($t['TenTour']) ?>">
                                                <?= h($t['TenTour']) ?>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <i class="fa-solid fa-location-dot me-1 text-danger"></i><?= h($t['DiaDiem'] ?? '-') ?>
                                                <span class="mx-1 text-secondary">•</span>
                                                <i class="fa-regular fa-clock me-1 text-primary"></i><?= h($t['ThoiLuong'] ?? '-') ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="fw-bold text-dark"><?= number_format($price, 0, ',', '.') ?>đ</div>
                                            <?php if ($gg > 0 && $gg < $g0): ?>
                                                <div class="text-muted small text-decoration-line-through"><?= number_format($g0, 0, ',', '.') ?>đ</div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="fw-bold"><?= (int)$t['SoChoDaDat'] ?></span> <span class="text-muted">/ <?= (int)$t['SoCho'] ?></span>
                                            <?php
                                            $percent = ($t['SoCho'] > 0) ? ($t['SoChoDaDat'] / $t['SoCho']) * 100 : 0;
                                            $percent = min(100, $percent);
                                            ?>
                                            <div class="progress mt-1" style="height: 4px; width: 60px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $percent ?>%"></div>
                                            </div>
                                        </td>

                                        <td><span class="small text-muted"><?= h($t['LoaiTour'] ?? '') ?></span></td>

                                        <td><span class="badge-soft <?= $badgeClass ?>"><?= h($ttour) ?></span></td>

                                        <td class="text-end">
                                            <?php
                                            // Logic: Nếu khác 'Ngừng hoạt động' (tức là Hoạt động hoặc Hết chỗ) thì coi là Đang hiện
                                            $isActive = ($t['TrangThai'] !== 'Ngừng hoạt động');
                                            ?>

                                            <a class="btn btn-sm btn-outline-primary rounded-pill me-1"
                                                href="tour_sua.php?id=<?= (int)$t['MaTour'] ?>" title="Sửa">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>

                                            <a href="tour_toggle.php?id=<?= (int)$t['MaTour'] ?>"
                                                class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-outline-success' ?> rounded-pill"
                                                onclick="return confirm('Bạn có chắc muốn <?= $isActive ? 'NGỪNG HOẠT ĐỘNG' : 'KÍCH HOẠT' ?> tour này?');"
                                                title="<?= $isActive ? 'Ngừng hoạt động' : 'Kích hoạt' ?>">
                                                <?= $isActive ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>' ?>
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
                            $base = "tour.php?tt=" . urlencode($tt) . "&loai=" . urlencode($loai) . "&q=" . urlencode($q) . "&page=";
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