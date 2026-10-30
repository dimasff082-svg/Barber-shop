<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Barber\ProfileController;
use App\Http\Controllers\Queue\StaffQueueController;
use App\Http\Controllers\Staff\QueueOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'staff.active', 'role:admin'])->group(function () {
    Route::get('admin/antrean', [QueueOperationsController::class, 'index'])->name('admin.queues.index');
    Route::get('admin/antrean/{ticket}', [QueueOperationsController::class, 'show'])->name('admin.queues.show');
    Route::get('admin/laporan', [ReportController::class, 'index'])->name('admin.reports.index');
});
Route::middleware(['auth', 'staff.active', 'role:barber'])->group(function () {
    Route::get('barber/antrean', [QueueOperationsController::class, 'index'])->name('barber.queues.index');
    Route::get('barber/antrean/{ticket}', [QueueOperationsController::class, 'show'])->name('barber.queues.show');
    Route::get('barber/riwayat', [QueueOperationsController::class, 'history'])->name('barber.history');
    Route::get('barber/profil', [ProfileController::class, 'show'])->name('barber.profile');
});
Route::post('staff/antrean/{ticket}/missed', [StaffQueueController::class, 'missed'])
    ->middleware(['auth', 'staff.active', 'throttle:queue.staff'])->block(30, 10)->name('queue.staff.missed');
