<?php

use App\Livewire\ActivarRol;
use App\Livewire\Dashboard;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'portal-inicio')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/roles/activar/{rol}', ActivarRol::class)->name('roles.activar');
    Route::prefix('pwa')->name('pwa.')->group(function (): void {
        Route::get('/configuracion', [PushSubscriptionController::class, 'configuration'])
            ->name('configuration');
        Route::post('/suscripciones', [PushSubscriptionController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('subscriptions.store');
        Route::delete('/suscripciones', [PushSubscriptionController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('subscriptions.destroy');
    });
});

require __DIR__.'/settings.php';
