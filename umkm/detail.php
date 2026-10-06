<?php
session_start();
include "config.php";

$sudahLogin = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') !== 'admin';
$namaUser   = $sudahLogin ? ($_SESSION['nama'] ?: $_SESSION['username']) : null;
$jumlahKeranjang = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'jumlah')) : 0;

function formatHarga($angka)
{
    // Nilai di database adalah ribuan rupiah, ditampilkan sebagai "Rp X.000"
    // (sama seperti di index.php).
    return "Rp " . number_format((float) $angka, 0, ',', '.') . ".000";
}

function fotoUrl($foto)
{
    if (!empty($foto) && file_exists(__DIR__ . "/assets/img/" . $foto)) {
        return "assets/img/" . $foto;
    }
    return null;
}

$id     = (int) ($_GET['id'] ?? 0);
$produk = null;

if ($id > 0) {
    $stmt = mysqli_prepare(
        $config,
        "SELECT p.*, k.nama_kategori, b.nama AS nama_bonus
         FROM tb_produk p
         LEFT JOIN tb_kategori k ON p.id_kategori = k.id_kategori
         LEFT JOIN tb_produk b ON b.id = COALESCE(p.bonus_id_produk, p.id)
         WHERE p.id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $hasil  = mysqli_stmt_get_result($stmt);
    $produk = $hasil ? mysqli_fetch_assoc($hasil) : null;
}

if (!$produk) {
    http_response_code(404);
}

// Produk lain di kategori yang sama
$terkait = [];
if ($produk && $produk['id_kategori'] !== null) {
    $idKat = (int) $produk['id_kategori'];
    $stmtT = mysqli_prepare(
        $config,
        "SELECT p.*, k.nama_kategori
         FROM tb_produk p
         LEFT JOIN tb_kategori k ON p.id_kategori = k.id_kategori
         WHERE p.id_kategori = ? AND p.id <> ?
         ORDER BY p.id DESC LIMIT 4"
    );
    mysqli_stmt_bind_param($stmtT, "ii", $idKat, $id);
    mysqli_stmt_execute($stmtT);
    $rT = mysqli_stmt_get_result($stmtT);
    while ($rT && ($row = mysqli_fetch_assoc($rT))) {
        $terkait[] = $row;
    }
}

if ($produk) {
    $foto  = fotoUrl($produk['foto']);
    $nama  = htmlspecialchars($produk['nama']);
    $stok  = (int) $produk['stok'];
    $habis = $stok <= 0;
}
?>
<!doctype html>
<html lang="id" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $produk ? htmlspecialchars($produk['nama']) : 'Produk tidak ditemukan' ?> — Kopinako</title>

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
            <li class="nav-item"><a class="nav-link active" href="index.php#menu">Menu</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php#kontak">Kontak</a></li>
          </ul>
          <div class="d-flex gap-2">
            <a href="keranjang.php" class="btn btn-outline-ink btn-sm px-3" data-cart-badge>
              Keranjang<?php if ($jumlahKeranjang > 0): ?> (<?= $jumlahKeranjang ?>)<?php endif; ?>
            </a>
            <?php if ($sudahLogin): ?>
              <span class="align-self-center me-1 d-none d-lg-inline">Halo, <?= htmlspecialchars($namaUser) ?></span>
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
        <?php if (!$produk): ?>
          <div class="kn-empty">
            <h3>Produk tidak ditemukan</h3>
            <p class="mb-3">Produk yang kamu cari tidak ada atau sudah dihapus.</p>
            <a href="index.php#menu" class="btn btn-wa btn-sm">&larr; Kembali ke menu</a>
          </div>
        <?php else: ?>
          <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb kn-breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
              <li class="breadcrumb-item"><a href="index.php#menu">Menu</a></li>
              <?php if (!empty($produk['nama_kategori'])): ?>
                <li class="breadcrumb-item"><?= htmlspecialchars($produk['nama_kategori']) ?></li>
              <?php endif; ?>
              <li class="breadcrumb-item active" aria-current="page"><?= $nama ?></li>
            </ol>
          </nav>

          <div class="row g-4 g-lg-5 align-items-start">
            <div class="col-md-6">
              <div class="kn-detail-media">
                <?php if ($foto): ?>
                  <img src="<?= htmlspecialchars($foto) ?>" alt="<?= $nama ?>" />
                <?php else: ?>
                  <div class="kn-card-media-fallback"><?= htmlspecialchars(mb_substr($produk['nama'], 0, 1)) ?></div>
                <?php endif; ?>
                <span class="kn-stock-flag <?= $habis ? 'habis' : '' ?>"><?= $habis ? 'Habis' : 'Tersedia' ?></span>
              </div>
            </div>

            <div class="col-md-6">
              <?php if (!empty($produk['nama_kategori'])): ?>
                <span class="kn-card-kategori"><?= htmlspecialchars($produk['nama_kategori']) ?></span>
              <?php endif; ?>
              <h1 class="kn-detail-title"><?= $nama ?></h1>
              <div class="kn-detail-price"><?= formatHarga($produk['harga']) ?></div>
              <?php if ((int) $produk['bonus_minimal'] > 0 && (int) $produk['bonus_jumlah'] > 0): ?>
                <div class="small fw-semibold text-success mb-2">
                  Promo: beli <?= (int) $produk['bonus_minimal'] ?>, gratis <?= (int) $produk['bonus_jumlah'] ?> <?= htmlspecialchars((string) $produk['nama_bonus']) ?>
                </div>
              <?php endif; ?>

              <dl class="kn-detail-meta">
                <dt>Stok</dt>
                <dd><?= $habis ? 'Kosong' : $stok . ' tersedia' ?></dd>
                <dt>Kategori</dt>
                <dd><?= !empty($produk['nama_kategori']) ? htmlspecialchars($produk['nama_kategori']) : '-' ?></dd>
              </dl>

              <h2 class="kn-detail-sub">Deskripsi</h2>
              <p class="kn-detail-desc">
                <?= !empty($produk['deskripsi']) ? nl2br(htmlspecialchars($produk['deskripsi'])) : 'Belum ada deskripsi untuk produk ini.' ?>
              </p>

              <?php if ($habis): ?>
                <span class="btn btn-wa" aria-disabled="true">Stok habis</span>
              <?php else: ?>
                <form action="keranjang.php?id=<?= (int) $produk['id'] ?>" method="post" class="kn-add-form kn-detail-form">
                  <div>
                    <label for="jumlah" class="form-label mb-1 small fw-semibold">Jumlah</label>
                    <div class="kn-qty">
                      <button type="button" class="kn-qty-btn" data-qty="-1" aria-label="Kurangi jumlah">&minus;</button>
                      <input type="number" id="jumlah" name="jumlah" value="1" min="1" max="<?= $stok ?>" class="form-control" inputmode="numeric" required>
                      <button type="button" class="kn-qty-btn" data-qty="1" aria-label="Tambah jumlah">+</button>
                    </div>
                  </div>
                  <input type="hidden" name="add" value="1">
                  <button type="submit" class="btn btn-wa">+ Keranjang</button>
                </form>
                <?php if (!$sudahLogin): ?>
                  <p class="small text-body-secondary mt-3 mb-0">
                    Kamu bisa mengisi keranjang dulu, tapi harus <a href="login.php">masuk</a> untuk menyelesaikan pembelian.
                  </p>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($terkait)): ?>
            <h2 class="kn-section-title mt-5 mb-3">Menu lain di kategori ini</h2>
            <div class="kn-grid">
              <?php foreach ($terkait as $t):
                  $fotoT = fotoUrl($t['foto']);
                  $namaT = htmlspecialchars($t['nama']);
              ?>
                <a href="detail.php?id=<?= (int) $t['id'] ?>" class="kn-card-link">
                  <article class="kn-card h-100">
                    <div class="kn-card-media">
                      <?php if ($fotoT): ?>
                        <img src="<?= htmlspecialchars($fotoT) ?>" alt="<?= $namaT ?>" loading="lazy" />
                      <?php else: ?>
                        <div class="kn-card-media-fallback"><?= htmlspecialchars(mb_substr($t['nama'], 0, 1)) ?></div>
                      <?php endif; ?>
                      <span class="kn-price-tag"><?= formatHarga($t['harga']) ?></span>
                    </div>
                    <div class="kn-card-body">
                      <h3 class="kn-card-title"><?= $namaT ?></h3>
                    </div>
                  </article>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
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
