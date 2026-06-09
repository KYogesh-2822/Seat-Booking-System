@extends('layouts.app')

  @section('title', $event->name)

  @section('content')

      <a href="{{ route('home') }}" class="btn btn-primary">← Back to Events</a>

      @auth('admin')
          @php $currentAdmin = Auth::guard('admin')->user(); @endphp
          @if($currentAdmin->id === $event->admin_id && !$currentAdmin->isStripeReady())
              <div class="alert alert-warning">
                  Your Stripe Connect onboarding is not complete for this event. 
                  <a href="{{ route('stripe.onboard') }}" class="btn btn-warning btn-sm">Complete Stripe Onboarding</a>
              </div>
          @endif
      @endauth

      <div class="event-header">
          <h1>{{ $event->name }}</h1>
          <p><strong>Venue:</strong> {{ $event->event_venue }}</p>
          <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($event->event_date)->format('d M Y, h:i A') }}</p>
      </div>

      <div class="screen">SCREEN THIS WAY</div>

      <div class="seat-legend">
          <div><span class="seat-sample available"></span> Available</div>
          <div><span class="seat-sample locked"></span> Locked</div>
          <div><span class="seat-sample booked"></span> Booked</div>
      </div>

      @php
          // Current booker (user or admin) — used to detect "my own locked seats" so we can render them as selected instead of locked.
          $booker = \App\Support\Booker::current();
      @endphp

      <div class="seat-grid" style="grid-template-columns: 40px repeat({{ $event->total_columns }}, 40px);">

          {{-- Top row: column numbers --}}
          <div class="seat-label"></div>
          @for ($c = 1; $c <= $event->total_columns; $c++)
              <div class="seat-label">{{ $c }}</div>
          @endfor

          {{-- Group seats by row --}}
          @php
              $seatsByRow = $event->seats->groupBy('seat_row');
          @endphp

          @foreach ($seatsByRow as $rowLetter => $rowSeats)
              {{-- Row label (A, B, C...) --}}
              <div class="seat-label">{{ $rowLetter }}</div>

              {{-- Seats in this row --}}
              @foreach ($rowSeats as $seat)
                  @php
                      // If this seat is locked by the current booker, show it as "selected" (yellow) instead of "locked" (gray).
                      $isMine = $booker
                          && $seat->status === 'locked'
                          && $seat->locked_by === $booker['id']
                          && $seat->locked_by_type === $booker['type'];
                      $displayClass = $isMine ? 'available selected' : $seat->status;
                  @endphp
                  <div class="seat {{ $displayClass }}"
                       data-seat-id="{{ $seat->id }}"
                       title="Seat {{ $seat->seat_row }}{{ $seat->seat_column }}">
                      {{ $seat->seat_column }}
                  </div>
              @endforeach
          @endforeach

      </div>
      <form id="booking-form" action="{{ route('booking.initiate') }}" method="POST">
      @csrf
      <input type="hidden" name="event_id" value="{{ $event->id }}">
      <input type="hidden" name="seat_ids" id="seat-ids-input" value="">

      <div id="booking-bar" class="booking-bar" style="display:none;">
          <span>Selected: <span id="selected-count">0</span> seat(s)</span>
          <button type="submit" class="btn btn-success">Book Selected Seats →</button>
      </div>
  </form>

  @endsection

