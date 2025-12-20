<?php
// public/nhanvien/tour_sua.php

require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Guard staff
if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: tour.php");
    exit;
}

$errors = [];
$success = false;

// 1. Lấy thông tin tour hiện tại
$stmt = $conn->prepare("SELECT * FROM tour WHERE MaTour=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$tour = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tour) {
    header("Location: tour.php");
    exit;
}

// 1b. Lấy ảnh chính hiện tại (để biết LoaiAnh và hiển thị form)
$stmtImg = $conn->prepare("SELECT DuongDan, LoaiAnh FROM hinhanhtour WHERE MaTour=? AND LaAnhChinh=1 LIMIT 1");
$stmtImg->bind_param("i", $id);
$stmtImg->execute();
$currImg = $stmtImg->get_result()->fetch_assoc();
$stmtImg->close();

$currLoaiAnh = (string)($currImg['LoaiAnh'] ?? ''); 
// 2. Lấy lịch trình hiện tại
$stmtLT = $conn->prepare("SELECT * FROM lichtrinhtour WHERE MaTour=? ORDER BY NgayThu ASC");
$stmtLT->bind_param("i", $id);
$stmtLT->execute();
$currLT = $stmtLT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLT->close();

// Giá trị hiển thị form
$old = [
    'TenTour'      => (string)($tour['TenTour'] ?? ''),
    'DiaDiem'      => (string)($tour['DiaDiem'] ?? ''),
    'ThoiLuong'    => (string)($tour['ThoiLuong'] ?? ''),
    'GiaGoc'       => (string)($tour['GiaGoc'] ?? ''),
    'GiaGiam'      => (string)($tour['GiaGiam'] ?? ''),
    'PhanTramGiam' => (string)($tour['PhanTramGiam'] ?? ''),
    'SoCho'        => (string)($tour['SoCho'] ?? ''),
    'NgayKhoiHanh' => (string)($tour['NgayKhoiHanh'] ?? ''),
    'NgayKetThuc'  => (string)($tour['NgayKetThuc'] ?? ''),
    'LoaiTour'     => (string)($tour['LoaiTour'] ?? ''),
    'Mien'         => (string)($tour['Mien'] ?? ''),
    'TrangThai'    => (string)($tour['TrangThai'] ?? ''),
    'LoaiAnh' => $currLoaiAnh,
];

