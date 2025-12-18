<?php
// public/nhanvien/chitietyeucau.php

require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d) {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '—';
}
function fmtMoney($n) {
    if ($n === null || $n === '') return '—';
    return number_format((float)$n, 0, ',', '.') . ' VNĐ';
}

// Guard dùng SESSION STAFF
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$maYC = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($maYC <= 0) {
    header("Location: donyeucau.php");
    exit;
}

// Lấy MaNV hiện tại
$myMaNV = !empty($_SESSION['staff']['MaNV']) ? (int)$_SESSION['staff']['MaNV'] : 0;

// 4 Trạng thái
$statusOptions = ["Chờ xử lý", "Đã liên hệ", "Hủy tour", "Hoàn thành"];

$errors = [];
$success = false;

// ==== Xử lý Update ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {

    $newStatus = trim($_POST['TrangThai'] ?? '');
    $newMaNV   = isset($_POST['MaNV']) ? (int)$_POST['MaNV'] : 0;
    $giaTriRaw = trim($_POST['GiaTriHopDong'] ?? '');
    $ngayTT    = trim($_POST['NgayThanhToan'] ?? '');

    $giaTriNumber = null;
    if ($giaTriRaw !== '') {
        $giaTriClean = str_replace([',', ' '], '', $giaTriRaw);
        if (!is_numeric($giaTriClean) || (float)$giaTriClean < 0) {
            $errors[] = "Giá trị hợp đồng không hợp lệ.";
        } else {
            $giaTriNumber = (float)$giaTriClean;
        }
    }

    if ($newStatus === '' || !in_array($newStatus, $statusOptions, true)) {
        $errors[] = "Vui lòng chọn trạng thái hợp lệ.";
    }

    if ($newStatus === 'Hoàn thành') {
        if ($giaTriNumber === null) $errors[] = "Khi chọn 'Hoàn thành' bạn phải nhập Giá trị hợp đồng.";
        if ($ngayTT === '') $errors[] = "Khi chọn 'Hoàn thành' bạn phải chọn Ngày thanh toán.";
    }

    if ($ngayTT !== '') {
        $today = date('Y-m-d');
        if ($ngayTT > $today) {
            $errors[] = "Ngày thanh toán không được lớn hơn ngày hiện tại.";
        }
    }

    if (empty($errors)) {
        try {
            $sqlUp = "
                UPDATE yeucaudoanhnghiep 
                SET TrangThai=?, 
                    MaNV = NULLIF(?,0),
                    GiaTriHopDong = NULLIF(?,0),
                    NgayThanhToan = NULLIF(?, '')
                WHERE MaYC=? LIMIT 1
            ";
            
            $giaBind = ($giaTriNumber === null) ? 0 : $giaTriNumber;

            $stmt = $conn->prepare($sqlUp);
            $stmt->bind_param("sidsi", $newStatus, $newMaNV, $giaBind, $ngayTT, $maYC);
            $stmt->execute();
            $stmt->close();

            $success = true;
        } catch (Throwable $e) {
            $errors[] = "Lỗi cập nhật: " . $e->getMessage();
        }
    }
}

// ==== Load chi tiết ====
$sql = "
  SELECT 
    y.MaYC, y.TenCongTy, y.NguoiLienHe, y.SDT, y.SoNguoi, y.ThoiGianKhoiHanh,
    y.GiaTriHopDong, y.NgayThanhToan,
    y.TrangThai, y.MaKH, y.MaNV, y.MaTour,
    kh.HoTen AS KH_HoTen, kh.Email AS KH_Email, kh.SoDienThoai AS KH_SoDienThoai, kh.DiaChi AS KH_DiaChi,
    t.TenTour AS Tour_TenTour, t.DiaDiem AS Tour_DiaDiem, t.ThoiLuong AS Tour_ThoiLuong, 
    t.GiaGoc AS Tour_GiaGoc, t.GiaGiam AS Tour_GiaGiam, t.PhanTramGiam AS Tour_PhanTramGiam,
    ha.DuongDan AS AnhChinh,
    nv.HoTen AS NV_HoTen
  FROM yeucaudoanhnghiep y
  LEFT JOIN khachhang kh ON kh.MaKH = y.MaKH
  LEFT JOIN tour t ON t.MaTour = y.MaTour
  LEFT JOIN hinhanhtour ha ON ha.MaTour = t.MaTour AND ha.LaAnhChinh = 1
  LEFT JOIN nhanvien nv ON nv.MaNV = y.MaNV
  WHERE y.MaYC=? 
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $maYC);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: donyeucau.php?notfound=1");
    exit;
}

