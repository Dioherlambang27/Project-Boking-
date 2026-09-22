<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login_pelanggan() {
    if (!isset($_SESSION['id_pelanggan'])) {
        header('Location: pelanggan_login.php');
        exit;
    }
}
