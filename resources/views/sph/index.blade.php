@extends('layouts.app')

@section('title', 'SPH')

{{-- Tab bar disembunyikan supaya FAB "Buat SPH" tidak berebut dasar layar
     dengan FAB Scan. Jalan pulang lewat tombol panah di header. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header d-flex align-items-center gap-3">
    <a href="{{ route('home') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke beranda">
      <i class="bi bi-arrow-left"></i>
    </a>

    <div class="min-width-0">
      <h1>SPH</h1>
      <p>Surat Penawaran Harga</p>
    </div>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    @if (session('pesan'))
      <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span>{{ session('pesan') }}</span>
      </div>
    @endif

    @livewire('sph.daftar')
  </div>

  <a href="{{ route('sph.buat') }}" wire:navigate class="fab-aksi">
    <i class="bi bi-plus-lg"></i> Buat SPH
  </a>
@endsection
