@extends('layouts.app')

@section('title', 'Isi Forecast')

{{-- Layar penuh seperti di Android: tombol kembali di header sudah menjadi
     jalan keluar, jadi tab bar dan FAB Scan tidak diperlukan di sini. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('forecast') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1>Isi Forecast</h1>
  </header>

  <div class="px-3" style="margin-top: -2.25rem;">
    @livewire('forecast.produk', ['id' => $id])
  </div>
@endsection
