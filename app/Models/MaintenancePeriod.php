<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenancePeriod extends Model
{
    protected $fillable = ['vehicle_id', 'starts_at', 'ends_at', 'note'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
}
