<?php

namespace Tests\Feature;

use App\Models\MaintenancePeriod;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(): Vehicle
    {
        return Vehicle::create(['name' => 'City Car', 'category' => 'Compact', 'pickup_location' => 'Central', 'daily_rate' => 50, 'active' => true]);
    }

    public function test_customer_can_book_and_server_calculates_price_and_cancellation_is_scoped(): void
    {
        $vehicle = $this->vehicle();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($customer)->post(route('reservations.store'), [
            'vehicle_id' => $vehicle->id, 'starts_at' => now()->addDays(2)->startOfDay()->toDateTimeString(),
            'ends_at' => now()->addDays(4)->startOfDay()->toDateTimeString(), 'pickup_location' => 'Central', 'return_location' => 'Airport', 'total_price' => 1,
        ])->assertRedirect(route('rentals.mine'));
        $reservation = Reservation::firstOrFail();
        $this->assertSame('100.00', $reservation->total_price);
        $this->actingAs($other)->patch(route('reservations.cancel', $reservation))->assertForbidden();
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->actingAs($customer)->patch(route('reservations.cancel', $reservation))->assertRedirect();
        $this->assertSame('cancelled', $reservation->fresh()->status);
    }

    public function test_overlapping_reservations_and_maintenance_are_rejected(): void
    {
        $vehicle = $this->vehicle();
        $customer = User::factory()->create();
        $starts = now()->addDays(2)->startOfDay(); $ends = $starts->copy()->addDays(2);
        Reservation::create(['vehicle_id' => $vehicle->id, 'user_id' => User::factory()->create()->id, 'starts_at' => $starts, 'ends_at' => $ends, 'pickup_location' => 'A', 'return_location' => 'A', 'billable_days' => 2, 'total_price' => 100, 'status' => 'confirmed']);
        $payload = ['vehicle_id' => $vehicle->id, 'starts_at' => $starts->copy()->addDay()->toDateTimeString(), 'ends_at' => $ends->copy()->addDay()->toDateTimeString(), 'pickup_location' => 'A', 'return_location' => 'A'];
        $this->actingAs($customer)->post(route('reservations.store'), $payload)->assertSessionHasErrors('vehicle_id');
        $vehicle->reservations()->update(['status' => 'cancelled']);
        MaintenancePeriod::create(['vehicle_id' => $vehicle->id, 'starts_at' => $starts, 'ends_at' => $ends, 'note' => 'Inspection']);
        $this->actingAs($customer)->post(route('reservations.store'), $payload)->assertSessionHasErrors('vehicle_id');
    }

    public function test_invalid_dates_and_admin_routes_are_rejected(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.rentals'))->assertForbidden();
        $vehicle = $this->vehicle();
        $this->actingAs($customer)->post(route('reservations.store'), ['vehicle_id' => $vehicle->id, 'starts_at' => now()->subDay()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(), 'pickup_location' => 'A', 'return_location' => 'A'])->assertSessionHasErrors(['starts_at']);
    }
}
