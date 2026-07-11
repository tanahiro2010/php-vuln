// Hifumi Secure Shop -- small UI helper, purely cosmetic.
// No AJAX/fetch here on purpose: every form is a plain HTML form so that
// scanners like OWASP ZAP can Spider/Active-Scan the app normally.

document.addEventListener("DOMContentLoaded", function () {
  // Auto-dismiss flash alerts after a few seconds so the UI stays tidy
  // during a live demo without requiring a click.
  document.querySelectorAll("[data-autohide]").forEach(function (el) {
    setTimeout(function () {
      el.style.transition = "opacity 0.4s ease";
      el.style.opacity = "0";
      setTimeout(function () { el.remove(); }, 400);
    }, 6000);
  });
});
