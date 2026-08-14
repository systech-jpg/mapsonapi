<div id="ios-install-banner" class="alert alert-info d-none m-3">
  <div class="d-flex justify-content-between align-items-start gap-2">
    <div>
      <strong>Pasang aplikasi ini</strong>
      <p class="mb-0 small">
        Ketuk tombol Bagikan di bawah layar Safari, lalu pilih
        <strong>Add to Home Screen</strong>. Notifikasi hanya aktif setelah aplikasi dipasang.
      </p>
    </div>
    <button type="button" id="ios-banner-tutup" class="btn-close flex-shrink-0" aria-label="Tutup"></button>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
  const sudahDitutup = localStorage.getItem('ios-banner-ditutup') === '1';
  const banner = document.getElementById('ios-install-banner');

  if (banner && isIos && !isStandalone && !sudahDitutup) {
    banner.classList.remove('d-none');
  }

  // Dokumen menyimpan penanda 'ios-banner-ditutup' tapi tidak pernah menulisnya,
  // sehingga banner akan muncul terus. Tombol tutup di bawah yang mengisinya.
  document.getElementById('ios-banner-tutup')?.addEventListener('click', () => {
    localStorage.setItem('ios-banner-ditutup', '1');
    banner.classList.add('d-none');
  });
})();
</script>
@endpush
