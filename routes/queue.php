<?php

use App\Http\Controllers\Queue\GuestQueueController;
use App\Http\Controllers\Queue\QueuePageController;
use App\Http\Controllers\Queue\StaffQueueController;
use App\Http\Middleware\PrivateQueueResponse;
use Illuminate\Support\Facades\Route;

// Inertia pages and small session/CSRF-protected private polling endpoints.
Route::middleware(PrivateQueueResponse::class)->group(function () {
    Route::get('antrean', [QueuePageController::class, 'index'])->block(30, 10)->name('queue.index');
    Route::get('antrean/lokasi/{code}', [QueuePageController::class, 'location'])->block(30, 10)->name('queue.location');
    Route::post('antrean', [GuestQueueController::class, 'store'])->middleware('throttle:queue.create')->block(30, 10)->name('queue.store');
    Route::post('antrean/lokasi/{code}', [GuestQueueController::class, 'storeOnsite'])->middleware('throttle:queue.create')->block(30, 10)->name('queue.onsite.store');
    Route::prefix('antrean/saya/{ticket}')->where(['ticket' => '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}'])->group(function () {
        Route::get('/', [QueuePageController::class, 'show'])->block(30, 10)->name('queue.show');
        Route::post('secret', [GuestQueueController::class, 'secret'])->middleware('throttle:queue.access')->block(30, 10)->name('queue.secret');
        Route::post('secret/ack', [GuestQueueController::class, 'acknowledgeSecret'])->middleware('throttle:queue.access')->block(30, 10)->name('queue.secret.ack');
        Route::post('akses', [GuestQueueController::class, 'exchange'])->middleware('throttle:queue.access')->block(30, 10)->name('queue.access');
        Route::get('status', [GuestQueueController::class, 'status'])->middleware('throttle:queue.status')->block(30, 10)->name('queue.status');
        Route::post('check-in', [GuestQueueController::class, 'checkIn'])->middleware('throttle:queue.guest')->block(30, 10)->name('queue.check-in');
        Route::post('cancel', [GuestQueueController::class, 'cancel'])->middleware('throttle:queue.guest')->block(30, 10)->name('queue.cancel');
    });

    Route::prefix('staff/antrean')->middleware(['auth', 'staff.active', 'throttle:queue.staff'])->group(function () {
        Route::post('barber/{barber}/call', [StaffQueueController::class, 'callNext'])->whereNumber('barber')->block(30, 10)->name('queue.staff.call');
        Route::patch('barber/{barber}/availability', [StaffQueueController::class, 'availability'])->whereNumber('barber')->block(30, 10)->name('queue.staff.availability');
        Route::post('{ticket}/start', [StaffQueueController::class, 'start'])->block(30, 10)->name('queue.staff.start');
        Route::post('{ticket}/complete', [StaffQueueController::class, 'complete'])->block(30, 10)->name('queue.staff.complete');
        Route::post('{ticket}/cancel', [StaffQueueController::class, 'cancel'])->middleware('role:admin')->block(30, 10)->name('queue.staff.cancel');
    });
});
