<?php
session_start();
include "config.php";
include "opsi.php";

// Invoice hanya untuk user yang sudah login.
if (!isset($_SESSION['id_user'])) {
    header('location: login.php');
    exit;
}

$idUser  = (int) $_SESSION['id_user'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$id      = (int) ($_GET['id'] ?? 0);

$trx = null;
if ($id > 0) {
    $stmt = mysqli_prepare(
        $config,
        "SELECT t.*, u.nama, u.username, u.email, u.hp, u.alamat
         FROM tb_transaksi t
         JOIN tb_user u ON t.id_pelanggan = u.id
         WHERE t.id_transakasi = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $r   = mysqli_stmt_get_result($stmt);
    $trx = $r ? mysqli_fetch_assoc($r) : null;
}

// Pelanggan hanya boleh melihat invoice miliknya sendiri; admin boleh semua.
if ($trx && !$isAdmin && (int) $trx['id_pelanggan'] !== $idUser) {
    $trx = null;
}

$items = [];
if ($trx) {
    $stmtD = mysqli_prepare(
        $config,
        "SELECT d.*, p.nama AS nama_produk, b.nama AS nama_bonus
         FROM tb_detail d
         JOIN tb_produk p ON d.id_produk = p.id
         LEFT JOIN tb_produk b ON d.id_produk_bonus = b.id
         WHERE d.id_transaksi = ?
         ORDER BY d.id_detail"
    );
    mysqli_stmt_bind_param($stmtD, "i", $id);
    mysqli_stmt_execute($stmtD);
    $rd = mysqli_stmt_get_result($stmtD);
    while ($rd && ($row = mysqli_fetch_assoc($rd))) {
        $items[] = $row;
    }
} else {
    http_response_code(404);
}

$linkKembali = $isAdmin ? 'dashboard.php?menu=transaksi' : 'profil.php?tab=riwayat';
$namaPelanggan = $trx ? ($trx['nama'] ?: $trx['username']) : '';
$ongkir     = $trx ? (int) $trx['ongkir'] : 0;
$totalBayar = $trx ? ((int) $trx['total_harga'] + $ongkir) : 0;
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $trx ? 'Invoice ' . nomorInvoice($trx['id_transakasi'], $trx['tanggal']) : 'Invoice tidak ditemukan' ?> — Kopinako</title>
    <link href="assets/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
      body { background: #f4efe9; color: #1c0f06; }
      .inv-paper {
        max-width: 820px;
        margin: 2rem auto;
        background: #fff;
        border-radius: 14px;
        padding: 2.5rem;
        box-shadow: 0 10px 40px -20px rgba(28, 15, 6, 0.35);
      }
      .inv-brand { font-weight: 800; font-size: 1.6rem; letter-spacing: -0.02em; color: #db4108; }
      .inv-title { font-weight: 800; letter-spacing: 0.12em; color: #6e5240; }
      .inv-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #6e5240; font-weight: 700; }
      .inv-table th { background: #fff7ec; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
      .inv-total { font-size: 1.15rem; font-weight: 800; }

      @media print {
        body { background: #fff; }
        .no-print { display: none !important; }
        .inv-paper { box-shadow: none; margin: 0; padding: 0; max-width: 100%; border-radius: 0; }
        @page { margin: 16mm; }
      }
    </style>
  </head>
  <body>
    <div class="container no-print pt-3" style="max-width: 820px;">
      <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <a href="<?= $linkKembali ?>" class="btn btn-outline-secondary btn-sm">&larr; Kembali</a>
        <?php if ($trx): ?>
          <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">Cetak / Simpan PDF</button>
        <?php endif; ?>
      </div>
      <?php if ($trx && isset($_GET['baru'])): ?>
        <div class="alert alert-success mt-3 mb-0">Pesanan berhasil dibuat. Terima kasih sudah memesan! Gunakan tombol di atas untuk mencetak invoice atau menyimpannya sebagai PDF.</div>
      <?php endif; ?>
    </div>

    <div class="inv-paper">
      <?php if (!$trx): ?>
        <h1 class="h4">Invoice tidak ditemukan</h1>
        <p class="mb-0">Invoice yang kamu cari tidak ada, sudah dihapus, atau bukan milik akunmu.</p>
      <?php else: ?>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
          <div>
            <div class="inv-brand">Kopinako</div>
            <div class="small text-secondary">Kedai kopi dengan racikan sendiri</div>
          </div>
          <div class="text-md-end">
            <div class="inv-title">INVOICE</div>
            <div class="fw-bold"><?= htmlspecialchars(nomorInvoice($trx['id_transakasi'], $trx['tanggal'])) ?></div>
            <div class="small text-secondary">Tanggal: <?= htmlspecialchars(date('d/m/Y', strtotime($trx['tanggal']))) ?></div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-sm-6">
            <div class="inv-label mb-1">Ditagihkan kepada</div>
            <div class="fw-bold"><?= htmlspecialchars($namaPelanggan) ?></div>
            <?php if (!empty($trx['hp'])): ?><div class="small"><?= htmlspecialchars($trx['hp']) ?></div><?php endif; ?>
            <?php if (!empty($trx['email'])): ?><div class="small"><?= htmlspecialchars($trx['email']) ?></div><?php endif; ?>
            <?php if (!empty($trx['alamat'])): ?><div class="small"><?= nl2br(htmlspecialchars($trx['alamat'])) ?></div><?php endif; ?>
          </div>
          <div class="col-sm-6">
            <div class="inv-label mb-1">Pembayaran &amp; pengiriman</div>
            <div class="small">Pembayaran: <strong><?= htmlspecialchars($trx['metode_pembayaran']) ?></strong></div>
            <div class="small">Pengiriman: <strong><?= htmlspecialchars($trx['metode_pengiriman']) ?></strong></div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table inv-table align-middle">
            <thead>
              <tr>
                <th>No</th>
                <th>Produk</th>
                <th class="text-center">Jumlah</th>
                <th class="text-end">Harga satuan</th>
                <th class="text-end">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($items)): ?>
                <tr><td colspan="5" class="text-center text-secondary py-4">Tidak ada rincian item pada transaksi ini.</td></tr>
              <?php endif; ?>
              <?php foreach ($items as $no => $it): ?>
                <tr>
                  <td><?= $no + 1 ?></td>
                  <td>
                    <?= htmlspecialchars($it['nama_produk']) ?>
                    <?php if ((int) $it['jumlah_bonus'] > 0): ?>
                      <div class="small text-success">+ Bonus <?= (int) $it['jumlah_bonus'] ?> <?= htmlspecialchars((string) $it['nama_bonus']) ?> (gratis)</div>
                    <?php endif; ?>
                  </td>
                  <td class="text-center"><?= (int) $it['jumlah'] ?></td>
                  <td class="text-end"><?= rupiah($it['harga_satuan']) ?></td>
                  <td class="text-end"><?= rupiah($it['subtotal']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-end">
          <table class="table table-sm table-borderless w-auto mb-0">
            <tr>
              <td class="text-end text-secondary">Subtotal</td>
              <td class="text-end"><?= rupiah($trx['total_harga']) ?></td>
            </tr>
            <tr>
              <td class="text-end text-secondary">Ongkos kirim</td>
              <td class="text-end"><?= $ongkir > 0 ? rupiah($ongkir) : 'Gratis' ?></td>
            </tr>
            <tr class="border-top">
              <td class="text-end inv-total">Total bayar</td>
              <td class="text-end inv-total"><?= rupiah($totalBayar) ?></td>
            </tr>
          </table>
        </div>

        <hr class="my-4">
        <p class="small text-secondary mb-0">Terima kasih sudah berbelanja di Kopinako. Simpan invoice ini sebagai bukti pembelian.</p>
      <?php endif; ?>
    </div>
  </body>
</html>
