<button id="btn-aktifkan-notifikasi" class="btn w-100 fw-semibold text-white d-none"
        style="background: var(--gold-500); border-radius: 999px;">
  <i class="bi bi-bell-fill me-1"></i> Aktifkan notifikasi
</button>

@push('scripts')
<script>
const VAPID_PUBLIC_KEY = @json(config('webpush.vapid.public_key'));

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
const isStandalone = window.matchMedia('(display-mode: standalone)').matches
  || window.navigator.standalone === true;

const btn = document.getElementById('btn-aktifkan-notifikasi');

// Di iOS, push hanya tersedia setelah aplikasi dipasang ke home screen.
if (btn && 'serviceWorker' in navigator && 'PushManager' in window && (!isIos || isStandalone)) {
  if (Notification.permission !== 'granted') {
    btn.classList.remove('d-none');
  }
}

btn?.addEventListener('click', async () => {
  btn.disabled = true;
  try {
    // Wajib dipanggil dari dalam handler klik, bukan saat halaman dimuat.
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      btn.disabled = false;
      return;
    }

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
    });

    const payload = subscription.toJSON();
    payload.contentEncoding = (PushManager.supportedContentEncodings || ['aesgcm'])[0];

    const res = await fetch('{{ route('push.subscribe') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify(payload),
    });

    if (!res.ok) throw new Error('Gagal menyimpan subscription: ' + res.status);

    btn.classList.add('d-none');
  } catch (e) {
    console.error(e);
    btn.disabled = false;
  }
});
</script>
@endpush
