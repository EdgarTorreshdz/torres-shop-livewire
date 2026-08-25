<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

// Deliberately NOT `use WithoutModelEvents` here (Laravel's default seeder
// stub includes it to speed up seeding): spatie/laravel-permission
// invalidates its permission cache by listening for the `saved` event on
// the Role/Permission models. With that trait active, roles get created in
// the database but the cache never finds out, and syncPermissions() /
// hasRole() checks fail claiming a role "doesn't exist" even though it's
// sitting right there in the table.
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Single guard ('web', session-based) for this whole app — unlike
        // the API+Sanctum split this template replaces, there's no second
        // guard to accidentally seed roles/permissions under.
        Role::findOrCreate('admin');

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ])->assignRole('admin');

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
