<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ResponsiveImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResponsiveImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach ([
            'products.manage', 'categories.manage', 'banners.manage', 'orders.manage',
            'users.manage', 'activity.view', 'roles.manage',
        ] as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findOrCreate('admin')->syncPermissions(Permission::pluck('name'));

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_storing_an_image_generates_the_original_plus_every_responsive_variant(): void
    {
        Storage::fake('public');

        // store() (like UploadedFile::store()) picks a random filename, not
        // "photo.jpg" — the variant names have to be derived from whatever
        // path actually comes back, same as application code does.
        $path = ResponsiveImage::store(
            UploadedFile::fake()->image('photo.jpg', 2000, 2000),
            'test-images',
        );
        $base = pathinfo($path, PATHINFO_FILENAME);

        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists("test-images/{$base}-sm.webp");
        Storage::disk('public')->assertExists("test-images/{$base}-md.webp");
        Storage::disk('public')->assertExists("test-images/{$base}-lg.webp");
    }

    public function test_srcset_returns_a_url_per_generated_variant(): void
    {
        Storage::fake('public');

        $path = ResponsiveImage::store(
            UploadedFile::fake()->image('photo.jpg', 2000, 2000),
            'test-images',
        );

        $srcset = ResponsiveImage::srcset($path);

        $this->assertNotNull($srcset);
        $this->assertStringContainsString('480w', $srcset);
        $this->assertStringContainsString('768w', $srcset);
        $this->assertStringContainsString('1200w', $srcset);
        $this->assertCount(3, explode(', ', $srcset));
    }

    public function test_srcset_is_null_for_a_missing_path(): void
    {
        $this->assertNull(ResponsiveImage::srcset(null));
    }

    public function test_srcset_is_null_when_no_variants_exist_on_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('legacy/old-photo.jpg', 'fake-bytes');

        // Simulates an image stored before ResponsiveImage existed: only
        // the original file is on disk, no -sm/-md/-lg.webp next to it.
        $this->assertNull(ResponsiveImage::srcset('legacy/old-photo.jpg'));
    }

    public function test_delete_removes_the_original_and_every_variant(): void
    {
        Storage::fake('public');

        $path = ResponsiveImage::store(
            UploadedFile::fake()->image('photo.jpg', 2000, 2000),
            'test-images',
        );

        ResponsiveImage::delete($path);

        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertMissing('test-images/photo-sm.webp');
        Storage::disk('public')->assertMissing('test-images/photo-md.webp');
        Storage::disk('public')->assertMissing('test-images/photo-lg.webp');
    }

    public function test_delete_is_a_no_op_for_a_null_path(): void
    {
        Storage::fake('public');

        ResponsiveImage::delete(null);

        $this->assertTrue(true); // just asserting it didn't throw
    }

    public function test_uploading_a_product_image_through_the_admin_form_generates_responsive_variants(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $product = Product::factory()->create();

        Volt::test('admin.products.form', ['product' => $product])
            ->set('newImages', [UploadedFile::fake()->image('product.jpg', 1600, 1600)])
            ->call('save');

        $image = $product->fresh()->images->first();
        $this->assertNotNull($image);
        $this->assertNotNull($image->srcset);
        $this->assertStringContainsString('480w', $image->srcset);
    }

    public function test_uploading_a_category_banner_generates_responsive_variants(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Volt::test('admin.categories.form')
            ->set('name', 'Con Imagen Responsiva')
            ->set('bannerImage', UploadedFile::fake()->image('banner.jpg', 1600, 500))
            ->call('save');

        $category = Category::where('name', 'Con Imagen Responsiva')->firstOrFail();
        $this->assertNotNull($category->banner_srcset);
        $this->assertStringContainsString('1200w', $category->banner_srcset);
    }

    public function test_uploading_the_three_banner_images_generates_responsive_variants_for_each(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Volt::test('admin.banners.form')
            ->set('title', 'Banner Con Imágenes')
            ->set('url', '/tienda')
            ->set('desktopImage', UploadedFile::fake()->image('desktop.jpg', 1920, 600))
            ->set('tabletImage', UploadedFile::fake()->image('tablet.jpg', 1200, 900))
            ->set('mobileImage', UploadedFile::fake()->image('mobile.jpg', 800, 1000))
            ->call('save');

        $banner = Banner::where('title', 'Banner Con Imágenes')->firstOrFail();
        $this->assertNotNull($banner->desktop_srcset);
        $this->assertNotNull($banner->tablet_srcset);
        $this->assertNotNull($banner->mobile_srcset);
        $this->assertStringContainsString('1200w', $banner->desktop_srcset);
    }
}
