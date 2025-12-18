<?php
// app/lib/Helpers.php

if (!function_exists('asset_url')) {
    function asset_url($path) {
        // 1. Nếu không có path, trả về ảnh rỗng
        if (empty($path)) {
            return ''; 
        }

        // 2. Nếu là link online (http...), trả về nguyên vẹn
        if (strpos($path, 'http') === 0) {
            return $path;
        }

        // 3. Xử lý đường dẫn tương đối
        // File login.php đang ở: /public/nhanvien/
        // Ảnh đang ở:           /public/assets/
        // => Cần đi ra 1 cấp (..) rồi vào assets
        
        $path = ltrim($path, '/'); // Xóa dấu / ở đầu nếu có
        
        return "../assets/" . $path;
    }
}