{{--
  Pendengar pesan masuk (realtime), padanan PusherManager di Android.

  Kanalnya sama persis: `chat.user.{rowid}` dengan nama event `new-message`,
  dikirim App\Events\MessageSent. Jadi satu pesan yang dikirim dari Android
  langsung muncul di PWA dan sebaliknya, tanpa endpoint tambahan.

  Dipasang di layout (berlaku di semua halaman), bukan cuma di halaman chat —
  supaya angka belum dibaca di beranda ikut hidup, sama seperti badge di
  dashboard Android.

  SENGAJA TIDAK memakai wire:poll sebagai cadangan: penanda "Memuat…" bersama
  di layouts/app.blade.php menyala untuk setiap permintaan Livewire, jadi
  polling akan membuat layar berkedip terus-menerus. Bila Pusher tidak
  terjangkau, pesan baru tetap muncul saat halaman dibuka/dimuat ulang.
--}}
@php
  $sayaId = (int) session('api_user.rowid');
  $kunciPusher = config('broadcasting.connections.pusher.key');

  /*
  | wsHost HANYA diisi untuk server Pusher sendiri (mis. Soketi).
  |
  | config('...options.host') tidak pernah kosong: bila PUSHER_HOST tidak diisi,
  | ia jatuh ke "api-<cluster>.pusher.com" — dan itu alamat REST API, BUKAN
  | alamat WebSocket ("ws-<cluster>.pusher.com"). Meneruskannya apa adanya ke
  | pusher-js membuat koneksi realtime tidak pernah tersambung. Jadi begitu
  | host-nya berakhiran .pusher.com, biarkan SDK menyusunnya sendiri dari
  | cluster.
  */
  $hostPusher = (string) config('broadcasting.connections.pusher.options.host');
  $wsHost = str_ends_with($hostPusher, '.pusher.com') ? null : ($hostPusher ?: null);

  $opsiPusher = array_filter([
      'cluster' => config('broadcasting.connections.pusher.options.cluster'),
      'forceTLS' => config('broadcasting.connections.pusher.options.scheme', 'https') === 'https',
      'wsHost' => $wsHost,
  ], fn ($v) => $v !== null);
@endphp

@if ($sayaId > 0 && filled($kunciPusher))
  @push('scripts')
  <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
  <script>
  (() => {
    // Penjaga di window: skrip ini ikut dijalankan ulang setiap wire:navigate,
    // dan tanpa penjaga, satu pesan masuk akan terhitung sebanyak jumlah
    // halaman yang pernah dibuka.
    if (window.__chatPusherTerpasang) return;
    window.__chatPusherTerpasang = true;

    const KUNCI = @json($kunciPusher);
    const OPSI = @json($opsiPusher);

    const KANAL = 'chat.user.' + @json($sayaId);

    let pusher;
    try {
      pusher = new Pusher(KUNCI, OPSI);
    } catch (e) {
      console.error('Pusher gagal dimulai', e);
      return;
    }

    pusher.subscribe(KANAL).bind('new-message', (data) => {
      const pesan = (data && data.message) || {};

      // Livewire.dispatch menyiarkan ke SEMUA komponen di halaman: inbox
      // menyegarkan daftarnya, lonceng menghitung ulang badge, dan ruang
      // percakapan menyaring sendiri apakah pesan ini miliknya.
      if (window.Livewire) {
        window.Livewire.dispatch('chat-masuk', {
          senderId: pesan.sender_id ? Number(pesan.sender_id) : null,
          groupId: pesan.group_id ? Number(pesan.group_id) : null,
        });
      }
    });
  })();
  </script>
  @endpush
@endif
