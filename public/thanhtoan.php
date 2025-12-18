<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$madon = isset($_GET['madon']) ? (int)$_GET['madon'] : 0;
if ($madon <= 0) {
    header("Location: trangchu.php");
    exit;
}

$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) {
    header("Location: auth.php?tab=login&redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Lấy đơn + đúng chủ đơn
$sql = "
  SELECT d.MaDon, d.NgayDat, d.SoLuongNguoiLon, d.SoLuongTreEm, d.SoLuongTreNho,
         d.GiaNguoiLonApDung, d.GiaTreEmApDung,
         d.TongTienGoc, d.TongTienPhaiTra, d.TrangThai,
         d.MaTour, d.MaCTKM,
         t.TenTour, t.DiaDiem, t.NgayKhoiHanh, t.GiaGoc, t.GiaGiam, t.PhanTramGiam,
         t.SoCho, t.SoChoDaDat,
         h.DuongDan AS AnhChinh
  FROM dondattour d
  JOIN khachhang kh ON kh.MaKH = d.MaKH
  JOIN tour t ON t.MaTour = d.MaTour
  LEFT JOIN hinhanhtour h ON h.MaTour=t.MaTour AND h.LaAnhChinh=1
  WHERE d.MaDon=? AND kh.MaTK=?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $madon, $matk);
$stmt->execute();
$don = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$don) {
    header("Location: trangchu.php");
    exit;
}

// ====== Tính KM tốt nhất (so sánh % trong Tour vs % CTKM đang hoạt động) ======
$pt_tour = 0;
$gia_goc_tour = (float)$don['GiaGoc'];
$gia_giam_tour = (float)$don['GiaGiam'];

if (!empty($don['PhanTramGiam']) && (float)$don['PhanTramGiam'] > 0) {
    $pt_tour = (float)$don['PhanTramGiam'];
} else if ($gia_goc_tour > 0 && $gia_giam_tour > 0 && $gia_giam_tour < $gia_goc_tour) {
    $pt_tour = 100 - ($gia_giam_tour / $gia_goc_tour * 100);
}

$pt_ctkm = 0;
$ten_km = '';
$ctkm_id_valid = null;

// CTKM tốt nhất cho tour (đang hoạt động + trong thời gian)
$sqlKM = "
  SELECT tk.MaCTKM, tk.PhanTramGiamKM, c.TenKM
  FROM tour_khuyenmai tk
  JOIN chuongtrinhkhuyenmai c ON c.MaCTKM = tk.MaCTKM
  WHERE tk.MaTour=?
    AND c.TrangThai='Hoạt động'
    AND c.NgayBatDau <= CURDATE()
    AND c.NgayKetThuc >= CURDATE()
  ORDER BY tk.PhanTramGiamKM DESC
  LIMIT 1
";
$stmt = $conn->prepare($sqlKM);
$stmt->bind_param("i", $don['MaTour']);
$stmt->execute();
$rowKM = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($rowKM) {
    $pt_ctkm = (float)$rowKM['PhanTramGiamKM'];
    $ten_km = (string)$rowKM['TenKM'];
    $ctkm_id_valid = (int)$rowKM['MaCTKM'];
}

// Chọn % lớn hơn
$pt_ap_dung = 0;
$loai_giam = '';
$ten_giam = '';

if ($pt_ctkm > $pt_tour) {
    $pt_ap_dung = $pt_ctkm;
    $loai_giam = 'CTKM';
    $ten_giam = $ten_km;
} else if ($pt_tour > 0) {
    $pt_ap_dung = $pt_tour;
    $loai_giam = 'TOUR';
    $ten_giam = 'Giảm theo tour';
}

// Tổng tiền gốc đã lưu (chưa KM)
$tong_goc = (float)$don['TongTienGoc'];
$tong_phai_tra = $tong_goc;

if ($pt_ap_dung > 0) {
    $tong_phai_tra = round($tong_goc * (100 - $pt_ap_dung) / 100);
}

