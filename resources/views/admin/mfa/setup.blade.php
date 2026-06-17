@extends('layouts.app')

@section('title', 'Admin 2FA Setup')

@section('content')

    <h1>Two-Factor Authentication</h1>

    @if(session('status'))
        <p>{{ session('status') }}</p>
    @endif

    @if(! empty($enabled) && $enabled)
        <p>2FA is currently enabled for {{ $admin->email }}.</p>

        <form method="POST" action="{{ route('admin.mfa.disable') }}">
            @csrf
            <button type="submit">Disable 2FA</button>
        </form>

    @else
        <p>Scan this QR code with your authenticator app, then enter the 6-digit code below to confirm.</p>

        <div class="qr">
            <img src="{{ $qrImageUrl }}" alt="QR code">
        </div>

        <p>Secret: <strong>{{ $secret }}</strong></p>

        <form method="POST" action="{{ route('admin.mfa.confirm') }}">
            @csrf
            <label for="code">MFA Code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code">
            @error('code') <div class="error">{{ $message }}</div> @enderror
            <button type="submit">Confirm and Enable</button>
        </form>
    @endif

@endsection
