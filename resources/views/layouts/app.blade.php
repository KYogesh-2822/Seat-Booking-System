<!DOCTYPE html>                                                                                                
  <html lang="en">                                                                                               
  <head>
      <meta charset="UTF-8">
      <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
      <meta name="csrf-token" content="{{ csrf_token() }}">
      <title>@yield('title', 'Seat Booking System')</title>
      <link rel="stylesheet" href="{{ asset('css/app.css') }}">
      <link rel="stylesheet" href="{{ asset('css/events.css') }}">


      @stack('styles')
  </head>
  <body>

      <nav class="navbar">
      <h2><a href="{{ route('home') }}">Seat Booking System</a></h2>

      <div class="nav-links">
          @auth('admin')
              <a href="{{ route('events.create') }}" class="btn btn-primary">+ Create Event</a>
              @if(!Auth::guard('admin')->user()->hasStripeAccount())
                  <a href="{{ route('stripe.onboard') }}" class="btn btn-warning">Connect Stripe</a>
              @elseif(!Auth::guard('admin')->user()->isStripeReady())
                  <a href="{{ route('stripe.onboard.refresh') }}" class="btn btn-warning">Finish Stripe Onboarding</a>
              @endif
              <span class="nav-user">Hello, {{ Auth::guard('admin')->user()->name }} ({{ Auth::guard('admin')->user()->role }})</span>
              <form action="{{ route('admin.logout') }}" method="POST" style="display:inline;">
                  @csrf
                  <button type="submit" class="btn btn-danger">Logout</button>
              </form>
          @endauth

          @auth('web')
              <span class="nav-user">Hello, {{ Auth::user()->name }}</span>
              <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                  @csrf
                  <button type="submit" class="btn btn-danger">Logout</button>
              </form>
          @endauth

          @guest('admin')
              @guest('web')
                  <a href="{{ route('login') }}" class="btn btn-primary">Login</a>
                  <a href="{{ route('register') }}" class="btn btn-success">Register</a>
                  <a href="{{ route('admin.login') }}" class="btn btn-warning">Admin Login</a>
              @endguest
          @endguest
      </div>
  </nav>


      <main>
          @if(session('success'))
              <div class="alert alert-success">{{ session('success') }}</div>
          @endif

          @if(session('error'))
              <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          @yield('content')
      </main>

      <footer>
          <small>&copy; {{ date('Y') }} Seat Booking System</small>
      </footer>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
      @stack('scripts')
  </body>
  </html>