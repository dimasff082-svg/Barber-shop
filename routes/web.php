<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Middleware\StaffPrivacy;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('layanan', [PublicSiteController::class, 'services'])->name('public.services');
Route::get('barber', [PublicSiteController::class, 'barbers'])->name('public.barbers');
Route::get('galeri', [PublicSiteController::class, 'gallery'])->name('public.gallery');
Route::get('kontak', [PublicSiteController::class, 'contact'])->name('public.contact');

Route::middleware(['auth', 'staff.active', StaffPrivacy::class])->group(function () {
    Route::get('dashboard', [StaffDashboardController::class, 'redirect'])->name('dashboard');

    Route::get('admin/dashboard', [StaffDashboardController::class, 'admin'])
        ->middleware('role:admin')->name('admin.dashboard');

    Route::get('barber/dashboard', [StaffDashboardController::class, 'barber'])
        ->middleware('role:barber')->name('barber.dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/queue.php';

Route::middleware(['auth', 'staff.active', 'role:admin', StaffPrivacy::class])->group(function () {
    require __DIR__.'/admin-catalogs.php';
    require __DIR__.'/admin-business.php';
    Route::get('admin/audit', [AuditController::class, 'index'])->name('admin.audit');
});
Route::middleware(StaffPrivacy::class)->group(function () {
    require __DIR__.'/operations.php';
});
