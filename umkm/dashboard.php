<?php
session_start();
include "config.php";
include "auth.php";

// Dashboard khusus admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('location: login.php');
    exit;
}

$menu = $_GET['menu'] ?? 'produk';
if (!in_array($menu, ['produk', 'transaksi', 'pelanggan'], true)) {
    $menu = 'produk';
}

if (isset($_GET['aksi'])) {
    $aksi = $_GET['aksi'];
} else {
    $aksi = "";
}

/* =========================================================
   DATA PRODUK
   ========================================================= */
$errorProduk = null;

// Simpan foto unggahan dengan aman: hanya gambar, nama file diacak.
// Mengembalikan nama file baru, '' kalau tidak ada unggahan, atau false kalau gagal.
function simpanFotoUpload($file, &$pesan)
{
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $pesan = "Foto gagal diunggah. Coba lagi.";
        return false;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        $pesan = "Ukuran foto maksimal 5 MB.";
        return false;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) || @getimagesize($file['tmp_name']) === false) {
        $pesan = "Foto harus berupa gambar JPG, PNG, WEBP, atau GIF.";
        return false;
    }
    $namaBaru = 'produk_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/assets/img/' . $namaBaru)) {
        $pesan = "Foto tidak bisa disimpan ke folder assets/img.";
        return false;
    }
    return $namaBaru;
}

