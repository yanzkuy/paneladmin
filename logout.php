<?php
// File: logout.php
// Deskripsi: Skrip untuk menghancurkan session dan logout pengguna.

// Mulai session untuk mengakses variabel session
session_start();
 
// Hapus semua variabel session
$_SESSION = array();
 
// Hancurkan session
session_destroy();
 
// Redirect ke halaman login dengan pesan sukses
header("location: login.php?status=logged_out");
exit;
?>
