@extends('layouts.app')

@section('title', $id ? 'Ubah SPH' : 'SPH Baru')

{{-- Halaman kerja penuh isian: tab bar dan FAB Scan tidak digambar supaya
     tidak bertabrakan dengan bilah aksi Simpan/Batal di dasar layar. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ $id ? route('sph.detail', $id) : route('sph') }}" wire:navigate
       class="header-btn flex-shrink-0" aria-label="Kembali">
      <i class="bi bi-arrow-left"></i>
    </a>

    <h1>{{ $id ? 'Ubah SPH' : 'SPH Baru' }}</h1>
  </header>

  <div class="px-3" style="margin-top: -2.25rem;">
    @livewire('sph.form', ['id' => $id])
  </div>
@endsection
