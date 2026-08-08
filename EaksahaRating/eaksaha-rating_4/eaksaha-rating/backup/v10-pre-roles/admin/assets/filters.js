/* ============================================================
   ตัวกรองแบบไม่โหลดหน้าใหม่ — EAKSAHA RATING
   ------------------------------------------------------------
   ปัญหาเดิม: กดปุ่มกรองทีนึง เบราว์เซอร์โหลดหน้าใหม่ทั้งหน้า
              จอกระพริบและเด้งกลับไปบนสุดทุกครั้ง

   วิธีทำงานของไฟล์นี้:
     1. ดักการกดปุ่มที่มี data-ajax แทนที่จะปล่อยให้เบราว์เซอร์ไปเอง
     2. ขอข้อมูลจาก PHP หน้าเดิม แต่เติม &ajax=1 ต่อท้าย
        PHP จะตอบกลับมาเป็น JSON เฉพาะส่วนที่เปลี่ยน ไม่ต้องส่งทั้งหน้า
     3. สลับ HTML เฉพาะจุดที่ระบุมา และสั่งกราฟอัปเดตข้อมูลของตัวเอง
     4. เปลี่ยน URL ด้วย pushState เพื่อให้ปุ่มย้อนกลับยังใช้ได้
        และก๊อป URL ส่งต่อให้คนอื่นเปิดได้เหมือนเดิม

   ถ้าเบราว์เซอร์ปิด JavaScript ไว้ ปุ่มทุกอันยังเป็นลิงก์/ฟอร์มปกติ
   ระบบจะกลับไปโหลดทั้งหน้าแบบเดิม — ใช้งานได้ ไม่พัง
   ============================================================ */
(function () {
    'use strict';

    var zone = document.querySelector('[data-ajax-zone]');
    if (!zone || !window.fetch || !window.history || !history.pushState) return;

    var busy = false;

    /* ---------- แสดงว่ากำลังโหลด ---------- */
    function setBusy(on) {
        busy = on;
        document.querySelectorAll('[data-ajax-zone]').forEach(function (z) {
            z.classList.toggle('is-loading', on);
        });
    }

    /* ---------- สลับ HTML เฉพาะจุด ---------- */
    function applyPatch(patch) {
        if (!patch) return;
        Object.keys(patch).forEach(function (sel) {
            var el = document.querySelector(sel);
            if (el) el.innerHTML = patch[sel];
        });
    }

    /* ---------- อัปเดตกราฟโดยไม่สร้างใหม่ ----------
       สร้างใหม่ทุกครั้งจะทำให้กราฟกระพริบและกินหน่วยความจำ
       Chart.getChart() หยิบกราฟตัวเดิมจาก id ของ canvas มาแก้ข้อมูลตรงๆ */
    function applyCharts(charts) {
        if (!charts || typeof Chart === 'undefined' || !Chart.getChart) return;
        Object.keys(charts).forEach(function (id) {
            var chart = Chart.getChart(id);
            if (!chart) return;
            var d = charts[id];
            if (d.labels) chart.data.labels = d.labels;
            if (d.data && chart.data.datasets[0]) {
                chart.data.datasets[0].data = d.data;
                // จุดบนเส้นจะรกถ้าข้อมูลเยอะ — ซ่อนจุดเมื่อเกิน 40 ช่วง
                chart.data.datasets[0].pointRadius = (d.labels && d.labels.length > 40) ? 0 : 3;
            }
            chart.update();
        });
    }

    /* ---------- ขอข้อมูลชุดใหม่ ---------- */
    function load(url, push) {
        if (busy) return;
        setBusy(true);

        var sep = url.indexOf('?') === -1 ? '?' : '&';
        fetch(url + sep + 'ajax=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                applyPatch(data.patch);
                applyCharts(data.charts);
                if (push && data.url) history.pushState({ ajax: true }, '', data.url);
                setBusy(false);
            })
            .catch(function () {
                // ขอข้อมูลไม่สำเร็จ (เน็ตหลุด / เซสชันหมดอายุ)
                // กลับไปโหลดทั้งหน้าแบบเดิม ผู้ใช้จะได้เห็นหน้า login ถ้าต้องล็อกอินใหม่
                setBusy(false);
                window.location.href = url;
            });
    }

    /* ---------- กดปุ่มที่เป็นลิงก์ ---------- */
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-ajax]') : null;
        if (!a) return;
        // ปล่อยให้เบราว์เซอร์จัดการเอง ถ้าผู้ใช้ตั้งใจเปิดแท็บใหม่
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        closePopovers();
        load(a.getAttribute('href'), true);
    });

    /* ---------- ส่งฟอร์ม (ช่วงวันที่ / ตัวกรองเจาะจง) ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form[data-ajax]')) return;
        e.preventDefault();
        closePopovers();
        submitForm(form);
    });

    /* ---------- เปลี่ยนค่าใน select / checkbox แล้วกรองทันที ---------- */
    document.addEventListener('change', function (e) {
        var form = e.target.form;
        if (!form || !form.matches || !form.matches('form[data-ajax][data-ajax-auto]')) return;
        submitForm(form);
    });

    function submitForm(form) {
        var qs = new URLSearchParams(new FormData(form)).toString();
        var base = (form.getAttribute('action') || window.location.pathname).split('?')[0];
        load(base + (qs ? '?' + qs : ''), true);
    }

    /* ---------- ปิดกล่อง "กำหนดเอง" หลังเลือกเสร็จ ---------- */
    function closePopovers() {
        document.querySelectorAll('details.range-custom[open]').forEach(function (d) {
            d.removeAttribute('open');
        });
    }

    // คลิกนอกกล่องแล้วปิดเอง
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.range-custom[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePopovers();
    });

    /* ---------- ปุ่มย้อนกลับ / เดินหน้าของเบราว์เซอร์ ---------- */
    window.addEventListener('popstate', function () {
        load(window.location.pathname + window.location.search, false);
    });

    // บอกเบราว์เซอร์ว่าสถานะปัจจุบันคือหน้านี้ เพื่อให้ย้อนกลับครั้งแรกทำงานถูก
    history.replaceState({ ajax: true }, '', window.location.href);
})();
