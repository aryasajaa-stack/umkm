<?php
// Pilihan pembayaran & pengiriman + helper format, dipakai keranjang.php dan invoice.php.
// Ubah daftar di bawah kalau mau menambah/mengurangi opsi (nilainya divalidasi di server).

const OPSI_PEMBAYARAN = ['Transfer Bank', 'QRIS', 'COD (Bayar di Tempat)'];

// nama pengiriman => ongkos kirim (rupiah)
const OPSI_PENGIRIMAN = [
    'Ambil di Toko' => 0,
    'Kurir Toko'    => 10000,
];

function rupiah($angka)
{
    return "Rp " . number_format((float) $angka, 0, ',', '.');
}

function nomorInvoice($idTransaksi, $tanggal)
{
    return 'INV-' . date('Ymd', strtotime($tanggal)) . '-' . str_pad((int) $idTransaksi, 4, '0', STR_PAD_LEFT);
}
