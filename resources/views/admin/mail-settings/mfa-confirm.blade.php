<h1>Confirm MFA</h1>

@if (session('status'))
    <p>{{ session('status') }}</p>
@endif

@if ($errors->any())
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('admin.mail-settings.mfa.store') }}">
    @csrf

    <label>MFA Code</label>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code">

    <button type="submit">Confirm</button>
</form>