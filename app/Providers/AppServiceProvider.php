<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Abilities used inside pages and forms (page access by role is handled by the "role" route middleware).
     * Use them with Gate::allows('give-discount') in PHP and @can('give-discount') in Blade.
     */
    public function boot(): void
    {
        // Take an amount off a guest's bill
        Gate::define('give-discount', fn (User $user) => $user->hasRole(User::ADMIN, User::MANAGER));

        // Give money back to a guest
        Gate::define('record-refund', fn (User $user) => $user->hasRole(User::ADMIN, User::MANAGER, User::ACCOUNTANT));

        // Delete a finished (checked-out) stay: it is part of the hotel's accounts
        Gate::define('delete-finished-stay', fn (User $user) => $user->isAdmin());
    }
}
