<?php

use App\Http\Controllers\Admin\BarberController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\ServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'staff.active', 'role:admin'])->group(function () {
    Route::get('barbers', [BarberController::class, 'index'])->name('admin.barbers.index');
    Route::post('barbers', [BarberController::class, 'store'])->name('admin.barbers.store');
    Route::put('barbers/{barber}', [BarberController::class, 'update'])->name('admin.barbers.update');
    Route::post('barbers/{barber}/archive', [BarberController::class, 'archive'])->name('admin.barbers.archive');
    Route::get('layanan', [ServiceController::class, 'index'])->name('admin.services.index');
    Route::post('layanan', [ServiceController::class, 'store'])->name('admin.services.store');
    Route::put('layanan/{service}', [ServiceController::class, 'update'])->name('admin.services.update');
    Route::post('layanan/{service}/archive', [ServiceController::class, 'archive'])->name('admin.services.archive');
    Route::get('galeri', [GalleryController::class, 'index'])->name('admin.gallery.index');
    Route::post('galeri', [GalleryController::class, 'store'])->name('admin.gallery.store');
    Route::put('galeri/{gallery}', [GalleryController::class, 'update'])->name('admin.gallery.update');
    Route::post('galeri/{gallery}/archive', [GalleryController::class, 'archive'])->name('admin.gallery.archive');
});
