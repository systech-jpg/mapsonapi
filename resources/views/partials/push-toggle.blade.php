<button id="btn-aktifkan-notifikasi" class="btn w-100 fw-semibold text-white d-none"
        style="background: var(--gold-500); border-radius: 999px;">
  <i class="bi bi-bell-fill me-1"></i> Aktifkan notifikasi
</button>

<div id="status-notifikasi" class="text-secondary small d-none">
  <i class="bi bi-bell-fill me-1"></i> Notifikasi aktif di perangkat ini.
</div>

<div id="notifikasi-diblokir" class="text-danger small d-none">
  <i class="bi bi-bell-slash-fill me-1"></i>
  Notifikasi diblokir untuk situs ini. Aktifkan lewat setelan situs di browser.
</div>

@push('scripts')
{{--
  Seluruh isi dibungkus IIFE. Tanpa itu, deklarasi const berada di scope global
  dan saat wire:navigate menjalankan ulang skrip ini, deklarasinya bentrok
  sehingga blok berhenti sebelum status sempat diperiksa.
--}}
<script>
(() => {
  const VAPID_PUBLIC_KEY = @json(config('webpush.vapid.public_key'));
  const URL_SUBSCRIBE = @json(route('push.subscribe'));

  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
  }

  async function kirimKeServer(subscription) {
    const payload = subscription.toJSON();
    payload.contentEncoding = (PushManager.supportedContentEncodings || ['aesgcm'])[0];

    const res = await fetch(URL_SUBSCRIBE, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify(payload),
    });

    if (!res.ok) throw new Error('Gagal menyimpan subscription: ' + res.status);
  }

  async function pasang() {
    // Elemen dicari ulang tiap kali, karena wire:navigate mengganti isi <body>
    // dan referensi lama menunjuk node yang sudah dibuang.
    const btn = document.getElementById('btn-aktifkan-notifikasi');
    const status = document.getElementById('status-notifikasi');
    const diblokir = document.getElementById('notifikasi-diblokir');

    if (!btn || !status || !diblokir) return;

    // Di iOS, push hanya tersedia setelah aplikasi dipasang ke home screen.
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || (isIos && !isStandalone)) {
      return;
    }

    // Penanda di elemen, bukan di window: elemennya ikut terbuang saat halaman
    // ditukar, jadi handler otomatis terpasang lagi di tombol yang baru.
    if (!btn.dataset.terpasang) {
      btn.dataset.terpasang = '1';
      btn.addEventListener('click', () => aktifkan(btn, status, diblokir));
    }

    if (Notification.permission === 'denied') {
      diblokir.classList.remove('d-none');
      return;
    }

    const registration = (await navigator.serviceWorker.getRegistration())
      || (await navigator.serviceWorker.ready);

    if (!registration) return;

    const subscription = await registration.pushManager.getSubscription();

    if (subscription) {
      // Tampilkan segera; sinkronisasi ke server berjalan di latar belakang.
      status.classList.remove('d-none');

      kirimKeServer(subscription).catch((e) => {
        console.error(e);
        // Ada di perangkat tapi gagal tersimpan di server: notifikasi tidak
        // akan sampai, jadi beri jalan untuk mencoba lagi.
        status.classList.add('d-none');
        btn.classList.remove('d-none');
      });

      return;
    }

    // Belum berlangganan — tampilkan tombol, apa pun status izinnya. Izin yang
    // sudah 'granted' tidak menjamin langganannya pernah tersimpan.
    btn.classList.remove('d-none');
  }

  async function aktifkan(btn, status, diblokir) {
    btn.disabled = true;
    try {
      // Wajib dipanggil dari dalam handler klik, bukan saat halaman dimuat.
      const permission = await Notification.requestPermission();

      if (permission !== 'granted') {
        btn.disabled = false;
        if (permission === 'denied') {
          btn.classList.add('d-none');
          diblokir.classList.remove('d-none');
        }
        return;
      }

      // Membuat langganan baru butuh worker yang sudah aktif.
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
      });

      await kirimKeServer(subscription);

      btn.classList.add('d-none');
      status.classList.remove('d-none');
    } catch (e) {
      console.error(e);
      btn.disabled = false;
    }
  }

  pasang().catch((e) => console.error(e));

  // wire:navigate menukar isi halaman tanpa memuat ulang dokumen. Listener ini
  // cukup dipasang sekali; penjaga di window mencegahnya menumpuk bila skrip
  // ini dijalankan ulang.
  if (!window.__pushTogglePendengarTerpasang) {
    window.__pushTogglePendengarTerpasang = true;
    document.addEventListener('livewire:navigated', () => {
      pasang().catch((e) => console.error(e));
    });
  }
})();
</script>
@endpush
