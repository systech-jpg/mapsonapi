@extends('layouts.app')

@section('title', 'Detail Service Report')

@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('service-report') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke daftar service report">
      <i class="bi bi-arrow-left"></i>
    </a>

    <h1>Detail Service Report</h1>
  </header>

  <div class="px-3" style="margin-top: -2.25rem;">
    @if (session('galat'))
      <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span class="small">{{ session('galat') }}</span>
      </div>
    @endif

    @livewire('service-report.detail', ['id' => $id])
  </div>
@endsection
