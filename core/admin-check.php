<?php
function verifyAdminAccess() {
    if ($_SESSION['USER_ROLE'] !== 'admin') {
        $_SESSION['status'] = "Access denied";
        $_SESSION['status_code'] = "error";
        // header('HTTP/1.0 403 Forbidden');
        exit('Access denied. This page is restricted to administrators.');
    }
}