// Nếu DB đang lưu TongTienPhaiTra khác, cập nhật lại cho đúng
if ((float)$don['TongTienPhaiTra'] != (float)$tong_phai_tra) {
    $newMaCTKM = ($loai_giam === 'CTKM') ? $ctkm_id_valid : null;

    $stmt = $conn->prepare("UPDATE DonDatTour SET TongTienPhaiTra=?, MaCTKM=? WHERE MaDon=? LIMIT 1");
    $maCtkmToSave = $newMaCTKM; // có thể NULL nếu cột cho phép NULL
    $stmt->bind_param("dii", $tong_phai_tra, $maCtkmToSave, $madon);
    $stmt->execute();
    $stmt->close();
}

// ===== VietQR: addInfo = "DH{MaDon}" để SePay nhận diện code =====
$amount = (int)$tong_phai_tra;
$addInfo = "DH" . $madon;

$qrUrl = "https://img.vietqr.io/image/"
    . urlencode(VIETQR_BANK_ID) . "-" . urlencode(VIETQR_ACCOUNT_NO) . "-" . urlencode(VIETQR_TEMPLATE)
    . ".png?amount=" . $amount
    . "&addInfo=" . urlencode($addInfo)
    . "&accountName=" . urlencode(VIETQR_ACCOUNT_NAME);

