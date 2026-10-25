<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('category'); $table->string('specifications')->nullable(); $table->text('description')->nullable();
            $table->string('image_path')->nullable(); $table->string('pickup_location'); $table->decimal('daily_rate', 10, 2); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('rental_extras', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->decimal('daily_rate', 10, 2); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('reservations', function (Blueprint $table) {
            $table->id(); $table->foreignId('vehicle_id')->constrained()->restrictOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at'); $table->dateTime('ends_at'); $table->string('pickup_location'); $table->string('return_location');
            $table->unsignedInteger('billable_days'); $table->decimal('total_price', 10, 2); $table->string('status')->default('confirmed'); $table->timestamps();
            $table->index(['vehicle_id', 'starts_at', 'ends_at', 'status']);
        });
        Schema::create('reservation_extra', function (Blueprint $table) {
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete(); $table->foreignId('rental_extra_id')->constrained()->restrictOnDelete();
            $table->decimal('daily_rate', 10, 2); $table->primary(['reservation_id', 'rental_extra_id']);
        });
        Schema::create('maintenance_periods', function (Blueprint $table) {
            $table->id(); $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete(); $table->dateTime('starts_at'); $table->dateTime('ends_at'); $table->string('note'); $table->timestamps();
            $table->index(['vehicle_id', 'starts_at', 'ends_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('maintenance_periods'); Schema::dropIfExists('reservation_extra'); Schema::dropIfExists('reservations'); Schema::dropIfExists('rental_extras'); Schema::dropIfExists('vehicles');
    }
};
