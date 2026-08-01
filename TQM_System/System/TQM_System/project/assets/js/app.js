/* ============================================================
   app.js — สคริปต์กลางที่ทุกหน้า dashboard ใช้ร่วมกัน
   ============================================================ */
(function () {
  "use strict";

  /* ── เปิด/ปิดเมนูซ้ายบนมือถือ ───────────────── */
  var sidebar = document.getElementById("sidebar");
  var scrim = document.getElementById("sidebarScrim");
  var toggle = document.getElementById("sidebarToggle");

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add("is-open");
    if (scrim) scrim.classList.add("is-open");
  }
  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove("is-open");
    if (scrim) scrim.classList.remove("is-open");
  }

  if (toggle) toggle.addEventListener("click", openSidebar);
  if (scrim) scrim.addEventListener("click", closeSidebar);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeSidebar();
  });

  /* ── ปุ่มที่มี data-modal="ไอดี" → เปิด <dialog> ── */
  document.querySelectorAll("[data-modal]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var dlg = document.getElementById(btn.dataset.modal);
      if (dlg && typeof dlg.showModal === "function") dlg.showModal();
    });
  });
  document.querySelectorAll("[data-modal-close]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var dlg = btn.closest("dialog");
      if (dlg) dlg.close();
    });
  });

  /* ── สวิตช์เปิด/ปิด (หน้าแอดมิน) ────────────── */
  document.querySelectorAll(".switch").forEach(function (sw) {
    sw.addEventListener("click", function () {
      sw.classList.toggle("is-on");
    });
  });
})();
