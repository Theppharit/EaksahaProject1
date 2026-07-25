<?php
// ตรวจสอบว่าล็อกอินอยู่หรือไม่ ถ้าไม่ ให้เด้งไปหน้า login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