// ==== Load DS Nhân viên ====
$nvList = [];
$rs = $conn->query("SELECT MaNV, HoTen, ChucVu FROM nhanvien ORDER BY MaNV ASC");
if ($rs) while ($r = $rs->fetch_assoc()) $nvList[] = $r;

$khDate = !empty($data['ThoiGianKhoiHanh']) ? date('d/m/Y', strtotime($data['ThoiGianKhoiHanh'])) : '—';
$currentStatus = (string)($data['TrangThai'] ?? '');
$currentMaNV = (int)($data['MaNV'] ?? 0);

// ✅ CẤU HÌNH MÀU SẮC CHO 4 TRẠNG THÁI
$badgeClass = 'badge-soft-secondary';
switch ($currentStatus) {
    case 'Chờ xử lý':  
        $badgeClass = 'badge-soft-warning'; // Vàng
        break;
    case 'Đã liên hệ': 
        $badgeClass = 'badge-soft-info';    // Xanh dương
        break;
    case 'Hoàn thành': 
        $badgeClass = 'badge-soft-success'; // Xanh lá
        break;
    case 'Hủy tour':   
        $badgeClass = 'badge-soft-danger';  // Đỏ
        break;
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Chi tiết yêu cầu #<?= (int)$data['MaYC'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/nhanvien.css">
    
    <style>
        .detail-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.04); margin-bottom: 24px; }
        .section-title { font-size: 16px; font-weight: 700; margin-bottom: 16px; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #e5e7eb; gap: 12px; }
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
            <a href="donyeucau.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Quay lại danh sách</a>
            
            <header class="page-header mb-3">
                <div>
                    <h1 class="page-title">Chi tiết yêu cầu #<?= (int)$data['MaYC'] ?></h1>
                    <div class="current-date">Dự kiến khởi hành: <?= h($khDate) ?></div>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                    <i class="fa-solid fa-circle-check"></i> Cập nhật thành công!
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="detail-card">
                        <div class="section-title"><i class="fa-solid fa-suitcase-rolling text-primary"></i> Tour / Dịch vụ quan tâm</div>
                        
                        <?php if (!empty($data['AnhChinh'])): ?>
                            <img class="tour-img" src="<?= h(asset_url($data['AnhChinh'])) ?>" alt="Ảnh tour" onerror="this.style.display='none'">
                        <?php endif; ?>

                        <h5 class="fw-bold mb-3"><?= h($data['Tour_TenTour'] ?? 'Khách yêu cầu tour riêng / chưa chọn tour cụ thể') ?></h5>
                        
                        <div class="info-row">
                            <span class="info-label">Địa điểm:</span>
                            <span class="info-value"><?= h($data['Tour_DiaDiem'] ?? '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Thời lượng:</span>
                            <span class="info-value"><?= h($data['Tour_ThoiLuong'] ?? '—') ?></span>
                        </div>
                        <?php 
                        $giaGoc = (float)($data['Tour_GiaGoc'] ?? 0);
                        $giaGiam = (float)($data['Tour_GiaGiam'] ?? 0);
                        $pt = (int)($data['Tour_PhanTramGiam'] ?? 0);
                        
                        $hasSale = ($giaGoc > 0 && $giaGiam > 0 && $giaGiam < $giaGoc) || ($pt > 0);
                        if ($pt <= 0 && $hasSale && $giaGoc > 0) {
                            $pt = (int)round(100 - ($giaGiam / $giaGoc * 100));
                        }
                        $displayPrice = $hasSale ? $giaGiam : $giaGoc;
                        ?>
                        <div class="info-row">
                            <span class="info-label">Giá:</span>
                            <span class="info-value">
                                <span style="color:#e11d48; font-weight:800;">
                                    <?= $displayPrice > 0 ? number_format($displayPrice, 0, ',', '.') . " VNĐ" : "—" ?>
                                </span>
                                
                                <?php if ($hasSale): ?>
                                    <span class="text-muted text-decoration-line-through ms-2">
                                        <?= $giaGoc > 0 ? number_format($giaGoc, 0, ',', '.') . " VNĐ" : "" ?>
                                    </span>
                                    <?php if ($pt > 0): ?>
                                        <span class="badge bg-warning text-dark ms-2">-<?= (int)$pt ?>%</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div class="detail-card">
                        <div class="section-title"><i class="fa-solid fa-building text-primary"></i> Thông tin Doanh nghiệp</div>
                        
                        <div class="info-row">
                            <span class="info-label">Tên công ty:</span>
                            <span class="info-value"><?= h($data['TenCongTy'] ?? '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Người liên hệ:</span>
                            <span class="info-value"><?= h($data['NguoiLienHe'] ?? '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Số điện thoại:</span>
                            <span class="info-value"><?= h($data['SDT'] ?? '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Số lượng khách:</span>
                            <span class="info-value"><?= (int)($data['SoNguoi'] ?? 0) ?> người</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Ngày khởi hành dự kiến:</span>
                            <span class="info-value"><?= h($khDate) ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Giá trị hợp đồng:</span>
                            <span class="info-value"><?= fmtMoney($data['GiaTriHopDong'] ?? null) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Ngày thanh toán:</span>
                            <span class="info-value"><?= fmtDate($data['NgayThanhToan'] ?? '') ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="detail-card">
                        <div class="section-title"><i class="fa-solid fa-user-circle text-primary"></i> Tài khoản đặt</div>
                        <div class="info-row">
                            <span class="info-label">Họ tên:</span>
                            <span class="info-value"><?= h($data['KH_HoTen'] ?? 'Khách vãng lai') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?= h($data['KH_Email'] ?? '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">SĐT:</span>
                            <span class="info-value"><?= h($data['KH_SoDienThoai'] ?? '—') ?></span>
                        </div>
                    </div>

                    <div class="detail-card bg-light border-0">
                        <div class="section-title"><i class="fa-solid fa-gear text-primary"></i> Xử lý yêu cầu</div>
                        
                        <div class="mb-3">
                            <div class="small text-muted mb-1">Trạng thái hiện tại:</div>
                            <div class="<?= $badgeClass ?>"><?= h($currentStatus !== '' ? $currentStatus : '—') ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="small text-muted mb-1">NV phụ trách:</div>
                            <div class="fw-bold text-dark"><?= h($data['NV_HoTen'] ?? 'Chưa gán') ?></div>
                        </div>

                        <hr class="my-3">

                        <form method="POST">
                            <input type="hidden" name="action" value="update">

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Trạng thái xử lý</label>
                                <select class="form-select" name="TrangThai" required>
                                    <?php 
                                    if ($currentStatus !== '' && !in_array($currentStatus, $statusOptions, true)) {
                                        echo '<option value="'.h($currentStatus).'" selected>'.h($currentStatus).'</option>';
                                    }
                                    foreach ($statusOptions as $st):
                                        $sel = ($st === $currentStatus) ? 'selected' : '';
                                    ?>
                                        <option value="<?= h($st) ?>" <?= $sel ?>><?= h($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Giá trị hợp đồng (VNĐ)</label>
                                <input type="text" class="form-control" 
                                    name="GiaTriHopDong" 
                                    value="<?= h((string)($data['GiaTriHopDong'] ?? '')) ?>"
                                    placeholder="VD: 20000000">
                                <div class="form-text">Chỉ bắt buộc khi chọn trạng thái <b>Hoàn thành</b>.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Ngày thanh toán</label>
                                <input type="date" class="form-control" 
                                    name="NgayThanhToan"
                                    max="<?= date('Y-m-d') ?>"
                                    value="<?= h((string)($data['NgayThanhToan'] ?? '')) ?>">
                                <div class="form-text">Chỉ bắt buộc khi chọn trạng thái <b>Hoàn thành</b>.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Gán nhân viên</label>
                                <select class="form-select" name="MaNV" id="selectMaNV">
                                    <option value="0">-- Không gán --</option>
                                    <?php foreach ($nvList as $nv):
                                        $id = (int)$nv['MaNV'];
                                        $sel = ($id === $currentMaNV) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $id ?>" <?= $sel ?>><?= h($nv['HoTen']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <?php if ($myMaNV > 0): ?>
                                    <div class="form-text text-end mt-1">
                                        <a href="#" onclick="document.getElementById('selectMaNV').value='<?= $myMaNV ?>'; return false;" class="text-decoration-none small">
                                            <i class="fa-solid fa-hand-point-up"></i> Gán cho tôi
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Lưu cập nhật
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>