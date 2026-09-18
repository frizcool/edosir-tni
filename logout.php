<?php
require_once __DIR__ . '/config/config.php';
do_logout($pdo);
set_flash('success', 'Anda telah keluar.');
redirect('/login.php');
