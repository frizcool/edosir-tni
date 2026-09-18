<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect($_SESSION['user']['role'] === 'admin' ? '/admin/dashboard.php' : '/personel/dashboard.php');
} else {
    redirect('/login.php');
}
