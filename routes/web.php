<?php

use App\Http\Controllers\Admin\AppointmentStatusController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PublicController;
use App\Http\Middleware\EnsureUserHasProvider;
use App\Models\Service;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/proveedores', [PublicController::class, 'providers'])->name('providers');
Route::get('/p/{provider}', [PublicController::class, 'profile'])->name('providers.show');
Route::get('/reservar/{provider}/{service}', [PublicController::class, 'booking'])
    ->scopeBindings()
    ->name('booking');
Route::post('/reservar/{provider}/{service}', [AppointmentController::class, 'store'])
    ->scopeBindings()
    ->middleware('throttle:booking')
    ->name('booking.store');

Route::middleware('guest')->group(function () {
    Route::get('/iniciar-sesion', [PublicController::class, 'signIn'])->name('sign-in');
    Route::get('/crear-cuenta', [PublicController::class, 'signUp'])->name('sign-up');
});

Route::get('/verificar-correo', [PublicController::class, 'verifyEmail'])
    ->middleware('auth')
    ->name('verification.notice');

// Every write route below carries ->can(...): EnsureUserHasProvider only
// proves *a* provider exists, not that the {service}/{appointment} in the
// URL belongs to it. Ownership is enforced here and nowhere else — see
// ServicePolicy / AppointmentPolicy. The param-less routes (horario, perfil)
// need no policy: their target is always $request->user()->provider.
//
// 'verified' sits between 'auth' and EnsureUserHasProvider: an unverified
// user is redirected to the notice page before anything provider-specific
// even runs.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', EnsureUserHasProvider::class])->group(function () {
    Route::get('/citas', [DashboardController::class, 'citas'])->name('citas');
    Route::patch('/citas/{appointment}/confirmar', [AppointmentStatusController::class, 'confirm'])
        ->can('update', 'appointment')->name('citas.confirm');
    Route::patch('/citas/{appointment}/cancelar', [AppointmentStatusController::class, 'cancel'])
        ->can('update', 'appointment')->name('citas.cancel');

    Route::get('/servicios', [DashboardController::class, 'servicios'])->name('servicios');
    Route::post('/servicios', [ServiceController::class, 'store'])
        ->can('create', Service::class)->name('servicios.store');
    Route::put('/servicios/{service}', [ServiceController::class, 'update'])
        ->can('update', 'service')->name('servicios.update');
    Route::patch('/servicios/{service}/desactivar', [ServiceController::class, 'deactivate'])
        ->can('update', 'service')->name('servicios.deactivate');
    Route::patch('/servicios/{service}/activar', [ServiceController::class, 'activate'])
        ->can('update', 'service')->name('servicios.activate');

    Route::get('/horario', [DashboardController::class, 'horario'])->name('horario');
    Route::put('/horario', [ScheduleController::class, 'update'])->name('horario.update');

    Route::get('/perfil', [DashboardController::class, 'perfil'])->name('perfil');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::patch('/perfil/publicacion', [ProfileController::class, 'updatePublication'])->name('perfil.publication');

    Route::get('/ajustes', [DashboardController::class, 'ajustes'])->name('ajustes');
});
