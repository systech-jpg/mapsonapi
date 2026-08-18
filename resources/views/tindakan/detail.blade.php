@extends('layouts.app')

@section('title', 'Detail Tindakan')

{{-- Halaman kerja: bilah aksi menempel di dasar layar, jadi tab bar dan FAB
     Scan tidak digambar supaya keduanya tidak bertabrakan. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('tindakan') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1>Detail Tindakan</h1>
  </header>

  <div class="px-3" style="margin-top: -2.25rem;">
    @if (session('pesan'))
      <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span class="small">{{ session('pesan') }}</span>
      </div>
    @endif

    @if (session('galat'))
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span class="small">{{ session('galat') }}</span>
      </div>
    @endif

    @livewire('tindakan.detail', ['id' => $id])
  </div>
@endsection

@push('scripts')
  <script>
    /**
     * Menghitung kolom Kembali di browser dan menandai baris yang qty pakainya
     * melebihi qty kirim. Dikerjakan di sini, bukan lewat wire:model.live,
     * supaya tidak ada satu request per ketikan untuk angka yang baru perlu
     * dikirim saat tombol Simpan ditekan.
     *
     * Angkanya tidak dipaksa turun sendiri: mengubah ketikan orang diam-diam
     * lebih membingungkan daripada menolaknya. Penolakan sebenarnya tetap di
     * sisi server, di Detail::simpanDraft().
     *
     * Ditempel ke window (bukan deklarasi function) supaya tidak bentrok saat
     * halaman yang sama dibuka ulang lewat wire:navigate.
     */
    window.tkHitungKembali = function (input, kirim) {
      const nilai = parseInt(input.value) || 0;
      const baris = input.closest('.tk-row');

      baris.querySelector('[data-kembali]').textContent = kirim - nilai;
      baris.classList.toggle('tk-salah', nilai > kirim || nilai < 0);
    };
  </script>
@endpush
