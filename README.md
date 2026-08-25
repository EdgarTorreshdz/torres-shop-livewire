# torres-shop-livewire

[![CI](https://github.com/EdgarTorreshdz/torres-shop-livewire/actions/workflows/ci.yml/badge.svg)](https://github.com/EdgarTorreshdz/torres-shop-livewire/actions/workflows/ci.yml)

Demo de ecommerce ("Torres Shop") — mismo negocio que
[`torres-shop`](https://github.com/EdgarTorreshdz/torres-shop) +
[`torres-shop-api`](https://github.com/EdgarTorreshdz/torres-shop-api) (Astro + Laravel API
separados), pero construido como **un solo monolito Laravel** (Blade + Livewire/Volt, sesiones,
sin API JSON) a partir de
[`template-laravel-monolith`](https://github.com/EdgarTorreshdz/template-laravel-monolith).
Pieza de portafolio: mismo problema, dos arquitecturas — ver el README de `template-laravel-monolith`
para la comparación completa de por qué existe esta segunda versión.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

Editar `.env` con las credenciales reales de SQL Server, o dejar `DB_USERNAME`/`DB_PASSWORD`
**completamente ausentes** para Windows Authentication contra una instancia local — ver el README
de `template-laravel-monolith` para el detalle completo, es el mismo setup.

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

`php artisan migrate:fresh --seed` regresa la base a este mismo estado limpio en cualquier momento.

## Arquitectura

Todo en un solo request/response ciclo de Laravel — nada de Sanctum, tokens, CORS, ni dos
servidores de dev con puertos que sincronizar:

- **Catálogo y checkout** (`/`, `/tienda`, `/producto/{slug}`, `/categoria/{slug}`, `/carrito`,
  `/checkout`) — Blade + Livewire/Volt.
- **`/producto/{slug}` y `/categoria/{slug}` son controllers + vistas Blade planas, no
  Livewire/Volt.** Necesitan un `<title>`/`<meta description>` real por producto/categoría en la
  primera respuesta del servidor (SEO), y el atributo `#[Layout(...)]` de Livewire solo acepta
  parámetros que sean constantes en tiempo de compilación — no puede recibir
  `$this->product->meta_title`. La única parte interactiva de esas páginas (cantidad + agregar al
  carrito) es un componente Livewire embebido (`App\Livewire\AddToCart`), no la página completa.
- **Carrito en sesión**, no en base de datos (`App\Services\Cart`, `session('cart')` =
  `[product_id => quantity]`) — no requiere cuenta, se resuelve del todo con el mismo mecanismo de
  sesión que ya trae Laravel.
- **Checkout**: mismo principio que la versión API (`torres-shop-api`) — precio/stock siempre se
  recalculan desde la base de datos, nunca se confía en lo que traiga el carrito de sesión, y
  `lockForUpdate()` dentro de una transacción evita sobrevender el último inventario si dos
  personas compran al mismo tiempo.
- **Admin** (`/admin/*`): cada sección (productos, categorías, usuarios, pedidos, bitácora, roles)
  es un componente Volt de página completa, protegido en su propio `mount()` vía
  `abort_unless(auth()->user()->can('permiso.especifico'), 403)`. `admin` pasa todas las
  verificaciones automáticamente vía un `Gate::before` en `AppServiceProvider` — no hay que repetir
  `hasRole('admin') || can(...)` en cada sección.
- Búsqueda + paginación en las listas de admin usan `Livewire\WithPagination` nativo — no existe el
  componente `DataTable` en JS que se escribió a mano para la versión Astro.
- Confirmaciones destructivas (`wire:confirm`) son nativas de Livewire 3 — no hay un `confirm()`
  de JS ni un modal casero.

## Roles, permisos y bitácora

Igual que `torres-shop-api`, pero con `spatie/laravel-permission` sobre un único guard `web`
(no hay guard `sanctum` con el que desalinearse):

- Roles: `admin`, `customer`, más los que se creen desde `/admin/roles`. `admin`/`customer`
  protegidos contra renombrar/eliminar; `admin` siempre conserva todos los permisos.
- Permisos: `products.manage`, `categories.manage`, `orders.manage`, `users.manage`,
  `activity.view`, `roles.manage`.
- `activity_logs` (`App\Models\ActivityLog`) registra cada acción de escritura del admin con
  `old_values`/`new_values` (snapshot completo antes/después, vía `ActivityLog::snapshot($model)`)
  — la contraseña de un usuario nunca aparece ahí, ni hasheada. `/admin/bitacora` muestra el diff
  campo por campo con un `<details>` nativo, mostrando solo los campos que de verdad cambiaron en
  una actualización.
- `/admin/bitacora` y `/admin/roles` son accesibles para `admin` **o** para cualquier rol que
  tenga el permiso puntual (`activity.view`/`roles.manage`) — no solo para admin. El nav de la
  cuenta (`resources/views/livewire/layout/navigation.blade.php`) filtra los links según ese mismo
  criterio, así un usuario con un permiso suelto no ve enlaces a secciones a las que de todos
  modos rebotaría con 403.

## Imágenes de producto

Igual que `torres-shop-api`: un producto tiene muchas imágenes (`product_images`), subidas vía
`Livewire\WithFileUploads` y guardadas en el disco `public` (local hoy, config-only para migrar a
S3/R2/Spaces más adelante).

## Usuarios de prueba (seed)

| Email | Password | Rol |
| --- | --- | --- |
| `admin@torresshop.com` | `admin12345` | admin |
| `cliente@example.com` | `cliente12345` | customer |

## Tests

`php artisan test` — 48 tests. Cubre: catálogo público solo muestra productos activos, filtro por
categoría, meta tags reales por producto (`/producto/{slug}` en una petición HTTP real, no solo el
componente), agregar al carrito actualiza la sesión, checkout calcula el total desde la base de
datos y descuenta stock, checkout falla limpiamente sin inventario suficiente, un customer recibe
403 en `/admin/productos`, CRUD de productos/categorías registra `old_values`/`new_values`
correctos en la bitácora, CRUD de roles respeta la protección de `admin`/`customer`, un rol
personalizado con solo `activity.view` o `roles.manage` accede a su sección sin ser admin,
cambiar el rol de un usuario queda registrado con el rol anterior y el nuevo.

Verificado también end-to-end contra SQL Server real: catálogo, meta tags reales en la respuesta
cruda (`curl`, no solo el DOM), agregar al carrito, checkout completo (con confirmación de pedido
y stock descontado en la base), bitácora con diffs reales, creación de un rol personalizado y
verificación de que un usuario con ese rol ve solo la sección permitida en el nav y recibe 403 real
al intentar entrar a una sección fuera de su permiso.
