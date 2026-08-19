@extends('layouts.app')

@section('title', 'Scan Produk')

{{-- Tab bar dan FAB Scan tidak digambar: layar ini isinya pratinjau kamera
     yang butuh tinggi penuh, dan tombol Scan di dasar layar tidak ada gunanya
     di halaman Scan itu sendiri. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('home') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke beranda">
      <i class="bi bi-arrow-left"></i>
    </a>

    <h1>Scan Produk</h1>
  </header>

  <div class="px-3" style="margin-top: -2.25rem;">
    @livewire('scan.produk')
  </div>
@endsection
