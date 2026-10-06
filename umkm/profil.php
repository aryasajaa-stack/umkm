<?php
session_start();
include "config.php";
include "opsi.php";
include "auth.php";

if (!isset($_SESSION['id_user'])) {
    header('location: login.php');
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$pesan   = null;
$error   = null;

if (isset($_POST['simpan'])) {
    $username     = trim($_POST['username']);
    $nama         = trim($_POST['nama']);
    $email        = trim($_POST['email']);
    $hp           = trim($_POST['hp']);
    $alamat       = trim($_POST['alamat']);
    $passwordBaru = trim($_POST['password']);

    $usernameDipakai = false;
    if ($username !== '') {
        $cekU = mysqli_prepare($config, "SELECT id FROM tb_user WHERE username = ? AND id <> ?");
        mysqli_stmt_bind_param($cekU, "si", $username, $id_user);
        mysqli_stmt_execute($cekU);
        $usernameDipakai = mysqli_num_rows(mysqli_stmt_get_result($cekU)) > 0;
    }

    if ($username === '') {
        $error = "Username tidak boleh kosong.";
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $error = "Username 3-30 karakter, hanya huruf, angka, titik, dan garis bawah.";
    } elseif ($usernameDipakai) {
        $error = "Username sudah dipakai pengguna lain.";
    } elseif ($nama === '') {
        $error = "Nama tidak boleh kosong.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        if ($passwordBaru !== '') {
            $stmt = mysqli_prepare($config, "UPDATE tb_user SET username=?, nama=?, email=?, hp=?, alamat=?, password=? WHERE id=?");
            $hashBaru = hashPassword($passwordBaru);
            mysqli_stmt_bind_param($stmt, "ssssssi", $username, $nama, $email, $hp, $alamat, $hashBaru, $id_user);
        } else {
            $stmt = mysqli_prepare($config, "UPDATE tb_user SET username=?, nama=?, email=?, hp=?, alamat=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssssi", $username, $nama, $email, $hp, $alamat, $id_user);
        }

        if (mysqli_stmt_execute($stmt)) {
            // Sinkronkan session supaya "Halo, ..." di navbar langsung ikut berubah
            $_SESSION['username'] = $username;
            $_SESSION['nama']     = $nama;
            $_SESSION['email']    = $email;
            $_SESSION['hp']       = $hp;
            $_SESSION['alamat']   = $alamat;
            $pesan = "Profil berhasil diperbarui.";
        } else {
            $error = "Gagal menyimpan perubahan. Coba lagi.";
        }
    }
}

$q    = mysqli_query($config, "SELECT * FROM tb_user WHERE id = $id_user");
$user = mysqli_fetch_assoc($q);

$tab = ($_GET['tab'] ?? 'profil') === 'riwayat' ? 'riwayat' : 'profil';

// Riwayat transaksi milik user ini (transaksi tanpa rincian item disembunyikan)
$riwayat = [];
if ($tab === 'riwayat') {
    $stmtR = mysqli_prepare(
        $config,
        "SELECT t.* FROM tb_transaksi t
         WHERE t.id_pelanggan = ?
           AND EXISTS (SELECT 1 FROM tb_detail d WHERE d.id_transaksi = t.id_transakasi)
         ORDER BY t.id_transakasi DESC"
    );
    mysqli_stmt_bind_param($stmtR, "i", $id_user);
    mysqli_stmt_execute($stmtR);
    $rr = mysqli_stmt_get_result($stmtR);
    while ($rr && ($row = mysqli_fetch_assoc($rr))) {
        $riwayat[] = $row;
    }
}

