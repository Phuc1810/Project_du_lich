<?php
// public/nhanvien/khuyenmai_them.php
require_once __DIR__ . "/../../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['staff']['MaTK']) || (($_SESSION['staff']['VaiTro'] ?? '') !== 'NV')) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$errors = [];

// 1. Load danh sách Tour (Đã thêm lại LoaiTour vào SELECT)
$tourList = [];
$rs = $conn->query("SELECT MaTour, TenTour, DiaDiem, LoaiTour, TrangThai FROM tour ORDER BY MaTour DESC");
if ($rs) while ($r = $rs->fetch_assoc()) $tourList[] = $r;

// Default values
$today = date('Y-m-d');
$old = [
    'TenKM'        => '',
    'NoiDung'      => '',
    'PhanTramGiam' => 0,
    'NgayBatDau'   => $today,
    'NgayKetThuc'  => '',
    'TrangThai'    => 'Hoạt động',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) $old[$k] = trim($_POST[$k] ?? '');

    // Validate
    if ($old['TenKM'] === '') $errors[] = "Vui lòng nhập tên chương trình khuyến mãi.";

    $pt = (int)$old['PhanTramGiam'];
    if ($pt < 0 || $pt > 100) $errors[] = "% giảm phải từ 0-100.";

    if ($old['NgayBatDau'] === '') $errors[] = "Vui lòng chọn ngày bắt đầu.";
    if ($old['NgayKetThuc'] === '') $errors[] = "Vui lòng chọn ngày kết thúc.";

    // Validate ngày quá khứ
    if ($old['NgayBatDau'] < $today) {
        $errors[] = "Ngày bắt đầu không được chọn ngày trong quá khứ.";
    }

    if ($old['NgayBatDau'] && $old['NgayKetThuc']) {
        if (strtotime($old['NgayKetThuc']) < strtotime($old['NgayBatDau'])) {
            $errors[] = "Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.";
        }
    }

    $selectedTours = $_POST['tours'] ?? [];
    if (!is_array($selectedTours)) $selectedTours = [];

    // Upload Ảnh
    $imgPath = '';
    if (isset($_FILES['AnhDaiDien']) && $_FILES['AnhDaiDien']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['AnhDaiDien'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Lỗi upload ảnh (Mã lỗi: " . $f['error'] . ")";
        } else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                $errors[] = "Chỉ chấp nhận file ảnh (JPG, JPEG, PNG, WEBP).";
            } elseif ($f['size'] > 5 * 1024 * 1024) {
                $errors[] = "File ảnh quá lớn (Tối đa 5MB).";
            } else {
                $targetDir = __DIR__ . "/../assets/img/";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

                $fileName = "km_" . date('Ymd_His') . "_" . uniqid() . "." . $ext;
                $targetFile = $targetDir . $fileName;

                if (move_uploaded_file($f['tmp_name'], $targetFile)) {
                    $imgPath = "img/" . $fileName;
                } else {
                    $errors[] = "Không thể lưu file ảnh.";
                }
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO chuongtrinhkhuyenmai (TenKM, NoiDung, AnhDaiDien, PhanTramGiam, NgayBatDau, NgayKetThuc, TrangThai)
              VALUES (?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssisss",
                $old['TenKM'],
                $old['NoiDung'],
                $imgPath,
                $pt,
                $old['NgayBatDau'],
                $old['NgayKetThuc'],
                $old['TrangThai']
            );
            $stmt->execute();
            $maCTKM = (int)$conn->insert_id;
            $stmt->close();

            if (!empty($selectedTours)) {
                $sqlIns = "INSERT INTO tour_khuyenmai (MaTour, MaCTKM, PhanTramGiamKM) VALUES (?,?,?)";
                $stmt2 = $conn->prepare($sqlIns);

                foreach ($selectedTours as $maTourRaw) {
                    $maTour = (int)$maTourRaw;
                    if ($maTour <= 0) continue;

                    $ptRieng = $_POST['ptkm'][$maTour] ?? '';
                    $ptFinal = ($ptRieng !== '' && is_numeric($ptRieng)) ? (int)$ptRieng : $pt;
                    if ($ptFinal < 0) $ptFinal = 0;
                    if ($ptFinal > 100) $ptFinal = 100;

                    $stmt2->bind_param("iii", $maTour, $maCTKM, $ptFinal);
                    $stmt2->execute();
                }
                $stmt2->close();
            }

            $conn->commit();
            header("Location: khuyenmai.php?msg=added");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Thêm khuyến mãi | VietJourney</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/nhanvien.css">
    <style>
        .cardx {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            border: 1px solid rgba(0, 0, 0, 0.04);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        .tour-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            transition: all 0.2s;
        }

        .tour-item:hover {
            background-color: #f8f9fa;
            border-color: #d1d5db;
        }

        .tour-name {
            font-weight: 700;
            color: #111827;
            cursor: pointer;
        }

        .tour-meta {
            color: #64748b;
            font-size: 12px;
        }

        .pt-input {
            width: 100px;
            text-align: center;
            font-weight: 600;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #374151;
        }

        .search-tour-box {
            position: relative;
            margin-bottom: 15px;
        }

        .search-tour-box input {
            padding-left: 35px;
            border-radius: 8px;
        }

        .search-tour-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="brand-section"><a href="#" class="brand-logo"><i class="fa-solid fa-compass"></i> VietJourney</a></div>
            <nav class="nav-section">
                <div class="nav-label">Tổng quan</div>
                <a href="index.php" class="nav-link"><i class="fa-solid fa-grid-2"></i> Dashboard</a>
                <div class="nav-label mt-4">Quản lý nghiệp vụ</div>
                <a href="donhang.php" class="nav-link"><i class="fa-solid fa-receipt"></i> Đơn đặt tour</a>
                <a href="donyeucau.php" class="nav-link"><i class="fa-solid fa-building-user"></i> Yêu cầu doanh nghiệp</a>
                <a href="tour.php" class="nav-link"><i class="fa-solid fa-map-location-dot"></i> Quản lý Tour</a>
                <a href="tintuc.php" class="nav-link"><i class="fa-solid fa-newspaper"></i> Quản lý tin tức</a>
                <a href="khuyenmai.php" class="nav-link active"><i class="fa-solid fa-tags"></i> Quản lý khuyến mãi</a>
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
                    <h1 class="page-title">Thêm chương trình khuyến mãi</h1>
                    <div class="text-muted">Tạo CTKM mới và chọn các tour được áp dụng</div>
                </div>
                <a class="btn btn-outline-secondary" href="khuyenmai.php"><i class="fa-solid fa-arrow-left me-1"></i> Quay lại</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <div class="fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i> Vui lòng kiểm tra lại:</div>
                    <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="row g-4">
                <div class="col-lg-5">
                    <div class="cardx h-100">
                        <div class="fw-bold mb-3 text-uppercase small text-secondary"><i class="fa-solid fa-tags me-2"></i>Thông tin chương trình</div>

                        <div class="mb-3">
                            <label class="form-label">Tên khuyến mãi <span class="text-danger">*</span></label>
                            <input class="form-control" name="TenKM" value="<?= h($old['TenKM']) ?>" required placeholder="VD: Khuyến mãi Hè 2025...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nội dung chi tiết</label>
                            <textarea class="form-control" name="NoiDung" rows="4" placeholder="Mô tả chi tiết chương trình..."><?= h($old['NoiDung']) ?></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">% Giảm chung <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" min="0" max="100" class="form-control fw-bold text-primary" name="PhanTramGiam" value="<?= h($old['PhanTramGiam']) ?>" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="TrangThai">
                                    <option value="Hoạt động" selected>Hoạt động</option>
                                    <option value="Ngừng hoạt động">Ngừng hoạt động</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="NgayBatDau" id="NgayBatDau"
                                    value="<?= h($old['NgayBatDau']) ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ngày kết thúc <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="NgayKetThuc" id="NgayKetThuc"
                                    value="<?= h($old['NgayKetThuc']) ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Ảnh đại diện (Banner)</label>
                            <input type="file" class="form-control" name="AnhDaiDien" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text small">Chọn ảnh để lưu</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="cardx h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-bold text-uppercase small text-secondary"><i class="fa-solid fa-list-check me-2"></i>Gán tour áp dụng</div>
                            <div class="badge bg-light text-dark border">Tổng: <span id="tour-count"><?= count($tourList) ?></span> tour</div>
                        </div>

                        <?php if (empty($tourList)): ?>
                            <div class="alert alert-warning mb-0 text-center">Chưa có tour nào trong hệ thống.</div>
                        <?php else: ?>

                            <div class="search-tour-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="inputSearchTour" class="form-control form-control-sm"
                                    placeholder="Gõ mã tour, tên tour hoặc địa điểm để tìm nhanh...">
                            </div>

                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fa-solid fa-circle-info me-1"></i> Tick chọn tour để áp dụng. Nếu để trống ô <b>% riêng</b>, hệ thống sẽ dùng <b>% Giảm chung</b>.
                            </div>

                            <div style="max-height: 500px; overflow-y: auto; padding-right: 5px;">
                                <div class="d-grid gap-2" id="tourContainer">
                                    <?php foreach ($tourList as $t): ?>
                                        <?php
                                        $maTour = (int)$t['MaTour'];
                                        // CHỈ LƯU MÃ, TÊN, ĐỊA ĐIỂM VÀO DATA-SEARCH (Không có LoaiTour)
                                        $searchString = h(strtolower($maTour . ' ' . $t['TenTour'] . ' ' . $t['DiaDiem']));
                                        ?>
                                        <div class="tour-item" data-search="<?= $searchString ?>">
                                            <div class="d-flex gap-3 align-items-center flex-grow-1">
                                                <div class="form-check" style="transform: scale(1.2);">
                                                    <input class="form-check-input" type="checkbox" name="tours[]"
                                                        value="<?= $maTour ?>" id="t<?= $maTour ?>">
                                                </div>
                                                <div class="flex-grow-1" onclick="document.getElementById('t<?= $maTour ?>').click()">
                                                    <label class="tour-name d-block mb-0" for="t<?= $maTour ?>">
                                                        <?= h($t['TenTour'] ?? '') ?>
                                                    </label>
                                                    <div class="tour-meta">
                                                        <span class="badge bg-light text-dark border me-1">#<?= $maTour ?></span>

                                                        <span class="text-primary fw-semibold me-1" style="font-size:11px;"><?= h($t['LoaiTour'] ?? '') ?></span>

                                                        <span class="mx-1">•</span>
                                                        <i class="fa-solid fa-location-dot me-1"></i><?= h($t['DiaDiem'] ?? '—') ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="text-end ps-2 border-start">
                                                <div class="text-muted small mb-1" style="font-size: 11px;">% Riêng</div>
                                                <input class="form-control form-control-sm pt-input" type="number" min="0" max="100"
                                                    name="ptkm[<?= $maTour ?>]" placeholder="Mặc định">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div id="noResult" class="text-center text-muted py-3" style="display: none;">
                                    <i class="fa-regular fa-face-frown mb-1"></i><br>Không tìm thấy tour phù hợp
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 text-end mt-2 pb-5">
                    <a class="btn btn-light border px-4 py-2 me-2" href="khuyenmai.php">Hủy bỏ</a>
                    <button class="btn btn-primary px-4 py-2 fw-bold shadow-sm" type="submit">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Lưu chương trình
                    </button>
                </div>
            </form>

        </main>
    </div>

    <script>
        const start = document.getElementById('NgayBatDau');
        const end = document.getElementById('NgayKetThuc');
        if (start && end) {
            start.addEventListener('change', function() {
                if (this.value) end.min = this.value;
            });
        }

        const searchInput = document.getElementById('inputSearchTour');
        const tourItems = document.querySelectorAll('.tour-item');
        const noResult = document.getElementById('noResult');

        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                const term = e.target.value.toLowerCase().trim();
                let hasVisible = false;

                tourItems.forEach(item => {
                    const textData = item.getAttribute('data-search');
                    if (textData.includes(term)) {
                        item.style.display = '';
                        hasVisible = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (noResult) {
                    noResult.style.display = hasVisible ? 'none' : 'block';
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
