{{--
  Tombol Bagikan berkas (PDF surat jalan, dsb.) lewat lembar bagi bawaan
  perangkat: WhatsApp, email, Drive, dan seterusnya.

  Pemakaian: pasang atribut pada tombol mana pun, tanpa JavaScript tambahan.

    <button type="button" data-bagikan="{{ route('...') }}"
            data-bagikan-judul="Surat Jalan TD/...">...</button>

  Berkasnya diambil lewat route web yang sama dengan tombol unduh, bukan
  langsung dari /api: browser tidak pernah memegang api_key.

  Dipasang di layout lewat satu pendengar klik di document, jadi tombol yang
  baru muncul sesudah Livewire menggambar ulang ikut bekerja.
--}}
<script data-navigate-once>
  (() => {
    // wire:navigate menjalankan ulang skrip di <body>; tanpa penjaga ini satu
    // ketukan membuka lembar bagi sebanyak halaman yang pernah dibuka.
    if (window.__bagikanTerpasang) return;
    window.__bagikanTerpasang = true;

    // Berkas yang sudah diambil disimpan per URL. Sengaja tidak diambil lebih
    // dulu saat halaman dibuka: setiap pengambilan surat jalan menulis log
    // PRINT_SJ di ERP, jadi hanya boleh terjadi bila petugas memang memintanya.
    const simpanan = new Map();

    const kabar = (teks) => {
      let el = document.getElementById('kabar');
      if (!el) {
        el = document.createElement('div');
        el.id = 'kabar';
        el.className = 'kabar';
        el.setAttribute('role', 'status');
        document.body.appendChild(el);
      }
      el.textContent = teks;
      el.classList.add('tampil');
      clearTimeout(el._tutup);
      el._tutup = setTimeout(() => el.classList.remove('tampil'), 4000);
    };

    const unduh = (berkas) => {
      const url = URL.createObjectURL(berkas);
      const a = document.createElement('a');
      a.href = url;
      a.download = berkas.name;
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 10000);
    };

    const namaBerkas = (header, cadangan) => {
      const cocok = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header || '');
      return cocok ? decodeURIComponent(cocok[1]) : cadangan;
    };

    const ambil = async (url) => {
      // Accept JSON supaya route menjawab pesan galatnya sebagai JSON. Tanpa
      // itu penolakan dialihkan ke halaman detail dan pesannya ikut habis
      // terbaca oleh halaman HTML yang tidak pernah tampil.
      const res = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      const tipe = res.headers.get('Content-Type') || '';

      if (!res.ok || !tipe.includes('pdf')) {
        let pesan = 'Berkas belum bisa diambil.';
        try { pesan = (await res.json()).message || pesan; } catch (e) {}
        throw new Error(pesan);
      }

      const blob = await res.blob();
      const nama = namaBerkas(res.headers.get('Content-Disposition'), 'dokumen.pdf');

      return new File([blob], nama, { type: 'application/pdf' });
    };

    document.addEventListener('click', async (e) => {
      const tombol = e.target.closest('[data-bagikan]');
      if (!tombol) return;

      e.preventDefault();
      if (tombol.getAttribute('aria-busy') === 'true') return;

      const url = tombol.dataset.bagikan;
      let berkas = simpanan.get(url);
      const baruDiambil = !berkas;

      if (!berkas) {
        const isiAsli = tombol.innerHTML;
        tombol.setAttribute('aria-busy', 'true');
        tombol.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

        try {
          berkas = await ambil(url);
          simpanan.set(url, berkas);
        } catch (err) {
          kabar(err.message || 'Gagal menghubungi server.');
          return;
        } finally {
          tombol.innerHTML = isiAsli;
          tombol.removeAttribute('aria-busy');
        }
      }

      const data = { files: [berkas], title: tombol.dataset.bagikanJudul || berkas.name };

      // Web Share untuk berkas hanya ada di HTTPS dan tidak di semua browser
      // (Firefox desktop, misalnya). Di sana berkasnya diunduh saja.
      if (!navigator.canShare || !navigator.canShare(data)) {
        unduh(berkas);
        kabar('Perangkat ini belum bisa membagikan berkas langsung. Berkas diunduh — bagikan dari folder Unduhan.');
        return;
      }

      try {
        await navigator.share(data);
      } catch (err) {
        if (err.name === 'AbortError') return;

        // Membuat PDF di server bisa lebih lama daripada izin "baru saja
        // diketuk" yang disyaratkan browser, terutama Safari. Berkasnya sudah
        // tersimpan, jadi ketukan berikutnya langsung membuka lembar bagi.
        if (err.name === 'NotAllowedError' && baruDiambil) {
          kabar('Berkas siap. Ketuk Bagikan sekali lagi.');
          return;
        }

        unduh(berkas);
        kabar('Lembar bagi tidak bisa dibuka. Berkas diunduh — bagikan dari folder Unduhan.');
      }
    });
  })();
</script>
