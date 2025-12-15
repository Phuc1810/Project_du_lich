(function () {
  const box = document.getElementById("resendBox");
  if (!box) return;

  let remain = parseInt(box.getAttribute("data-remain") || "0", 10);

  const countdownText = document.getElementById("countdownText");
  const secLeft = document.getElementById("secLeft");
  const resendForm = document.getElementById("resendForm");

  function render() {
    if (remain > 0) {
      countdownText.style.display = "block";
      resendForm.style.display = "none";
      if (secLeft) secLeft.textContent = remain;
    } else {
      countdownText.style.display = "none";
      resendForm.style.display = "block";
    }
  }

  render();

  if (remain > 0) {
    const timer = setInterval(() => {
      remain--;
      render();
      if (remain <= 0) clearInterval(timer);
    }, 1000);
  }
})();
