@extends('layouts.app')

@section('title', 'Edit SMTP Mail Settings')

@section('content')
  <h1>Mail Settings: {{ strtoupper($environment) }}</h1>

@if (session('status'))
    <p style="color: green;">{{ session('status') }}</p>
@endif

@if ($errors->any())
    <ul style="color: red;">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<p>
    <a href="{{ route('admin.mail-settings.edit', ['environment' => 'test']) }}">Test Settings</a>
    |
    <a href="{{ route('admin.mail-settings.edit', ['environment' => 'live']) }}">Live Settings</a>
</p>

<hr>

<h2>Current Active Version</h2>

@if ($active)
    <p>Mailer: {{ $active->mail_mailer }}</p>
    <p>Host: {{ $active->mail_host }}</p>
    <p>Port: {{ $active->mail_port }}</p>
    <p>Username: {{ $active->mail_username }}</p>
    <p>Password: [hidden]</p>
    <p>Secret Fingerprint: {{ $active->secret_fingerprint ?? 'none' }}</p>
    <p>From: {{ $active->mail_from_name }} &lt;{{ $active->mail_from_address }}&gt;</p>
@else
    <p>No active mail settings.</p>
@endif

<hr>

<h2>Create Pending Version</h2>

<form method="POST" action="{{ route('admin.mail-settings.pending.store', ['environment' => $environment]) }}">
    @csrf

    <div>
        <label>Mailer</label>
        <select name="mail_mailer">
            <option value="smtp">SMTP</option>
            <option value="log">Log</option>
            <option value="array">Array</option>
        </select>
    </div>

    <div>
        <label>Scheme</label>
        <select name="mail_scheme">
            <option value="">Auto / Null</option>
            <option value="smtp">smtp</option>
            <option value="smtps">smtps</option>
        </select>
    </div>

    <div>
        <label>Host</label>
        <input type="text" name="mail_host" value="{{ old('mail_host', $active->mail_host ?? '') }}">
    </div>

    <div>
        <label>Port</label>
        <input type="number" name="mail_port" value="{{ old('mail_port', $active->mail_port ?? '') }}">
    </div>

    <div>
        <label>Username</label>
        <input type="text" name="mail_username" value="{{ old('mail_username', $active->mail_username ?? '') }}">
    </div>

    <div>
        <label>Password / Secret</label>
        <input
            type="password"
            name="mail_password"
            value=""
            autocomplete="new-password"
            placeholder="Leave blank to keep current active secret"
        >
    </div>

    <div>
        <label>From Address</label>
        <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $active->mail_from_address ?? '') }}">
    </div>

    <div>
        <label>From Name</label>
        <input type="text" name="mail_from_name" value="{{ old('mail_from_name', $active->mail_from_name ?? config('app.name')) }}">
    </div>

    <button type="submit">Save Pending Version</button>
</form>

<hr>

<h2>Pending Version</h2>

@if ($pending)
    <p>Status: {{ $pending->status }}</p>
    <p>Validated At: {{ $pending->validated_at ?? 'not validated' }}</p>
    <p>Password: [hidden]</p>
    <p>Secret Fingerprint: {{ $pending->secret_fingerprint ?? 'none' }}</p>

    <form method="POST" action="{{ route('admin.mail-settings.pending.validate', ['environment' => $environment]) }}">
        @csrf

        <label>Send test email to</label>
        <input type="email" name="test_email">

        <button type="submit">Validate Pending Settings</button>
    </form>

    @if ($pending->validated_at)
        <form method="POST" action="{{ route('admin.mail-settings.activate', ['environment' => $environment]) }}">
            @csrf

            <button type="submit">Activate Validated Settings</button>
        </form>
    @endif
@else
    <p>No pending version.</p>
@endif

<hr>

<h2>Rollback</h2>

@if ($previous)
    <p>Previous version available.</p>
    <p>Host: {{ $previous->mail_host }}</p>
    <p>Secret Fingerprint: {{ $previous->secret_fingerprint ?? 'none' }}</p>

    <form method="POST" action="{{ route('admin.mail-settings.rollback', ['environment' => $environment]) }}">
        @csrf

        <button type="submit">Rollback to Previous Version</button>
    </form>
@else
    <p>No previous version available.</p>
@endif

@endsection

@push('scripts')
    <!-- <script src="{{ asset('js/login-form-validation.js') }}"></script> -->
@endpush