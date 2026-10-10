<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BreakfastController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PaymentAccountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomImportController;
use App\Http\Controllers\RoomSetupController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/password', [AccountController::class, 'update'])->name('account.password');

    // Room board: everyone can see it
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::patch('/rooms/{room}', [RoomController::class, 'update'])
        ->middleware('role:admin,manager,receptionist')->name('rooms.update');

    // Front desk
    Route::middleware('role:admin,manager,receptionist,accountant')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])
            ->whereNumber('reservation')->name('reservations.show');
        Route::get('/reservations/{reservation}/invoice', [ReservationController::class, 'invoice'])->name('reservations.invoice');
        Route::post('/reservations/{reservation}/payments', [PaymentController::class, 'store'])->name('payments.store');

        Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
        Route::get('/guests/{guest}', [GuestController::class, 'show'])->whereNumber('guest')->name('guests.show');
        Route::get('/breakfast', BreakfastController::class)->name('breakfast');
        Route::get('/my-activity', [ReportController::class, 'mine'])->name('my-activity');
        Route::get('/reservations/{reservation}/credit-invoice', [ReservationController::class, 'creditInvoice'])->name('reservations.credit-invoice');

        // Organisations: what they owe on credit, statements and payment request letters
        Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->whereNumber('organization')->name('organizations.show');
        Route::get('/organizations/{organization}/payment-request', [OrganizationController::class, 'paymentRequest'])->name('organizations.payment-request');
        Route::post('/organizations/{organization}/payments', [OrganizationController::class, 'receivePayment'])->name('organizations.payments');

        Route::get('/guests/{guest}/id/{side}', [GuestController::class, 'idPhoto'])->whereIn('side', ['front', 'back'])->name('guests.id-photo');
    });

    Route::middleware('role:admin,manager,receptionist')->group(function () {
        Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/{reservation}/edit', [ReservationController::class, 'edit'])->name('reservations.edit');
        Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
        Route::post('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->name('reservations.check-in');
        Route::post('/reservations/{reservation}/check-out', [ReservationController::class, 'checkOut'])->name('reservations.check-out');
        Route::post('/reservations/{reservation}/rooms/{room}/move', [ReservationController::class, 'moveRoom'])->name('reservations.move-room');
        Route::post('/reservations/{reservation}/half-day', [ReservationController::class, 'halfDay'])->name('reservations.half-day');
        Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::post('/reservations/{reservation}/charges', [ChargeController::class, 'store'])->name('charges.store');

        Route::get('/guests/create', [GuestController::class, 'create'])->name('guests.create');
        Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
        Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
        Route::put('/guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
        Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');
    });

    // Deleting a booking made by mistake: managers; restoring one: admin
    Route::middleware('role:admin,manager')->group(function () {
        Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
        Route::get('/reservations/deleted', [ReservationController::class, 'deleted'])->name('reservations.deleted');
    });
    Route::post('/reservations/deleted/{id}/restore', [ReservationController::class, 'restore'])
        ->middleware('role:admin')->whereNumber('id')->name('reservations.restore');

    // Adding organisations and deciding who may pay on credit: managers
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
        Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
        Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
    });

    // Only managers can remove a line from a bill
    Route::delete('/reservations/{reservation}/charges/{charge}', [ChargeController::class, 'destroy'])
        ->middleware('role:admin,manager')->name('charges.destroy');

    // Reports
    Route::middleware('role:admin,manager,accountant')->prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/daily', [ReportController::class, 'daily'])->name('reports.daily');
        Route::get('/staff', [ReportController::class, 'staff'])->name('reports.staff');
    });

    // Setup: rooms, prices, services, hotel details
    Route::middleware('role:admin,manager')->prefix('setup')->group(function () {
        Route::get('/rooms', [RoomTypeController::class, 'index'])->name('setup.rooms');
        Route::get('/room-types/create', [RoomTypeController::class, 'create'])->name('room-types.create');
        Route::post('/room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
        Route::get('/room-types/{roomType}/edit', [RoomTypeController::class, 'edit'])->name('room-types.edit');
        Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update');
        Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');

        Route::get('/rooms/import', [RoomImportController::class, 'create'])->name('room-import.create');
        Route::post('/rooms/import/preview', [RoomImportController::class, 'preview'])->name('room-import.preview');
        Route::post('/rooms/import', [RoomImportController::class, 'store'])->name('room-import.store');
        Route::get('/rooms/import/template', [RoomImportController::class, 'template'])->name('room-import.template');
        Route::get('/rooms/create', [RoomSetupController::class, 'create'])->name('room-setup.create');
        Route::post('/rooms', [RoomSetupController::class, 'store'])->name('room-setup.store');
        Route::get('/rooms/{room}/edit', [RoomSetupController::class, 'edit'])->name('room-setup.edit');
        Route::put('/rooms/{room}', [RoomSetupController::class, 'update'])->name('room-setup.update');
        Route::delete('/rooms/{room}', [RoomSetupController::class, 'destroy'])->name('room-setup.destroy');

        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    // Staff accounts and hotel settings: admin only
    Route::middleware('role:admin')->prefix('setup')->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/accounts', [PaymentAccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/create', [PaymentAccountController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [PaymentAccountController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}', [PaymentAccountController::class, 'show'])->whereNumber('account')->name('accounts.show');
        Route::get('/accounts/{account}/edit', [PaymentAccountController::class, 'edit'])->name('accounts.edit');
        Route::put('/accounts/{account}', [PaymentAccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [PaymentAccountController::class, 'destroy'])->name('accounts.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
