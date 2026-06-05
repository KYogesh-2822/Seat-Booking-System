<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';
    protected $fillable = [
          'user_id',
          'booker_type',
          'seat_id',
          'event_id',
          'booked_at',

          'total_amount',
          'currency',
          'payment_gateway',
          'payment_status',
          'stripe_checkout_session_id',
          'stripe_payment_intent_id',
          'platform_fee_amount',
          'vendor_amount',
          'paid_at',
          'stripe_payload',
    ];
   

    protected $casts = [
        'booked_at' => 'datetime',
        'paid_at' => 'datetime',
        'stripe_payload' => 'array',
    ];


    // this booking belog to which user
     public function user()
      {
          return $this->belongsTo(User::class);
      }

    //   this booking tell the seat to that user
      public function seat()
      {
          return $this->belongsTo(Seat::class);
      }

    //   here tell that this booking belog to which event
      public function event()
      {
          return $this->belongsTo(Event::class);
      }
}
