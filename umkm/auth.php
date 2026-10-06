<?php
// Helper kata sandi, dipakai login.php, register.php, profil.php, dan dashboard.php.
// Kata sandi disimpan sebagai hash bcrypt (60 karakter, muat di kolom varchar(80)).
// Data lama yang masih berupa teks biasa tetap bisa masuk SEKALI, lalu otomatis
// diubah menjadi hash saat login berhasil (lihat login.php).

function hashPassword($plain)
{
    return password_hash($plain, PASSWORD_BCRYPT);
}

function sudahDihash($tersimpan)
{
    return str_starts_with((string) $tersimpan, '$2y$');
}

function passwordCocok($input, $tersimpan)
{
    if (sudahDihash($tersimpan)) {
        return password_verify($input, $tersimpan);
    }
    // Data lama (teks biasa): bandingkan dengan cara yang aman dari timing attack
    return hash_equals((string) $tersimpan, (string) $input);
}
