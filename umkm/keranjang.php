<?php
session_start();

// Tangkap semua output (warning/notice PHP dsb) supaya tidak ikut bocor
// sebelum JSON dikirim di jalur AJAX (lihat blok "ajax" di bawah).
ob_start();

include "config.php";
include "opsi.php";

$sudahLogin = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') !== 'admin';
$namaUser   = $sudahLogin ? ($_SESSION['nama'] ?: $_SESSION['username']) : null;

function formatHarga($angka)
{
    return "Rp " . number_format((float) $angka, 0, ',', '.');
}

function fotoUrl($foto)
{
    if (!empty($foto) && file_exists(__DIR__ . "/assets/img/" . $foto)) {
        return "assets/img/" . $foto;
    }
    return null;
}

/* =========================================================
   Tambah produk ke keranjang.
   Tidak wajib login supaya tamu tetap bisa isi keranjang;
   login baru diminta saat checkout.
   ========================================================= */
if (isset($_POST["add"])) {
    $id_produk = (int) $_GET["id"];

    // Validasi stok terkini dari database, jangan cuma percaya form
    $cek    = mysqli_query($config, "SELECT * FROM tb_produk WHERE id = $id_produk");
    $produk = $cek ? mysqli_fetch_assoc($cek) : null;

    $berhasil = false;
    $pesanGagal = 'Maaf, stok produk ini sedang habis.';
    if ($produk && (int) $produk['stok'] > 0) {
        $stokTersedia = (int) $produk['stok'];
        $jumlahMinta  = max(1, (int) ($_POST["jumlah"] ?? 1));
        $sudahDiKeranjang = (int) ($_SESSION["cart"][$id_produk]['jumlah'] ?? 0);

        if ($sudahDiKeranjang + $jumlahMinta > $stokTersedia) {
            $pesanGagal = 'Jumlah melebihi stok. Stok tersisa ' . $stokTersedia . ', di keranjangmu sudah ' . $sudahDiKeranjang . '.';
        } else {
            $berhasil = true;
            if (isset($_SESSION["cart"][$id_produk])) {
                $_SESSION["cart"][$id_produk]['jumlah'] += $jumlahMinta;
            } else {
                // Nama, harga, dan foto diambil dari database (bukan dari form)
                // supaya harga tidak bisa diubah lewat inspect element.
                $_SESSION["cart"][$id_produk] = [
                    'id'     => $id_produk,
                    'nama'   => $produk['nama'],
                    'harga'  => (int) $produk['harga'] * 1000,
                    'foto'   => $produk['foto'],
                    'jumlah' => $jumlahMinta,
                ];
            }
        }
    }

    // Dipanggil lewat AJAX (fetch) dari product card: balas JSON, jangan pindah halaman
    if (isset($_POST["ajax"])) {
        // Buang semua output yang tertangkap (warning/notice dsb) supaya
        // respons benar-benar JSON murni dan bisa di-parse oleh home.js.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Content-Type: application/json");
        echo json_encode([
            'berhasil'         => $berhasil,
            'pesan'            => $berhasil ? 'Produk berhasil ditambahkan ke keranjang.' : $pesanGagal,
            'jumlah_keranjang' => array_sum(array_column($_SESSION['cart'] ?? [], 'jumlah')),
        ]);
        exit;
    }

    // Fallback tanpa JavaScript: tetap redirect seperti biasa
    header("location: keranjang.php");
    exit;
}

if (isset($_GET["aksi"])) {
    if ($_GET["aksi"] == "hapus") {
        $id_produk = (int) $_GET["id"];
        if (isset($_SESSION["cart"][$id_produk])) {
            unset($_SESSION["cart"][$id_produk]);
        }
        header("location: keranjang.php");
        exit;
    }
}

/* =========================================================
   Checkout (POST). Harga, subtotal, dan total dihitung di sini.
   Cek stok, penentuan bonus, dan pengurangan stok (beli + bonus)
   dilakukan trigger database saat INSERT ke tb_detail. Semuanya
   dibungkus satu transaksi: kalau satu langkah gagal, dibatalkan.
   ========================================================= */
