@extends('layouts.app')

@section('title', 'Tindakan')

@section('content')
  <header class="app-header">
    <h1>Tindakan</h1>
    <p>Jadwal Operasi</p>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    @if (session('pesan'))
      <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span>{{ session('pesan') }}</span>
      </div>
    @endif

    @livewire('tindakan.daftar')
  </div>
@endsection