if ($menu === 'produk') {
    $idProduk = (int) ($_GET['id'] ?? 0);

    if ($aksi === 'edit') {
        // Isi form dengan data produk yang sedang diedit
        $stmt = mysqli_prepare($config, "SELECT * FROM tb_produk WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $idProduk);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $nama_produk = $row['nama'];
            $harga       = $row['harga'];
            $stok        = $row['stok'];
            $kategori    = $row['id_kategori'];
            $deskripsi   = $row['deskripsi'];
            $foto        = $row['foto'];
            $bonus_minimal = (string) $row['bonus_minimal'];
            $bonus_jumlah  = (string) $row['bonus_jumlah'];
            $bonus_produk  = $row['bonus_id_produk'] === null ? '' : (string) $row['bonus_id_produk'];
        } else {
            $aksi = '';
            $errorProduk = "Produk yang mau diedit tidak ditemukan.";
        }
    } elseif ($aksi === 'hapus') {
        $stmt = mysqli_prepare($config, "DELETE FROM tb_produk WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $idProduk);
        if (mysqli_stmt_execute($stmt)) {
            header("location: dashboard.php?menu=produk");
        } else {
            // Gagal biasanya karena produk sudah tercatat di transaksi pelanggan
            header("location: dashboard.php?menu=produk&hapusgagal=1");
        }
        exit;
    }

    if (isset($_POST['tambah'])) {
        $nama_produk = trim($_POST['nama_produk'] ?? '');
        $harga       = trim($_POST['harga'] ?? '');
        $stok        = trim($_POST['stok'] ?? '');
        $kategori    = (int) ($_POST['kategori'] ?? 0);
        $deskripsi   = trim($_POST['deskripsi'] ?? '');
        $bonus_minimal = trim($_POST['bonus_minimal'] ?? '');
        $bonus_jumlah  = trim($_POST['bonus_jumlah'] ?? '');
        $bonus_produk  = trim($_POST['bonus_produk'] ?? '');

        // Produk bonus (kalau diisi) harus ada. Kosong = produk yang sama.
        $bonusProdukAda = true;
        if ($bonus_produk !== '') {
            $bonusProdukAda = false;
            if (ctype_digit($bonus_produk)) {
                $idBonusCek = (int) $bonus_produk;
                $cekBon = mysqli_prepare($config, "SELECT 1 FROM tb_produk WHERE id = ?");
                mysqli_stmt_bind_param($cekBon, "i", $idBonusCek);
                mysqli_stmt_execute($cekBon);
                $bonusProdukAda = mysqli_num_rows(mysqli_stmt_get_result($cekBon)) > 0;
            }
        }

        $cekKat = mysqli_prepare($config, "SELECT 1 FROM tb_kategori WHERE id_kategori = ?");
        mysqli_stmt_bind_param($cekKat, "i", $kategori);
        mysqli_stmt_execute($cekKat);
        $kategoriAda = mysqli_num_rows(mysqli_stmt_get_result($cekKat)) > 0;

        if ($nama_produk === '') {
            $errorProduk = "Nama produk wajib diisi.";
        } elseif (!ctype_digit($harga)) {
            $errorProduk = "Harga harus berupa angka bulat (dalam ribuan), tanpa titik atau koma.";
        } elseif (!ctype_digit($stok)) {
            $errorProduk = "Stok harus berupa angka bulat.";
        } elseif (!$kategoriAda) {
            $errorProduk = "Kategori tidak valid.";
        } elseif (($bonus_minimal !== '' && !ctype_digit($bonus_minimal)) || ($bonus_jumlah !== '' && !ctype_digit($bonus_jumlah))) {
            $errorProduk = "Syarat minimal beli dan jumlah bonus harus berupa angka bulat.";
        } elseif (((int) $bonus_minimal > 0) !== ((int) $bonus_jumlah > 0)) {
            $errorProduk = "Isi syarat minimal beli dan jumlah bonus sekaligus, atau kosongkan keduanya.";
        } elseif (!$bonusProdukAda) {
            $errorProduk = "Produk bonus tidak ditemukan.";
        } else {
            $hargaInt = (int) $harga;
            $stokInt  = (int) $stok;
            $bonusMinInt = (int) $bonus_minimal;
            $bonusJmlInt = (int) $bonus_jumlah;
            // NULL = bonus berupa produk yang sama (juga kalau memilih dirinya sendiri)
            $bonusProdInt = ($bonus_produk === '' || ($aksi === 'edit' && (int) $bonus_produk === $idProduk)) ? null : (int) $bonus_produk;
            if ($bonusMinInt === 0) {
                $bonusJmlInt  = 0;
                $bonusProdInt = null;
            }
            $fotoBaru = simpanFotoUpload($_FILES['foto'] ?? [], $errorProduk);

            if ($fotoBaru !== false) {
                if ($aksi === 'edit') {
                    // Foto lama diambil dari database, bukan dari form
                    $fotoFinal = ($fotoBaru !== '') ? $fotoBaru : (string) ($foto ?? '');
                    $stmt = mysqli_prepare($config, "UPDATE tb_produk SET nama = ?, harga = ?, stok = ?, foto = ?, id_kategori = ?, deskripsi = ?, bonus_minimal = ?, bonus_jumlah = ?, bonus_id_produk = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "siisisiiii", $nama_produk, $hargaInt, $stokInt, $fotoFinal, $kategori, $deskripsi, $bonusMinInt, $bonusJmlInt, $bonusProdInt, $idProduk);
                } else {
                    $stmt = mysqli_prepare($config, "INSERT INTO tb_produk (nama, harga, stok, foto, id_kategori, deskripsi, bonus_minimal, bonus_jumlah, bonus_id_produk) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "siisisiii", $nama_produk, $hargaInt, $stokInt, $fotoBaru, $kategori, $deskripsi, $bonusMinInt, $bonusJmlInt, $bonusProdInt);
                }

                if (mysqli_stmt_execute($stmt)) {
                    header("location: dashboard.php?menu=produk");
                    exit;
                }
                $errorProduk = "Produk gagal disimpan. Coba lagi.";
            }
        }
    }
}

/* =========================================================
   DATA TRANSAKSI
   ========================================================= */
$detailTransaksi = null;
$detailItems     = null;

if ($menu === 'transaksi') {
    if ($aksi === 'hapus' && isset($_GET['id'])) {
        $idTransaksi = (int) $_GET['id'];
        // Hapus detail dulu supaya tidak melanggar foreign key, baru transaksinya
        mysqli_begin_transaction($config);
        $s1 = mysqli_prepare($config, "DELETE FROM tb_detail WHERE id_transaksi = ?");
        mysqli_stmt_bind_param($s1, "i", $idTransaksi);
        $s2 = mysqli_prepare($config, "DELETE FROM tb_transaksi WHERE id_transakasi = ?");
        mysqli_stmt_bind_param($s2, "i", $idTransaksi);
        if (mysqli_stmt_execute($s1) && mysqli_stmt_execute($s2)) {
            mysqli_commit($config);
        } else {
            mysqli_rollback($config);
        }
        header("location: dashboard.php?menu=transaksi");
        exit;
    }

    if ($aksi === 'detail' && isset($_GET['id'])) {
        $idTransaksi = (int) $_GET['id'];
        $stmt = mysqli_prepare($config, "SELECT t.*, u.nama AS nama_pelanggan, u.username
                                          FROM tb_transaksi t
                                          JOIN tb_user u ON t.id_pelanggan = u.id
                                          WHERE t.id_transakasi = ?");
        mysqli_stmt_bind_param($stmt, "i", $idTransaksi);
        mysqli_stmt_execute($stmt);
        $detailTransaksi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;

        if ($detailTransaksi) {
            $stmtI = mysqli_prepare($config, "SELECT d.*, p.nama AS nama_produk, b.nama AS nama_bonus
                                               FROM tb_detail d
                                               JOIN tb_produk p ON d.id_produk = p.id
                                               LEFT JOIN tb_produk b ON d.id_produk_bonus = b.id
                                               WHERE d.id_transaksi = ?");
            mysqli_stmt_bind_param($stmtI, "i", $idTransaksi);
            mysqli_stmt_execute($stmtI);
            $detailItems = mysqli_stmt_get_result($stmtI);
        }
    }
}

/* =========================================================
   DATA PELANGGAN
   ========================================================= */
if ($menu === 'pelanggan' && $aksi === 'reset' && isset($_GET['id'])) {
    $idUser    = (int) $_GET['id'];
    $hashBaru  = hashPassword('reset123');
    $stmt = mysqli_prepare($config, "UPDATE tb_user SET password = ? WHERE id = ? AND role = 'pelanggan'");
    mysqli_stmt_bind_param($stmt, "si", $hashBaru, $idUser);
    mysqli_stmt_execute($stmt);
    header("location: dashboard.php?menu=pelanggan&resetsukses=1");
    exit;
}
?>

<?php
// Ringkasan jumlah data untuk subjudul halaman
$jmlProduk    = (int) mysqli_fetch_row(mysqli_query($config, "SELECT COUNT(*) FROM tb_produk"))[0];
$jmlTransaksi = (int) mysqli_fetch_row(mysqli_query($config, "SELECT COUNT(*) FROM tb_transaksi"))[0];
$jmlPelanggan = (int) mysqli_fetch_row(mysqli_query($config, "SELECT COUNT(*) FROM tb_user WHERE role = 'pelanggan'"))[0];

$judul = ['produk' => 'Data produk', 'transaksi' => 'Data transaksi', 'pelanggan' => 'Data pelanggan'][$menu];
$subjudul = [
    'produk'    => $jmlProduk . ' produk terdaftar. Tambah, ubah, atau hapus menu dari sini.',
    'transaksi' => $jmlTransaksi . ' transaksi tercatat. Lihat rincian, cetak invoice, atau hapus.',
    'pelanggan' => $jmlPelanggan . ' pelanggan terdaftar. Reset kata sandi kalau pelanggan lupa.',
][$menu];

function fotoProduk($foto)
{
    if (!empty($foto) && file_exists(__DIR__ . "/assets/img/" . $foto)) {
        return "assets/img/" . $foto;
    }
    return null;
}

// Harga di database dalam ribuan rupiah (25 = Rp 25.000)
function hargaRibuan($angka)
{
    return "Rp " . number_format((float) $angka, 0, ',', '.') . ".000";
}
?>
<!doctype html>
<html lang="id" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($judul) ?> — Admin Kopinako</title>

    <link
      href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <script src="assets/js/color-modes.js"></script>
    <link href="assets/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="home.css?v=<?= filemtime(__DIR__ . '/home.css') ?>" rel="stylesheet" />
    <link href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>" rel="stylesheet" />
    <meta name="theme-color" content="#1c0f06" />
  </head>
  <body class="ad">
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

    <div class="ad-shell">
      <aside class="ad-side">
        <a class="kn-brand ad-brand" href="dashboard.php">
          <span class="kn-brand-mark" aria-hidden="true">K</span>
          Kopinako
        </a>

        <nav class="ad-nav" aria-label="Menu admin">
          <a class="ad-link<?= $menu === 'produk' ? ' active' : '' ?>" <?= $menu === 'produk' ? 'aria-current="page"' : '' ?> href="dashboard.php?menu=produk">Data produk</a>
          <a class="ad-link<?= $menu === 'transaksi' ? ' active' : '' ?>" <?= $menu === 'transaksi' ? 'aria-current="page"' : '' ?> href="dashboard.php?menu=transaksi">Data transaksi</a>
          <a class="ad-link<?= $menu === 'pelanggan' ? ' active' : '' ?>" <?= $menu === 'pelanggan' ? 'aria-current="page"' : '' ?> href="dashboard.php?menu=pelanggan">Data pelanggan</a>
        </nav>

        <div class="ad-side-foot">
          <span class="ad-who">Masuk sebagai <strong><?= htmlspecialchars($_SESSION['nama'] ?: $_SESSION['username']) ?></strong></span>
          <a class="ad-link" href="index.php">Lihat toko</a>
          <a class="ad-link" href="logout.php">Keluar</a>
        </div>
      </aside>

      <main class="ad-main">
        <header class="ad-head">
          <h1><?= htmlspecialchars($judul) ?></h1>
          <p><?= htmlspecialchars($subjudul) ?></p>
        </header>

        <?php if ($menu === 'produk'): ?>
          <?php $editing = ($aksi === 'edit' && isset($nama_produk)); ?>

          <?php if ($errorProduk): ?>
            <div class="ad-alert ad-alert-warn" role="alert"><?= htmlspecialchars($errorProduk) ?></div>
          <?php endif; ?>
          <?php if (isset($_GET['hapusgagal'])): ?>
            <div class="ad-alert ad-alert-warn" role="alert">Produk tidak bisa dihapus karena sudah tercatat di transaksi pelanggan. Ubah stoknya menjadi 0 kalau tidak ingin dijual lagi.</div>
          <?php endif; ?>

          <section class="ad-panel" aria-labelledby="judulForm">
            <h2 class="ad-panel-title" id="judulForm"><?= $editing ? 'Edit produk' : 'Tambah produk' ?></h2>

            <form
              action="?menu=produk<?= $editing ? '&aksi=edit&id=' . (int) $_GET['id'] : '' ?>"
              method="POST"
              enctype="multipart/form-data"
              class="ad-form"
            >
              <div class="ad-field span-4">
                <label for="nama">Nama produk</label>
                <input type="text" id="nama" name="nama_produk" value="<?= htmlspecialchars($nama_produk ?? '') ?>" required>
              </div>

              <div class="ad-field span-2">
                <label for="inputState">Kategori</label>
                <select id="inputState" name="kategori" required>
                  <?php
                  $result = mysqli_query($config, "SELECT * FROM tb_kategori");
                  while ($list = mysqli_fetch_array($result)) { ?>
                    <option value="<?= (int) $list['id_kategori'] ?>" <?= (isset($kategori) && (int) $kategori === (int) $list['id_kategori']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($list['nama_kategori']) ?>
                    </option>
                  <?php } ?>
                </select>
              </div>

              <div class="ad-field span-3">
                <label for="harga">Harga</label>
                <div class="ad-affix">
                  <span>Rp</span>
                  <input type="number" id="harga" name="harga" min="0" value="<?= htmlspecialchars((string) ($harga ?? '')) ?>" aria-describedby="hintHarga" required>
                  <span>.000</span>
                </div>
                <p class="ad-hint" id="hintHarga">Isi dalam ribuan: 25 berarti Rp 25.000.</p>
              </div>

              <div class="ad-field span-3">
                <label for="stok">Stok</label>
                <input type="number" id="stok" name="stok" min="0" value="<?= htmlspecialchars((string) ($stok ?? '')) ?>" required>
              </div>

              <div class="ad-field span-2">
                <label for="bonus_minimal">Bonus: minimal beli</label>
                <input type="number" id="bonus_minimal" name="bonus_minimal" min="0" value="<?= htmlspecialchars((string) ($bonus_minimal ?? '0')) ?>" aria-describedby="hintBonus">
              </div>

              <div class="ad-field span-2">
                <label for="bonus_jumlah">Bonus: jumlah gratis</label>
                <input type="number" id="bonus_jumlah" name="bonus_jumlah" min="0" value="<?= htmlspecialchars((string) ($bonus_jumlah ?? '0')) ?>">
              </div>

              <div class="ad-field span-2">
                <label for="bonus_produk">Bonus: produk</label>
                <select id="bonus_produk" name="bonus_produk">
                  <option value="">Produk yang sama</option>
                  <?php
                  $resBonus = mysqli_query($config, "SELECT id, nama FROM tb_produk ORDER BY nama");
                  while ($resBonus && ($pb = mysqli_fetch_assoc($resBonus))) {
                      if ($editing && (int) $pb['id'] === (int) ($_GET['id'] ?? 0)) { continue; }
                  ?>
                    <option value="<?= (int) $pb['id'] ?>" <?= ((string) ($bonus_produk ?? '') === (string) $pb['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pb['nama']) ?></option>
                  <?php } ?>
                </select>
              </div>
              <p class="ad-hint span-6" id="hintBonus">Contoh: minimal beli 5, jumlah gratis 1 = beli 5 gratis 1. Isi 0 pada keduanya kalau tanpa bonus. Bonus mengurangi stok dan dibatasi sisa stok.</p>

              <div class="ad-field span-6">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars($deskripsi ?? '') ?></textarea>
              </div>

              <div class="ad-field span-6">
                <label for="foto"><?= $editing ? 'Ganti foto' : 'Foto' ?></label>
                <div class="ad-file">
                  <?php $fotoLama = $editing ? fotoProduk($foto ?? '') : null; ?>
                  <?php if ($fotoLama): ?>
                    <img src="<?= htmlspecialchars($fotoLama) ?>" alt="Foto saat ini: <?= htmlspecialchars($nama_produk) ?>" class="ad-thumb">
                  <?php endif; ?>
                  <input type="file" id="foto" name="foto" accept="image/*">
                </div>
                <?php if ($editing): ?>
                  <p class="ad-hint">Kosongkan kalau tidak ingin mengganti foto.</p>
                <?php endif; ?>
              </div>

              <div class="ad-form-actions span-6">
                <button type="submit" name="tambah" class="btn btn-caramel px-4"><?= $editing ? 'Simpan perubahan' : 'Tambah produk' ?></button>
                <?php if ($editing): ?>
                  <a href="dashboard.php?menu=produk" class="ad-btn">Batal</a>
                <?php endif; ?>
              </div>
            </form>
          </section>

          <section class="ad-panel ad-panel-flush" aria-label="Daftar produk">
            <div class="ad-tablewrap">
              <table class="ad-table">
                <thead>
                  <tr>
                    <th scope="col">Produk</th>
                    <th scope="col">Kategori</th>
                    <th scope="col" class="num">Harga</th>
                    <th scope="col" class="num">Stok</th>
                    <th scope="col">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $hasil = mysqli_query($config, "SELECT p.*, k.nama_kategori FROM tb_produk p JOIN tb_kategori k ON p.id_kategori = k.id_kategori ORDER BY p.id DESC");
                  if ($hasil && mysqli_num_rows($hasil) === 0): ?>
                    <tr><td colspan="5" class="ad-empty">Belum ada produk. Tambahkan produk pertama lewat form di atas.</td></tr>
                  <?php endif;
                  while ($hasil && ($data = mysqli_fetch_array($hasil))) {
                      $fotoRow = fotoProduk($data['foto']);
                      $stokRow = (int) $data['stok'];
                  ?>
                    <tr>
                      <td>
                        <div class="ad-prod">
                          <?php if ($fotoRow): ?>
                            <img src="<?= htmlspecialchars($fotoRow) ?>" alt="" class="ad-thumb" loading="lazy">
                          <?php else: ?>
                            <span class="ad-thumb ad-thumb-empty" aria-hidden="true"><?= htmlspecialchars(mb_substr($data['nama'], 0, 1)) ?></span>
                          <?php endif; ?>
                          <div>
                            <div class="ad-prod-name"><?= htmlspecialchars($data['nama']) ?></div>
                            <?php if (!empty($data['deskripsi'])): ?>
                              <div class="ad-prod-desc"><?= htmlspecialchars($data['deskripsi']) ?></div>
                            <?php endif; ?>
                            <?php if ((int) $data['bonus_minimal'] > 0 && (int) $data['bonus_jumlah'] > 0): ?>
                              <div class="ad-prod-desc">Bonus: beli <?= (int) $data['bonus_minimal'] ?> gratis <?= (int) $data['bonus_jumlah'] ?><?= $data['bonus_id_produk'] ? ' (produk lain)' : '' ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td><?= htmlspecialchars($data['nama_kategori']) ?></td>
                      <td class="num"><?= hargaRibuan($data['harga']) ?></td>
                      <td class="num">
                        <?php if ($stokRow <= 0): ?>
                          <span class="ad-stok ad-stok-low">Habis</span>
                        <?php elseif ($stokRow <= 5): ?>
                          <span class="ad-stok ad-stok-low"><?= $stokRow ?> (menipis)</span>
                        <?php else: ?>
                          <?= $stokRow ?>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="ad-actions">
                          <a href="dashboard.php?menu=produk&aksi=edit&id=<?= (int) $data['id'] ?>" class="ad-btn">Edit</a>
                          <a href="dashboard.php?menu=produk&aksi=hapus&id=<?= (int) $data['id'] ?>" class="ad-btn ad-btn-danger" onclick="return confirm('Hapus produk ini?')">Hapus</a>
                        </div>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </section>

        <?php elseif ($menu === 'transaksi'): ?>

          <?php if ($aksi === 'detail' && $detailTransaksi): ?>
            <p class="ad-back"><a href="dashboard.php?menu=transaksi">Kembali ke daftar transaksi</a></p>

            <section class="ad-panel">
              <h2 class="ad-panel-title">Transaksi #<?= (int) $detailTransaksi['id_transakasi'] ?></h2>
              <dl class="ad-meta">
                <div><dt>Pelanggan</dt><dd><?= htmlspecialchars($detailTransaksi['nama_pelanggan'] ?: $detailTransaksi['username']) ?></dd></div>
                <div><dt>Tanggal</dt><dd><?= htmlspecialchars(date('d/m/Y', strtotime($detailTransaksi['tanggal']))) ?></dd></div>
                <div><dt>Pembayaran</dt><dd><?= htmlspecialchars($detailTransaksi['metode_pembayaran']) ?></dd></div>
                <div><dt>Pengiriman</dt><dd><?= htmlspecialchars($detailTransaksi['metode_pengiriman']) ?></dd></div>
              </dl>
              <a href="invoice.php?id=<?= (int) $detailTransaksi['id_transakasi'] ?>" class="btn btn-caramel btn-sm px-3" target="_blank">Lihat / cetak invoice</a>
            </section>

            <section class="ad-panel ad-panel-flush" aria-label="Rincian item">
              <div class="ad-tablewrap">
                <table class="ad-table">
                  <thead>
                    <tr>
                      <th scope="col">Produk</th>
                      <th scope="col" class="num">Jumlah</th>
                      <th scope="col" class="num">Harga satuan</th>
                      <th scope="col" class="num">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $adaItem = false;
                    while ($item = mysqli_fetch_assoc($detailItems)) {
                        $adaItem = true;
                    ?>
                      <tr>
                        <td>
                          <?= htmlspecialchars($item['nama_produk']) ?>
                          <?php if ((int) $item['jumlah_bonus'] > 0): ?>
                            <div class="ad-prod-desc">+ Bonus <?= (int) $item['jumlah_bonus'] ?> <?= htmlspecialchars((string) $item['nama_bonus']) ?> (gratis)</div>
                          <?php endif; ?>
                        </td>
                        <td class="num"><?= (int) $item['jumlah'] ?></td>
                        <td class="num">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                        <td class="num">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                      </tr>
                    <?php } ?>
                    <?php if (!$adaItem): ?>
                      <tr><td colspan="4" class="ad-empty">Transaksi ini tidak punya rincian item.</td></tr>
                    <?php endif; ?>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="3" class="num">Subtotal</td>
                      <td class="num">Rp <?= number_format($detailTransaksi['total_harga'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                      <td colspan="3" class="num">Ongkos kirim</td>
                      <td class="num">Rp <?= number_format($detailTransaksi['ongkir'], 0, ',', '.') ?></td>
                    </tr>
                    <tr class="ad-total">
                      <td colspan="3" class="num">Total bayar</td>
                      <td class="num">Rp <?= number_format($detailTransaksi['total_harga'] + $detailTransaksi['ongkir'], 0, ',', '.') ?></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </section>

          <?php else: ?>

            <?php if ($aksi === 'detail'): ?>
              <div class="ad-alert ad-alert-warn" role="alert">Transaksi tidak ditemukan. Mungkin sudah dihapus.</div>
            <?php endif; ?>

            <section class="ad-panel ad-panel-flush" aria-label="Daftar transaksi">
              <div class="ad-tablewrap">
                <table class="ad-table">
                  <thead>
                    <tr>
                      <th scope="col">Tanggal</th>
                      <th scope="col">Pelanggan</th>
                      <th scope="col">Pembayaran</th>
                      <th scope="col">Pengiriman</th>
                      <th scope="col" class="num">Total bayar</th>
                      <th scope="col">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $hasil = mysqli_query($config, "SELECT t.*, u.nama AS nama_pelanggan, u.username
                                                     FROM tb_transaksi t
                                                     JOIN tb_user u ON t.id_pelanggan = u.id
                                                     ORDER BY t.id_transakasi DESC");
                    if ($hasil && mysqli_num_rows($hasil) === 0): ?>
                      <tr><td colspan="6" class="ad-empty">Belum ada transaksi.</td></tr>
                    <?php endif;
                    while ($hasil && ($data = mysqli_fetch_array($hasil))) { ?>
                      <tr>
                        <td class="nowrap"><?= htmlspecialchars(date('d/m/Y', strtotime($data['tanggal']))) ?></td>
                        <td><?= htmlspecialchars($data['nama_pelanggan'] ?: $data['username']) ?></td>
                        <td><?= htmlspecialchars($data['metode_pembayaran']) ?></td>
                        <td><?= htmlspecialchars($data['metode_pengiriman']) ?></td>
                        <td class="num">Rp <?= number_format($data['total_harga'] + $data['ongkir'], 0, ',', '.') ?></td>
                        <td>
                          <div class="ad-actions">
                            <a href="dashboard.php?menu=transaksi&aksi=detail&id=<?= (int) $data['id_transakasi'] ?>" class="ad-btn">Detail</a>
                            <a href="invoice.php?id=<?= (int) $data['id_transakasi'] ?>" class="ad-btn" target="_blank">Invoice</a>
                            <a href="dashboard.php?menu=transaksi&aksi=hapus&id=<?= (int) $data['id_transakasi'] ?>" class="ad-btn ad-btn-danger" onclick="return confirm('Hapus transaksi ini beserta detailnya?')">Hapus</a>
                          </div>
                        </td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </section>

          <?php endif; ?>

        <?php else: ?>

          <?php if (isset($_GET['resetsukses'])): ?>
            <div class="ad-alert ad-alert-ok" role="status">Kata sandi pelanggan direset menjadi <strong>reset123</strong>. Sampaikan ke pelanggan supaya bisa masuk lagi.</div>
          <?php endif; ?>

          <section class="ad-panel ad-panel-flush" aria-label="Daftar pelanggan">
            <div class="ad-tablewrap">
              <table class="ad-table">
                <thead>
                  <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Username</th>
                    <th scope="col">Email</th>
                    <th scope="col">HP</th>
                    <th scope="col">Alamat</th>
                    <th scope="col">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $hasil = mysqli_query($config, "SELECT * FROM tb_user WHERE role = 'pelanggan' ORDER BY id DESC");
                  if ($hasil && mysqli_num_rows($hasil) === 0): ?>
                    <tr><td colspan="6" class="ad-empty">Belum ada pelanggan yang mendaftar.</td></tr>
                  <?php endif;
                  while ($hasil && ($data = mysqli_fetch_array($hasil))) { ?>
                    <tr>
                      <td><?= htmlspecialchars($data['nama'] ?: '-') ?></td>
                      <td><?= htmlspecialchars($data['username']) ?></td>
                      <td><?= htmlspecialchars($data['email'] ?: '-') ?></td>
                      <td class="nowrap"><?= htmlspecialchars($data['hp'] ?: '-') ?></td>
                      <td class="ad-wrap"><?= htmlspecialchars($data['alamat'] ?: '-') ?></td>
                      <td>
                        <a href="dashboard.php?menu=pelanggan&aksi=reset&id=<?= (int) $data['id'] ?>" class="ad-btn" onclick="return confirm('Reset kata sandi pelanggan ini ke reset123?')">Reset kata sandi</a>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </section>

        <?php endif; ?>
      </main>
    </div>

    <script src="assets/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
