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
<script>
const VAPID_PUBLIC_KEY = @json(config('webpush.vapid.public_key'));
const URL_SUBSCRIBE = @json(route('push.subscribe'));

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

const btn = document.getElementById('btn-aktifkan-notifikasi');
const status = document.getElementById('status-notifikasi');
const diblokir = document.getElementById('notifikasi-diblokir');

const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
const isStandalone = window.matchMedia('(display-mode: standalone)').matches
  || window.navigator.standalone === true;

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

async function periksaStatus() {
  // Di iOS, push hanya tersedia setelah aplikasi dipasang ke home screen.
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || (isIos && !isStandalone)) {
    return;
  }

  if (Notification.permission === 'denied') {
    diblokir.classList.remove('d-none');
    return;
  }

  // getRegistration() cukup untuk membaca langganan dan langsung tersedia.
  // serviceWorker.ready menunggu worker berstatus aktif — pada pemuatan
  // pertama setelah versi SW baru terpasang, itu bisa resolve sangat lambat
  // sehingga status terlihat baru muncul setelah refresh.
  const registration = (await navigator.serviceWorker.getRegistration())
    || (await navigator.serviceWorker.ready);

  if (! registration) return;

  const subscription = await registration.pushManager.getSubscription();

  if (subscription) {
    // Tampilkan segera; sinkronisasi ke server berjalan di latar belakang.
    // Menunggunya lebih dulu membuat status baru muncul setelah satu round
    // trip jaringan selesai.
    status.classList.remove('d-none');

    // Kirim ulang supaya baris di server ikut pulih bila sebelumnya gagal
    // tersimpan — endpoint-nya idempoten, jadi tidak menggandakan data.
    kirimKeServer(subscription).catch((e) => {
      console.error(e);
      // Langganan ada di perangkat tapi tidak tersimpan di server: notifikasi
      // tidak akan sampai, jadi beri jalan untuk mencoba lagi.
      status.classList.add('d-none');
      btn.classList.remove('d-none');
    });

    return;
  }

  // Belum berlangganan — tampilkan tombol, apa pun status izinnya. Izin yang
  // sudah 'granted' tidak menjamin langganannya pernah tersimpan.
  btn.classList.remove('d-none');
}

btn?.addEventListener('click', async () => {
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
});

periksaStatus().catch((e) => console.error(e));
</script>
@endpush
