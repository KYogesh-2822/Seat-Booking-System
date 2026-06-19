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
        <p>Scan this QR code with an authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.), then enter the 6-digit code below to confirm.</p>

        <div class="qr">
            <img src="{{ $qrImageUrl }}" alt="QR code">
        </div>

        <p>If scanning opens a URL or shows the raw text, that means your scanner app is not a TOTP authenticator. Use one of the supported authenticator apps, or enter the secret below manually:</p>

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
