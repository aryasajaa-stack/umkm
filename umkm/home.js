// Bayangan tipis di navbar saat halaman discroll (murni kosmetik).
(function () {
  var navbar = document.querySelector(".kn-navbar");
  if (!navbar) return;

  function toggleShadow() {
    navbar.classList.toggle("kn-scrolled", window.scrollY > 8);
  }

  toggleShadow();
  window.addEventListener("scroll", toggleShadow, { passive: true });
})();

// Filter menu berdasarkan kategori (tanpa reload halaman).
// Bersifat progressive enhancement: tanpa JS, semua menu tetap tampil.
(function () {
  var chips = document.querySelectorAll(".kn-chip");
  var cards = document.querySelectorAll(".kn-card-wrap");

  if (!chips.length || !cards.length) return;

  chips.forEach(function (chip) {
    chip.addEventListener("click", function () {
      var target = chip.getAttribute("data-kategori");

      chips.forEach(function (c) {
        c.classList.remove("active");
        c.setAttribute("aria-pressed", "false");
      });
      chip.classList.add("active");
      chip.setAttribute("aria-pressed", "true");

      cards.forEach(function (card) {
        var matches = target === "semua" || card.getAttribute("data-kategori") === target;
        card.classList.toggle("d-none", !matches);
      });
    });
  });
})();

// Tambah ke keranjang tanpa pindah halaman (AJAX progressive enhancement).
// Tanpa JS, form tetap submit biasa dan redirect ke keranjang.php seperti sebelumnya.
(function () {
  var forms = document.querySelectorAll(".kn-add-form");
  if (!forms.length || typeof window.fetch !== "function") return;

  var cartBadge = document.querySelector("[data-cart-badge]");

  function updateBadge(jumlah) {
    if (!cartBadge) return;
    cartBadge.textContent = "Keranjang" + (jumlah > 0 ? " (" + jumlah + ")" : "");
  }

  function showToast(pesan, isError) {
    var existing = document.querySelector(".kn-toast");
    if (existing) existing.remove();

    var toast = document.createElement("div");
    toast.className = "kn-toast" + (isError ? " kn-toast-error" : "");

    var msg = document.createElement("span");
    msg.className = "kn-toast-msg";
    msg.textContent = pesan;
    toast.appendChild(msg);

    var actions = document.createElement("div");
    actions.className = "kn-toast-actions";

    if (!isError) {
      var checkoutBtn = document.createElement("a");
      checkoutBtn.href = "keranjang.php";
      checkoutBtn.className = "btn btn-caramel btn-sm";
      checkoutBtn.textContent = "Checkout";
      actions.appendChild(checkoutBtn);
    }

    var closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "btn btn-outline-ink btn-sm";
    closeBtn.textContent = "Tutup";
    closeBtn.addEventListener("click", function () {
      toast.remove();
    });
    actions.appendChild(closeBtn);

    toast.appendChild(actions);
    document.body.appendChild(toast);

    requestAnimationFrame(function () {
      toast.classList.add("kn-toast-show");
    });

    window.setTimeout(function () {
      if (toast.parentNode) toast.remove();
    }, 6000);
  }

  forms.forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      var btn = form.querySelector("button[type=submit]");
      var originalText = btn ? btn.textContent : null;
      if (btn) {
        btn.disabled = true;
        btn.textContent = "Menambahkan...";
      }

      var formData = new FormData(form);
      formData.append("ajax", "1");

      fetch(form.action, { method: "POST", body: formData })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
          }
          showToast(data.pesan, !data.berhasil);
          updateBadge(data.jumlah_keranjang);
        })
        .catch(function () {
          // Kalau AJAX gagal (misal koneksi putus), pakai cara lama: submit biasa.
          form.submit();
        });
    });
  });
})();

// Tombol +/- jumlah di halaman detail produk.
(function () {
  var qtyBtns = document.querySelectorAll(".kn-qty-btn");
  if (!qtyBtns.length) return;

  qtyBtns.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = btn.parentNode.querySelector("input[type=number]");
      if (!input) return;

      var min = parseInt(input.min, 10) || 1;
      var max = parseInt(input.max, 10) || Infinity;
      var val = parseInt(input.value, 10) || min;
      val += parseInt(btn.getAttribute("data-qty"), 10);
      input.value = Math.min(max, Math.max(min, val));
    });
  });
})();

// Ringkasan checkout: ongkir & total bayar ikut berubah saat cara pengiriman dipilih.
(function () {
  var form = document.getElementById("formCheckout");
  if (!form) return;

  var subtotal = parseInt(form.getAttribute("data-subtotal"), 10) || 0;
  var ongkirEl = document.getElementById("ringkasanOngkir");
  var totalEl = document.getElementById("ringkasanTotal");

  function rupiah(n) {
    return "Rp " + n.toLocaleString("id-ID");
  }

  function hitung() {
    var dipilih = form.querySelector("input[name=pengiriman]:checked");
    var ongkir = dipilih ? parseInt(dipilih.getAttribute("data-ongkir"), 10) || 0 : 0;
    ongkirEl.textContent = dipilih ? (ongkir > 0 ? rupiah(ongkir) : "Gratis") : "-";
    totalEl.textContent = rupiah(subtotal + ongkir);
  }

  form.querySelectorAll("input[name=pengiriman]").forEach(function (r) {
    r.addEventListener("change", hitung);
  });
  hitung();
})();
