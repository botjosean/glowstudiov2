<?php

use App\Http\Controllers\Admin\AppointmentStatusController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProfilePhotoController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\InitialPasswordController;
use App\Http\Controllers\PublicController;
use App\Http\Middleware\EnsureUserHasPassword;
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

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::get('/verificar-correo', [PublicController::class, 'verifyEmail'])
    ->middleware('auth')
    ->name('verification.notice');

// Not under 'admin': a user who just signed up via Google and hasn't set a
// password yet still needs somewhere to land, and EnsureUserHasPassword
// would just bounce them back here in a loop if it guarded its own target.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/crear-contrasena', [PublicController::class, 'createPassword'])->name('password.create');
    Route::put('/crear-contrasena', [InitialPasswordController::class, 'update'])->name('password.create.store');
});

// Every write route below carries ->can(...): EnsureUserHasProvider only
// proves *a* provider exists, not that the {service}/{appointment} in the
// URL belongs to it. Ownership is enforced here and nowhere else — see
// ServicePolicy / AppointmentPolicy. The param-less routes (horario, perfil)
// need no policy: their target is always $request->user()->provider.
//
// 'verified' sits between 'auth' and EnsureUserHasProvider: an unverified
// user is redirected to the notice page before anything provider-specific
// even runs.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', EnsureUserHasPassword::class, EnsureUserHasProvider::class])->group(function () {
    Route::get('/inicio', [DashboardController::class, 'inicio'])->name('inicio');

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

    // POST, not PUT/PATCH: Inertia can't send files over PUT/PATCH — it
    // converts them to FormData and PHP won't parse the body. A transport
    // constraint, not a style choice.
    Route::middleware('throttle:uploads')->group(function () {
        Route::post('/perfil/avatar', [ProfilePhotoController::class, 'storeAvatar'])->name('perfil.avatar');
        Route::post('/perfil/portada', [ProfilePhotoController::class, 'storeBanner'])->name('perfil.banner');
        Route::post('/perfil/galeria', [ProfilePhotoController::class, 'storeGalleryPhoto'])->name('perfil.gallery.store');
        Route::delete('/perfil/galeria/{photo}', [ProfilePhotoController::class, 'destroyGalleryPhoto'])
            ->can('delete', 'photo')->name('perfil.gallery.destroy');
    });

    Route::get('/ajustes', [DashboardController::class, 'ajustes'])->name('ajustes');
});
