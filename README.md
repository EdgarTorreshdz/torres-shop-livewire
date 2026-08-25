# template-laravel-monolith

[![CI](https://github.com/EdgarTorreshdz/template-laravel-monolith/actions/workflows/ci.yml/badge.svg)](https://github.com/EdgarTorreshdz/template-laravel-monolith/actions/workflows/ci.yml)

Template base para proyectos que **no** necesitan un frontend separado: Laravel + Blade +
[Livewire](https://livewire.laravel.com)/[Volt](https://livewire.laravel.com/docs/volt) + Alpine,
todo en un solo repo, un solo deploy, sesiones de Laravel de toda la vida (sin API JSON, sin
Sanctum, sin CORS). Ver [`astro-template`](../astro-template) +
[`template-laravel-api`](../template-laravel-api) si el proyecto sí necesita frontend y backend
separados (varios consumidores, equipos distintos, etc.) — la mayoría de proyectos de un solo
sitio con panel admin no lo necesitan, y este template existe para esos casos.

## Por qué un monolito en vez de API + frontend separados

Construimos primero el otro patrón (`astro-template` + `template-laravel-api`, ver
[`torres-shop`](https://github.com/EdgarTorreshdz/torres-shop) como ejemplo) y varios de los bugs
reales que encontramos ahí son **síntomas directos de la separación**, no de la lógica de negocio:
mismatch de puerto entre los dos servidores de dev, CORS, un guard de Sanctum (`sanctum`) que no
coincidía con el guard bajo el que se sembraron los permisos (`web`), validación duplicada
(reglas en el controller Y en el JS del formulario), SEO que requiere `prerender = false`
página por página en vez de venir gratis por defecto. Todo eso desaparece con un monolito:

- Sesiones de Laravel (`auth`/`guest` middleware) en vez de tokens Bearer + `localStorage`.
- Un solo guard (`web`) — no hay un segundo guard con el que un rol/permiso pueda desalinearse.
- HTML renderizado en el servidor por defecto — SEO no requiere ningún truco especial.
- Un solo repo, un solo `.env`, un solo deploy.

## Requisitos

- PHP 8.3+, Composer, Node 20+
- **SQL Server** + los drivers de Microsoft para PHP (`sqlsrv` y `pdo_sqlsrv`) — ver la sección
  de instalación en el README de [`template-laravel-api`](../template-laravel-api) para el
  detalle completo, es el mismo requisito.

## Setup por proyecto nuevo

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

Editar en `.env` las credenciales reales de SQL Server (`DB_HOST`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD`) — o dejar `DB_USERNAME`/`DB_PASSWORD` **completamente ausentes**
para autenticación de Windows contra una instancia local (ver "Conexión local con Windows Auth"
abajo).

```bash
php artisan migrate --seed
php artisan serve
npm run dev   # en otra terminal, para hot-reload de Blade/CSS/JS mientras desarrollas
```

### Conexión local con Windows Auth (sin login SQL)

```env
DB_CONNECTION=sqlsrv
DB_HOST=(local)\SQLEXPRESS
DB_PORT=
DB_DATABASE=laravel
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=true
```

- `DB_HOST` usa el nombre de instancia, no una IP con puerto; `DB_PORT` debe quedar **vacío**.
- `DB_USERNAME`/`DB_PASSWORD` deben quedar **completamente ausentes** del `.env` (ni siquiera
  `DB_USERNAME=` vacío) — así es como `pdo_sqlsrv` decide usar Windows Integrated Authentication
  en vez de intentar un login SQL. `config/database.php` ya está preparado para esto (sin
  fallback a `root`/`''`).
- La base debe existir de antemano: `sqlcmd -S "(local)\SQLEXPRESS" -E -Q "CREATE DATABASE laravel"`.

## Qué trae de fábrica

- **Auth completa** vía [Laravel Breeze (stack Livewire)](https://laravel.com/docs/starter-kits#breeze-and-livewire):
  registro, login, "olvidé mi contraseña", verificación de email, perfil (nombre/email/password,
  eliminar cuenta) — todo Blade + Volt, sin una sola línea de JS de más.
- **Roles y permisos** vía [spatie/laravel-permission](https://spatie.be/docs/laravel-permission):
  `App\Models\User` ya trae el trait `HasRoles`, los middleware `role`/`permission`/
  `role_or_permission` ya están registrados en `bootstrap/app.php` (no vienen de fábrica en
  Laravel 11+, hay que darlos de alta a mano tras instalar el paquete — ya está hecho aquí).
- **Un solo guard.** A diferencia de la API+Sanctum (`web` para seeders/consola vs. `sanctum`
  dentro de una request autenticada), aquí solo existe `web` — no hay una segunda superficie con
  la que un rol/permiso pueda desalinearse por accidente.
- **`DatabaseSeeder` sin `WithoutModelEvents`** a propósito: ese trait (que Laravel incluye por
  defecto en el stub) rompe el cache de permisos de Spatie, que se invalida escuchando el evento
  `saved` de `Role`/`Permission`. Con el trait activo, los roles se crean en la base pero el
  cache nunca se entera — el porqué está documentado directamente en el archivo.
- **Un panel admin de referencia** (`/admin/usuarios`, protegido con middleware `role:admin`,
  componente Volt en
  [`resources/views/livewire/admin/users-index.blade.php`](resources/views/livewire/admin/users-index.blade.php)):
  búsqueda + paginación usando `Livewire\WithPagination` — nativo, sin escribir una clase de
  tabla en JS ni una API JSON aparte. Proyectos concretos reemplazan/extienden este único ejemplo
  con sus propias secciones (productos, pedidos, lo que el proyecto necesite), siguiendo el mismo
  patrón: middleware `role:`/`permission:` en la ruta, componente Volt, listo.

## Tests

`php artisan test` — corre contra sqlite en memoria (ver `phpunit.xml`), no necesita el driver
`sqlsrv` ni una instancia real de SQL Server. Incluye `AdminAccessTest` como referencia de cómo
probar una sección protegida por rol: un invitado es redirigido a `/login`, un usuario sin el rol
`admin` recibe 403, un admin ve la lista y la búsqueda filtra correctamente (probado directo
sobre el componente Volt con `Livewire\Volt\Volt::test()`, sin necesidad de un navegador real).
