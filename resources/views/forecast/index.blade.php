@extends('layouts.app')

@section('title', 'Forecast')

@section('content')
  <header class="app-header">
    <h1>Forecast</h1>
    <p>Permintaan Barang</p>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    @if (session('pesan'))
      <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span>{{ session('pesan') }}</span>
      </div>
    @endif

    @livewire('forecast.buat')
  </div>
@endsection
