<?php
require_once __DIR__ . "/../app/config/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../app/includes/auth_guard.php";
require_login($_SERVER['REQUEST_URI']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clamp_int($v, $min=0, $max=1000000){
  $v = (int)$v;
  if ($v < $min) $v = $min;
  if ($v > $max) $v = $max;
  return $v;
}

$tour_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tour_id <= 0) { header("Location: trangchu.php"); exit; }

$ctkm_id = isset($_GET['ctkm']) ? (int)$_GET['ctkm'] : 0; 
$matk = (int)($_SESSION['user']['MaTK'] ?? 0);
if ($matk <= 0) {
  header("Location: auth.php?tab=login&redirect=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

/* =========================
   1. LOAD TOUR
========================= */
$sqlTour = "
  SELECT t.MaTour, t.TenTour, t.DiaDiem, t.NgayKhoiHanh, t.GiaGoc, t.GiaGiam, t.ThoiLuong, t.SoCho, t.TrangThai,
         h.DuongDan AS AnhChinh
  FROM tour t
  LEFT JOIN hinhanhtour h ON h.MaTour=t.MaTour AND h.LaAnhChinh=1
  WHERE t.MaTour=? AND t.TrangThai='Hoạt động'
  LIMIT 1
";
$stmt = $conn->prepare($sqlTour);
$stmt->bind_param("i", $tour_id);
$stmt->execute();
$tour = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tour) { header("Location: trangchu.php"); exit; }

/* =========================
   2. LOAD THÔNG TIN KHÁCH TỪ TÀI KHOẢN (ĐỂ ĐIỀN SẴN FORM)
========================= */
$stmt = $conn->prepare("SELECT HoTen, Email, SoDienThoai, DiaChi, NgaySinh, GioiTinh FROM khachhang WHERE MaTK=? ORDER BY MaKH DESC LIMIT 1");
$stmt->bind_param("i", $matk);
$stmt->execute();
$kh_prefill = $stmt->get_result()->fetch_assoc(); // Dữ liệu gợi ý
$stmt->close();

/* =========================
   3. GIÁ & RULE
========================= */
$rateTreEm = 0.7;                      
$gia_goc = (float)$tour['GiaGoc'];     
$giaNguoiLon = $gia_goc;
$giaTreEm    = round($gia_goc * $rateTreEm);
$giaTreNho   = 0;                      

$errors = [];
$old = [
  'nguoi_lon' => 1,
  'tre_em'    => 0,
  'tre_nho'   => 0,
  'HoTen'     => $kh_prefill['HoTen'] ?? $_SESSION['user']['HoTen'] ?? '',
  'Email'     => $kh_prefill['Email'] ?? $_SESSION['user']['Email'] ?? '',
  'SoDienThoai' => $kh_prefill['SoDienThoai'] ?? $_SESSION['user']['SoDienThoai'] ?? '',
  'DiaChi'    => $kh_prefill['DiaChi'] ?? '',
  'NgaySinh'  => $kh_prefill['NgaySinh'] ?? '',
  'GioiTinh'  => $kh_prefill['GioiTinh'] ?? '',
];

/* =========================
   4. XỬ LÝ POST (ĐẶT TOUR)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old['nguoi_lon'] = clamp_int($_POST['nguoi_lon'] ?? 1, 1, 1000);
  $old['tre_em']    = clamp_int($_POST['tre_em'] ?? 0, 0, 1000);
  $old['tre_nho']   = clamp_int($_POST['tre_nho'] ?? 0, 0, 2000);

  $old['HoTen']       = trim($_POST['HoTen'] ?? '');
  $old['Email']       = trim($_POST['Email'] ?? '');
  $old['SoDienThoai'] = trim($_POST['SoDienThoai'] ?? '');
  $old['DiaChi']      = trim($_POST['DiaChi'] ?? '');
  $old['NgaySinh']    = trim($_POST['NgaySinh'] ?? '');
  $old['GioiTinh']    = trim($_POST['GioiTinh'] ?? '');

  // --- VALIDATE ---
  if ($old['HoTen'] === '') $errors[] = "Vui lòng nhập Họ tên.";
  if ($old['Email'] === '') $errors[] = "Vui lòng nhập Email.";
  else if (!filter_var($old['Email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email không hợp lệ.";

  if ($old['SoDienThoai'] === '') $errors[] = "Vui lòng nhập Số điện thoại.";
  else if (!preg_match('/^0\d{9}$/', $old['SoDienThoai'])) $errors[] = "Số điện thoại phải đủ 10 số và bắt đầu bằng 0.";

  if ($old['DiaChi'] === '') $errors[] = "Vui lòng nhập Địa chỉ.";
  if ($old['GioiTinh'] === '' || !in_array($old['GioiTinh'], ['Nam','Nữ','Khác'])) $errors[] = "Giới tính không hợp lệ.";

  if ($old['NgaySinh'] === '') {
    $errors[] = "Vui lòng chọn Ngày sinh.";
  } else {
    $dob = DateTime::createFromFormat('Y-m-d', $old['NgaySinh']);
    $today = new DateTime('today');
    if (!$dob || $dob >= $today) $errors[] = "Ngày sinh không hợp lệ (phải nhỏ hơn hôm nay).";
  }

  if ($old['nguoi_lon'] < 1) $errors[] = "Phải có ít nhất 1 người lớn.";
  $maxTreNho = $old['nguoi_lon'] * 2;
  if ($old['tre_nho'] > $maxTreNho) $errors[] = "Trẻ nhỏ tối đa là {$maxTreNho} (mỗi 1 người lớn tối đa 2 trẻ nhỏ).";

  // --- TÍNH TIỀN GỐC ---
  $tongTienGoc = ($old['nguoi_lon'] * $giaNguoiLon) + ($old['tre_em'] * $giaTreEm) + ($old['tre_nho'] * $giaTreNho);

  if (empty($errors)) {
    $conn->begin_transaction();
    try {
      $finalMaKH = 0;

      // >>> LOGIC MỚI: KIỂM TRA SĐT ĐỂ TRÁNH TRÙNG <<<
      
      // 1. Tìm xem SĐT này đã có trong bảng khachhang chưa?
      $stmtCheck = $conn->prepare("SELECT MaKH FROM khachhang WHERE SoDienThoai = ? LIMIT 1");
      $stmtCheck->bind_param("s", $old['SoDienThoai']);
      $stmtCheck->execute();
      $resCheck = $stmtCheck->get_result();
      $existingCust = $resCheck->fetch_assoc();
      $stmtCheck->close();

      if ($existingCust) {
          // A. NẾU CÓ RỒI -> Dùng lại MaKH cũ & Update thông tin mới nhất
          $finalMaKH = $existingCust['MaKH'];
          
          $sqlUp = "UPDATE khachhang 
                    SET HoTen=?, Email=?, DiaChi=?, NgaySinh=?, GioiTinh=?, MaTK=? 
                    WHERE MaKH=?";
          $stmtUp = $conn->prepare($sqlUp);
          $stmtUp->bind_param("sssssii", 
              $old['HoTen'], $old['Email'], $old['DiaChi'], $old['NgaySinh'], $old['GioiTinh'], $matk, $finalMaKH
          );
          $stmtUp->execute();
          $stmtUp->close();

      } else {
          // B. NẾU CHƯA CÓ -> Tạo mới
          $sqlIn = "INSERT INTO khachhang (HoTen, Email, SoDienThoai, DiaChi, NgaySinh, GioiTinh, MaTK) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
          $stmtIn = $conn->prepare($sqlIn);
          $stmtIn->bind_param("ssssssi", 
              $old['HoTen'], $old['Email'], $old['SoDienThoai'], $old['DiaChi'], $old['NgaySinh'], $old['GioiTinh'], $matk
          );
          if (!$stmtIn->execute()) {
              throw new Exception("Lỗi tạo khách hàng: " . $stmtIn->error);
          }
          $finalMaKH = $conn->insert_id;
          $stmtIn->close();
      }

      // 2. TẠO ĐƠN HÀNG (Dùng $finalMaKH)
      $trangthai = "Chờ thanh toán";
      $tongTienPhaiTra = (float)$tongTienGoc; 
      $maCtkmToSave = ($ctkm_id > 0) ? (int)$ctkm_id : null;

      $sqlInsDon = "
        INSERT INTO dondattour
          (NgayDat, SoLuongNguoiLon, SoLuongTreEm, SoLuongTreNho,
           GiaNguoiLonApDung, GiaTreEmApDung,
           TongTienGoc, TongTienPhaiTra, TrangThai, MaKH, MaTour, MaCTKM)
        VALUES
          (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ";
      
      $stmtDon = $conn->prepare($sqlInsDon);
      $stmtDon->bind_param(
        "iiiddddsiii",
        $old['nguoi_lon'], $old['tre_em'], $old['tre_nho'],
        $giaNguoiLon, $giaTreEm,
        $tongTienGoc, $tongTienPhaiTra,
        $trangthai, $finalMaKH, $tour_id, $maCtkmToSave
      );

      if (!$stmtDon->execute()) {
          throw new Exception("Lỗi tạo đơn hàng: " . $stmtDon->error);
      }
      $madon = $conn->insert_id;
      $stmtDon->close();

      $conn->commit();

      // Cập nhật session tên mới
      $_SESSION['user']['HoTen'] = $old['HoTen'];

      header("Location: thanhtoan.php?madon=" . (int)$madon);
      exit;

    } catch (Throwable $e) {
      $conn->rollback();
      $errors[] = "Hệ thống đang bận, vui lòng thử lại sau. (" . $e->getMessage() . ")";
    }
  }
}

/* =========================
   UI DEFAULT
========================= */
$tongTienUocTinh = ($old['nguoi_lon'] * $giaNguoiLon)
                 + ($old['tre_em']    * $giaTreEm)
                 + ($old['tre_nho']   * $giaTreNho);
$maxTreNhoUI = $old['nguoi_lon'] * 2;
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đặt tour - <?= h($tour['TenTour']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="./assets/css/chung.css">

  <style>
    body{ background:#f6f8fb; }
    .page-wrap{ padding-top:120px; padding-bottom:40px; }
    .card{ border:0; border-radius:16px; box-shadow: 0 10px 30px rgba(16,24,40,.08); }
    .tour-hero{ display:flex; gap:16px; align-items:center; }
    .tour-thumb{ width:120px; height:90px; border-radius:14px; object-fit:cover; background:#eee; }
    .badge-soft{ background:#eaf2ff; color:#0b5ed7; font-weight:600; }
    .qty{ display:flex; gap:10px; align-items:center; }
    .qty input{ width:90px; text-align:center; font-weight:700; border-radius:12px; }
    .qty button{ width:42px; height:42px; border-radius:12px; }
    .summary{ position: sticky; top: 120px; }
    .total{ font-size:26px; font-weight:800; }
    .hint{ color:#64748b; font-size:13px; }
    .divider{ height:1px; background: rgba(15,23,42,.08); margin:14px 0; }
    .form-control, .form-select{ border-radius:12px; }
  </style>
</head>
<body>

<?php require_once __DIR__ . "/../app/includes/header.php"; ?>

<div class="container page-wrap">
  <div class="row g-4">
    <div class="col-lg-8">

      <div class="card p-4 mb-4">
        <div class="tour-hero">
          <img class="tour-thumb" src="assets/<?= h($tour['AnhChinh'] ?? '') ?>" alt="">
          <div class="flex-grow-1">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <h4 class="mb-0 fw-bold"><?= h($tour['TenTour']) ?></h4>
              <span class="badge badge-soft"><?= h($tour['DiaDiem']) ?></span>
              <span class="badge text-bg-light"><?= h($tour['ThoiLuong']) ?></span>
            </div>
            <div class="hint mt-1">
              <i class="fa-regular fa-calendar-days me-1"></i>
              Khởi hành:
              <?= !empty($tour['NgayKhoiHanh']) ? date('d/m/Y', strtotime($tour['NgayKhoiHanh'])) : 'Đang cập nhật' ?>
              &nbsp; • &nbsp;
              Giá gốc: <strong><?= number_format((float)$tour['GiaGoc'], 0, ',', '.') ?> VNĐ</strong>
            </div>
            <div class="hint mt-1">
              Trẻ em: <?= (int)round($rateTreEm*100) ?>% &nbsp;|&nbsp;
              Trẻ nhỏ: <strong>Miễn phí</strong> (tối đa = Người lớn × 2)
            </div>
          </div>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" class="card p-4">
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-users me-2"></i>Số lượng tham gia</h5>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Người lớn</label>
            <div class="qty">
              <button class="btn btn-outline-secondary" type="button" onclick="dec('nguoi_lon',1)">-</button>
              <input class="form-control" id="nguoi_lon" name="nguoi_lon" type="number" min="1" value="<?= (int)$old['nguoi_lon'] ?>">
              <button class="btn btn-outline-secondary" type="button" onclick="inc('nguoi_lon',1)">+</button>
            </div>
            <div class="hint mt-1">Bắt buộc >= 1</div>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Trẻ em</label>
            <div class="qty">
              <button class="btn btn-outline-secondary" type="button" onclick="dec('tre_em',0)">-</button>
              <input class="form-control" id="tre_em" name="tre_em" type="number" min="0" value="<?= (int)$old['tre_em'] ?>">
              <button class="btn btn-outline-secondary" type="button" onclick="inc('tre_em',0)">+</button>
            </div>
            <div class="hint mt-1">Tính <?= (int)round($rateTreEm*100) ?>% giá gốc</div>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Trẻ nhỏ</label>
            <div class="qty">
              <button class="btn btn-outline-secondary" type="button" onclick="dec('tre_nho',0)">-</button>
              <input class="form-control" id="tre_nho" name="tre_nho" type="number" min="0" value="<?= (int)$old['tre_nho'] ?>">
              <button class="btn btn-outline-secondary" type="button" onclick="inc('tre_nho',0)">+</button>
            </div>
            <div class="hint mt-1">Miễn phí • Tối đa: <span id="maxTreNhoText"><?= (int)$maxTreNhoUI ?></span></div>
          </div>
        </div>

        <div class="divider"></div>

        <h5 class="fw-bold mb-3"><i class="fa-regular fa-id-card me-2"></i>Thông tin khách hàng</h5>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Họ tên</label>
            <input class="form-control" name="HoTen" value="<?= h($old['HoTen']) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="Email" value="<?= h($old['Email']) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Số điện thoại (10 số)</label>
            <input class="form-control"
                   name="SoDienThoai"
                   value="<?= h($old['SoDienThoai']) ?>"
                   required
                   inputmode="numeric"
                   maxlength="10"
                   pattern="0[0-9]{9}"
                   title="Số điện thoại phải đủ 10 số và bắt đầu bằng 0 (vd: 0xxxxxxxxx)">
          </div>

          <div class="col-md-6">
            <label class="form-label">Địa chỉ</label>
            <input class="form-control" name="DiaChi" value="<?= h($old['DiaChi']) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Ngày sinh</label>
            <input class="form-control" type="date" name="NgaySinh" id="NgaySinh" value="<?= h($old['NgaySinh']) ?>" required>
            <div class="hint mt-1">Không được chọn hôm nay hoặc tương lai</div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Giới tính</label>
            <select class="form-select" name="GioiTinh" required>
              <option value="" disabled <?= ($old['GioiTinh']===''?'selected':'') ?>>-- Chọn --</option>
              <option value="Nam" <?= ($old['GioiTinh']==='Nam'?'selected':'') ?>>Nam</option>
              <option value="Nữ" <?= ($old['GioiTinh']==='Nữ'?'selected':'') ?>>Nữ</option>
              <option value="Khác" <?= ($old['GioiTinh']==='Khác'?'selected':'') ?>>Khác</option>
            </select>
          </div>
        </div>

        <div class="divider"></div>

        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <div class="hint">
            “Tổng tiền phải trả” ở đây là <strong>ước tính theo giá gốc</strong> (chưa áp dụng KM).
            <br>Qua bước sau hệ thống sẽ so sánh KM và tính lại số tiền thật.
          </div>
          <button class="btn btn-primary btn-lg px-4" type="submit">
            <i class="fa-solid fa-check me-2"></i> Đặt tour
          </button>
        </div>
      </form>
    </div>

    <div class="col-lg-4">
      <div class="card p-4 summary">
        <h5 class="fw-bold mb-2">Tóm tắt chi phí</h5>
        <div class="hint mb-3">Tổng tiền phải trả (ước tính – chưa áp dụng KM)</div>

        <div class="d-flex justify-content-between">
          <span>Người lớn</span>
          <strong id="lineAdult"></strong>
        </div>
        <div class="d-flex justify-content-between">
          <span>Trẻ em</span>
          <strong id="lineChild"></strong>
        </div>
        <div class="d-flex justify-content-between">
          <span>Trẻ nhỏ</span>
          <strong id="lineInfant"></strong>
        </div>

        <div class="divider"></div>

        <div class="d-flex justify-content-between align-items-end">
          <span class="fw-semibold">Tổng</span>
          <div class="text-end">
            <div class="total" id="totalText"></div>
            <div class="hint">VNĐ</div>
          </div>
        </div>

        <div class="mt-3 alert alert-warning mb-0">
          <div class="fw-semibold">Lưu ý</div>
          <div class="small">
            Đây là tổng ước tính theo giá gốc. Qua bước thanh toán sẽ áp dụng KM tốt nhất (nếu có).
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
  const giaNguoiLon = <?= (int)$giaNguoiLon ?>;
  const giaTreEm    = <?= (int)$giaTreEm ?>;
  const giaTreNho   = <?= (int)$giaTreNho ?>;

  function fmt(n){ n=Math.round(n); return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g,"."); }
  function getVal(id){ const el=document.getElementById(id); return Math.max(0, parseInt(el.value||'0',10)); }

  function inc(id,min){ const v=getVal(id)+1; document.getElementById(id).value=Math.max(min,v); calc(); }
  function dec(id,min){ const v=getVal(id)-1; document.getElementById(id).value=Math.max(min,v); calc(); }

  function calc(){
    const nl = Math.max(1, getVal('nguoi_lon'));
    const te = getVal('tre_em');
    let tn   = getVal('tre_nho');

    const maxTN = nl * 2;
    document.getElementById('maxTreNhoText').innerText = maxTN;
    if (tn > maxTN) { tn = maxTN; document.getElementById('tre_nho').value = tn; }

    const adultMoney  = nl * giaNguoiLon;
    const childMoney  = te * giaTreEm;
    const infantMoney = tn * giaTreNho;
    const total = adultMoney + childMoney + infantMoney;

    document.getElementById('lineAdult').innerText  = fmt(adultMoney);
    document.getElementById('lineChild').innerText  = fmt(childMoney);
    document.getElementById('lineInfant').innerText = fmt(infantMoney);
    document.getElementById('totalText').innerText  = fmt(total);
  }

  ['nguoi_lon','tre_em','tre_nho'].forEach(id=>{
    document.getElementById(id).addEventListener('input', calc);
  });
  calc();

  // set max ngày sinh = hôm qua (không cho chọn hôm nay/tương lai)
  (function(){
    const el = document.getElementById('NgaySinh');
    if (!el) return;
    const d = new Date();
    d.setDate(d.getDate() - 1);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth()+1).padStart(2,'0');
    const dd = String(d.getDate()).padStart(2,'0');
    el.max = `${yyyy}-${mm}-${dd}`;
  })();
</script>

<?php require_once __DIR__ . "/../app/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
