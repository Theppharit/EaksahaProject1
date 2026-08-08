<?php
// ============================================================
//  ค่าลับของระบบ — ห้ามแชร์ไฟล์นี้ให้ใคร
//  ห้ามอัปขึ้น GitHub และห้ามส่งในแชท/อีเมล
// ------------------------------------------------------------
//  แยกออกมาจาก config.php เพื่อให้ส่ง config.php ให้คนอื่นดูได้
//  โดยไม่หลุดกุญแจ
// ============================================================

// กุญแจเรียกใช้ Claude API — ขอ/เปลี่ยนได้ที่ https://console.anthropic.com
define('ANTHROPIC_API_KEY', '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');

// รุ่นที่ใช้ให้ดาว — Haiku เร็วและถูกที่สุด เหมาะกับงานอ่านข้อความสั้นๆ
define('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001');

// กุญแจสำหรับเซ็นลิงก์ภายใน (ใช้กันคนยิง ai_score.php มั่ว)
// เปลี่ยนเป็นข้อความสุ่มยาวๆ อะไรก็ได้ ไม่ต้องจำ
define('AI_TOKEN_SECRET', 'eaksaha-rating-2026-x9Qm4Tz7Lb2Vn8Kd3Rp6Ws1Yc5Hj0Ug');
