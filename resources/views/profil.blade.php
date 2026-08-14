@extends('layouts.app')

@section('title', 'Profil')

@section('content')
  <header class="app-header">
    <h1>Profil</h1>
    <p>{{ session('api_user.login') }}</p>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    <div class="bg-white rounded-4 p-4 shadow-sm">
      @if ($roles = session('api_user.roles'))
        <div class="mb-3">
          <div class="text-secondary small mb-1">Role</div>
          @foreach ($roles as $role)
            <span class="badge text-bg-light">{{ $role }}</span>
          @endforeach
        </div>
      @endif

      <div class="mb-3">
        @include('partials.push-toggle')
      </div>

      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn-outline-secondary w-100" style="border-radius: 999px;">Keluar</button>
      </form>
    </div>
  </div>
@endsection