$sudahLogin      = true;
$namaUser        = $_SESSION['nama'] ?: $_SESSION['username'];
$jumlahKeranjang = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'jumlah')) : 0;
?>
<!doctype html>
<html lang="id" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Profil Saya — Kopinako</title>

    <link
      href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <script src="assets/js/color-modes.js"></script>
    <link href="assets/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="home.css?v=<?= filemtime(__DIR__ . '/home.css') ?>" rel="stylesheet" />
    <meta name="theme-color" content="#ff5a1f" />
  </head>
  <body>
    <svg xmlns="http://www.w3.org/2000/svg" class="d-none">
      <symbol id="circle-half" viewBox="0 0 16 16">
        <path d="M8 15A7 7 0 1 0 8 1v14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z"></path>
      </symbol>
      <symbol id="sun-fill" viewBox="0 0 16 16">
        <path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"></path>
      </symbol>
      <symbol id="moon-stars-fill" viewBox="0 0 16 16">
        <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"></path>
        <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"></path>
      </symbol>
      <symbol id="check2" viewBox="0 0 16 16">
        <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"></path>
      </symbol>
    </svg>

    <!-- Toggle tema -->
    <div class="dropdown position-fixed bottom-0 end-0 mb-3 me-3 bd-mode-toggle">
      <button
        class="btn btn-bd-brand py-2 dropdown-toggle d-flex align-items-center shadow"
        id="bd-theme"
        type="button"
        aria-expanded="false"
        data-bs-toggle="dropdown"
        aria-label="Ganti tema (auto)"
      >
        <svg class="bi my-1 theme-icon-active" aria-hidden="true"><use href="#circle-half"></use></svg>
        <span class="visually-hidden" id="bd-theme-text">Ganti tema</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="bd-theme-text">
        <li>
          <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
            <svg class="bi me-2 opacity-50" aria-hidden="true"><use href="#sun-fill"></use></svg>
            Terang
            <svg class="bi ms-auto d-none" aria-hidden="true"><use href="#check2"></use></svg>
          </button>
        </li>
        <li>
          <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
            <svg class="bi me-2 opacity-50" aria-hidden="true"><use href="#moon-stars-fill"></use></svg>
            Gelap
            <svg class="bi ms-auto d-none" aria-hidden="true"><use href="#check2"></use></svg>
          </button>
        </li>
        <li>
          <button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto" aria-pressed="true">
            <svg class="bi me-2 opacity-50" aria-hidden="true"><use href="#circle-half"></use></svg>
            Auto
            <svg class="bi ms-auto d-none" aria-hidden="true"><use href="#check2"></use></svg>
          </button>
        </li>
      </ul>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-md kn-navbar sticky-top py-3">
      <div class="container">
        <a class="kn-brand navbar-brand" href="index.php">
          <span class="kn-brand-mark" aria-hidden="true">K</span>
          Kopinako
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navUtama" aria-controls="navUtama" aria-expanded="false" aria-label="Buka menu">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navUtama">
          <ul class="navbar-nav mx-md-auto my-3 my-md-0">
            <li class="nav-item"><a class="nav-link" href="index.php#beranda">Beranda</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php#menu">Menu</a></li>
            <li class="nav-item"><a class="nav-link" href="keranjang.php">Keranjang<?php if ($jumlahKeranjang > 0): ?> (<?= $jumlahKeranjang ?>)<?php endif; ?></a></li>
          </ul>
          <div class="d-flex gap-2">
            <span class="align-self-center me-1 d-none d-md-inline">Halo, <?= htmlspecialchars($namaUser) ?></span>
            <a href="profil.php" class="btn btn-outline-ink btn-sm px-3 active">Profil</a>
            <a href="logout.php" class="btn btn-caramel btn-sm px-3">Keluar</a>
          </div>
        </div>
      </div>
    </nav>

    <section class="py-5">
      <div class="container" style="max-width: 980px;">
        <h2 class="kn-section-title mb-1">Akun saya</h2>
        <p class="kn-section-sub mb-4">Kelola data akun dan lihat riwayat belanjamu.</p>

        <div class="row g-4">
          <div class="col-md-3">
            <div class="list-group kn-profil-menu">
              <a href="profil.php" class="list-group-item list-group-item-action <?= $tab === 'profil' ? 'active' : '' ?>">Edit profil</a>
              <a href="profil.php?tab=riwayat" class="list-group-item list-group-item-action <?= $tab === 'riwayat' ? 'active' : '' ?>">Riwayat transaksi</a>
            </div>
          </div>

          <div class="col-md-9">
            <?php if ($tab === 'riwayat'): ?>
              <h3 class="h5 mb-3">Riwayat transaksi</h3>
              <?php if (empty($riwayat)): ?>
                <div class="kn-empty">
                  <h3>Belum ada transaksi</h3>
                  <p class="mb-3">Pesananmu akan muncul di sini setelah kamu checkout.</p>
                  <a href="index.php#menu" class="btn btn-caramel">Lihat menu</a>
                </div>
              <?php else: ?>
                <div class="table-responsive kn-cart-wrap">
                  <table class="table align-middle mb-0 kn-cart-table">
                    <thead>
                      <tr>
                        <th>Invoice</th>
                        <th>Tanggal</th>
                        <th>Pembayaran</th>
                        <th>Pengiriman</th>
                        <th class="text-end">Total bayar</th>
                        <th class="text-center">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($riwayat as $t): ?>
                        <tr>
                          <td class="fw-semibold"><?= htmlspecialchars(nomorInvoice($t['id_transakasi'], $t['tanggal'])) ?></td>
                          <td><?= htmlspecialchars(date('d/m/Y', strtotime($t['tanggal']))) ?></td>
                          <td><?= htmlspecialchars($t['metode_pembayaran']) ?></td>
                          <td><?= htmlspecialchars($t['metode_pengiriman']) ?></td>
                          <td class="text-end fw-semibold"><?= rupiah($t['total_harga'] + $t['ongkir']) ?></td>
                          <td class="text-center">
                            <a href="invoice.php?id=<?= (int) $t['id_transakasi'] ?>" class="btn btn-caramel btn-sm" target="_blank">Invoice</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>

            <?php else: ?>
              <?php if ($pesan): ?>
                <div class="alert alert-success"><?= htmlspecialchars($pesan) ?></div>
              <?php endif; ?>
              <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
              <?php endif; ?>

              <form method="post" class="d-flex flex-column gap-3" style="max-width: 560px;">
                <div>
                  <label class="form-label">Username</label>
                  <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? $user['username']) ?>" required>
                </div>
                <div>
                  <label class="form-label">Nama</label>
                  <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama'] ?? '') ?>" required>
                </div>
                <div>
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>
                <div>
                  <label class="form-label">Nomor HP</label>
                  <input type="text" name="hp" class="form-control" value="<?= htmlspecialchars($user['hp'] ?? '') ?>">
                </div>
                <div>
                  <label class="form-label">Alamat</label>
                  <textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                </div>
                <div>
                  <label class="form-label">Kata sandi baru</label>
                  <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah">
                </div>
                <div>
                  <button type="submit" name="simpan" class="btn btn-caramel px-4">Simpan perubahan</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer id="kontak" class="kn-footer py-5">
      <div class="container">
        <div class="row g-4">
          <div class="col-md-4">
            <a class="kn-brand navbar-brand mb-2" href="index.php">
              <span class="kn-brand-mark" aria-hidden="true">K</span>
              Kopinako
            </a>
            <p class="mb-0">Kedai kopi dengan racikan sendiri, dibuat segar setiap hari dari kedai kami ke meja kamu.</p>
          </div>
          <div class="col-6 col-md-4">
            <div class="kn-foot-heading">Jelajah</div>
            <ul class="list-unstyled d-flex flex-column gap-2">
              <li><a href="index.php#beranda">Beranda</a></li>
              <li><a href="index.php#menu">Menu</a></li>
              <li><a href="keranjang.php">Keranjang</a></li>
            </ul>
          </div>
          <div class="col-6 col-md-4">
            <div class="kn-foot-heading">Kontak</div>
            <ul class="list-unstyled d-flex flex-column gap-2">
              <li><a href="#">Instagram</a></li>
            </ul>
          </div>
        </div>
        <hr class="my-4" />
        <p class="mb-0 small">&copy; <?= date('Y') ?> Kopinako. Semua hak dilindungi.</p>
      </div>
    </footer>

    <script src="assets/dist/js/bootstrap.bundle.min.js"></script>
    <script src="home.js?v=<?= filemtime(__DIR__ . '/home.js') ?>"></script>
  </body>
</html>
