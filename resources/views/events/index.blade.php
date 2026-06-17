  @extends('layouts.app')

  @section('title', 'All Events')

  @section('content')

   @auth('admin')
   @endauth
      <h1>Upcoming Events</h1>

      @if($events->isEmpty())
          <p>No events available yet. Click "Create Event" to add one.</p>
      @else
          <div class="events-list">
              @foreach($events as $event)
                  <div class="event-card">
                      <h3>{{ $event->event_name }}</h3>
                      <p><strong>Venue:</strong> {{ $event->event_venue }}</p>
                      <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($event->event_date)->format('d M Y, h:i A') }}</p>
                      <p><strong>Seats:</strong> {{ $event->total_rows * $event->total_columns }} total</p>
                      <a href="{{ route('events.show', $event->id) }}" class="btn btn-success">View Seats</a>
                  </div>
              @endforeach
          </div>
      @endif

  @endsection