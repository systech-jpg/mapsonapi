@extends('layouts.app')

@section('title', 'Service Report')

@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header d-flex align-items-center gap-3">
    <a href="{{ route('home') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke beranda">
      <i class="bi bi-arrow-left"></i>
    </a>

    <div class="min-width-0">
      <h1>Service Report</h1>
      <p>Laporan masuk</p>
    </div>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    @livewire('service-report.daftar')
  </div>
@endsection
