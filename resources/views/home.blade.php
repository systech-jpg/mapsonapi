@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
  <header class="app-header">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h1>Selamat Datang</h1>
        {{-- Response /api/login memakai field 'login', bukan 'username'. --}}
        <p>{{ session('api_user.login') }}</p>
      </div>
      <a href="{{ route('pesan') }}" wire:navigate class="header-btn" aria-label="Pesan">
        <i class="bi bi-chat-fill"></i>
      </a>
    </div>
  </header>

  <div class="menu-grid">
    @foreach ($menu as $item)
      <a href="{{ route($item['route']) }}" wire:navigate class="menu-card">
        <i class="bi {{ $item['icon'] }}"></i>
        <span>{{ $item['label'] }}</span>
      </a>
    @endforeach
  </div>

  @include('partials.ios-install-banner')
@endsection
