<?php

namespace App\Providers;

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
     */
    public function boot(): void
    {
        // 'admin' always passes every permission check (`$user->can('...')`),
        // the same way the old API+Sanctum split's RoleController forced
        // 'admin' to always keep every permission — one place instead of
        // repeating `hasRole('admin') || can(...)` in every admin section.
        Gate::before(fn ($user, string $ability) => $user->hasRole('admin') ? true : null);
    }
}
