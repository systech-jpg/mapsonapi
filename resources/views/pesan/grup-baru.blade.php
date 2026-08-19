@extends('layouts.app')

@section('title', 'Grup Baru')

@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('pesan') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke daftar pesan">
      <i class="bi bi-arrow-left"></i>
    </a>

    <div class="min-width-0">
      <h1>Grup Baru</h1>
    </div>
  </header>

  <div class="px-3 pt-3">
    @livewire('chat.grup-baru')
  </div>
@endsection
