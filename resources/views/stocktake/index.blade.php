@extends('layouts.app')

@section('title', 'Stocktake')

@section('content')
  <header class="app-header">
    <h1>Stocktake</h1>
    <p>Stock Opname</p>
  </header>

  <div class="px-3 st-halaman" style="margin-top: -3rem;">
    @livewire('stocktake.daftar')
  </div>
@endsection