@push('scripts')
  <script>
  $(document).ready(function () {
      // --- Config passed in from Blade ---
      const EVENT_ID = {{ $event->id }};
      const IS_LOGGED_IN = {{ $booker ? 'true' : 'false' }};
      const CSRF = $('meta[name="csrf-token"]').attr('content');
      const POLL_INTERVAL_MS = 5000; // refresh other users' locks every 5 seconds

      // --- State ---
      // Track seats the current user has selected in THIS browser tab (ids as numbers).
      // Seeded from any seats already rendered as "selected" by the Blade (i.e. locks owned by me).
      let selectedSeats = $('.seat.selected').map(function () {
          return parseInt($(this).data('seat-id'));
      }).get();

      function updateBookingBar() {
          $('#selected-count').text(selectedSeats.length);
          $('#seat-ids-input').val(selectedSeats.join(','));
          $('#booking-bar').toggle(selectedSeats.length > 0);
      }

      // Apply a fresh status to a seat in the DOM. Used both after a click response and after each poll tick.
      function applySeatStatus(seatId, status, mine) {
          const $seat = $('.seat[data-seat-id="' + seatId + '"]');
          if (!$seat.length) return;

          // Preserve a guest's local-only selection: if the server still says the seat is available and the user picked it in this tab, leave it alone.
          // Without this, polling would wipe a guest's selection every 5s because guests never write to the DB until they log in.
          const isLocallySelected = $seat.hasClass('selected');
          if (!IS_LOGGED_IN && isLocallySelected && status === 'available') {
              return;
          }

          $seat.removeClass('available locked booked selected');

          if (mine) {
              // It's locked in the DB by me → show as selected (yellow). Keep "available" so click handler still fires.
              $seat.addClass('available selected');
              if (!selectedSeats.includes(seatId)) selectedSeats.push(seatId);
          } else if (status === 'locked' || status === 'booked') {
              $seat.addClass(status);
              selectedSeats = selectedSeats.filter(id => id !== seatId);
          } else {
              $seat.addClass('available');
              selectedSeats = selectedSeats.filter(id => id !== seatId);
          }
      }

      // --- Click handler ---
      // Delegated so it still works after polling re-applies classes.
      $(document).on('click', '.seat.available', function () {
          const seatId = parseInt($(this).data('seat-id'));
          const $seat = $(this);
          const isCurrentlySelected = $seat.hasClass('selected');

          // Guest users: keep the old client-side-only flow. Submitting the form will redirect them to login.
          if (!IS_LOGGED_IN) {
              if (isCurrentlySelected) {
                  selectedSeats = selectedSeats.filter(id => id !== seatId);
                  $seat.removeClass('selected');
              } else {
                  selectedSeats.push(seatId);
                  $seat.addClass('selected');
              }
              updateBookingBar();
              return;
          }

          // Logged-in user: hit the server so the lock is persisted and visible to other browsers.
          if (isCurrentlySelected) {
              // Deselect → unlock in DB.
              $.ajax({
                  url: '/seats/' + seatId + '/unlock',
                  type: 'DELETE',
                  headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
              }).done(function () {
                  applySeatStatus(seatId, 'available', false);
                  updateBookingBar();
              }).fail(function (xhr) {
                  alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not release seat.');
              });
          } else {
              // Select → lock in DB.
              $.ajax({
                  url: '/seats/' + seatId + '/lock',
                  type: 'POST',
                  headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
              }).done(function () {
                  applySeatStatus(seatId, 'locked', true);
                  updateBookingBar();
              }).fail(function (xhr) {
                  // 409 = someone else grabbed it first. Refresh just this seat so the user sees it as locked.
                  const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Seat not available.';
                  alert(msg);
                  refreshAllSeats();
              });
          }
      });

      // --- Polling ---
      // Every N seconds, ask the server for the current status of every seat and sync the DOM.
      // This is what makes a lock placed in Edge appear in Chrome/incognito without a manual page reload.
      function refreshAllSeats() {
          $.getJSON('/events/' + EVENT_ID + '/seat-statuses').done(function (data) {
              data.seats.forEach(function (s) {
                  applySeatStatus(s.id, s.status, s.mine);
              });
              updateBookingBar();
          });
      }

      setInterval(refreshAllSeats, POLL_INTERVAL_MS);

      // --- Restore-after-login flow (unchanged behavior from before) ---
      @if(session('restore_seats'))
          const restoreIds = "{{ session('restore_seats') }}".split(',').map(Number);
          restoreIds.forEach(function (seatId) {
              const $seat = $('.seat[data-seat-id="' + seatId + '"]');
              if ($seat.hasClass('available') && !$seat.hasClass('selected')) {
                  // Programmatically click to trigger the AJAX lock for each restored seat.
                  $seat.trigger('click');
              }
          });
      @endif

      updateBookingBar();
  });
  </script>
  @endpush
