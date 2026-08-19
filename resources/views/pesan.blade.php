@extends('layouts.app')

@section('title', 'Pesan')

{{-- Tab bar dan FAB Scan tidak digambar: dasar layar dipakai FAB "pesan baru",
     dan dua tombol melayang yang berebut tempat sama membuat salah satunya
     pasti tertimpa. Alasannya sama dengan halaman Tindakan. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header d-flex align-items-center gap-3">
    <a href="{{ route('home') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke beranda">
      <i class="bi bi-arrow-left"></i>
    </a>

    <div class="min-width-0">
      <h1>Pesan</h1>
      <p>Obrolan</p>
    </div>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    @if (session('pesan'))
      <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span>{{ session('pesan') }}</span>
      </div>
    @endif

    @livewire('chat.inbox')
  </div>

  {{-- <details>, bukan dropdown Bootstrap: konvensi proyek melarang komponen
       JavaScript Bootstrap bersama Livewire, dan menu dua baris seperti ini
       tidak butuh JavaScript sama sekali. --}}
  <details class="ch-fab">
    <summary aria-label="Mulai percakapan baru"><i class="bi bi-plus-lg"></i></summary>

    <div class="ch-fab-menu">
      <a href="{{ route('pesan.kontak') }}" wire:navigate>
        <i class="bi bi-person-fill"></i> Pesan pribadi
      </a>
      <a href="{{ route('pesan.grup-baru') }}" wire:navigate>
        <i class="bi bi-people-fill"></i> Grup baru
      </a>
    </div>
  </details>
@endsection
