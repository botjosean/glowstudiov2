<?php

use App\Http\Controllers\Admin\AppointmentStatusController;
use App\Http\Controllers\Admin\BioSuggestionController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManualAppointmentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProfilePhotoController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\InitialPasswordController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SuperadminController;
use App\Http\Middleware\EnsureUserHasPassword;
use App\Http\Middleware\EnsureUserHasProvider;
use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Service;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::inertia('/terminos', 'Public/Terminos')->name('terms');
Route::inertia('/privacidad', 'Public/Privacidad')->name('privacy');
Route::get('/proveedores', [PublicController::class, 'providers'])->name('providers');
// Los enlaces viejos siguen vivos: los que las profesionales ya mandaron por
// WhatsApp y los publicados en glowstudios.vip apuntan aquí. 301 permanente
// para que buscadores y clientes de chat aprendan la forma nueva.
Route::get('/p/{provider:slug}', fn (string $provider) => redirect()->route('providers.show', $provider, 301))
    ->name('providers.show.legacy');
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

    // Named exactly 'password.request' / 'password.reset': Fortify itself
    // would register views under these names if config('fortify.views')
    // were true, and Illuminate's default ResetPassword notification builds
    // its email link from route('password.reset', ...) regardless of that
    // setting. The POST targets (password.email / password.update) are
    // Fortify's own — see FortifyServiceProvider for their responses.
    Route::get('/olvide-contrasena', [PublicController::class, 'forgotPassword'])->name('password.request');
    Route::get('/restablecer-contrasena/{token}', [PublicController::class, 'resetPassword'])->name('password.reset');

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
    Route::get('/citas/horas', [ManualAppointmentController::class, 'slots'])->name('citas.slots');
    Route::post('/citas', [ManualAppointmentController::class, 'store'])->name('citas.store');

    Route::get('/clientes', [ClientController::class, 'index'])->name('clientes');
    Route::post('/clientes', [ClientController::class, 'store'])
        ->can('create', Client::class)->name('clientes.store');
    Route::get('/clientes/{client}', [ClientController::class, 'show'])
        ->can('view', 'client')->name('clientes.show');
    Route::put('/clientes/{client}', [ClientController::class, 'update'])
        ->can('update', 'client')->name('clientes.update');
    Route::delete('/clientes/{client}', [ClientController::class, 'destroy'])
        ->can('delete', 'client')->name('clientes.destroy');

    Route::get('/ventas', [SalesController::class, 'index'])->name('ventas');
    Route::post('/ventas', [SalesController::class, 'store'])
        ->can('create', Sale::class)->name('ventas.store');
    // PUT before the {sale} route so 'metodos' can never be captured as an id.
    Route::put('/ventas/metodos', [SalesController::class, 'updateMethods'])->name('ventas.metodos');
    Route::delete('/ventas/{sale}', [SalesController::class, 'destroy'])
        ->can('delete', 'sale')->name('ventas.destroy');

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
    // Time off carries an {id} but no ->can(): the controller resolves it
    // through the caller's own provider, so another provider's block is a 404
    // rather than a 403 and cannot be probed for.
    Route::post('/horario/parar', [ScheduleController::class, 'pause'])->name('horario.parar');
    Route::post('/horario/ausencias', [ScheduleController::class, 'storeTimeOff'])->name('horario.ausencias.store');
    Route::delete('/horario/ausencias/{timeOff}', [ScheduleController::class, 'destroyTimeOff'])
        ->whereNumber('timeOff')->name('horario.ausencias.destroy');

    Route::get('/perfil', [DashboardController::class, 'perfil'])->name('perfil');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('perfil.update');
    // Throttled because every call costs a model request: a suggestion is a
    // convenience, not something worth letting one account hammer.
    Route::post('/perfil/biografia', BioSuggestionController::class)
        ->middleware('throttle:10,1')->name('perfil.biografia');
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

// General administration (the owner's panel, not a provider's): guarded by
// the SUPERADMIN_USERNAMES allowlist — for everyone else these routes 404.
Route::prefix('admin-general')->middleware(['auth', 'verified', EnsureUserIsSuperadmin::class])->group(function () {
    Route::get('/', [SuperadminController::class, 'index'])->name('superadmin.index');
    Route::post('/limpiar', [SuperadminController::class, 'wipe'])->name('superadmin.wipe');
    Route::delete('/citas/{appointment}', [SuperadminController::class, 'destroyAppointment'])
        ->name('superadmin.appointments.destroy');
});

// AL FINAL, siempre. Un perfil vive en la raíz (/pati), así que este comodín
// captura todo lo que ninguna ruta anterior reclamó. Registrado último, una
// ruta real nunca puede quedar tapada por un nombre de usuario; ReservedSlugs
// impide además que alguien se registre con uno de esos nombres y termine con
// un perfil inalcanzable.
Route::get('/{provider}', [PublicController::class, 'profile'])->name('providers.show');
