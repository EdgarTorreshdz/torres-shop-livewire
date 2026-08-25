<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    // Deliberately NOT using WithoutModelEvents: spatie/laravel-permission
    // busts its permission cache via model 'saved' events, so disabling
    // model events here makes syncPermissions() fail to find permissions
    // that were, in fact, just created in the same run.
    public function run(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedUsers();
        $this->seedCatalog();
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'products.manage',
            'categories.manage',
            'banners.manage',
            'orders.manage',
            'users.manage',
            'activity.view',
            'roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $admin = Role::findOrCreate('admin');
        $admin->syncPermissions($permissions);

        // Customers have no extra permissions — they can browse the public
        // catalog and place orders without any Spatie permission check.
        Role::findOrCreate('customer');
    }

    private function seedUsers(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Torres Shop',
            'email' => 'admin@torresshop.com',
            'password' => bcrypt('admin12345'),
        ]);
        $admin->assignRole('admin');

        $customer = User::factory()->create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
            'password' => bcrypt('cliente12345'),
        ]);
        $customer->assignRole('customer');
    }

    private function seedCatalog(): void
    {
        $catalog = [
            'Electrónica' => [
                ['name' => 'Audífonos Inalámbricos Bluetooth', 'price' => 899, 'stock' => 40],
                ['name' => 'Cargador Portátil 10000mAh', 'price' => 459, 'stock' => 60],
                ['name' => 'Smartwatch Deportivo', 'price' => 1299, 'stock' => 25],
            ],
            'Hogar y Cocina' => [
                ['name' => 'Termo de Acero Inoxidable 750ml', 'price' => 349, 'stock' => 80],
                ['name' => 'Set de Sartenes Antiadherentes', 'price' => 999, 'stock' => 20],
                ['name' => 'Cafetera de Goteo Programable', 'price' => 749, 'stock' => 15],
            ],
            'Deportes y Aire Libre' => [
                ['name' => 'Mochila Urbana Impermeable', 'price' => 649, 'stock' => 35],
                ['name' => 'Botella Deportiva con Filtro', 'price' => 299, 'stock' => 70],
                ['name' => 'Tapete de Yoga Antideslizante', 'price' => 399, 'stock' => 45],
            ],
            'Accesorios' => [
                ['name' => 'Billetera de Piel Genuina', 'price' => 549, 'stock' => 30],
                ['name' => 'Lentes de Sol Polarizados', 'price' => 429, 'stock' => 50],
                ['name' => 'Cinturón de Cuero Reversible', 'price' => 379, 'stock' => 40],
            ],
        ];

        foreach ($catalog as $categoryName => $products) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName]
            );

            foreach ($products as $productData) {
                Product::firstOrCreate(
                    ['slug' => Str::slug($productData['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $productData['name'],
                        'description' => "Producto de la categoría {$categoryName}, disponible en Torres Shop.",
                        'price' => $productData['price'],
                        'stock' => $productData['stock'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
