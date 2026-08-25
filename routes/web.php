<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutSuccessController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// --- Public storefront ---

Volt::route('/', 'storefront.home')->name('home');
Volt::route('/tienda', 'storefront.shop')->name('shop');
Route::get('/producto/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/categoria/{slug}', [CategoryController::class, 'show'])->name('category.show');
Volt::route('/carrito', 'storefront.cart')->name('cart');
Volt::route('/checkout', 'storefront.checkout')->name('checkout');
Route::get('/checkout/exito/{order}', CheckoutSuccessController::class)->name('checkout.success');

// --- Authenticated account area ---

// The customer-facing "account home": their own order history, scoped by
// user_id (see account/orders.blade.php). Route name stays 'dashboard' —
// every post-login/register/verify-email redirect already targets it by
// that name — only what it renders changed, from the generic Breeze
// "You're logged in!" stub to something an actual customer wants.
Volt::route('dashboard', 'account.orders')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('mis-pedidos/{order}', 'account.order-show')
    ->middleware(['auth', 'verified'])
    ->name('pedidos.show');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// --- Admin ---

// No 'role:admin' middleware here on purpose: each admin section is
// gated by its own matching fine-grained permission (checked in every
// Volt component's mount(), via the Gate::before in AppServiceProvider
// that lets 'admin' through everything) — a custom role holding just
// e.g. 'activity.view' can reach /admin/bitacora without being a full
// admin, and the dashboard itself only shows the links that role qualifies
// for.
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/', 'admin.dashboard')->name('dashboard');

    Volt::route('/productos', 'admin.products.index')->name('productos');
    Volt::route('/productos/nuevo', 'admin.products.form')->name('productos.nuevo');
    Volt::route('/productos/destacados', 'admin.products.featured')->name('productos.destacados');
    Volt::route('/productos/papelera', 'admin.products.trash')->name('productos.papelera');
    Volt::route('/productos/{product}', 'admin.products.form')->name('productos.editar');
    Volt::route('/productos/{product}/colores', 'admin.products.colors.index')->name('productos.colores');
    Volt::route('/productos/{product}/colores/nuevo', 'admin.products.colors.form')->name('productos.colores.nuevo');
    Volt::route('/productos/{product}/colores/{color}', 'admin.products.colors.form')->name('productos.colores.editar');

    Volt::route('/categorias', 'admin.categories.index')->name('categorias');
    Volt::route('/categorias/nueva', 'admin.categories.form')->name('categorias.nueva');
    Volt::route('/categorias/destacadas', 'admin.categories.featured')->name('categorias.destacadas');
    Volt::route('/categorias/papelera', 'admin.categories.trash')->name('categorias.papelera');
    Volt::route('/categorias/{category}', 'admin.categories.form')->name('categorias.editar');

    Volt::route('/banners', 'admin.banners.index')->name('banners');
    Volt::route('/banners/nuevo', 'admin.banners.form')->name('banners.nuevo');
    Volt::route('/banners/{banner}', 'admin.banners.form')->name('banners.editar');

    Volt::route('/usuarios', 'admin.users-index')->name('usuarios');
    Volt::route('/usuarios/{user}', 'admin.user-edit')->name('usuarios.editar');

    Volt::route('/pedidos', 'admin.orders.index')->name('pedidos');
    Volt::route('/pedidos/{order}', 'admin.orders.show')->name('pedidos.show');

    Volt::route('/bitacora', 'admin.activity-log')->name('bitacora');

    Volt::route('/roles', 'admin.roles.index')->name('roles');
    Volt::route('/roles/nuevo', 'admin.roles.form')->name('roles.nuevo');
    Volt::route('/roles/{role}', 'admin.roles.form')->name('roles.editar');
});

require __DIR__.'/auth.php';
