@extends('layouts.app')

@section('title', 'Pratinjau Laporan')

@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('tindakan.detail', $id) }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h1>Pratinjau Laporan</h1>
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

    @livewire('tindakan.pratinjau', ['id' => $id])
  </div>
@endsection