// Xử lý POST cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) $old[$k] = trim($_POST[$k] ?? '');

    // ===== 1. Validate BẮT BUỘC KHÔNG ĐƯỢC ĐỂ TRỐNG =====
    if ($old['TenTour'] === '') $errors[] = "Vui lòng nhập Tên tour.";
    if ($old['DiaDiem'] === '') $errors[] = "Vui lòng nhập Địa điểm.";
    if ($old['ThoiLuong'] === '') $errors[] = "Vui lòng nhập Thời lượng.";

    if ($old['GiaGoc'] === '') $errors[] = "Vui lòng nhập Giá gốc.";
    if ($old['GiaGiam'] === '') $errors[] = "Vui lòng nhập Giá giảm.";
    if ($old['PhanTramGiam'] === '') $errors[] = "Vui lòng nhập Phần trăm giảm.";

    if ($old['SoCho'] === '') $errors[] = "Vui lòng nhập Số chỗ.";
    if ($old['NgayKhoiHanh'] === '') $errors[] = "Vui lòng chọn Ngày khởi hành.";
    if ($old['NgayKetThuc'] === '') $errors[] = "Vui lòng chọn Ngày kết thúc.";

    if ($old['Mien'] === '') $errors[] = "Vui lòng chọn Miền.";
    if ($old['LoaiTour'] === '') $errors[] = "Vui lòng chọn Loại tour.";
    if ($old['TrangThai'] === '') $errors[] = "Vui lòng chọn Trạng thái.";

    // ===== 2. Validate KIỂU DỮ LIỆU & LOGIC (Chỉ chạy khi đã có dữ liệu) =====
    if (empty($errors)) {
        if (!ctype_digit($old['SoCho']) || (int)$old['SoCho'] <= 0) {
            $errors[] = "Số chỗ phải là số nguyên > 0.";
        }

        if (!is_numeric($old['GiaGoc']) || (float)$old['GiaGoc'] < 0) $errors[] = "Giá gốc không hợp lệ.";
        if (!is_numeric($old['GiaGiam']) || (float)$old['GiaGiam'] < 0) $errors[] = "Giá giảm không hợp lệ.";
        if (!is_numeric($old['PhanTramGiam']) || (float)$old['PhanTramGiam'] < 0 || (float)$old['PhanTramGiam'] > 100)
            $errors[] = "% giảm phải từ 0-100.";

        if ($old['NgayKhoiHanh'] < date('Y-m-d')) {
            $errors[] = "Ngày khởi hành phải từ hôm nay trở đi.";
        }
        
        // Ngày kết thúc phải >= Ngày khởi hành
        if ($old['NgayKetThuc'] < $old['NgayKhoiHanh']) {
            $errors[] = "Ngày kết thúc phải lớn hơn hoặc bằng ngày khởi hành.";
        }
    }

    // ===== 3. Validate Ảnh (Nếu có chọn file mới) =====
    $hasNewFile = isset($_FILES['AnhChinh']) && $_FILES['AnhChinh']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($hasNewFile) {
        if ($_FILES['AnhChinh']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Upload ảnh lỗi (code: " . (int)$_FILES['AnhChinh']['error'] . ").";
        } else {
            $ext = strtolower(pathinfo($_FILES['AnhChinh']['name'], PATHINFO_EXTENSION));
            $allow = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allow, true)) $errors[] = "Ảnh chỉ hỗ trợ: jpg, jpeg, png, webp.";
            if ((int)$_FILES['AnhChinh']['size'] > 5 * 1024 * 1024) $errors[] = "Ảnh quá lớn (tối đa 5MB).";
        }
    }

    // ===== 4. Validate Lịch Trình =====
    $lt_ngay = $_POST['lt_ngay'] ?? [];
    $lt_tieude = $_POST['lt_tieude'] ?? [];
    $lt_noidung = $_POST['lt_noidung'] ?? [];
    $lichtrinh = [];
    $maxRows = max(count($lt_ngay), count($lt_tieude), count($lt_noidung));

    for ($i = 0; $i < $maxRows; $i++) {
        $ng = trim((string)($lt_ngay[$i] ?? ''));
        $td = trim((string)($lt_tieude[$i] ?? ''));
        $nd = trim((string)($lt_noidung[$i] ?? ''));

        // Nếu dòng trống hoàn toàn thì bỏ qua
        if ($ng === '' && $td === '' && $nd === '') continue;

        // Nếu dòng có dữ liệu nhưng thiếu 1 trong 3 trường -> Báo lỗi
        if ($ng === '' || $td === '' || $nd === '') {
            $errors[] = "Lịch trình dòng " . ($i + 1) . ": Vui lòng nhập đủ Ngày thứ, Tiêu đề và Nội dung.";
            continue;
        }
        $lichtrinh[] = ['NgayThu' => (int)$ng, 'TieuDe' => $td, 'NoiDung' => $nd];
    }

    if (empty($lichtrinh)) {
        $errors[] = "Vui lòng nhập ít nhất 1 dòng lịch trình chi tiết.";
    }

    $validLoaiAnh = ['', 'banner', 'noibat'];
    if (!in_array($old['LoaiAnh'], $validLoaiAnh, true)) {
        $errors[] = "Loại ảnh không hợp lệ.";
    }

    // ===== CẬP NHẬT DỮ LIỆU =====
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // 1. Update Tour
            $ten  = $old['TenTour'];
            $dd   = $old['DiaDiem'];
            $tl   = $old['ThoiLuong'];
            $g0   = (float)$old['GiaGoc'];
            $gg   = (float)$old['GiaGiam'];
            $pt   = (float)$old['PhanTramGiam'];
            $sc   = (int)$old['SoCho'];
            $ngayKH = $old['NgayKhoiHanh'];
            $ngayKT = $old['NgayKetThuc'];
            $loai = $old['LoaiTour'];
            $mien = $old['Mien'];
            $tt   = $old['TrangThai'];
            $loaiAnh = $old['LoaiAnh'] ?? '';

            $sql = "UPDATE tour 
                    SET TenTour=?, DiaDiem=?, ThoiLuong=?, GiaGoc=?, GiaGiam=?, PhanTramGiam=?, 
                        SoCho=?, NgayKhoiHanh=?, NgayKetThuc=?, LoaiTour=?, Mien=?, TrangThai=? 
                    WHERE MaTour=? LIMIT 1";

            $stmt = $conn->prepare($sql);
            // Types: s s s d d d i s s s s s i
            $stmt->bind_param("sssddiisssssi", $ten, $dd, $tl, $g0, $gg, $pt, $sc, $ngayKH, $ngayKT, $loai, $mien, $tt, $id);
            $stmt->execute();
            $stmt->close();

            $stmtLoai = $conn->prepare("UPDATE hinhanhtour SET LoaiAnh=? WHERE MaTour=? AND LaAnhChinh=1");
            $stmtLoai->bind_param("si", $loaiAnh, $id);
            $stmtLoai->execute();
            $stmtLoai->close();

            // 2. Xử lý Ảnh
            if ($hasNewFile) {
                // Xóa ảnh cũ
                $sqlGetOld = "SELECT DuongDan FROM hinhanhtour WHERE MaTour = ? AND LaAnhChinh = 1 LIMIT 1";
                $stmtOld = $conn->prepare($sqlGetOld);
                $stmtOld->bind_param("i", $id);
                $stmtOld->execute();
                $oldImgRow = $stmtOld->get_result()->fetch_assoc();
                $stmtOld->close();

                if ($oldImgRow && !empty($oldImgRow['DuongDan'])) {
                    $fileToDelete = __DIR__ . "/../assets/" . $oldImgRow['DuongDan'];
                    if (file_exists($fileToDelete)) unlink($fileToDelete);
                }

                // Upload mới
                $uploadDir = __DIR__ . "/../assets/img/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext = strtolower(pathinfo($_FILES['AnhChinh']['name'], PATHINFO_EXTENSION));
                $safeName = 'tour_' . $id . '_' . time() . '.' . $ext;
                $destAbs = $uploadDir . $safeName;

                if (!move_uploaded_file($_FILES['AnhChinh']['tmp_name'], $destAbs)) throw new Exception("Lỗi lưu ảnh.");

                $dbPath = "img/" . $safeName;

                // Update DB ảnh
                if ($oldImgRow) {
                     $sqlUpImg = "UPDATE hinhanhtour SET DuongDan=?, LoaiAnh=? WHERE MaTour=? AND LaAnhChinh=1";
                    $stmtUp = $conn->prepare($sqlUpImg);
                    $stmtUp->bind_param("ssi", $dbPath, $loaiAnh, $id);
                    $stmtUp->execute();
                    $stmtUp->close();
                } else {
                    // Chú ý: LoaiAnh để rỗng ''
                    $sqlInImg = "INSERT INTO hinhanhtour (DuongDan, LaAnhChinh, LoaiAnh, MaTour) VALUES (?, 1, ?, ?)";
                    $stmtIn = $conn->prepare($sqlInImg);
                    $stmtIn->bind_param("ssi", $dbPath, $loaiAnh, $id);
                    $stmtIn->execute();
                    $stmtIn->close();
                }
            }

            // 3. Xử lý Lịch Trình (Xóa hết cũ -> Thêm mới)
            $stmtDelLT = $conn->prepare("DELETE FROM lichtrinhtour WHERE MaTour = ?");
            $stmtDelLT->bind_param("i", $id);
            $stmtDelLT->execute();
            $stmtDelLT->close();

            $sqlLT = "INSERT INTO lichtrinhtour (NgayThu, TieuDe, NoiDung, MaTour) VALUES (?,?,?,?)";
            $stmtLT = $conn->prepare($sqlLT);
            foreach ($lichtrinh as $lt) {
                $stmtLT->bind_param("issi", $lt['NgayThu'], $lt['TieuDe'], $lt['NoiDung'], $id);
                $stmtLT->execute();
            }
            $stmtLT->close();

            $conn->commit();
            header("Location: tour.php?msg=updated");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = "Lỗi cập nhật: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Sửa Tour #<?= $id ?> | VietJourney</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title">Sửa Tour #<?= $id ?></h1>
                    <div class="text-muted">Cập nhật thông tin và lịch trình</div>
                </div>
                <a class="btn btn-outline-secondary" href="tour.php">
                    <i class="fa-solid fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <div class="fw-bold mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i> Vui lòng kiểm tra lại:</div>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card p-4" style="border-radius:16px;">
                <form method="POST" enctype="multipart/form-data" class="row g-3">

                    <div class="col-12">
                        <div class="fw-bold text-primary">1. Thông tin cơ bản</div>
                        <hr>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Tên tour <span class="text-danger">*</span></label>
                        <input class="form-control" name="TenTour" value="<?= h($old['TenTour']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Loại tour <span class="text-danger">*</span></label>
                        <select class="form-select" name="LoaiTour">
                            <?php
                            $loaiOpts = ['Cá nhân', 'Doanh nghiệp'];
                            if ($old['LoaiTour'] !== '' && !in_array($old['LoaiTour'], $loaiOpts)) $loaiOpts[] = $old['LoaiTour'];
                            foreach ($loaiOpts as $l) {
                                $sel = ($old['LoaiTour'] == $l) ? 'selected' : '';
                                echo "<option value='" . h($l) . "' $sel>$l</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Địa điểm <span class="text-danger">*</span></label>
                        <input class="form-control" name="DiaDiem" value="<?= h($old['DiaDiem']) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Thời lượng <span class="text-danger">*</span></label>
                        <input class="form-control" name="ThoiLuong" value="<?= h($old['ThoiLuong']) ?>" placeholder="vd: 2N1Đ" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Số chỗ <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" min="0" name="SoCho" value="<?= h($old['SoCho']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Miền <span class="text-danger">*</span></label>
                        <select class="form-select" name="Mien">
                            <option value="Bắc" <?= $old['Mien'] === 'Bắc' ? 'selected' : ''; ?>>Bắc</option>
                            <option value="Trung" <?= $old['Mien'] === 'Trung' ? 'selected' : ''; ?>>Trung</option>
                            <option value="Nam" <?= $old['Mien'] === 'Nam' ? 'selected' : ''; ?>>Nam</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Trạng thái <span class="text-danger">*</span></label>
                        <select class="form-select" name="TrangThai">
                            <?php
                            $opts = ['Hoạt động', 'Hết chỗ', 'Ngừng hoạt động'];
                            foreach ($opts as $op) {
                                $sel = ($old['TrangThai'] === $op) ? 'selected' : '';
                                echo "<option value='" . h($op) . "' $sel>" . h($op) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Ảnh chính (Chọn để thay đổi)</label>
                        <input type="file" class="form-control" name="AnhChinh" accept=".jpg,.jpeg,.png,.webp">
                        <div class="form-text">Để trống nếu giữ nguyên ảnh cũ.</div>

                         <!--  THÊM dropdown LoaiAnh -->
                         <div class="mt-2">
                            <label class="form-label fw-semibold">Loại ảnh</label>
                            <select class="form-select" name="LoaiAnh">
                                <option value="" <?= $old['LoaiAnh'] === '' ? 'selected' : ''; ?>>(Rỗng / Không chọn)</option>
                                <option value="banner" <?= $old['LoaiAnh'] === 'banner' ? 'selected' : ''; ?>>banner</option>
                                <option value="noibat" <?= $old['LoaiAnh'] === 'noibat' ? 'selected' : ''; ?>>noibat</option>
                            </select>
                            <div class="form-text">Không chọn sẽ lưu rỗng.</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="fw-bold text-primary mt-3">2. Giá & Vận hành</div>
                        <hr>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Giá gốc (VNĐ) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" min="0" name="GiaGoc" id="GiaGoc" value="<?= h($old['GiaGoc']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Phần trăm giảm (%) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" min="0" max="100" name="PhanTramGiam" id="PhanTram" value="<?= h($old['PhanTramGiam']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Giá giảm (VNĐ) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" min="0" name="GiaGiam" id="GiaGiam" value="<?= h($old['GiaGiam']) ?>" required>
                        <div class="form-text text-primary small">Tự động tính khi nhập Giá gốc và %</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Ngày khởi hành <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" name="NgayKhoiHanh" id="NgayKhoiHanh" value="<?= h($old['NgayKhoiHanh']) ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Ngày kết thúc <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" name="NgayKetThuc" id="NgayKetThuc" value="<?= h($old['NgayKetThuc']) ?>" required>
                    </div>

                    <div class="col-12">
                        <div class="fw-bold text-primary mt-3">3. Lịch trình tour</div>
                        <hr>
                    </div>

                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <div class="text-muted">Chỉnh sửa chi tiết các ngày (Bắt buộc phải có)</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLT">
                            <i class="fa-solid fa-plus me-1"></i> Thêm dòng
                        </button>
                    </div>

                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="tblLT">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:110px;">Ngày thứ</th>
                                        <th style="width:260px;">Tiêu đề</th>
                                        <th>Nội dung</th>
                                        <th style="width:80px;" class="text-center">Xóa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($currLT)): ?>
                                        <?php foreach ($currLT as $lt): ?>
                                            <tr>
                                                <td><input class="form-control" name="lt_ngay[]" type="number" min="1" value="<?= h($lt['NgayThu']) ?>" required></td>
                                                <td><input class="form-control" name="lt_tieude[]" value="<?= h($lt['TieuDe']) ?>" required></td>
                                                <td><textarea class="form-control" name="lt_noidung[]" rows="2" required><?= h($lt['NoiDung']) ?></textarea></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-danger btnDelLT"><i class="fa-solid fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td><input class="form-control" name="lt_ngay[]" type="number" min="1" value="1" required></td>
                                            <td><input class="form-control" name="lt_tieude[]" placeholder="VD: Ngày 1..." required></td>
                                            <td><textarea class="form-control" name="lt_noidung[]" rows="2" required></textarea></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger btnDelLT"><i class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-light border px-4" href="tour.php">Hủy bỏ</a>
                        <button class="btn btn-primary px-4 fw-bold" type="submit">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // 1. TÍNH GIÁ
        const inpGiaGoc = document.getElementById('GiaGoc');
        const inpPhanTram = document.getElementById('PhanTram');
        const inpGiaGiam = document.getElementById('GiaGiam');

        function calculatePrice() {
            const g0 = parseFloat(inpGiaGoc.value) || 0;
            const pt = parseFloat(inpPhanTram.value) || 0;
            if (g0 > 0) {
                const finalPrice = g0 * (100 - pt) / 100;
                inpGiaGiam.value = Math.round(finalPrice);
            }
        }
        if (inpGiaGoc && inpPhanTram) {
            inpGiaGoc.addEventListener('input', calculatePrice);
            inpPhanTram.addEventListener('input', calculatePrice);
        }

        // 2. NGÀY
        const ngayKH = document.getElementById('NgayKhoiHanh');
        const ngayKT = document.getElementById('NgayKetThuc');

        function syncMinEnd() {
            if (ngayKH && ngayKT && ngayKH.value) ngayKT.min = ngayKH.value;
        }
        if (ngayKH && ngayKT) {
            ngayKH.addEventListener('change', syncMinEnd);
            syncMinEnd();
        }

        // 3. LỊCH TRÌNH
        const tblBody = document.querySelector("#tblLT tbody");
        const btnAdd = document.getElementById("btnAddLT");

        function bindDelete() {
            document.querySelectorAll(".btnDelLT").forEach(btn => {
                btn.onclick = () => {
                    if (confirm('Xóa dòng này?')) btn.closest("tr").remove();
                };
            });
        }
        bindDelete();

        btnAdd?.addEventListener("click", () => {
            let nextDay = 1;
            const rows = tblBody.querySelectorAll("tr");
            if (rows.length > 0) {
                const lastRow = rows[rows.length - 1];
                const lastDayInput = lastRow.querySelector('input[name="lt_ngay[]"]');
                if (lastDayInput) {
                    const val = parseInt(lastDayInput.value);
                    if (!isNaN(val)) nextDay = val + 1;
                }
            }

            const tr = document.createElement("tr");
            tr.innerHTML = `
      <td><input class="form-control" name="lt_ngay[]" type="number" min="1" value="${nextDay}" required></td>
      <td><input class="form-control" name="lt_tieude[]" placeholder="VD: Ngày ${nextDay}..." required></td>
      <td><textarea class="form-control" name="lt_noidung[]" rows="2" required></textarea></td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btnDelLT"><i class="fa-solid fa-trash"></i></button>
      </td>
    `;
            tblBody.appendChild(tr);
            bindDelete();
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
