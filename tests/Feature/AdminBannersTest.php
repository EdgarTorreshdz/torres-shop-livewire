<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBannersTest extends TestCase
{
    use RefreshDatabase;

    private function seedRolesAndPermissions(): void
    {
        foreach ([
            'products.manage', 'categories.manage', 'banners.manage', 'orders.manage',
            'users.manage', 'activity.view', 'roles.manage',
        ] as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findOrCreate('admin')->syncPermissions(Permission::pluck('name'));
        Role::findOrCreate('customer');
    }

    private function admin(): User
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function customer(): User
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    public function test_a_customer_is_forbidden_from_the_banners_admin_page(): void
    {
        $this->actingAs($this->customer());

        $this->get('/admin/banners')->assertForbidden();
    }

    public function test_an_admin_can_create_a_banner_and_it_is_logged_with_new_values(): void
    {
        $this->actingAs($this->admin());

        Volt::test('admin.banners.form')
            ->set('title', 'Venta de Verano')
            ->set('description', 'Hasta 50% de descuento')
            ->set('url', '/tienda')
            ->set('is_active', true)
            ->set('sort_order', 1)
            ->call('save')
            ->assertRedirect(route('admin.banners'));

        $banner = Banner::where('title', 'Venta de Verano')->first();
        $this->assertNotNull($banner);
        $this->assertTrue($banner->is_active);
        $this->assertSame('/tienda', $banner->url);

        $log = ActivityLog::where('action', 'banner.created')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->old_values);
        $this->assertSame('Venta de Verano', $log->new_values['title']);
    }

    public function test_updating_a_banner_records_both_old_and_new_values(): void
    {
        $this->actingAs($this->admin());
        $banner = Banner::factory()->create(['title' => 'Título Original']);

        Volt::test('admin.banners.form', ['banner' => $banner])
            ->set('title', 'Título Actualizado')
            ->set('url', $banner->url)
            ->call('save');

        $log = ActivityLog::where('action', 'banner.updated')->first();
        $this->assertSame('Título Original', $log->old_values['title']);
        $this->assertSame('Título Actualizado', $log->new_values['title']);
        $this->assertSame('Título Actualizado', $banner->fresh()->title);
    }

    public function test_toggling_a_banner_flips_is_active_and_logs_it(): void
    {
        $this->actingAs($this->admin());
        $banner = Banner::factory()->create(['is_active' => false]);

        Volt::test('admin.banners.index')->call('toggleActive', $banner->id);

        $this->assertTrue($banner->fresh()->is_active);
        $this->assertNotNull(ActivityLog::where('action', 'banner.updated')->first());
    }

    public function test_deleting_a_banner_records_old_values_with_no_new_values(): void
    {
        $this->actingAs($this->admin());
        $banner = Banner::factory()->create(['title' => 'Banner a Eliminar']);

        Volt::test('admin.banners.index')->call('delete', $banner->id);

        $log = ActivityLog::where('action', 'banner.deleted')->first();
        $this->assertSame('Banner a Eliminar', $log->old_values['title']);
        $this->assertNull($log->new_values);
        $this->assertNull(Banner::find($banner->id));
    }

    public function test_only_active_banners_with_at_least_one_image_are_shown_on_the_home_page_in_sort_order(): void
    {
        // Distinct fake paths (not real files — Home::with() only checks
        // the DB column, and this test only asserts on rendered text) so
        // each banner counts as "has an image" for the home query's filter.
        Banner::factory()->create(['title' => 'Inactivo', 'is_active' => false, 'sort_order' => 0, 'desktop_image_path' => 'banners/1/fake.jpg']);
        Banner::factory()->create(['title' => 'Sin Imagen', 'is_active' => true, 'sort_order' => 0]);
        Banner::factory()->create(['title' => 'Segundo', 'is_active' => true, 'sort_order' => 2, 'desktop_image_path' => 'banners/2/fake.jpg']);
        Banner::factory()->create(['title' => 'Primero', 'is_active' => true, 'sort_order' => 1, 'desktop_image_path' => 'banners/3/fake.jpg']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeInOrder(['Primero', 'Segundo']);
        $response->assertDontSee('Inactivo');
        $response->assertDontSee('Sin Imagen');
    }
}
