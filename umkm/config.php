<?php
// PHP 8.1+ membuat mysqli melempar exception saat query gagal (mode default).
// Kode di project ini masih gaya lama (mengecek hasil query dengan if/ternary),
// jadi mode dikembalikan ke "silent" supaya error tidak jadi Fatal Error/exception
// yang merusak balasan JSON pada endpoint AJAX (mis. tambah ke keranjang).
mysqli_report(MYSQLI_REPORT_OFF);

$config = mysqli_connect("localhost","root","","db_toko");
if (!$config) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>