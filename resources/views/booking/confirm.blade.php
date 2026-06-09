  @extends('layouts.app')                                                                                         
  @section('title', 'Confirm Booking')

  @section('content')

      <div class="confirm-box">

          <div class="countdown-bar">
              <span class="countdown-label">⏱ Complete booking in:</span>
              <span class="countdown-timer" id="countdown-timer">00:00</span>
          </div>

          <h1>Booking Summary</h1>

          <div class="booking-info">
              <p><strong>Event:</strong> {{ $event->name }}</p>
              <p><strong>Venue:</strong> {{ $event->venue }}</p>
              <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($event->event_date)->format('d M Y, h:i A')
  }}</p>
          </div>

          <div class="selected-seats-box">
              <strong>Your seats:</strong>
              <div class="selected-seats-list">
                  @foreach ($seats as $seat)
                      <span class="seat-badge">{{ $seat->seat_row }}-{{ $seat->seat_column }}</span>
                  @endforeach
              </div>
              <p class="seat-total">Total: {{ $seats->count() }} seat(s)</p>
          </div>

          <div class="confirm-actions">
              <form action="{{ route('booking.confirmBooking', $event->id) }}" method="POST"
  style="display:inline;">
                  @csrf
                  <button type="submit" class="btn btn-success">Confirm Booking</button>
              </form>

              <form action="{{ route('booking.cancel', $event->id) }}" method="POST" style="display:inline;">
                  @csrf
                  <button type="submit" class="btn btn-danger">Cancel & Release Seats</button>
              </form>
          </div>

      </div>

  @endsection

  @push('scripts')
  <script>
  $(document).ready(function () {
      const expiresAt = new Date("{{ \Carbon\Carbon::parse($expiresAt)->toIso8601String() }}").getTime();

      function updateCountdown() {
          const now = new Date().getTime();
          const diff = expiresAt - now;

          if (diff <= 0) {
              $('#countdown-timer').text('EXPIRED');
              $('.countdown-bar').addClass('expired');
              $('.confirm-actions button').prop('disabled', true);
              alert('Your lock has expired. The seats have been released.');
              window.location.href = "{{ route('events.show', $event->id) }}";
              return;
          }

          const minutes = Math.floor(diff / 60000);
          const seconds = Math.floor((diff % 60000) / 1000);

          const display = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
          $('#countdown-timer').text(display);

          // Add warning class when under 1 minute
          if (diff < 60000) {
              $('.countdown-bar').addClass('warning');
          }
      }

      updateCountdown();
      setInterval(updateCountdown, 1000);
  });
  </script>
  @endpush