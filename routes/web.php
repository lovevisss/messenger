<?php

use App\Http\Controllers\Admin\NotificationConsoleController;
use App\Http\Middleware\EnsureNotificationAdmin;
use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('docs/email', fn () => Inertia::render('docs/Email', [
    'apiUrl' => url('/api/v1/notifications'),
]))->name('docs.email');

Route::get('docs/sms', fn () => Inertia::render('docs/Sms', [
    'apiUrl' => url('/api/v1/notifications'),
]))->name('docs.sms');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $isAdmin = (bool) $request->user()->is_admin;

        return Inertia::render('Dashboard', [
            'canManageNotifications' => $isAdmin,
            'channelStatus' => [
                'email' => config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password')),
                'sms' => collect(['ec_name', 'ap_id', 'secret_key', 'sign'])->every(fn ($key) => filled(config('notifications.sms.'.$key))),
                'portal' => filled(config('notifications.portal.base_url')) && filled(config('notifications.portal.app_id'))
                    && (filled(config('notifications.portal.poa_token')) || (filled(config('notifications.portal.client_id')) && filled(config('notifications.portal.client_secret')))),
            ],
            'channelCounts' => $isAdmin
                ? NotificationDelivery::query()->selectRaw('channel, count(*) as total')->groupBy('channel')->pluck('total', 'channel')
                : [],
        ]);
    })->name('dashboard');
});

Route::middleware(['auth', EnsureNotificationAdmin::class])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationConsoleController::class, 'index'])->name('index');
    Route::get('/{notification}', [NotificationConsoleController::class, 'show'])->name('show');
    Route::post('/clients', [NotificationConsoleController::class, 'createClient'])->name('clients.store');
    Route::post('/clients/{client}/rotate', [NotificationConsoleController::class, 'rotateClient'])->name('clients.rotate');
    Route::post('/clients/{client}/toggle', [NotificationConsoleController::class, 'toggleClient'])->name('clients.toggle');
});

require __DIR__.'/settings.php';
