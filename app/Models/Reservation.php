<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['vehicle_id', 'user_id', 'starts_at', 'ends_at', 'pickup_location', 'return_location', 'billable_days', 'total_price', 'status'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'total_price' => 'decimal:2']; }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function extras() { return $this->belongsToMany(RentalExtra::class, 'reservation_extra', 'reservation_id', 'rental_extra_id')->withPivot('daily_rate'); }
    public function user() { return $this->belongsTo(User::class); }
}