// Nếu đã thanh toán thì chuyển luôn
if (mb_strtolower(trim((string)$don['TrangThai']), 'UTF-8') === mb_strtolower('Đã thanh toán', 'UTF-8')) {
    header("Location: dattour_thanhcong.php?madon=" . (int)$madon);
    exit;
}
if (mb_strtolower(trim((string)$don['TrangThai']), 'UTF-8') === mb_strtolower('Hết chỗ', 'UTF-8')) {
    header("Location: dattour_thanhcong.php?madon=" . (int)$madon);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Thanh toán đơn #<?= (int)$madon ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/chung.css">

    <style>
        :root {
            --bg: #f3f6f9;
            /* Màu nền sáng nhẹ hơn */
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --primary: #4f46e5;
            --line: #e2e8f0;
            --shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.08);
            --r: 24px;
            /* Khai báo Font chữ chính */
            --font-main: 'Be Vietnam Pro', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-main);
            /* Áp dụng font */
            -webkit-font-smoothing: antialiased;
            /* Làm mượt chữ trên Mac/iOS */
        }

        /* ✅ CÁCH HEADER RÕ RÀNG */
        .page {
            padding-top: 165px;
            padding-bottom: 60px;
        }

        @media (max-width: 992px) {
            .page {
                padding-top: 140px;
            }
        }

        .shell {
            max-width: 1080px;
        }

        .cardx {
            background: var(--card);
            border: 1px solid #eef2f6;
            border-radius: var(--r);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        /* Hero section đẹp hơn */
        .hero {
            padding: 24px 28px;
            background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
            border: 1px solid #e0e7ff;
        }

        .thumb {
            width: 110px;
            height: 82px;
            border-radius: 16px;
            object-fit: cover;
            background: #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .title {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.3;
            letter-spacing: -0.02em;
            color: #1e293b;
        }

        .meta {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }

        .meta i {
            color: var(--primary);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 99px;
            background: #fffbeb;
            border: 1px solid #fcd34d;
            font-weight: 700;
            color: #b45309;
            font-size: 13px;
            white-space: nowrap;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #f59e0b;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }
        }

        /* QR section */
        .qrHead {
            padding: 24px 24px 0;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .qrHead .h1 {
            font-weight: 800;
            font-size: 18px;
            margin: 0;
            color: #0f172a;
        }

        .qrHead .sub {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .code {
            font-weight: 700;
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 8px;
            background: #e0e7ff;
            font-size: 14px;
            font-family: monospace;
            /* Font code cho dễ nhìn số */
        }

        /* Hiển thị tiền trên đầu QR */
        .badge-money {
            background: #ecfdf5;
            color: #059669;
            padding: 6px 12px;
            border-radius: 99px;
            font-weight: 800;
            font-size: 14px;
            border: 1px solid #d1fae5;
        }

        .qrBody {
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qrImg {
            width: 100%;
            max-width: 380px;
            height: auto;
            border-radius: 16px;
            background: #fff;
            padding: 8px;
            border: 1px solid #f1f5f9;
        }

        .qrFoot {
            padding: 0 24px 24px;
            text-align: center;
            color: var(--muted);
            font-size: 14px;
        }

        /* Summary section */
        .sumBody {
            padding: 24px;
        }

        .divider {
            height: 1px;
            background: var(--line);
            margin: 18px 0;
        }

        .rowline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .rowline:last-child {
            margin-bottom: 0;
        }

        .label {
            color: #64748b;
            font-weight: 600;
        }

        .val {
            font-weight: 700;
            color: #334155;
            text-align: right;
        }

        .money {
            font-weight: 800;
            font-size: 16px;
            color: #334155;
            font-feature-settings: "tnum";
            /* Số thẳng hàng */
        }

        .neg {
            color: #dc2626;
        }

        .totalBox {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            padding: 20px;
            border-radius: 16px;
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: white;
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.5);
            margin-top: 10px;
        }

        .totalLabel {
            font-weight: 600;
            font-size: 16px;
            opacity: 0.9;
        }

        .total {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -1px;
            line-height: 1;
        }

        .vnd {
            font-size: 13px;
            font-weight: 600;
            opacity: 0.8;
            margin-top: 4px;
        }

        .note {
            margin-top: 20px;
            border-radius: 12px;
            background: #fffbeb;
            border: 1px solid #fef3c7;
            padding: 16px;
            color: #92400e;
            font-size: 13px;
            line-height: 1.6;
        }

        .note b {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .note b::before {
            content: '\f071';
            /* Icon warning */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
        }

        @media (max-width: 992px) {
            .title {
                font-size: 20px;
            }

            .total {
                font-size: 28px;
            }

            .thumb {
                width: 90px;
                height: 70px;
            }
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . "/../app/includes/header.php"; ?>

    <div class="container page shell">

        <div class="cardx hero mb-4">
            <div class="d-flex gap-3 align-items-center">
                <?php if (!empty($don['AnhChinh'])): ?>
                    <img class="thumb" src="assets/<?= h($don['AnhChinh']) ?>" alt="">
                <?php else: ?>
                    <div class="thumb"></div>
                <?php endif; ?>

                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="title"><?= h($don['TenTour']) ?></div>
                    </div>
                    <div class="meta mt-2">
                        <i class="fa-solid fa-receipt me-1"></i>Đơn hàng: <span class="text-dark fw-bold">#<?= (int)$madon ?></span>
                        &nbsp; • &nbsp;
                        <i class="fa-regular fa-calendar-days me-1"></i>
                        <?= !empty($don['NgayKhoiHanh']) ? date('d/m/Y', strtotime($don['NgayKhoiHanh'])) : 'Đang cập nhật' ?>
                    </div>
                </div>

                <span class="pill d-none d-md-inline-flex">
                    <span class="dot"></span>
                    Đang chờ thanh toán
                </span>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="cardx h-100">
                    <div class="qrHead">
                        <div>
                            <p class="h1">Quét mã VietQR</p>
                            <p class="sub">Nội dung CK: <span class="code"><?= h($addInfo) ?></span></p>
                        </div>
                        <span class="badge-money"><?= number_format($tong_phai_tra, 0, ',', '.') ?>đ</span>
                    </div>

                    <div class="qrBody">
                        <img class="qrImg" src="<?= h($qrUrl) ?>" alt="VietQR">
                    </div>

                    <div class="qrFoot">
                        <div>Sử dụng App Ngân hàng hoặc Ví điện tử để quét.</div>
                        <div class="mt-2" id="liveStatus" class="fw-bold text-primary">
                            <i class="fa-solid fa-spinner fa-spin me-1"></i> Đang chờ nhận tiền...
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="cardx h-100">
                    <div class="sumBody">
                        <div class="rowline">
                            <div class="label">Khách hàng</div>
                            <div class="val text-truncate" style="max-width: 200px;"><?= isset($_SESSION['user']['HoTen']) ? h($_SESSION['user']['HoTen']) : 'Khách' ?></div>
                        </div>
                        <div class="rowline">
                            <?php if ($don['SoLuongNguoiLon'] > 0): ?>
                                <div class="rowline mb-2">
                                    <div class="label text-dark">
                                        <i class="fa-solid fa-user me-2" style="color:#64748b; width:16px;"></i>Người lớn
                                    </div>
                                    <div class="val">
                                        <?= (int)$don['SoLuongNguoiLon'] ?> <span style="font-weight:400; color:#64748b;">vé</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($don['SoLuongTreEm'] > 0): ?>
                                <div class="rowline mb-2">
                                    <div class="label text-dark">
                                        <i class="fa-solid fa-child me-2" style="color:#64748b; width:16px;"></i>Trẻ em
                                    </div>
                                    <div class="val">
                                        <?= (int)$don['SoLuongTreEm'] ?> <span style="font-weight:400; color:#64748b;">vé</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($don['SoLuongTreNho'] > 0): ?>
                                <div class="rowline mb-2">
                                    <div class="label text-dark">
                                        <i class="fa-solid fa-baby me-2" style="color:#64748b; width:16px;"></i>Trẻ nhỏ
                                    </div>
                                    <div class="val">
                                        <?= (int)$don['SoLuongTreNho'] ?> <span style="font-weight:400; color:#64748b;">vé</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="divider"></div>
                        </div>

                        <div class="divider"></div>

                        <div class="rowline">
                            <div class="label">Tổng tiền gốc</div>
                            <div class="money"><?= number_format($tong_goc, 0, ',', '.') ?> <small>đ</small></div>
                        </div>

                        <?php if ($pt_ap_dung > 0): ?>
                            <div class="rowline mt-3">
                                <div class="label text-success">
                                    <i class="fa-solid fa-tag me-1"></i>
                                    Ưu đãi (Giảm <?= (int)round($pt_ap_dung) ?>%)
                                </div>
                                <div class="money neg">-<?= number_format(round($tong_goc - $tong_phai_tra), 0, ',', '.') ?> <small>đ</small></div>
                            </div>
                            <?php if ($loai_giam === 'CTKM' && $ten_giam !== ''): ?>
                                <div class="text-end mt-1" style="font-size:12px;color:#64748b;">
                                    <?= h($ten_giam) ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="mt-4">
                            <div class="totalBox">
                                <div class="totalLabel">Thanh toán</div>
                                <div class="text-end">
                                    <div class="total"><?= number_format($tong_phai_tra, 0, ',', '.') ?></div>
                                    <div class="vnd">VND</div>
                                </div>
                            </div>
                        </div>

                        <div class="note">
                            <b>Lưu ý quan trọng</b>
                            Hệ thống sẽ tự động kích hoạt vé sau khi nhận được chuyển khoản (thường mất 10-30 giây). Vui lòng không tắt trình duyệt.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . "/../app/includes/footer.php"; ?>

    <script>
        const madon = <?= (int)$madon ?>;

        async function ping() {
            try {
                // SỬA DÒNG NÀY: Bỏ dấu / ở đầu để gọi đúng đường dẫn
                const res = await fetch("api/check_payment.php?madon=" + madon, {
                    cache: "no-store"
                });

                if (!res.ok) throw new Error("API Error");

                const data = await res.json();

                if (data?.status === 'paid') {
                    const statusEl = document.getElementById('liveStatus');
                    statusEl.innerHTML = '<i class="fa-solid fa-circle-check text-success"></i> Đã nhận tiền thành công!';
                    statusEl.className = "mt-2 fw-bold text-success fs-5";

                    setTimeout(() => {
                        window.location.href = "dattour_thanhcong.php?madon=" + madon;
                    }, 1500);
                    return;
                }
                if (data?.status === 'soldout') {
                    document.getElementById('liveStatus').textContent = "Đã hết chỗ!";
                    window.location.href = "dattour_thanhcong.php?madon=" + madon;
                    return;
                }
            } catch (e) {
                // Silent error
            }

            setTimeout(ping, 2000);
        }
        ping();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>