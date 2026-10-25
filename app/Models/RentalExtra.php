<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalExtra extends Model
{
    protected $fillable = ['name', 'daily_rate', 'active'];
    protected function casts(): array { return ['daily_rate' => 'decimal:2', 'active' => 'boolean']; }
}
