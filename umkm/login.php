<?php
session_start();
include 'config.php';
include 'auth.php';

$error    = null;
$username = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($config, "SELECT * FROM tb_user WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $data  = $hasil ? mysqli_fetch_array($hasil) : null;

    if ($data && passwordCocok($password, $data['password'])) {
        // Akun lama dengan kata sandi teks biasa: ubah jadi hash sekarang juga
        if (!sudahDihash($data['password'])) {
            $hashBaru = hashPassword($password);
            $upd = mysqli_prepare($config, "UPDATE tb_user SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $hashBaru, $data['id']);
            mysqli_stmt_execute($upd);
        }

        // ID sesi baru setelah login, mencegah session fixation
        session_regenerate_id(true);

        // Simpan data user ke session supaya status login dikenali di semua halaman
        $_SESSION['id_user']  = $data['id'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['nama']     = $data['nama'];
        $_SESSION['email']    = $data['email'];
        $_SESSION['hp']       = $data['hp'];
        $_SESSION['alamat']   = $data['alamat'];
        $_SESSION['role']     = $data['role'];

        if ($data['role'] == 'admin') {
            header('location: dashboard.php');
            exit;
        } elseif ($data['role'] == 'pelanggan') {
            header('location: index.php');
            exit;
        }
    } else {
        $error = "Username atau kata sandi salah. Periksa lagi, lalu coba masuk kembali.";
    }
}
?>
<!doctype html>
<html lang="id" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Masuk — Kopinako</title>

    <link
      href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <script src="assets/js/color-modes.js"></script>
    <link href="assets/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="home.css?v=<?= filemtime(__DIR__ . '/home.css') ?>" rel="stylesheet" />
    <link href="sign-in.css?v=<?= filemtime(__DIR__ . '/sign-in.css') ?>" rel="stylesheet" />
    <meta name="theme-color" content="#1c0f06" />
  </head>
  <body class="kn-auth">
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

    <div class="kn-auth-shell">
      <aside class="kn-auth-art">
        <a class="kn-brand" href="index.php">
          <span class="kn-brand-mark" aria-hidden="true">K</span>
          Kopinako
        </a>
        <div class="kn-auth-copy">
          <h2>Masuk, lalu pesan kopimu.</h2>
          <p>Keranjang, riwayat pesanan, dan invoice tersimpan di akunmu.</p>
        </div>
      </aside>

      <main class="kn-auth-main">
        <div class="kn-auth-card">
          <a href="index.php" class="kn-auth-back">Kembali ke beranda</a>

          <h1 class="kn-auth-title">Masuk</h1>
          <p class="kn-auth-sub">Gunakan username dan kata sandi akunmu.</p>

          <?php if (isset($_GET['daftar']) && !$error): ?>
            <div class="kn-auth-ok" role="status">Akun berhasil dibuat. Silakan masuk.</div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="kn-auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form action="" method="post" class="kn-auth-form" novalidate>
            <div class="kn-field">
              <label for="username">Username</label>
              <input
                type="text"
                id="username"
                name="username"
                value="<?= htmlspecialchars($username) ?>"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                <?= $error ? 'aria-invalid="true"' : '' ?>
                required
                <?= $username === '' ? 'autofocus' : '' ?>
              />
            </div>

            <div class="kn-field">
              <label for="password">Kata sandi</label>
              <div class="kn-field-pass">
                <input
                  type="password"
                  id="password"
                  name="password"
                  autocomplete="current-password"
                  <?= $error ? 'aria-invalid="true"' : '' ?>
                  required
                  <?= $username !== '' ? 'autofocus' : '' ?>
                />
                <button type="button" class="kn-pass-toggle" aria-controls="password" aria-pressed="false">Lihat</button>
              </div>
            </div>

            <button class="btn btn-caramel kn-auth-submit" type="submit" name="login">Masuk</button>
          </form>

          <p class="kn-auth-foot">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
        </div>
      </main>
    </div>

    <script src="assets/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Tombol lihat/sembunyikan kata sandi
      (function () {
        var btn = document.querySelector(".kn-pass-toggle");
        var input = document.getElementById("password");
        if (!btn || !input) return;
        btn.addEventListener("click", function () {
          var tampil = input.type === "password";
          input.type = tampil ? "text" : "password";
          btn.textContent = tampil ? "Sembunyikan" : "Lihat";
          btn.setAttribute("aria-pressed", tampil ? "true" : "false");
          input.focus();
        });
      })();
    </script>
  </body>
</html>
