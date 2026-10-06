<?php
session_start();
include 'config.php';
include 'auth.php';

$error    = null;
$errField = null;   // field yang bermasalah, untuk aria-invalid
$nama     = '';
$username = '';

if (isset($_POST['register'])) {
    $nama      = trim($_POST['nama'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    $dipakai = false;
    if ($username !== '') {
        $cek = mysqli_prepare($config, "SELECT id FROM tb_user WHERE username = ?");
        mysqli_stmt_bind_param($cek, "s", $username);
        mysqli_stmt_execute($cek);
        $dipakai = mysqli_num_rows(mysqli_stmt_get_result($cek)) > 0;
    }

    if ($nama === '') {
        $error = "Nama lengkap wajib diisi."; $errField = 'nama';
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $error = "Username 3-30 karakter, hanya huruf, angka, titik, dan garis bawah."; $errField = 'username';
    } elseif ($dipakai) {
        $error = "Username sudah dipakai. Coba username lain."; $errField = 'username';
    } elseif (strlen($password) < 6) {
        $error = "Kata sandi minimal 6 karakter."; $errField = 'password';
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi kata sandi tidak sama."; $errField = 'konfirmasi';
    } else {
        $stmt = mysqli_prepare($config, "INSERT INTO tb_user (nama, username, password, role) VALUES (?, ?, ?, 'pelanggan')");
        $hash = hashPassword($password);
        mysqli_stmt_bind_param($stmt, "sss", $nama, $username, $hash);

        if (mysqli_stmt_execute($stmt)) {
            header('location: login.php?daftar=1');
            exit;
        }
        $error = "Pendaftaran gagal. Coba lagi sebentar lagi.";
    }
}

function invalid($field)
{
    global $errField;
    return $errField === $field ? 'aria-invalid="true"' : '';
}
?>
<!doctype html>
<html lang="id" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Daftar — Kopinako</title>

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
          <h2>Satu akun untuk semua pesananmu.</h2>
          <p>Daftar sekali, lalu pesan menu favorit, lihat riwayat belanja, dan cetak invoice kapan saja.</p>
        </div>
      </aside>

      <main class="kn-auth-main">
        <div class="kn-auth-card">
          <a href="index.php" class="kn-auth-back">Kembali ke beranda</a>

          <h1 class="kn-auth-title">Buat akun</h1>
          <p class="kn-auth-sub">Isi data di bawah, tidak sampai satu menit.</p>

          <?php if ($error): ?>
            <div class="kn-auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form action="" method="post" class="kn-auth-form" novalidate>
            <div class="kn-field">
              <label for="nama">Nama lengkap</label>
              <input
                type="text"
                id="nama"
                name="nama"
                value="<?= htmlspecialchars($nama) ?>"
                autocomplete="name"
                <?= invalid('nama') ?>
                required
                autofocus
              />
            </div>

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
                aria-describedby="hint-username"
                <?= invalid('username') ?>
                required
              />
              <p class="kn-field-hint" id="hint-username">3-30 karakter: huruf, angka, titik, atau garis bawah.</p>
            </div>

            <div class="kn-field">
              <label for="password">Kata sandi</label>
              <div class="kn-field-pass">
                <input
                  type="password"
                  id="password"
                  name="password"
                  autocomplete="new-password"
                  aria-describedby="hint-password"
                  <?= invalid('password') ?>
                  required
                />
                <button type="button" class="kn-pass-toggle" aria-controls="password" aria-pressed="false">Lihat</button>
              </div>
              <p class="kn-field-hint" id="hint-password">Minimal 6 karakter.</p>
            </div>

            <div class="kn-field">
              <label for="konfirmasi">Ulangi kata sandi</label>
              <div class="kn-field-pass">
                <input
                  type="password"
                  id="konfirmasi"
                  name="konfirmasi"
                  autocomplete="new-password"
                  <?= invalid('konfirmasi') ?>
                  required
                />
                <button type="button" class="kn-pass-toggle" aria-controls="konfirmasi" aria-pressed="false">Lihat</button>
              </div>
            </div>

            <button class="btn btn-caramel kn-auth-submit" type="submit" name="register">Buat akun</button>
          </form>

          <p class="kn-auth-foot">Sudah punya akun? <a href="login.php">Masuk</a></p>
        </div>
      </main>
    </div>

    <script src="assets/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Tombol lihat/sembunyikan kata sandi (berlaku untuk tiap kolom kata sandi)
      document.querySelectorAll(".kn-pass-toggle").forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute("aria-controls"));
        if (!input) return;
        btn.addEventListener("click", function () {
          var tampil = input.type === "password";
          input.type = tampil ? "text" : "password";
          btn.textContent = tampil ? "Sembunyikan" : "Lihat";
          btn.setAttribute("aria-pressed", tampil ? "true" : "false");
          input.focus();
        });
      });
    </script>
  </body>
</html>
