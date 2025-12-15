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
  FROM DonDatTour d
  JOIN KhachHang kh ON kh.MaKH = d.MaKH
  JOIN Tour t ON t.MaTour = d.MaTour
  LEFT JOIN HinhAnhTour h ON h.MaTour=t.MaTour AND h.LaAnhChinh=1
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
  FROM Tour_KhuyenMai tk
  JOIN ChuongTrinhKhuyenMai c ON c.MaCTKM = tk.MaCTKM
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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/chung.css">

    <style>
        :root {
            --bg: #f6f8fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --line: rgba(15, 23, 42, .08);
            --shadow: 0 14px 42px rgba(16, 24, 40, .10);
            --r: 22px;
        }

        body {
            background: var(--bg);
            color: var(--text);
        }

        /* ✅ CÁCH HEADER RÕ RÀNG */
        .page {
            padding-top: 165px;
            /* tăng khoảng cách so với header */
            padding-bottom: 40px;
        }

        @media (max-width: 992px) {
            .page {
                padding-top: 140px;
            }
        }

        .shell {
            max-width: 1120px;
        }

        .cardx {
            background: var(--card);
            border: 1px solid rgba(15, 23, 42, .06);
            border-radius: var(--r);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .hero {
            padding: 18px 20px;
            background:
                radial-gradient(900px 380px at 0% 0%, rgba(99, 102, 241, .18), transparent 60%),
                radial-gradient(900px 380px at 100% 0%, rgba(14, 165, 233, .12), transparent 55%),
                linear-gradient(180deg, rgba(255, 255, 255, .95), rgba(255, 255, 255, 1));
        }

        .thumb {
            width: 104px;
            height: 78px;
            border-radius: 16px;
            object-fit: cover;
            background: #e5e7eb;
            border: 1px solid rgba(15, 23, 42, .06);
        }

        .title {
            font-size: 26px;
            font-weight: 1000;
            line-height: 1.15;
        }

        .meta {
            font-size: 14px;
            color: #475569;
            font-weight: 650;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff7ed;
            border: 1px solid rgba(245, 158, 11, .25);
            font-weight: 900;
            color: #9a3412;
            font-size: 13px;
            white-space: nowrap;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #f59e0b;
            box-shadow: 0 0 0 6px rgba(245, 158, 11, .15);
            animation: pulse 1.2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.15);
                opacity: .85;
            }
        }

        /* QR card */
        .qrHead {
            padding: 16px 18px 0;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .qrHead .h1 {
            font-weight: 950;
            font-size: 16px;
            margin: 0;
        }

        .qrHead .sub {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .code {
            font-weight: 1000;
            color: #111827;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eef2ff;
            border: 1px solid rgba(55, 48, 163, .12);
        }

        .qrBody {
            padding: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qrImg {
            width: min(420px, 92%);
            height: auto;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 12px 30px rgba(16, 24, 40, .10);
            border: 1px solid rgba(15, 23, 42, .06);
        }

        .qrFoot {
            padding: 0 18px 18px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        /* Summary card */
        .sumBody {
            padding: 18px 20px;
        }

        .divider {
            height: 1px;
            background: var(--line);
            margin: 14px 0;
        }

        .rowline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 14px;
        }

        .label {
            color: #475569;
            font-weight: 750;
        }

        .val {
            font-weight: 1000;
        }

        .money {
            font-weight: 1000;
            font-size: 16px;
        }

        .neg {
            color: #b42318;
        }

        .totalBox {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 16px;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(99, 102, 241, .16), rgba(14, 165, 233, .10), rgba(255, 255, 255, 1));
            border: 1px solid rgba(99, 102, 241, .22);
        }

        .totalLabel {
            font-weight: 1000;
            font-size: 17px;
        }

        .total {
            font-size: 35px;
            font-weight: 1200;
            letter-spacing: .3px;
            line-height: 1;
            text-shadow: 0 6px 18px rgba(2, 6, 23, .12);
        }

        .vnd {
            font-size: 12px;
            color: #334155;
            font-weight: 900;
            margin-top: 6px;
        }

        .note {
            margin-top: 14px;
            border-radius: 16px;
            background: #fffbeb;
            border: 1px solid rgba(245, 158, 11, .28);
            padding: 14px 14px;
        }

        .note b {
            display: block;
            margin-bottom: 4px;
        }

        /* nicer spacing on mobile */
        @media (max-width: 992px) {
            .title {
                font-size: 22px;
            }

            .total {
                font-size: 34px;
            }

            .thumb {
                width: 92px;
                height: 70px;
            }
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . "/../app/includes/header.php"; ?>

    <div class="container page shell">

        <!-- HERO / HEADER TOUR -->
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
                        <span class="code">Đơn #<?= (int)$madon ?></span>
                    </div>
                    <div class="meta mt-1">
                        <i class="fa-solid fa-location-dot me-1"></i><?= h($don['DiaDiem']) ?>
                        &nbsp; • &nbsp;
                        <i class="fa-regular fa-calendar-days me-1"></i>
                        <?= !empty($don['NgayKhoiHanh']) ? date('d/m/Y', strtotime($don['NgayKhoiHanh'])) : 'Đang cập nhật' ?>
                        &nbsp; • &nbsp;
                        <span class="text-muted">Code thanh toán:</span> <span class="fw-bold"><?= h($addInfo) ?></span>
                    </div>
                </div>

                <span class="pill">
                    <span class="dot"></span>
                    Đang chờ giao dịch
                </span>
            </div>
        </div>

        <div class="row g-4">
            <!-- QR -->
            <div class="col-lg-6">
                <div class="cardx">
                    <div class="qrHead">
                        <div>
                            <p class="h1">Quét QR để thanh toán</p>
                            <p class="sub">Nội dung chuyển khoản tự điền: <span class="fw-bold"><?= h($addInfo) ?></span></p>
                        </div>
                        <span class="code"><?= number_format($tong_phai_tra, 0, ',', '.') ?> VNĐ</span>
                    </div>

                    <div class="qrBody">
                        <img class="qrImg" src="<?= h($qrUrl) ?>" alt="VietQR">
                    </div>

                    <div class="qrFoot">
                        Hệ thống sẽ tự nhận tiền và tự chuyển sang trang thành công.
                        <span id="liveStatus" class="fw-bold"></span>
                    </div>
                </div>
            </div>

            <!-- SUMMARY -->
            <div class="col-lg-6">
                <div class="cardx">
                    <div class="sumBody">
                        <div class="rowline">
                            <div class="label">Số lượng</div>
                            <div class="val">
                                Người lớn: <?= (int)$don['SoLuongNguoiLon'] ?> • Trẻ em: <?= (int)$don['SoLuongTreEm'] ?> • Trẻ nhỏ: <?= (int)$don['SoLuongTreNho'] ?>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="rowline">
                            <div class="label">Tổng tiền gốc (chưa KM)</div>
                            <div class="money"><?= number_format($tong_goc, 0, ',', '.') ?> VNĐ</div>
                        </div>

                        <?php if ($pt_ap_dung > 0): ?>
                            <div class="rowline mt-2">
                                <div class="label">Giảm <?= (int)round($pt_ap_dung) ?>% (<?= h($loai_giam) ?>)</div>
                                <div class="money neg">-<?= number_format(round($tong_goc - $tong_phai_tra), 0, ',', '.') ?> VNĐ</div>
                            </div>
                            <?php if ($loai_giam === 'CTKM' && $ten_giam !== ''): ?>
                                <div class="mt-2" style="font-size:13px;color:#64748b;">
                                    CTKM áp dụng: <span class="fw-bold"><?= h($ten_giam) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="divider"></div>

                        <div class="totalBox">
                            <div class="totalLabel">Tổng phải trả</div>
                            <div class="text-end">
                                <div class="total"><?= number_format($tong_phai_tra, 0, ',', '.') ?></div>
                                <div class="vnd">VNĐ</div>
                            </div>
                        </div>

                        <div class="note">
                            <b>Lưu ý</b>
                            Vui lòng chuyển khoản đúng số tiền. Hệ thống tự cập nhật trạng thái và chỗ trống sau khi nhận tiền.
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
                const res = await fetch("api/check_payment.php?madon=" + madon, {
                    cache: "no-store"
                });
                const data = await res.json();

                if (data?.status === 'paid') {
                    document.getElementById('liveStatus').textContent = " (đã nhận tiền ✓)";
                    window.location.href = "dattour_thanhcong.php?madon=" + madon;
                    return;
                }
                if (data?.status === 'soldout') {
                    document.getElementById('liveStatus').textContent = " (hết chỗ)";
                    window.location.href = "dattour_thanhcong.php?madon=" + madon;
                    return;
                }
            } catch (e) {}

            setTimeout(ping, 2000);
        }
        ping();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>