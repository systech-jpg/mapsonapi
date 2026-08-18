@extends('layouts.app')

@section('title', $id ? 'Ubah Tindakan' : 'Buat Tindakan')

{{-- Layar penuh isian: tombol kembali di header sudah menjadi jalan keluar,
     jadi tab bar dan FAB Scan tidak digambar di sini. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ $id ? route('tindakan.detail', $id) : route('tindakan') }}" wire:navigate
       class="header-btn flex-shrink-0" aria-label="Kembali">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1>{{ $id ? 'Ubah Jadwal' : 'Buat Jadwal' }}</h1>
  </header>

  <div class="px-3 pb-4" style="margin-top: -2.25rem;">
    @livewire('tindakan.form', ['id' => $id])
  </div>
@endsection
