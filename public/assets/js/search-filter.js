const diaInput   = document.getElementById("dia_diem");
const suggestBox = document.getElementById("suggest-dia-diem");

if (diaInput && suggestBox) {

  function attachItemClickEvents() {
    suggestBox.querySelectorAll(".suggest-item").forEach(item => {
      item.addEventListener("click", () => {
        const value = item.getAttribute("data-value") || item.textContent.trim();
        diaInput.value = value;
        suggestBox.classList.add("d-none");
        checkFilters && checkFilters();   // cập nhật trạng thái nút tìm kiếm
      });
    });
  }

  //Gọi hàm ajax_diadiem.php
  function loadSuggestions(keyword = "") {
    fetch("ajax_diadiem.php?key=" + encodeURIComponent(keyword))
      .then(res => res.text())
      .then(html => {
        suggestBox.innerHTML = html;
        suggestBox.classList.remove("d-none");
        attachItemClickEvents();
      })
      .catch(err => console.error(err));
  }

  // ❶ Nhấp / focus vào ô => hiện list ngay (lấy tất cả hoặc top 30 theo ajax_diadiem.php)
  diaInput.addEventListener("focus", () => {
    const key = diaInput.value.trim();
    loadSuggestions(key);      // nếu đang có chữ, load theo chữ – nếu rỗng sẽ load full
  });

  diaInput.addEventListener("click", () => {
    const key = diaInput.value.trim();
    loadSuggestions(key);
  });

  // ❷ Gõ vào => lọc lại list theo key
  diaInput.addEventListener("keyup", function () {
    const key = this.value.trim();
    // vẫn load cả khi rỗng để show full list
    loadSuggestions(key);
  });

  // ❸ Click ra ngoài => ẩn box gợi ý
  document.addEventListener("click", function (e) {
    if (!suggestBox.contains(e.target) && e.target !== diaInput) {
      suggestBox.classList.add("d-none");
    }
  });
}

// ===========================
// NGĂN CHỌN NGÀY NHỎ HƠN HIỆN TẠI
// ===========================
const today = new Date().toISOString().split("T")[0];
const ngayInput = document.getElementById("ngay_khoi_hanh");
if (ngayInput) {
  ngayInput.setAttribute("min", today);

  ngayInput.addEventListener("change", function () {
    const selectedDate = this.value;

    if (selectedDate < today) {
      alert("Ngày khởi hành không được nhỏ hơn ngày hiện tại!");
      this.value = "";
      checkFilters();
    } else {
      checkFilters();
    }
  });
}

// ===========================
// KIỂM TRA CÁC BỘ LỌC
// ===========================
function checkFilters() {
  const btn = document.getElementById("btnTimKiem");
  if (!btn) return;
  const diaDiem   = document.getElementById("dia_diem").value.trim();
  const ngay      = document.getElementById("ngay_khoi_hanh").value;
  const thoiluong = document.getElementById("thoi_luong").value;
  const gia       = document.getElementById("gia").value;


  if (!diaDiem && !ngay && !thoiluong && !gia) {
    btn.disabled = true;
  } else {
    btn.disabled = false;
  }
}

// Gắn event cho các input liên quan
document.querySelectorAll("#formAdvanceSearch select, #formAdvanceSearch input")
        .forEach(item => {
          item.addEventListener("change", checkFilters);
          item.addEventListener("keyup", checkFilters);
        });

// ===========================
// SUBMIT FORM – kiểm tra lần cuối
// ===========================
const form = document.getElementById("formAdvanceSearch");
if (form) {
  form.addEventListener("submit", function(e) {
    const diaDiem   = document.getElementById("dia_diem").value.trim();
    const ngay      = document.getElementById("ngay_khoi_hanh").value;
    const thoiluong = document.getElementById("thoi_luong").value;
    const gia       = document.getElementById("gia").value;

    if (!diaDiem && !ngay && !thoiluong && !gia) {
      e.preventDefault();
      alert("Vui lòng chọn ít nhất một tiêu chí để tìm kiếm!");
    }
  });
}