$errorCheckout = null;
if (isset($_POST["checkout"])) {
    if (!$sudahLogin) {
        header("location: login.php");
        exit;
    }

    $id_user    = (int) $_SESSION['id_user'];
    $pembayaran = $_POST['pembayaran'] ?? '';
    $pengiriman = $_POST['pengiriman'] ?? '';

    $qa     = mysqli_query($config, "SELECT alamat FROM tb_user WHERE id = $id_user");
    $alamat = $qa ? trim((string) (mysqli_fetch_assoc($qa)['alamat'] ?? '')) : '';

    if (empty($_SESSION["cart"])) {
        $errorCheckout = "Keranjang masih kosong.";
    } elseif (!in_array($pembayaran, OPSI_PEMBAYARAN, true)) {
        $errorCheckout = "Pilih cara pembayaran terlebih dahulu.";
    } elseif (!array_key_exists($pengiriman, OPSI_PENGIRIMAN)) {
        $errorCheckout = "Pilih cara pengiriman terlebih dahulu.";
    } elseif ($pengiriman !== 'Ambil di Toko' && $alamat === '') {
        $errorCheckout = "Alamat pengiriman belum diisi. Lengkapi dulu di halaman Profil, atau pilih Ambil di Toko.";
    } else {
        $ongkir  = OPSI_PENGIRIMAN[$pengiriman];
        $tanggal = date("Y-m-d");

        mysqli_begin_transaction($config);
        $pesanGagal = null;
        $baris      = [];   // data final tiap item (harga diambil dari database)
        $total      = 0;

        // 1) Kunci & cek setiap produk: harus ada dan stoknya cukup
        foreach ($_SESSION["cart"] as $value) {
            $idP    = (int) $value['id'];
            $jumlah = max(1, (int) $value['jumlah']);

            $stmtP = mysqli_prepare($config, "SELECT nama, harga, stok FROM tb_produk WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmtP, "i", $idP);
            mysqli_stmt_execute($stmtP);
            $produk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP));

            if (!$produk) {
                $pesanGagal = "Salah satu produk di keranjang sudah tidak tersedia.";
                break;
            }
            if ((int) $produk['stok'] < $jumlah) {
                $pesanGagal = "Stok \"" . $produk['nama'] . "\" tidak mencukupi (sisa " . (int) $produk['stok'] . ").";
                break;
            }

            $hargaSatuan = (int) $produk['harga'] * 1000;   // harga di database dalam ribuan
            $subtotal    = $hargaSatuan * $jumlah;
            $total      += $subtotal;
            $baris[]     = ['id' => $idP, 'jumlah' => $jumlah, 'harga' => $hargaSatuan, 'subtotal' => $subtotal];
        }

        // 2) Simpan transaksi lalu rincian (trigger mengurangi stok & menentukan bonus)
        $id_transaksi = 0;
        if ($pesanGagal === null) {
            $stmt = mysqli_prepare($config, "INSERT INTO tb_transaksi (tanggal, id_pelanggan, total_harga, metode_pembayaran, metode_pengiriman, ongkir) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "siissi", $tanggal, $id_user, $total, $pembayaran, $pengiriman, $ongkir);
            if (mysqli_stmt_execute($stmt)) {
                $id_transaksi = mysqli_insert_id($config);
            } else {
                $pesanGagal = "Gagal menyimpan transaksi: " . mysqli_stmt_error($stmt);
            }
        }

        if ($pesanGagal === null) {
            foreach ($baris as $b) {
                $stmtD = mysqli_prepare($config, "INSERT INTO tb_detail (id_transaksi, id_produk, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmtD, "iiiii", $id_transaksi, $b['id'], $b['jumlah'], $b['harga'], $b['subtotal']);

                // Stok & bonus diurus trigger; kalau stok kurang, trigger melempar error di sini
                if (!mysqli_stmt_execute($stmtD)) {
                    $pesanGagal = "Gagal menyimpan rincian pesanan: " . mysqli_stmt_error($stmtD);
                    break;
                }
            }
        }

        if ($pesanGagal === null) {
            mysqli_commit($config);
            unset($_SESSION["cart"]);
            header("location: invoice.php?id=" . $id_transaksi . "&baru=1");
            exit;
        }

        // Ada yang gagal: batalkan semuanya supaya tidak ada transaksi "yatim"
        mysqli_rollback($config);
        $errorCheckout = $pesanGagal;
    }
}

$cartItems  = $_SESSION["cart"] ?? [];
$grandTotal = 0;
foreach ($cartItems as $item) {
    $grandTotal += $item["jumlah"] * $item["harga"];
}
$jumlahKeranjang = array_sum(array_column($cartItems, 'jumlah'));

// Pratinjau bonus untuk tampilan saja. Yang berlaku tetap hasil trigger
// saat checkout (bonus juga dibatasi sisa stok).
$infoBonus = [];   // id_produk => ['jumlah' => N, 'nama' => nama produk bonus]
if (!empty($cartItems)) {
    $daftarId = implode(',', array_map('intval', array_keys($cartItems)));
    $rb = mysqli_query(
        $config,
        "SELECT p.id, p.bonus_minimal, p.bonus_jumlah, b.nama AS nama_bonus
         FROM tb_produk p
         LEFT JOIN tb_produk b ON b.id = COALESCE(p.bonus_id_produk, p.id)
         WHERE p.id IN ($daftarId)"
    );
    while ($rb && ($r = mysqli_fetch_assoc($rb))) {
        $jml = (int) ($cartItems[(int) $r['id']]['jumlah'] ?? 0);
        if ((int) $r['bonus_minimal'] > 0 && (int) $r['bonus_jumlah'] > 0 && $jml >= (int) $r['bonus_minimal']) {
            $infoBonus[(int) $r['id']] = ['jumlah' => (int) $r['bonus_jumlah'], 'nama' => (string) $r['nama_bonus']];
        }
    }
}
?>
<!doctype html>
<html lang="id" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Keranjang — Kopinako</title>

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
            <li class="nav-item"><a class="nav-link active" href="keranjang.php">Keranjang</a></li>
          </ul>
          <div class="d-flex gap-2">
            <?php if ($sudahLogin): ?>
              <span class="align-self-center me-1 d-none d-md-inline">Halo, <?= htmlspecialchars($namaUser) ?></span>
              <a href="profil.php" class="btn btn-outline-ink btn-sm px-3">Profil</a>
              <a href="logout.php" class="btn btn-caramel btn-sm px-3">Keluar</a>
            <?php elseif (($_SESSION['role'] ?? '') === 'admin'): ?>
              <a href="dashboard.php" class="btn btn-outline-ink btn-sm px-3">Dashboard</a>
              <a href="logout.php" class="btn btn-caramel btn-sm px-3">Keluar</a>
            <?php else: ?>
              <a href="login.php" class="btn btn-outline-ink btn-sm px-3">Masuk</a>
              <a href="register.php" class="btn btn-caramel btn-sm px-3">Daftar</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </nav>

    <section class="py-5">
      <div class="container">
        <h2 class="kn-section-title mb-1">Keranjangmu</h2>
        <p class="kn-section-sub mb-4">Cek lagi menu yang kamu pilih sebelum checkout.</p>

        <?php if (isset($_GET['berhasil'])): ?>
          <div class="alert alert-success">Pesanan berhasil dibuat. Terima kasih sudah memesan!</div>
        <?php endif; ?>

        <?php if (!empty($cartItems)): ?>
          <div class="table-responsive kn-cart-wrap">
            <table class="table align-middle mb-0 kn-cart-table">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="text-center">Jumlah</th>
                  <th class="text-center">Harga</th>
                  <th class="text-center">Subtotal</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cartItems as $item):
                    $foto = fotoUrl($item['foto']);
                ?>
                  <tr>
                    <td>
                      <div class="d-flex align-items-center gap-3">
                        <?php if ($foto): ?>
                          <img src="<?= htmlspecialchars($foto) ?>" alt="" width="56" height="56" style="object-fit:cover;border-radius:12px;">
                        <?php endif; ?>
                        <div>
                          <span class="fw-semibold"><?= htmlspecialchars($item['nama']) ?></span>
                          <?php if (isset($infoBonus[(int) $item['id']])): $bn = $infoBonus[(int) $item['id']]; ?>
                            <div class="small text-success">+ Bonus <?= (int) $bn['jumlah'] ?> <?= htmlspecialchars($bn['nama']) ?> (gratis)</div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td class="text-center"><?= (int) $item['jumlah'] ?></td>
                    <td class="text-center"><?= formatHarga($item['harga']) ?></td>
                    <td class="text-center fw-semibold"><?= formatHarga($item['jumlah'] * $item['harga']) ?></td>
                    <td class="text-center">
                      <a href="keranjang.php?aksi=hapus&id=<?= (int) $item['id'] ?>" class="btn btn-outline-ink btn-sm">Hapus</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end fw-semibold">Subtotal</td>
                  <td class="text-center fw-bold"><?= formatHarga($grandTotal) ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <?php if ($errorCheckout): ?>
            <div class="alert alert-danger mt-4 mb-0"><?= htmlspecialchars($errorCheckout) ?></div>
          <?php endif; ?>

          <form method="post" action="keranjang.php" class="kn-checkout mt-4" id="formCheckout" data-subtotal="<?= (int) $grandTotal ?>">
            <div class="row g-4">
              <div class="col-md-4">
                <h3 class="kn-checkout-title">Cara pembayaran</h3>
                <?php foreach (OPSI_PEMBAYARAN as $i => $opsi): ?>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="pembayaran" id="bayar<?= $i ?>" value="<?= htmlspecialchars($opsi) ?>" required <?= (($_POST['pembayaran'] ?? '') === $opsi) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="bayar<?= $i ?>"><?= htmlspecialchars($opsi) ?></label>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="col-md-4">
                <h3 class="kn-checkout-title">Cara pengiriman</h3>
                <?php $j = 0; foreach (OPSI_PENGIRIMAN as $opsi => $biaya): ?>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="pengiriman" id="kirim<?= $j ?>" value="<?= htmlspecialchars($opsi) ?>" data-ongkir="<?= (int) $biaya ?>" required <?= (($_POST['pengiriman'] ?? '') === $opsi) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="kirim<?= $j ?>">
                      <?= htmlspecialchars($opsi) ?>
                      <span class="text-body-secondary">&middot; <?= $biaya > 0 ? formatHarga($biaya) : 'Gratis' ?></span>
                    </label>
                  </div>
                <?php $j++; endforeach; ?>
                <div class="form-text">Pengiriman memakai alamat di profilmu.</div>
              </div>

              <div class="col-md-4">
                <h3 class="kn-checkout-title">Ringkasan</h3>
                <dl class="kn-summary mb-3">
                  <dt>Subtotal</dt>
                  <dd><?= formatHarga($grandTotal) ?></dd>
                  <dt>Ongkos kirim</dt>
                  <dd id="ringkasanOngkir">-</dd>
                  <dt class="fw-bold">Total bayar</dt>
                  <dd class="fw-bold" id="ringkasanTotal"><?= formatHarga($grandTotal) ?></dd>
                </dl>
                <?php if ($sudahLogin): ?>
                  <button type="submit" name="checkout" value="1" class="btn btn-caramel w-100">Beli sekarang</button>
                <?php else: ?>
                  <a href="login.php" class="btn btn-caramel w-100">Masuk untuk membeli</a>
                <?php endif; ?>
              </div>
            </div>
          </form>

          <?php if (!$sudahLogin): ?>
            <p class="kn-section-sub mt-3 mb-0">Kamu perlu <a href="login.php">masuk</a> dulu untuk menyelesaikan checkout.</p>
          <?php endif; ?>
        <?php else: ?>
          <div class="kn-empty">
            <h3>Keranjang masih kosong</h3>
            <p class="mb-3">Yuk pilih menu favoritmu dulu.</p>
            <a href="index.php#menu" class="btn btn-caramel">Lihat menu</a>
          </div>
        <?php endif; ?>
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