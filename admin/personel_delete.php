<?php
/**
 * TRISULA TNI AD - PENGHAPUSAN DATA PERSONEL KASKADE TERPADU
 * Menangani penghapusan data personel, seluruh berkas dosir digital fisik di server,
 * pas foto profil, akun sistem (users), dan log autentikasi secara transaksional.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('/admin/personel_list.php');
}

verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$admin = current_user();

if (!$id) {
    set_flash('error', 'ID data personel tidak valid.');
    redirect('/admin/personel_list.php');
}

$result = delete_personel_cascade($pdo, $id, (int)($admin['id'] ?? 0));

if ($result['success']) {
    set_flash('success', $result['message']);
} else {
    set_flash('error', $result['message']);
}

$redirectBack = $_POST['redirect_to'] ?? '/admin/personel_list.php';
// Validasi pengalihan internal admin yang aman
if (strpos($redirectBack, '/admin/') !== 0) {
    $redirectBack = '/admin/personel_list.php';
}

redirect($redirectBack);
