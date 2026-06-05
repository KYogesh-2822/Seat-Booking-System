<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'events';
    protected $fillable = [
          'admin_id',
          'event_name',
          'event_venue',
          'event_date',
          'ticket_price',
          'currency',
          'total_rows',
          'total_columns',
      ];


      public function vendor()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    //   an event has all seats for that event 

      public function seats(){
        return $this->hasMany(Seat::class);
      }

    //   an event can have multiple booking at a time 
    
      public function bookings(){
        return $this->hasMany(Booking::class);
      }
}
