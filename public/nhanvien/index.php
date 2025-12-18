<?php
// public/nhanvien/index.php

// 1. Nạp config & Session
require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// 2. BẢO MẬT: Kiểm tra đúng session 'staff'
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ====== DATA LOGIC ======
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// 1. Đơn tour
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM dondattour");
$stmt->execute();
$totalDon = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM dondattour WHERE TrangThai = 'Đã thanh toán'");
$stmt->execute();
$paidDon = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM dondattour WHERE DATE(NgayDat)=?");
$stmt->bind_param("s", $today);
$stmt->execute();
$todayDon = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Doanh thu tháng
$stmt = $conn->prepare("SELECT COALESCE(SUM(TongTienPhaiTra),0) AS s FROM dondattour WHERE TrangThai='Đã thanh toán' AND DATE(NgayDat) BETWEEN ? AND ?");
$stmt->bind_param("ss", $monthStart, $monthEnd);
$stmt->execute();
$revMonth = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
$stmt->close();

// 2. Yêu cầu DN
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM yeucaudoanhnghiep");
$stmt->execute();
$totalYC = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM yeucaudoanhnghiep WHERE TrangThai = 'Chờ xử lý'");
$stmt->execute();
$pendingYC = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// 3. Tour
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tour");
$stmt->execute();
$totalTour = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tour WHERE LoaiTour LIKE '%Doanh%'");
$stmt->execute();
$totalTourDN = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// 4. Recent Lists
// Đơn đặt tour
$recentDon = [];
$rs = $conn->query("SELECT d.MaDon, d.NgayDat, d.TrangThai, d.TongTienPhaiTra, t.TenTour FROM dondattour d JOIN tour t ON t.MaTour = d.MaTour ORDER BY d.MaDon DESC LIMIT 6");
if ($rs) while ($r = $rs->fetch_assoc()) $recentDon[] = $r;

// Yêu cầu Doanh nghiệp
$recentYC = [];
$rs = $conn->query("SELECT y.MaYC, y.NgayThanhToan, y.ThoiGianKhoiHanh, y.TrangThai, y.SoNguoi, t.TenTour FROM yeucaudoanhnghiep y LEFT JOIN tour t ON t.MaTour = y.MaTour ORDER BY y.MaYC DESC LIMIT 6");
if ($rs) while ($r = $rs->fetch_assoc()) $recentYC[] = $r;

?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Dashboard Nhân viên | VietJourney</title>
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
            <a href="index.php" class="nav-link active"><i class="fa-solid fa-grid-2"></i> Dashboard</a>
            
            <div class="nav-label mt-4">Quản lý nghiệp vụ</div>
            <a href="donhang.php" class="nav-link"><i class="fa-solid fa-receipt"></i> Đơn đặt tour</a>
            <a href="donyeucau.php" class="nav-link"><i class="fa-solid fa-building-user"></i> Yêu cầu doanh nghiệp</a>
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
                <h1 class="page-title">Dashboard Tổng Quan</h1>
                <div class="current-date">Hôm nay, ngày <?= date('d/m/Y') ?></div>
            </div>
        </header>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon icon-blue"><i class="fa-solid fa-cart-shopping"></i></div>
                    </div>
                    <div class="stat-label">Tổng đơn tour</div>
                    <div class="stat-value"><?= number_format($totalDon) ?></div>
                    <div class="stat-trend trend-neutral"><i class="fa-solid fa-calendar-day"></i> +<?= number_format($todayDon) ?> đơn hôm nay</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon icon-orange"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                    <div class="stat-label">Doanh thu tháng này</div>
                    <div class="stat-value"><?= number_format($revMonth, 0, ',', '.') ?>đ</div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-trend-up"></i> Đã thanh toán</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon icon-green"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                    <div class="stat-label">Đơn thành công</div>
                    <div class="stat-value"><?= number_format($paidDon) ?></div>
                    <div class="stat-trend trend-up">Tỷ lệ <?= ($totalDon>0? round($paidDon*100/$totalDon):0) ?>% tổng đơn</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon icon-purple"><i class="fa-solid fa-briefcase"></i></div>
                    </div>
                    <div class="stat-label">Yêu cầu Doanh nghiệp</div>
                    <div class="stat-value"><?= number_format($totalYC) ?></div>
                    <div class="stat-trend trend-neutral">Đang chờ xử lý: <b><?= number_format($pendingYC) ?></b></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="table-card">
                    <div class="table-header">
                        <h2 class="table-title">Đơn đặt tour mới nhất</h2>
                        <a href="donhang.php" class="view-all">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Tên Tour</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Tổng tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentDon)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                                <?php else: foreach ($recentDon as $d): ?>
                                    <?php 
                                        // Logic màu sắc đồng bộ donhang.php
                                        $st = (string)($d['TrangThai'] ?? '');
                                        $badgeClass = 'badge-soft-secondary';
                                        if ($st === 'Đã thanh toán') $badgeClass = 'badge-soft-success'; // Xanh lá
                                        else if ($st === 'Chờ thanh toán') $badgeClass = 'badge-soft-warning'; // Vàng
                                        else if ($st === 'Đang diễn ra') $badgeClass = 'badge-soft-primary'; // Xanh dương
                                        else if ($st === 'Đã hoàn tất') $badgeClass = 'badge-soft-secondary'; // Xám
                                    ?>
                                    <tr>
                                        <td><span class="fw-bold">#<?= $d['MaDon'] ?></span></td>
                                        <td>
                                            <div class="cell-truncate" title="<?= h($d['TenTour']) ?>"><?= h($d['TenTour']) ?></div>
                                            <small class="text-muted"><?= date('d/m', strtotime($d['NgayDat'])) ?></small>
                                        </td>
                                        <td><span class="badge-soft <?= $badgeClass ?>"><?= h($st) ?></span></td>
                                        <td class="text-end fw-bold"><?= number_format($d['TongTienPhaiTra'], 0, ',', '.') ?>đ</td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="table-card">
                    <div class="table-header">
                        <h2 class="table-title">Yêu cầu Doanh nghiệp</h2>
                        <a href="donyeucau.php" class="view-all">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tour yêu cầu</th>
                                    <th>Khởi hành</th>
                                    <th>Số khách</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentYC)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                                <?php else: foreach ($recentYC as $y): ?>
                                    <?php 
                                        // Logic màu sắc đồng bộ donyeucau.php
                                        $stText = (string)($y['TrangThai'] ?? '');
                                        $badgeClass = 'badge-soft-secondary';
                                        switch ($stText) {
                                            case 'Chờ xử lý':  $badgeClass = 'badge-soft-warning'; break; // Vàng
                                            case 'Đã liên hệ': $badgeClass = 'badge-soft-info';    break; // Xanh dương
                                            case 'Hoàn thành': $badgeClass = 'badge-soft-success'; break; // Xanh lá
                                            case 'Hủy tour':   $badgeClass = 'badge-soft-danger';  break; // Đỏ
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold cell-truncate" title="<?= h($y['TenTour'] ?? 'Tour theo yêu cầu') ?>">
                                                <?= h($y['TenTour'] ?? 'Tour theo yêu cầu') ?>
                                            </div>
                                            <small class="text-muted">#<?= $y['MaYC'] ?></small>
                                        </td>
                                        <td><?= !empty($y['ThoiGianKhoiHanh']) ? date('d/m/Y', strtotime($y['ThoiGianKhoiHanh'])) : '-' ?></td>
                                        <td><?= $y['SoNguoi'] ?></td>
                                        <td><span class="badge-soft <?= $badgeClass ?>"><?= h($stText) ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
