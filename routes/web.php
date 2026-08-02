<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/proveedores', [PublicController::class, 'providers'])->name('providers');
Route::get('/p/{provider}', [PublicController::class, 'profile'])->name('providers.show');
Route::get('/reservar/{provider}/{service}', [PublicController::class, 'booking'])->name('booking');
Route::get('/iniciar-sesion', [PublicController::class, 'signIn'])->name('sign-in');
Route::get('/crear-cuenta', [PublicController::class, 'signUp'])->name('sign-up');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/citas', [DashboardController::class, 'citas'])->name('citas');
    Route::get('/servicios', [DashboardController::class, 'servicios'])->name('servicios');
    Route::get('/horario', [DashboardController::class, 'horario'])->name('horario');
    Route::get('/perfil', [DashboardController::class, 'perfil'])->name('perfil');
    Route::get('/ajustes', [DashboardController::class, 'ajustes'])->name('ajustes');
});
