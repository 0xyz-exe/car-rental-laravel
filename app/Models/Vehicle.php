<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['name', 'category', 'specifications', 'description', 'image_path', 'pickup_location', 'daily_rate', 'active'];
    protected function casts(): array { return ['daily_rate' => 'decimal:2', 'active' => 'boolean']; }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function maintenancePeriods() { return $this->hasMany(MaintenancePeriod::class); }
}
