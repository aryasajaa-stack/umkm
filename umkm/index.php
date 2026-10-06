<?php
session_start();
include "config.php";

/* =========================================================
   Ambil data kategori
   ========================================================= */
$kategoriList = [];
$qKategori = mysqli_query($config, "SELECT * FROM tb_kategori ORDER BY nama_kategori ASC");
if ($qKategori) {
    while ($k = mysqli_fetch_array($qKategori)) {
        $kategoriList[] = $k;
    }
}

/* =========================================================
   Ambil data menu (produk) + nama kategorinya
   ========================================================= */
$produkList = [];
$qProduk = mysqli_query(
    $config,
    "SELECT p.*, k.nama_kategori
     FROM tb_produk p
     LEFT JOIN tb_kategori k ON p.id_kategori = k.id_kategori
     ORDER BY p.id DESC"
);
if ($qProduk) {
    while ($p = mysqli_fetch_array($qProduk)) {
        $produkList[] = $p;
    }
}

// 3 menu terbaru dipakai untuk hero carousel
$produkUnggulan = array_slice($produkList, 0, 3);

// Status login (khusus pelanggan — sesi admin tidak dianggap "login" di halaman ini)
$sudahLogin = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') !== 'admin';
$namaUser   = $sudahLogin ? ($_SESSION['nama'] ?: $_SESSION['username']) : null;

// Jumlah item di keranjang, dipakai untuk badge di navbar
$jumlahKeranjang = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'jumlah')) : 0;

function formatHarga($angka)
{
    // Mengikuti konvensi form tambah produk: nilai di database adalah
    // ribuan rupiah, ditampilkan sebagai "Rp X.000".
    return "Rp " . number_format((float) $angka, 0, ',', '.') . ".000";
}

