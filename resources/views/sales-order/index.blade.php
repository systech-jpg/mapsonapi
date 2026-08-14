@extends('layouts.app')

@section('title', 'Sales Order')

@section('content')
  <header class="app-header">
    <h1>Sales Order</h1>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    @livewire('sales-order.daftar')
  </div>
@endsection
