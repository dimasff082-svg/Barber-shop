<?php

use App\Http\Controllers\Admin\BusinessSettingsController;
use App\Http\Controllers\Admin\QrCodeController;
use App\Http\Controllers\Admin\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('admin/pengaturan', [BusinessSettingsController::class, 'edit'])->name('admin.settings.edit');
Route::put('admin/pengaturan', [BusinessSettingsController::class, 'update'])->name('admin.settings.update');
Route::get('admin/jadwal', [ScheduleController::class, 'index'])->name('admin.schedules.index');
Route::put('admin/jadwal/jam-buka', [ScheduleController::class, 'hours'])->name('admin.schedules.hours');
Route::put('admin/jadwal/barber/{barber}', [ScheduleController::class, 'barber'])->name('admin.schedules.barber');
Route::post('admin/jadwal/libur', [ScheduleController::class, 'storeClosure'])->name('admin.schedules.closures.store');
Route::delete('admin/jadwal/libur/{closure}', [ScheduleController::class, 'destroyClosure'])->name('admin.schedules.closures.destroy');
Route::get('admin/qr', [QrCodeController::class, 'index'])->name('admin.qr.index');
Route::post('admin/qr', [QrCodeController::class, 'store'])->name('admin.qr.store');
Route::put('admin/qr/{qr}', [QrCodeController::class, 'update'])->name('admin.qr.update');
Route::get('admin/qr/{qr}/print', [QrCodeController::class, 'print'])->name('admin.qr.print');
Route::get('admin/qr/{qr}/image', [QrCodeController::class, 'image'])->name('admin.qr.image');
Route::get('admin/qr/{qr}/download', [QrCodeController::class, 'download'])->name('admin.qr.download');
