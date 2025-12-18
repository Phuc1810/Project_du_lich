<?php
// public/nhanvien/login.php

// 1. Nạp cấu hình
require_once __DIR__ . "/../../app/config/config.php";

// 2. Khởi động Session
if (session_status() === PHP_SESSION_NONE) session_start();

// Helper
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// 3. Nếu đã đăng nhập nhân viên -> Vào Dashboard ngay
if (!empty($_SESSION['staff']['MaTK'])) {
    header("Location: index.php");
    exit;
}

// 4. Lấy Logo
$logo2 = '';
try {
    $rs = $conn->query("SELECT Logo_2 FROM congty LIMIT 1");
    if ($rs && ($row = $rs->fetch_assoc())) {
        $logo2 = (string)($row['Logo_2'] ?? '');
    }
} catch (Throwable $e) {}

$errors = [];

// 5. CSRF
if (empty($_SESSION['csrf_nv'])) {
    $_SESSION['csrf_nv'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_nv'];

$oldUser = '';

// 6. Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf_nv'], $token)) {
        $errors[] = "Phiên làm việc không hợp lệ. Vui lòng tải lại trang.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $oldUser = $username;

        if ($username === '' || $password === '') {
            $errors[] = "Vui lòng nhập đầy đủ tài khoản và mật khẩu.";
        } else {
            try {
                $sql = "SELECT MaTK, TenDangNhap, MatKhau, VaiTro, TrangThai
                        FROM taikhoan
                        WHERE TenDangNhap=? AND VaiTro='NV'
                        LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $tk = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$tk) {
                    $errors[] = "Tài khoản nhân viên không tồn tại.";
                } else if (mb_strtolower((string)$tk['TrangThai'], 'UTF-8') !== mb_strtolower('Hoạt động', 'UTF-8')) {
                    $errors[] = "Tài khoản đang bị khóa.";
                } else {
                    if (!password_verify($password, (string)$tk['MatKhau'])) {
                        $errors[] = "Mật khẩu không đúng.";
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['staff'] = [
                            'MaTK' => (int)$tk['MaTK'],
                            'TenDangNhap' => (string)$tk['TenDangNhap'],
                            'VaiTro' => (string)$tk['VaiTro'],
                        ];

                        try {
                            $stmt = $conn->prepare("SELECT MaNV, HoTen, Email, SDT, ChucVu FROM nhanvien WHERE MaTK=? LIMIT 1");
                            $matk = (int)$tk['MaTK'];
                            $stmt->bind_param("i", $matk);
                            $stmt->execute();
                            $nv = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                            if ($nv) {
                                $_SESSION['staff']['MaNV']   = (int)($nv['MaNV'] ?? 0);
                                $_SESSION['staff']['HoTen']  = (string)($nv['HoTen'] ?? '');
                                $_SESSION['staff']['ChucVu'] = (string)($nv['ChucVu'] ?? '');
                            }
                        } catch (Throwable $e) {}

                        header("Location: index.php");
                        exit;
                    }
                }
            } catch (Throwable $e) {
                $errors[] = "Lỗi hệ thống: " . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Đăng nhập quản trị</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --primary: #ff7a00;       /* Màu cam thương hiệu */
            --primary-dark: #e66000;
            --text-dark: #111827;
            --text-gray: #6b7280;
            --bg-body: #f3f4f6;
        }

        body {
            font-family: 'Be Vietnam Pro', sans-serif;
            background-color: var(--bg-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Card Container */
        .login-card {
            background: #fff;
            width: 100%;
            max-width: 900px;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            display: flex;
            min-height: 550px;
        }

        /* Left Side (Branding) */
        .login-left {
            width: 45%;
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            position: relative;
            text-align: center;
        }
        
        /* Trang trí nền trái */
        .login-left::before {
            content: '';
            position: absolute;
            top: -50px; left: -50px;
            width: 200px; height: 200px;
            background: rgba(255, 122, 0, 0.1);
            border-radius: 50%;
            z-index: 0;
        }
        .login-left::after {
            content: '';
            position: absolute;
            bottom: -30px; right: -30px;
            width: 150px; height: 150px;
            background: rgba(255, 122, 0, 0.15);
            border-radius: 50%;
            z-index: 0;
        }

        .brand-logo {
            width: 160px;
            height: auto;
            object-fit: contain;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }
        .brand-logo:hover { transform: scale(1.05); }

        .brand-title {
            font-weight: 800;
            font-size: 24px;
            color: var(--text-dark);
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .brand-desc {
            color: var(--text-gray);
            font-size: 14px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        /* Right Side (Form) */
        .login-right {
            width: 55%;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .form-subtitle {
            color: var(--text-gray);
            margin-bottom: 32px;
            font-size: 15px;
        }

        /* Custom Input */
        .form-label {
            font-weight: 600;
            font-size: 14px;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 122, 0, 0.1);
        }

        /* Password Input Group */
        .input-group-password {
            position: relative;
        }
        .input-group-password .form-control {
            padding-right: 45px; /* Chừa chỗ cho icon mắt */
        }
        .btn-toggle-pass {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            z-index: 10;
            padding: 0;
        }
        .btn-toggle-pass:hover { color: var(--primary); }

        /* Submit Button */
        .btn-submit {
            background: linear-gradient(90deg, var(--primary) 0%, #ff9f43 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
            padding: 16px;
            font-weight: 700;
            font-size: 16px;
            width: 100%;
            margin-top: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 122, 0, 0.25);
            color: #fff;
        }
        .btn-submit:active { transform: translateY(0); }

        /* Error Alert */
        .alert-custom {
            border-radius: 12px;
            font-size: 14px;
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #ef4444;
            padding: 12px 16px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .login-card { flex-direction: column; height: auto; }
            .login-left { width: 100%; padding: 30px 20px; min-height: 200px; }
            .login-right { width: 100%; padding: 30px 20px; }
            .login-left::before, .login-left::after { display: none; }
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="login-left">
            <?php if ($logo2 !== '' && function_exists('asset_url')): ?>
                <img class="brand-logo" src="<?= h(asset_url($logo2)) ?>" alt="Logo">
            <?php else: ?>
                <div class="brand-title" style="font-size: 32px;">VietJourney</div>
            <?php endif; ?>
            
            <div class="brand-title">Khu vực Nhân viên</div>
            <div class="brand-desc">
                Hệ thống quản lý tour du lịch, booking<br>và chăm sóc khách hàng chuyên nghiệp.
            </div>
        </div>

        <div class="login-right">
            <div>
                <h1 class="form-title">Xin chào! 👋</h1>
                <p class="form-subtitle">Vui lòng đăng nhập để bắt đầu phiên làm việc.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-custom mb-4">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <?php echo reset($errors); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                    <div class="mb-3">
                        <label class="form-label">Tên tài khoản</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0" style="border-radius: 12px 0 0 12px; border-color: #e5e7eb;">
                                <i class="fa-regular fa-user text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" 
                                   style="border-radius: 0 12px 12px 0;"
                                   name="username" 
                                   value="<?= h($oldUser) ?>" 
                                   placeholder="Ví dụ: nhanvien01" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Mật khẩu</label>
                        <div class="input-group-password">
                            <input type="password" class="form-control" 
                                   id="passwordInput"
                                   name="password" 
                                   placeholder="Nhập mật khẩu..." required>
                            
                            <button type="button" class="btn-toggle-pass" onclick="togglePassword()">
                                <i class="fa-regular fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        Đăng nhập 
                    </button>
                </form>
            </div>
            
            <div class="text-center mt-4 text-muted small">
                &copy; <?= date('Y') ?> VietJourney Staff Portal
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const eyeIcon = document.getElementById('eyeIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>