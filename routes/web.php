<?php

use App\Http\Controllers\RentalController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RentalController::class, 'index'])->name('rentals.index');
Route::redirect('/dashboard', '/')->middleware('auth')->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::post('/reservations', [RentalController::class, 'store'])->name('reservations.store');
    Route::get('/my-reservations', [RentalController::class, 'mine'])->name('rentals.mine');
    Route::patch('/reservations/{reservation}/cancel', [RentalController::class, 'cancel'])->name('reservations.cancel');
    Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
        Route::get('/rentals', [RentalController::class, 'admin'])->name('rentals');
        Route::post('/vehicles', [RentalController::class, 'saveVehicle'])->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', [RentalController::class, 'saveVehicle'])->name('vehicles.update');
        Route::post('/maintenance', [RentalController::class, 'maintenance'])->name('maintenance.store');
        Route::patch('/reservations/{reservation}', [RentalController::class, 'updateReservation'])->name('reservations.update');
        Route::post('/extras', [RentalController::class, 'saveExtra'])->name('extras.store');
        Route::get('/report.csv', [RentalController::class, 'report'])->name('report');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
