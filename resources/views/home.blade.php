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

  @if ($galatMenu)
    <div class="px-3" style="margin-top: -3rem;">
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-0">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span class="small">{{ $galatMenu }}</span>
      </div>
    </div>
  @elseif (empty($menu))
    {{-- Menu kosong hampir selalu berarti akun ini belum diberi menu apa pun di
         ERP, bukan aplikasi yang rusak. Kalimatnya menyebut langkah berikutnya
         supaya petugas tidak menyangka aplikasinya gagal termuat. --}}
    <div class="px-3" style="margin-top: -3rem;">
      <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary">
        <i class="bi bi-grid-3x3-gap fs-2 d-block mb-2"></i>
        Belum ada menu yang diberikan untuk akun ini.
        <div class="small mt-1">Minta admin membuka halaman menu di ERP lalu menekan ASSIGN USER.</div>
      </div>
    </div>
  @else
    <div class="menu-grid">
      @foreach ($menu as $item)
        <a href="{{ route($item['route']) }}" wire:navigate class="menu-card">
          <i class="bi {{ $item['icon'] }}"></i>
          <span>{{ $item['label'] }}</span>
        </a>
      @endforeach
    </div>
  @endif

  @include('partials.ios-install-banner')
@endsection