function fotoUrl($foto)
{
    if (!empty($foto) && file_exists(__DIR__ . "/assets/img/" . $foto)) {
        return "assets/img/" . $foto;
    }
    return null;
}
?>
<!doctype html>
<html lang="id" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta
      name="description"
      content="Kopinako — kedai kopi dengan racikan sendiri. Lihat menu dan pesan langsung lewat keranjang online."
    />
    <title>Kopinako — Kedai Kopi</title>

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
    <!-- Ikon (dipertahankan dari pola yang sudah dipakai di halaman lain) -->
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
            <li class="nav-item"><a class="nav-link active" href="#beranda">Beranda</a></li>
            <li class="nav-item"><a class="nav-link" href="#menu">Menu</a></li>
            <li class="nav-item"><a class="nav-link" href="#kontak">Kontak</a></li>
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

    <!-- Hero carousel -->
    <section id="beranda" class="kn-hero">
      <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
        <div class="carousel-indicators">
          <?php if (!empty($produkUnggulan)): ?>
            <?php foreach ($produkUnggulan as $i => $p): ?>
              <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
          <?php else: ?>
            <?php for ($i = 0; $i < 3; $i++): ?>
              <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endfor; ?>
          <?php endif; ?>
        </div>

        <div class="carousel-inner">
          <?php if (!empty($produkUnggulan)): ?>
            <?php foreach ($produkUnggulan as $i => $p):
                $foto = fotoUrl($p['foto']);
                $nama = htmlspecialchars($p['nama']);
            ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
              <div class="kn-hero-slide">
                <?php if ($foto): ?>
                  <img src="<?= htmlspecialchars($foto) ?>" alt="<?= $nama ?>" />
                <?php else: ?>
                  <div class="kn-hero-fallback-bg"></div>
                  <div class="kn-hero-fallback-mark"><?= htmlspecialchars(mb_substr($p['nama'], 0, 1)) ?></div>
                <?php endif; ?>
                <div class="kn-hero-overlay"></div>
                <div class="kn-hero-caption">
                  <span class="kn-hero-tag">Baru di menu</span>
                  <h2><?= $nama ?></h2>
                  <p><?= htmlspecialchars($p['deskripsi'] ?: 'Racikan Kopinako, dibuat langsung saat kamu pesan.') ?></p>
                  <a href="#produk-<?= (int) $p['id'] ?>" class="btn btn-caramel">Lihat menu ini</a>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="carousel-item active">
              <div class="kn-hero-slide">
                <div class="kn-hero-fallback-bg"></div>
                <div class="kn-hero-fallback-mark">K</div>
                <div class="kn-hero-overlay"></div>
                <div class="kn-hero-caption">
                  <span class="kn-hero-tag">Selamat datang</span>
                  <h2>Ngopi santai, racikan sendiri</h2>
                  <p>Kopinako menyajikan kopi dan menu pendamping yang dibuat fresh setiap hari, langsung dari kedai kami.</p>
                  <a href="#menu" class="btn btn-caramel">Lihat menu</a>
                </div>
              </div>
            </div>
            <div class="carousel-item">
              <div class="kn-hero-slide">
                <div class="kn-hero-fallback-bg"></div>
                <div class="kn-hero-fallback-mark">O</div>
                <div class="kn-hero-overlay"></div>
                <div class="kn-hero-caption">
                  <span class="kn-hero-tag">Checkout online</span>
                  <h2>Pesan langsung, tanpa antre</h2>
                  <p>Pilih menu yang kamu mau, masukkan ke keranjang, lalu checkout langsung dari sini.</p>
                  <a href="#menu" class="btn btn-caramel">Mulai pesan</a>
                </div>
              </div>
            </div>
            <div class="carousel-item">
              <div class="kn-hero-slide">
                <div class="kn-hero-fallback-bg"></div>
                <div class="kn-hero-fallback-mark">P</div>
                <div class="kn-hero-overlay"></div>
                <div class="kn-hero-caption">
                  <span class="kn-hero-tag">Menu baru menyusul</span>
                  <h2>Dari kedai kami ke meja kamu</h2>
                  <p>Daftar menu akan tampil di sini setelah ditambahkan lewat halaman dashboard.</p>
                  <a href="#menu" class="btn btn-caramel">Lihat halaman menu</a>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Sebelumnya</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Berikutnya</span>
        </button>
      </div>
    </section>

    <!-- Strip info -->
    <div class="kn-marquee" aria-hidden="true">
      <div class="kn-marquee-track">
        <span class="kn-marquee-accent">Racikan sendiri</span>
        <span class="kn-marquee-dot">•</span>
        <span>Fresh setiap hari</span>
        <span class="kn-marquee-dot">•</span>
        <span class="kn-marquee-accent">Checkout online</span>
        <span class="kn-marquee-dot">•</span>
        <span>Tanpa antre</span>
      </div>
    </div>

    <!-- Filter kategori -->
    <?php if (!empty($kategoriList)): ?>
    <section class="kn-kategori py-3">
      <div class="container">
        <div class="kn-chip-row" role="group" aria-label="Filter kategori menu">
          <button type="button" class="kn-chip active" data-kategori="semua" aria-pressed="true">Semua</button>
          <?php foreach ($kategoriList as $k): ?>
            <button type="button" class="kn-chip" data-kategori="<?= (int) $k['id_kategori'] ?>" aria-pressed="false">
              <?= htmlspecialchars($k['nama_kategori']) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Album menu -->
    <section id="menu" class="py-5">
      <div class="container">
        <h2 class="kn-section-title">Menu kami</h2>
        <p class="kn-section-sub mb-4">Kopi dan menu pendamping dibuat dalam batch kecil supaya tetap segar. Tekan tambah ke keranjang, lalu checkout langsung dari sini.</p>

        <?php if (empty($produkList)): ?>
          <div class="kn-empty">
            <h3>Belum ada menu</h3>
            <p class="mb-0">Menu yang ditambahkan lewat halaman dashboard akan otomatis muncul di sini.</p>
          </div>
        <?php else: ?>
          <div class="kn-grid">
            <?php foreach ($produkList as $p):
                $foto  = fotoUrl($p['foto']);
                $nama  = htmlspecialchars($p['nama']);
                $stok  = (int) $p['stok'];
                $habis = $stok <= 0;
                $idKategori = $p['id_kategori'] !== null ? (int) $p['id_kategori'] : 'lainnya';
            ?>
            <div class="kn-card-wrap" data-kategori="<?= $idKategori ?>" id="produk-<?= (int) $p['id'] ?>">
              <article class="kn-card h-100">
                <div class="kn-card-media">
                  <a href="detail.php?id=<?= (int) $p['id'] ?>" class="kn-card-media-link" aria-label="Lihat detail <?= $nama ?>">
                    <?php if ($foto): ?>
                      <img src="<?= htmlspecialchars($foto) ?>" alt="<?= $nama ?>" loading="lazy" />
                    <?php else: ?>
                      <div class="kn-card-media-fallback"><?= htmlspecialchars(mb_substr($p['nama'], 0, 1)) ?></div>
                    <?php endif; ?>
                  </a>
                  <span class="kn-price-tag"><?= formatHarga($p['harga']) ?></span>
                  <span class="kn-stock-flag <?= $habis ? 'habis' : '' ?>">
                    <?= $habis ? 'Habis' : 'Tersedia' ?>
                  </span>
                </div>
                <div class="kn-card-body">
                  <?php if (!empty($p['nama_kategori'])): ?>
                    <span class="kn-card-kategori"><?= htmlspecialchars($p['nama_kategori']) ?></span>
                  <?php endif; ?>
                  <h3 class="kn-card-title"><a href="detail.php?id=<?= (int) $p['id'] ?>"><?= $nama ?></a></h3>
                  <?php if (!empty($p['deskripsi'])): ?>
                    <p class="kn-card-desc"><?= htmlspecialchars($p['deskripsi']) ?></p>
                  <?php endif; ?>
                  <div class="kn-card-footer">
                    <span class="kn-stok-text"><?= $habis ? 'Stok kosong' : 'Sisa ' . $stok ?></span>
                    <?php if ($habis): ?>
                      <span class="btn btn-wa btn-sm" aria-disabled="true">Habis</span>
                    <?php else: ?>
                      <form action="keranjang.php?id=<?= (int) $p['id'] ?>" method="post" class="kn-add-form">
                        <input type="hidden" name="hidden_nama" value="<?= $nama ?>">
                        <input type="hidden" name="hidden_harga" value="<?= (int) ($p['harga'] * 1000) ?>">
                        <input type="hidden" name="hidden_foto" value="<?= htmlspecialchars($p['foto']) ?>">
                        <input type="hidden" name="jumlah" value="1">
                        <!-- "add" dipindah jadi hidden input (bukan cuma name pada tombol) supaya
                             tetap ikut terkirim walau form di-submit lewat JS (new FormData(form)
                             atau form.submit() tidak menyertakan nama tombol submit). -->
                        <input type="hidden" name="add" value="1">
                        <button type="submit" class="btn btn-wa btn-sm">+ Keranjang</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            </div>
            <?php endforeach; ?>
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
              <li><a href="#beranda">Beranda</a></li>
              <li><a href="#menu">Menu</a></li>
              <li><a href="keranjang.php">Keranjang</a></li>
              <?php if (!$sudahLogin): ?>
                <li><a href="login.php">Masuk</a></li>
                <li><a href="register.php">Daftar</a></li>
              <?php endif; ?>
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