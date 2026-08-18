@extends('layouts.app')

@section('title', 'Tindakan')

{{-- Tab bar dan FAB Scan tidak digambar: dasar layar di halaman ini dipakai
     FAB "Buat Jadwal", dan dua tombol melayang yang berebut tempat sama
     membuat salah satunya pasti tertimpa. Jalan pulang tetap ada lewat tombol
     panah di header. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header d-flex align-items-center gap-3">
    <a href="{{ route('home') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke beranda">
      <i class="bi bi-arrow-left"></i>
    </a>

    <div class="min-width-0">
      <h1>Tindakan</h1>
      <p>Jadwal Operasi</p>
    </div>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    @if (session('pesan'))
      <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span>{{ session('pesan') }}</span>
      </div>
    @endif

    @livewire('tindakan.daftar')
  </div>

  <a href="{{ route('tindakan.buat') }}" wire:navigate class="fab-aksi">
    <i class="bi bi-plus-lg"></i> Buat Jadwal
  </a>
@endsection
