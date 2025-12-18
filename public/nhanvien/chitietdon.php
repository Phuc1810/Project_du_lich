<?php
// public/nhanvien/chitietdon.php

require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// 1. Guard dùng SESSION STAFF
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$maDon = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($maDon <= 0) {
    header("Location: donhang.php");
    exit;
}

// (ĐÃ XÓA PHẦN XỬ LÝ POST CẬP NHẬT TRẠNG THÁI TẠI ĐÂY)

// ==== Lấy chi tiết đơn ====
$sql = "
  SELECT
    d.MaDon, d.NgayDat, d.SoLuongNguoiLon, d.SoLuongTreEm, d.SoLuongTreNho,
    d.GiaNguoiLonApDung, d.GiaTreEmApDung, d.TongTienGoc, d.TongTienPhaiTra,
    d.TrangThai, d.MaKH, d.MaTour, d.MaCTKM,
    kh.HoTen AS KH_HoTen, kh.Email AS KH_Email, kh.SoDienThoai AS KH_SoDienThoai, kh.DiaChi AS KH_DiaChi,
    t.TenTour AS Tour_TenTour, t.DiaDiem AS Tour_DiaDiem, t.ThoiLuong AS Tour_ThoiLuong, t.NgayKhoiHanh AS Tour_NgayKhoiHanh,
    ha.DuongDan AS AnhChinh
  FROM dondattour d
  LEFT JOIN khachhang kh ON kh.MaKH = d.MaKH
  LEFT JOIN tour t ON t.MaTour = d.MaTour
  LEFT JOIN hinhanhtour ha ON ha.MaTour = t.MaTour AND ha.LaAnhChinh = 1
  WHERE d.MaDon = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $maDon);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: donhang.php?notfound=1");
    exit;
}

$ngayDat = !empty($data['NgayDat']) ? date('d/m/Y', strtotime($data['NgayDat'])) : '—';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Chi tiết đơn #<?= (int)$data['MaDon'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/nhanvien.css">
    
    <style>
        .detail-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.04);
            margin-bottom: 24px;
        }
        .section-title { font-size: 16px; font-weight: 700; margin-bottom: 16px; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #e5e7eb; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-gray); font-weight: 500; font-size: 14px; }
        .info-value { font-weight: 600; color: var(--text-dark); font-size: 14px; text-align: right; }
        .tour-img { width: 100%; height: 240px; object-fit: cover; border-radius: 12px; margin-bottom: 16px; border: 1px solid #eee; }
        .btn-back { text-decoration: none; font-weight: 600; color: var(--text-gray); display: flex; align-items: center; gap: 6px; margin-bottom: 20px; transition: 0.2s; }
        .btn-back:hover { color: var(--primary); }
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
            <a href="donhang.php" class="nav-link active"><i class="fa-solid fa-receipt"></i> Đơn đặt tour</a>
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
        <a href="donhang.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Quay lại danh sách</a>
        
        <header class="page-header mb-3">
            <div>
                <h1 class="page-title">Chi tiết đơn hàng #<?= (int)$data['MaDon'] ?></h1>
                <div class="current-date">Ngày đặt: <?= h($ngayDat) ?></div>
            </div>
            <div>
                 <?php
                    $st = $data['TrangThai'];
                    $cls = 'bg-secondary';
                    if($st == 'Đã thanh toán') $cls = 'bg-success';
                    if($st == 'Chờ thanh toán') $cls = 'bg-warning text-dark';
                 ?>
                 <span class="badge <?= $cls ?> fs-6"><?= h($st) ?></span>
            </div>
        </header>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="detail-card">
                    <div class="section-title"><i class="fa-solid fa-suitcase-rolling text-primary"></i> Thông tin Tour</div>
                    
                    <?php if (!empty($data['AnhChinh'])): ?>
                        <img class="tour-img" src="<?= h(asset_url($data['AnhChinh'])) ?>" alt="Ảnh tour" onerror="this.style.display='none'">
                    <?php endif; ?>

                    <h5 class="fw-bold mb-3"><?= h($data['Tour_TenTour'] ?? '—') ?></h5>
                    
                    <div class="info-row">
                        <span class="info-label">Địa điểm:</span>
                        <span class="info-value"><?= h($data['Tour_DiaDiem'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Thời lượng:</span>
                        <span class="info-value"><?= h($data['Tour_ThoiLuong'] ?? '—') ?></span>
                    </div>
                     <div class="info-row">
                        <span class="info-label">Ngày khởi hành:</span>
                        <span class="info-value"><?= h($data['Tour_NgayKhoiHanh'] ?? '—') ?></span>
                    </div>
                </div>

                <div class="detail-card">
                    <div class="section-title"><i class="fa-solid fa-user-circle text-primary"></i> Thông tin Khách hàng</div>
                    <div class="info-row">
                        <span class="info-label">Họ tên:</span>
                        <span class="info-value"><?= h($data['KH_HoTen'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= h($data['KH_Email'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Số điện thoại:</span>
                        <span class="info-value"><?= h($data['KH_SoDienThoai'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Địa chỉ:</span>
                        <span class="info-value"><?= h($data['KH_DiaChi'] ?? '—') ?></span>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="detail-card">
                    <div class="section-title"><i class="fa-solid fa-receipt text-primary"></i> Chi tiết thanh toán</div>
                    
                    <div class="info-row">
                        <span class="info-label">Người lớn (x<?= (int)$data['SoLuongNguoiLon'] ?>):</span>
                        <span class="info-value"><?= number_format($data['GiaNguoiLonApDung'] * $data['SoLuongNguoiLon']) ?>đ</span>
                    </div>
                    <?php if($data['SoLuongTreEm'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Trẻ em (x<?= (int)$data['SoLuongTreEm'] ?>):</span>
                        <span class="info-value"><?= number_format($data['GiaTreEmApDung'] * $data['SoLuongTreEm']) ?>đ</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-row mt-3 pt-2 border-top">
                        <span class="info-label fw-bold text-dark">TỔNG TIỀN:</span>
                        <span class="info-value fs-5 text-primary"><?= number_format((float)$data['TongTienPhaiTra'], 0, ',', '.') ?> VNĐ</span>
                    </div>
                </div>
                
                </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>