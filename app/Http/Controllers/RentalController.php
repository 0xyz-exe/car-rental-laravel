<?php

namespace App\Http\Controllers;

use App\Models\MaintenancePeriod;
use App\Models\RentalExtra;
use App\Models\Reservation;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['starts_at' => 'nullable|date|after:now', 'ends_at' => 'nullable|date|after:starts_at', 'category' => 'nullable|string|max:80']);
        $vehicles = Vehicle::query()->where('active', true)->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when(isset($data['starts_at'], $data['ends_at']), function ($q) use ($data) {
                $q->whereDoesntHave('reservations', fn ($r) => $r->where('status', 'confirmed')->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']))
                    ->whereDoesntHave('maintenancePeriods', fn ($m) => $m->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']));
            })->orderBy('name')->get();
        return view('rentals.index', ['vehicles' => $vehicles, 'extras' => RentalExtra::where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id', 'starts_at' => 'required|date|after:now', 'ends_at' => 'required|date|after:starts_at',
            'pickup_location' => 'required|string|max:160', 'return_location' => 'required|string|max:160',
            'extras' => 'nullable|array', 'extras.*' => 'integer|distinct|exists:rental_extras,id',
        ]);
        $start = Carbon::parse($data['starts_at']); $end = Carbon::parse($data['ends_at']);
        $days = max(1, (int) ceil($start->diffInSeconds($end) / 86400));
        $reservation = DB::transaction(function () use ($request, $data, $start, $end, $days) {
            $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();
            if (!$vehicle->active || $vehicle->reservations()->where('status', 'confirmed')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()
                || $vehicle->maintenancePeriods()->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['vehicle_id' => 'Vehicle is unavailable for the selected period.']);
            }
            $extras = RentalExtra::whereIn('id', $data['extras'] ?? [])->where('active', true)->get();
            if ($extras->count() !== count($data['extras'] ?? [])) throw ValidationException::withMessages(['extras' => 'One or more selected extras are unavailable.']);
            $total = round(($vehicle->daily_rate + $extras->sum('daily_rate')) * $days, 2);
            $booking = Reservation::create([
                'vehicle_id' => $vehicle->id, 'user_id' => $request->user()->id, 'starts_at' => $start, 'ends_at' => $end,
                'pickup_location' => $data['pickup_location'], 'return_location' => $data['return_location'], 'billable_days' => $days,
                'total_price' => $total, 'status' => 'confirmed',
            ]);
            foreach ($extras as $extra) $booking->extras()->attach($extra->id, ['daily_rate' => $extra->daily_rate]);
            return $booking;
        });
        return redirect()->route('rentals.mine')->with('status', 'Reservation confirmed.');
    }

    public function mine(Request $request)
    {
        return view('rentals.mine', ['reservations' => $request->user()->reservations()->with('vehicle', 'extras')->latest()->get()]);
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);
        abort_unless($reservation->status === 'confirmed' && $reservation->starts_at->isFuture(), 422);
        $reservation->update(['status' => 'cancelled']);
        return back()->with('status', 'Reservation cancelled.');
    }

    public function admin(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        return view('rentals.admin', ['vehicles' => Vehicle::withCount(['reservations as confirmed_reservations' => fn ($q) => $q->where('status', 'confirmed')])->orderBy('name')->get(), 'reservations' => Reservation::with(['vehicle', 'user'])->latest()->get(), 'maintenance' => MaintenancePeriod::with('vehicle')->latest()->get(), 'extras' => RentalExtra::orderBy('name')->get()]);
    }

    public function saveVehicle(Request $request, ?Vehicle $vehicle = null)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'category' => 'required|string|max:80', 'specifications' => 'nullable|string|max:255', 'description' => 'nullable|string|max:3000', 'pickup_location' => 'required|string|max:160', 'daily_rate' => 'required|numeric|min:0|max:999999', 'image' => 'nullable|image|max:2048']);
        if ($request->hasFile('image')) $data['image_path'] = $request->file('image')->store('vehicles', 'public');
        unset($data['image']);
        if ($vehicle?->exists) $vehicle->update($data); else Vehicle::create($data);
        return back()->with('status', 'Vehicle saved.');
    }

    public function maintenance(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['vehicle_id' => 'required|exists:vehicles,id', 'starts_at' => 'required|date|after:now', 'ends_at' => 'required|date|after:starts_at', 'note' => 'required|string|max:500']);
        DB::transaction(function () use ($data) {
            $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();
            if ($vehicle->reservations()->where('status', 'confirmed')->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists()
                || $vehicle->maintenancePeriods()->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'Period conflicts with an existing reservation or maintenance.']);
            }
            MaintenancePeriod::create($data);
        });
        return back()->with('status', 'Maintenance period added.');
    }

    public function saveExtra(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'daily_rate' => 'required|numeric|min:0|max:999999']);
        RentalExtra::create($data);
        return back()->with('status', 'Extra saved.');
    }

    public function updateReservation(Request $request, Reservation $reservation)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['status' => 'required|in:confirmed,cancelled,completed']);
        if ($data['status'] === 'confirmed' && $reservation->status !== 'confirmed') {
            DB::transaction(function () use ($reservation) {
                $vehicle = Vehicle::whereKey($reservation->vehicle_id)->lockForUpdate()->firstOrFail();
                if ($vehicle->reservations()->where('status', 'confirmed')->where('id', '!=', $reservation->id)->where('starts_at', '<', $reservation->ends_at)->where('ends_at', '>', $reservation->starts_at)->exists()
                    || $vehicle->maintenancePeriods()->where('starts_at', '<', $reservation->ends_at)->where('ends_at', '>', $reservation->starts_at)->exists()) {
                    throw ValidationException::withMessages(['status' => 'Vehicle conflicts with another reservation or maintenance.']);
                }
                $reservation->update(['status' => 'confirmed']);
            });
        } else {
            $reservation->update($data);
        }
        return back()->with('status', 'Reservation status updated.');
    }

    public function report(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $rows = Reservation::with('vehicle')->where('status', 'confirmed')->when($data['from'] ?? null, fn ($q, $d) => $q->where('starts_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('starts_at', '<=', Carbon::parse($d)->endOfDay()))->orderBy('starts_at')->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w'); fputcsv($out, ['reservation_id', 'vehicle', 'start', 'end', 'status', 'price']);
            foreach ($rows as $row) fputcsv($out, [$row->id, $row->vehicle->name, $row->starts_at->toIso8601String(), $row->ends_at->toIso8601String(), $row->status, $row->total_price]); fclose($out);
        }, 'rental-report.csv', ['Content-Type' => 'text/csv']);
    }
}
