<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $table = 'admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'stripe_account_id',
        'stripe_details_submitted',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
        'stripe_onboarded_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

     protected function casts(): array
    {
        return [
            'password' => 'hashed',

            'stripe_details_submitted' => 'boolean',
            'stripe_charges_enabled' => 'boolean',
            'stripe_payouts_enabled' => 'boolean',
            'stripe_onboarded_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isVendor(): bool
    {
        return $this->role === 'vendor';
    }

    public function hasStripeAccount(): bool
    {
        return !empty($this->stripe_account_id);
    }

    public function isStripeReady(): bool
    {
        return $this->stripe_account_id && $this->stripe_charges_enabled && $this->stripe_payouts_enabled;
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'admin_id');
    }
}
