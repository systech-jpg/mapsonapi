@extends('layouts.app')

@section('title', 'Percakapan')

{{-- Halaman kerja penuh isian: kotak tulis pesan menempel di dasar layar, jadi
     tab bar dan FAB Scan wajib tidak digambar. --}}
@section('tanpa-menu', 'ya')

{{-- Header ikut digambar DI DALAM komponen, bukan di sini, karena judulnya
     (nama lawan bicara / nama grup) baru diketahui setelah komponen memanggil
     API. Menaruhnya di sini berarti halaman ini harus memanggil API yang sama
     sekali lagi hanya untuk satu baris teks. --}}
@section('content')
  @livewire('chat.ruang', ['tipe' => $tipe, 'id' => $id])
@endsection
