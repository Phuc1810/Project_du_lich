// 1) Toggle hiện/ẩn mật khẩu
document.querySelectorAll('[data-toggle="password"]').forEach(btn => {
  btn.addEventListener('click', () => {
    const inputId = btn.getAttribute('data-target');
    const input = document.getElementById(inputId);
    if (!input) return;

    const icon = btn.querySelector('i');
    const isHidden = input.type === 'password';

    input.type = isHidden ? 'text' : 'password';
    if (icon) {
      icon.classList.toggle('fa-eye', !isHidden);
      icon.classList.toggle('fa-eye-slash', isHidden);
    }
  });
});

// 2) Google callback (PHẢI gán vào window để Google gọi được)
window.onGoogleLogin = async function (response) {
  const form = new FormData();
  form.append("credential", response.credential);
  form.append("redirect", window.AUTH_REDIRECT || "trangchu.php");

  const res = await fetch("google_login.php", { method: "POST", body: form });
  const data = await res.json();

  if (data.ok) {
    window.location.href = data.redirect;
  } else {
    alert(data.message || "Google login thất bại");
  }
};
