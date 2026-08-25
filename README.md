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

## Imágenes de producto y categoría

Un producto tiene muchas imágenes (`product_images`); una categoría tiene un banner de escritorio
y uno móvil (`banner_image_path`/`mobile_image_path`). Ambos usan subida real de archivo vía
`Livewire\WithFileUploads`, guardados en el disco `public` (local hoy, config-only para migrar a
S3/R2/Spaces más adelante) — el modelo `Category` expone `banner_image_url`/`mobile_image_url`
como accessors computados a partir del path, así que nada que ya usara esos nombres tuvo que
cambiar. (Los campos de categoría empezaron como URL de texto plano al portar este proyecto desde
`torres-shop`/`torres-shop-api` — donde sí quedaron así por alcance limitado — y se corrigieron
acá para subir el archivo real, igual que ya funcionaba en productos.)

### Imágenes responsivas (menos megas en el cliente)

Cada imagen subida (`App\Services\ResponsiveImage`, vía [`intervention/image`](https://image.intervention.io)
+ el driver GD) genera, además del archivo original, tres copias en WebP a distintos anchos —
480px, 768px y 1200px — guardadas junto al original con un sufijo (`foto.jpg` →
`foto-sm.webp`/`foto-md.webp`/`foto-lg.webp`). No hay columna nueva en la base para esto: las
rutas de las variantes se derivan del path ya guardado por convención, así que cualquier modelo
que ya guarde un path (`ProductImage::path`, `Category::banner_image_path`/`mobile_image_path`)
obtiene variantes responsivas gratis con solo llamar `ResponsiveImage::srcset($path)`.

- **`<x-responsive-image>`** (usado en el home, la tienda, la ficha de categoría, la ficha de
  producto y los carruseles) renderiza `<img src srcset sizes loading>` — es el **navegador**, no
  el servidor, quien decide cuál de las variantes descargar según su viewport real y densidad de
  píxeles. `sizes` lo define cada vista según su propio layout (una grilla de 3 columnas necesita
  un valor distinto al de una foto a todo el ancho), y `loading="lazy"` es el default —
  `eager` solo se usa en la imagen principal de la ficha de producto, la única realmente visible
  sin hacer scroll.
- `ResponsiveImage::srcset()` **verifica que la variante exista en disco** antes de incluirla —
  nunca apunta a un archivo que no está ahí (por ejemplo, una imagen subida antes de que esta
  clase existiera, que solo tendría el archivo original).
- `ResponsiveImage::delete($path)` reemplaza cualquier `Storage::delete($path)` suelto en todo el
  proyecto — sin esto, cada eliminación (reemplazar un banner, borrar una imagen de producto,
  eliminar permanentemente un producto/categoría desde la papelera) dejaría las 3 variantes
  huérfanas en disco para siempre, ya que la base de datos solo conoce el path del original.

## Datos de mercadería del producto (SKU, color, material, precios, costo)

`products` ganó cinco columnas nuevas, todas opcionales (`nullable`, un producto existente no
necesitó que se le llenaran de inmediato):

- **`sku`** — código de referencia interno, único. No es lo mismo que `slug` (que sale del nombre
  para la URL pública) — dos productos pueden llamarse distinto pero compartir intención de SKU
  por error, de ahí la restricción `unique`.
- **`material`** — texto libre (ej. `"Algodón 100%"`), aplica al producto completo. `color` empezó
  igual (texto libre) pero se reemplazó por una tabla real de variantes — ver la sección de abajo
  — así que esa columna ya no existe.
- **`wholesale_price`** (precio mayoreo) y **`cost`** (costo de producción) — **nunca se muestran
  en ninguna vista pública**, solo en `/admin/productos`. `price` (sin cambios) sigue siendo el
  "precio menudeo" — el que ya se usaba en toda la tienda/carrito/checkout — así que no hubo que
  tocar ningún lugar que ya dependiera de esa columna.
- **Margen calculado** (`Product::margin_amount`/`margin_percent`, accessors) — `price - cost` y su
  porcentaje, `null` cuando `cost` no está capturado (no `0`, para no confundir "no sé el costo"
  con "no hay margen"). El formulario de admin muestra una vista previa en vivo del margen
  mientras se escribe precio/costo, vía un método `#[Computed]` de Livewire.

**Bug real encontrado al construir esto:** un `->unique()` normal en una columna nullable se rompe
en SQL Server en cuanto hay más de una fila con `NULL` — a diferencia de SQLite/MySQL/Postgres (que
tratan cada `NULL` como distinto de cualquier otro `NULL` para efectos de unicidad), SQL Server por
defecto sí los considera duplicados entre sí. Pasó de verdad al correr la migración contra los 12
productos ya sembrados sin SKU: `CREATE UNIQUE INDEX` falló con "duplicate key value is
(<NULL>)". La migración ahora detecta el driver (`DB::connection()->getDriverName()`) y en sqlsrv
crea un índice único **filtrado** (`... where sku is not null`) en vez de uno normal; en cualquier
otro driver (incluyendo sqlite, que es lo que usan los tests) usa el `$table->unique()` de
siempre, que ya funciona bien con múltiples `NULL`.

**Otro real, mismo origen que el de las páginas de auth:** la regla `Rule::unique()` que valida el
SKU es la primera vez que este proyecto usa esa regla en un form de admin (categorías/productos ya
dedupicaban su `slug` con un loop manual, `uniqueSlug()`, no con `unique`), así que fue la primera
vez que un mensaje de validación nuevo cayó en el fallback en inglés de Laravel (sin `lang/` propio
en este proyecto — ver la sección de Auth más abajo). Se le pasó un mensaje en español directo como
segundo argumento de `$this->validate()`. El resto de mensajes de validación del admin (los que ya
existían antes de esto — "The price field must be at least 0.", etc.) siguen en inglés; es un
problema real pero mucho más grande, ya separado como una tarea aparte.

## Colores de producto con imagen y precio propios (variantes estilo Nike)

El campo `color` de texto libre (de la sección anterior) se reemplazó por una variante real:
`product_colors` (`id`, `product_id`, `name`, `hex` opcional, `price` opcional, `sort_order`), y
`product_images` ganó una `product_color_id` opcional — así que un color puede tener su propia
**galería completa de fotos** (no solo una imagen) y su propio **precio** (si se deja vacío, usa
el del producto — `ProductColor::effective_price`).

> **El stock ya no vive aquí.** Cuando después se agregaron las tallas, el inventario se movió a
> la matriz color × talla (`product_variants`) — ver la sección siguiente. `ProductColor` conserva
> un accesor `total_stock` que suma sus variantes, pero la columna `stock` que tenía se eliminó
> para no dejar dos números que pudieran contradecirse.

- **Admin** (`/admin/productos/{id}/colores`, mismo permiso `products.manage`): CRUD de colores
  con el mismo patrón de imágenes que ya usan productos (`WithFileUploads` + `ResponsiveImage`,
  cada foto con sus 3 variantes WebP). Sin papelera — igual que banners, un color es una parte
  poseída de un producto, sin nada más que lo referencie. `Product::margin_amount` y compañía
  (costo/precio mayoreo) siguen siendo por producto, no por color — no se pidió que variaran por
  color y hubiera sido alcance extra sin necesidad real.
- **Tienda**: en la ficha del producto, los swatches (miniatura de la primera foto del color, o un
  punto de su `hex` si todavía no tiene fotos) cambian la foto principal, la galería de miniaturas
  y el precio mostrado **sin recargar la página** — todo en Alpine puro con los datos de cada color
  ya embebidos en la página (`@js(...)`), no una llamada a servidor por cada clic. El componente
  `<livewire:add-to-cart>` (que sí necesita saber el color elegido para validar stock/precio en el
  servidor) se mantiene sincronizado vía el helper global `Livewire.dispatch('color-selected', ...)`
  desde Alpine, capturado por `#[On('color-selected')]` en `App\Livewire\AddToCart` — funciona sin
  importar que las dos piezas vivan en sitios distintos del DOM (la galería es Blade+Alpine plano,
  fuera del componente Livewire).
- **Un color sin foto no se ve roto.** El swatch tiene tres formas, no una con un respaldo a
  medias: foto si el color tiene galería, punto sólido si tiene `hex`, y si no tiene ninguno de
  los dos, un **chip con el nombre completo** ("Rojo"). El respaldo original metía las primeras 3
  letras en un círculo gris ("Roj"), que se leía como una imagen que no cargó — de hecho fue
  reportado como bug ("no se ve la imagen del primer color") cuando en realidad el color
  simplemente nunca tuvo foto. Para que eso sea evidente del lado del admin, el listado de colores
  ahora marca esas filas con una etiqueta **"Sin fotos"**.

**Tres bugs reales encontrados al construir esto** (además del de SQL Server con `NULL` en índices
únicos, ya cubierto arriba):

1. **SQL Server rechazó la FK `product_images.product_color_id → product_colors` con cascada**:
   *"Introducing FOREIGN KEY constraint ... may cause cycles or multiple cascade paths"* — porque
   `product_images` ya cascadea desde `products` vía `product_id` (toda fila de imagen, sea de un
   color o no, conserva su `product_id`), y SQL Server no permite una segunda ruta de cascada hacia
   la misma tabla a través de `product_colors`. La FK se dejó sin cascada (`NO ACTION`), y limpiar
   las imágenes de un color al eliminarlo (archivos **y** filas) se volvió responsabilidad explícita
   del código — igual que ya pasaba con los archivos en disco al eliminar permanentemente un
   producto, solo que ahora también las filas.
2. **Eloquent no refleja un default de columna a nivel de base de datos en el modelo recién
   creado.** `product_colors.sort_order` tiene `->default(0)` en la migración, pero
   `$product->colors()->create(['name' => 'Rojo'])` (sin pasar `sort_order`) deja la
   instancia en memoria con `sort_order === null` hasta que se refresca — el formulario de edición,
   que arranca de esa instancia, terminaba mandando `sort_order = ''` y la validación `required`
   tronaba. Encontrado escribiendo `ProductColorsTest`, no en la app real (el formulario de "Nuevo
   color" siempre manda `sort_order` explícito), pero se corrigió en la factory de pruebas para que
   nadie más lo repita.
3. **`Str::plural('color', $n)` pluraliza en inglés** — muestra *"(2 colors)"* en el listado de
   admin en vez de *"(2 colores)"*, porque el inflector de Doctrine que usa por debajo no depende
   del locale de la app. Mismo problema de raíz que el de las páginas de auth (`LoginForm`, etc.):
   un helper de Laravel que resuelve texto en inglés sin que este proyecto lo note, porque nunca
   configuró nada de i18n. Reemplazado por un ternario simple.

## Tallas y la matriz de inventario color × talla

Las tallas se agregaron como una **segunda dimensión de variante**, y con ellas el inventario dejó
de vivir en el color:

- **`sizes`** — catálogo **global** (`name` único, `sort_order`), administrado en `/admin/tallas`.
  Un producto no escribe sus tallas: elige cuáles de este catálogo le aplican. Evita que `"M"`,
  `"m"` y `"Mediana"` convivan como tres tallas distintas y deja abierta la posibilidad de filtrar
  por talla en la tienda. Gated por `products.manage` en vez de un permiso propio — es plomería de
  catálogo para productos, no una sección que alguien vaya a tener suelta.
- **`product_variants`** (`product_id`, `product_color_id` nullable, `size_id` nullable, `stock`) —
  una fila por combinación realmente vendible, y **el único lugar donde vive el stock** en cuanto
  un producto tiene variantes. Ambas dimensiones son nullable, así que un producto puede tener solo
  colores (`size_id` null), solo tallas (`product_color_id` null), ambas, o ninguna (sin variantes,
  usando `products.stock` como siempre).

**Por qué una matriz y no dos listas independientes:** con stock por color *y* stock por talla por
separado, "Rojo tiene 10" y "M tiene 5" no responden cuántos Rojo/M quedan, y el checkout tendría
que decidir arbitrariamente de cuál descontar. Fue una decisión consultada explícitamente antes de
construirla, no una suposición.

- **Admin** (`/admin/productos/{id}/variantes`): una rejilla con los colores como filas y las
  tallas como columnas, un input de stock por celda. Marcar una talla **materializa** una variante
  por color; desmarcarla borra esas filas (con su stock — por eso el botón pide confirmación).
  Las tallas de un producto no se guardan en un pivote `product_size`: **son** las tallas distintas
  entre sus variantes (`Product::available_sizes`), para no tener dos lugares donde el mismo hecho
  pueda discrepar.
- **Agregar el primer color a un producto que ya tenía stock sin color no lo pierde**: sus filas
  sin color se le **traspasan** a ese color en vez de duplicarse, porque ese stock ya era "todo
  este producto". Si no, quedaría bajo una fila "Sin color" que la matriz ya no dibuja.
- **Tienda**: los swatches de color siguen siendo Alpine (cambian la foto sin ir al servidor), pero
  el **selector de talla vive dentro de `<livewire:add-to-cart>`** — una talla no cambia la foto, y
  lo que sí cambia (qué variante, y por lo tanto el tope de stock) es un dato del servidor. Las
  tallas agotadas **en el color seleccionado** se muestran tachadas y deshabilitadas en vez de
  desaparecer, y al cambiar de color se re-elige automáticamente la primera talla con stock en ese
  color.
- **Carrito**: cada línea es `"{product_id}:{variant_id}"` (`Cart::lineKey()`) — el mismo producto
  en Rojo/M y en Rojo/L son dos líneas distintas. `Cart::add()` cambió de firma otra vez
  (`add($productId, $colorId, $quantity)` → `add($productId, $variantId, $quantity)`).
- **Checkout**: bloquea (`lockForUpdate()`) y descuenta el stock de la **variante**, no del
  producto ni del color. `OrderItem` ganó `size_name` junto a `color_name` (ambos snapshots de
  texto, sin FK, para que renombrar o borrar una talla no reescriba pedidos ya hechos) y un accesor
  `variant_label` que arma `"Rojo / M"` en las tres pantallas que listan pedidos.

**Gotcha de SQL Server, otra vez el mismo:** `product_variants` no puede cascadear desde
`product_colors` ni desde `sizes` — ya cascadea desde `products` vía `product_id`, y SQL Server
solo admite una ruta de cascada hacia cada tabla. Borrar un color o una talla limpia sus variantes
en código de aplicación, exactamente como ya se hacía con las imágenes de un color.

**Y un detalle de índices al revés que el del SKU:** la unicidad de `(product_id,
product_color_id, size_id)` **no** se declaró en la base. Con columnas nullable los drivers no se
ponen de acuerdo en qué es un duplicado — SQL Server trata `NULL = NULL` (sí la aplicaría),
mientras sqlite/mysql/pgsql tratan cada `NULL` como distinto (no la aplicaría). Como el admin
*genera* la matriz en vez de dejar escribir combinaciones arbitrarias, la unicidad se garantiza ahí
y en la base solo queda un índice normal para las búsquedas.

## Categorías destacadas y productos seleccionados

Dos listas curadas a mano desde el admin, ambas implementadas igual: una columna
`featured_order` (nullable) en la tabla — `null` = no aparece en la lista curada, un número = su
posición ahí. Sin tabla pivote aparte, sin relación con fecha/temporada real: es simplemente qué
categorías/productos existentes se destacan hoy, y en qué orden.

- **`/admin/categorias/destacadas`** (permiso `categories.manage`): elige qué categorías aparecen
  en el dropdown "Categorías" del header de la tienda (`components/storefront-shell.blade.php`) y
  en qué orden. Las categorías no destacadas siguen apareciendo en el grid del home
  (`Category::has('products')`, sin cambios) — el dropdown del header es la única vista que
  filtra por `featured_order`.
- **`/admin/productos/destacados`** (permiso `products.manage`): elige qué productos aparecen (1)
  en la sección "Productos destacados" del home — con fallback a los 6 más recientes si todavía no
  se ha curado nada — y (2) en el bloque "Productos seleccionados" de la ficha de cada producto.
- La ficha de producto (`/producto/{slug}`) agrega dos carruseles debajo de la info principal,
  ambos vía el componente reutilizable `<x-product-carousel>` (scroll horizontal con
  `snap-x`/`snap-mandatory` de Tailwind, sin librería de JS): "Productos de la misma categoría"
  (mismo `category_id`, excluyendo el producto actual) y "Productos seleccionados" (la lista
  curada de arriba).
- Ambas pantallas registran su cambio en la bitácora (`categories.featured_updated`/
  `products.featured_updated`) con la lista completa de nombres antes/después.

## Banners dinámicos del home

`/admin/banners` (permiso `banners.manage`): CRUD de banners promocionales para el carrusel del
home, sin tabla de curación aparte — cada fila ya trae todo lo que necesita:

- **Título, descripción y URL destino** (interna como `/tienda`/`/categoria/electronica`, o
  externa completa) — se muestran sobre la imagen y son el link de todo el slide.
- **Tres imágenes independientes** (`desktop_image_path`/`tablet_image_path`/`mobile_image_path`),
  no tres tamaños de una sola foto: a diferencia de `ResponsiveImage::srcset()` (que genera copias
  más chicas de la *misma* imagen para ahorrar banda), un banner de escritorio y uno de mobile
  suelen necesitar un encuadre distinto de la misma promoción (texto/producto en otro lugar de la
  composición), así que el admin sube un archivo por breakpoint. Cada uno igual pasa por
  `ResponsiveImage::store()`, así que también obtiene sus propias variantes WebP por densidad de
  píxeles dentro de su propio breakpoint.
- **Activo/inactivo**: un toggle de un clic en el listado (`toggleActive()`), sin abrir el
  formulario completo — solo los banners activos entran a la consulta del home.
- **Orden manual** (`sort_order`, entero simple y siempre editable): a diferencia de "Categorías
  destacadas"/"Productos seleccionados" (que tienen su propia pantalla de curación aparte), acá el
  campo vive directo en el formulario — no hace falta una pantalla dedicada para algo tan chico.
- Sin papelera: a diferencia de categorías/productos, un banner promocional no tiene pedidos ni
  relaciones que sobrevivan a su borrado, así que `delete()` es un borrado real de una vez
  (`$banner->delete()` + `ResponsiveImage::delete()` de las 3 imágenes), con el mismo modal de
  confirmación del resto del admin.

**`<x-banner-carousel>`** (home únicamente, `resources/views/components/banner-carousel.blade.php`)
renderiza cada banner activo como un `<picture>` con tres `<source media="...">` — uno por
breakpoint — dejando que el navegador elija el archivo correcto según el ancho real de la
ventana, no un JS que detecte el viewport. Auto-avanza cada 6s (Alpine `setInterval`, con
pausa al pasar el mouse), con flechas y puntos si hay más de un banner activo, y no se renderiza
nada en absoluto si no hay ninguno — el home nunca muestra una caja gris vacía a la espera de que
un admin suba algo.

`Home::with()` solo pasa banners que son **a la vez** activos **y** tienen ya alguna imagen
cargada — un admin puede activar un banner antes de subirle fotos (el toggle y las imágenes son
campos independientes), y sin este filtro esa combinación produciría un `<img src="">` roto en
producción.

## Sistema de alertas del admin

Todo el lado administrativo usa un sistema propio de toasts y confirmaciones — nada de
`confirm()` nativo del navegador ni mensajes de error sin marcar visualmente:

- **Toasts** (`resources/views/components/admin-toast.blade.php` + `App\Concerns\Notifies`):
  cualquier componente Volt admin llama `$this->notifySuccess('...')`/`$this->notifyError('...')`,
  que hace dos cosas a la vez — flashea a la sesión y dispara un evento de navegador `toast` — para
  cubrir ambos casos posibles: una acción que se queda en la misma página (ej. borrar una fila de
  una lista) se entera por el evento en vivo; una acción que redirige (crear/guardar un formulario)
  se entera leyendo la sesión en el siguiente render.

  **Bug real encontrado al construir esto:** inicialmente el toast post-redirect se disparaba con
  `x-init` de Alpine leyendo la sesión flasheada. Funcionaba en la wire:carga inicial pero dejaba de
  funcionar en la segunda navegación en adelante — el contenedor del toast es estructuralmente
  idéntico en cada página del admin, así que el morph de `wire:navigate` de Livewire actualiza los
  atributos del mismo nodo del DOM en vez de reemplazarlo, y `x-init` solo se ejecuta una vez por
  nodo. Se corrigió escuchando el evento `livewire:navigated` (que sí se dispara en cada render,
  incluyendo la carga inicial) y leyendo el valor flasheado desde un atributo `data-*` en ese
  momento, en vez de depender de `x-init`.
- **Confirmaciones** (`resources/views/components/confirm-modal.blade.php` +
  `window.confirmAction()` en `resources/js/app.js`): un modal global, con la misma paleta que el
  resto del admin, reemplaza `wire:confirm` (que es literalmente el `confirm()` nativo sin estilo)
  en cada botón destructivo — `x-on:click="confirmAction('¿Seguro?', () => $wire.delete(1))"`. El
  callback de confirmación es JS puro pasado por el que dispara el modal; el componente del modal
  no sabe qué está confirmando.
- **Errores marcados visualmente**: cada campo con una regla de validación real (nombre, precio,
  stock, imágenes) tiene un borde/anillo rojo condicional (`@error('campo') border-red-500
  ring-1 ring-red-500 @enderror`) además del texto de error que ya existía — no solo un mensaje
  perdido debajo del campo.

## Borrado lógico (soft deletes)

`Category` y `Product` usan `Illuminate\Database\Eloquent\SoftDeletes` — el botón "Eliminar" que
ya existía en ambas listas no cambió de código (`$model->delete()` ya hace soft delete solo con
el trait aplicado): el registro se queda en la base con `deleted_at`, excluido automáticamente de
cualquier consulta normal (catálogo público, listas de admin, curación de destacados/
seleccionados) por el scope global que agrega el trait — sin tocar una sola query existente.

- **`/admin/categorias/papelera`** y **`/admin/productos/papelera`** (mismos permisos
  `categories.manage`/`products.manage` de siempre): listan lo eliminado
  (`Model::onlyTrashed()`), con **Restaurar** (`->restore()`, sin confirmación — es reversible) y
  **Eliminar permanentemente** (`->forceDelete()`, con el modal de confirmación — esta sí es
  irreversible).
- Eliminar un producto **permanentemente** primero borra sus archivos de imagen del disco
  (`Storage::disk('public')->delete(...)` por cada una) antes del `forceDelete()` — el `FK
  cascadeOnDelete` de `product_images` sí se dispara en ese punto (es un delete real a nivel de
  base de datos), pero esa cascada nunca toca el sistema de archivos, así que sin este paso las
  imágenes quedarían huérfanas en disco para siempre.
- Cada acción (borrar/restaurar/eliminar permanente) sigue quedando en la bitácora
  (`category.deleted`/`category.restored`/`category.force_deleted`, mismo patrón para productos).
- Un producto/categoría eliminada (soft) desaparece de inmediato de la tienda pública — no hace
  falta ningún cambio en `ProductController`/`CategoryController`, el scope global ya lo cubre —
  pero sus datos (incluyendo las imágenes de producto y las líneas de pedidos ya facturados, que
  guardan su propio snapshot de nombre/precio) permanecen intactos y recuperables.

## Cuenta del cliente (`/dashboard`, `/mis-pedidos/{order}`)

El rol `customer` ya existía desde el seeder (se asigna solo, en `register()`, a cualquiera que
se registre — `admin` nunca se otorga por registro abierto) y nunca necesitó permisos de Spatie:
comprar y ver el catálogo no está gateado por `can()`, solo `/admin/*` lo está. Lo que sí faltaba
era dónde ver sus propios pedidos — `/dashboard` era todavía el stub genérico de Breeze
("You're logged in!"), y la única pantalla que listaba pedidos (`/admin/pedidos`) es de solo
lectura para admins y ve **todos** los pedidos de todos.

- **`/dashboard`** ahora es un componente Volt (`account.orders`) que lista los pedidos del
  usuario logueado — `Order::where('user_id', auth()->id())`. El nombre de ruta sigue siendo
  `dashboard` (todo el flujo de login/registro/verificación ya redirige ahí por nombre), solo
  cambió qué renderiza.
- **`/mis-pedidos/{order}`** (`account.order-show`) muestra el detalle de un pedido propio —
  mismo layout que el detalle de admin (`/admin/pedidos/{order}`), pero gateado por **dueño**, no
  por permiso: `abort_unless($order->user_id === auth()->id(), 403)`. Un pedido de invitado
  (`user_id` nulo, el checkout no exige cuenta) o el pedido de otro cliente devuelven 403 igual —
  ni siquiera un admin lo ve por esta ruta, para eso está la ruta de admin aparte.
- El checkout ya guardaba `user_id => auth()->id()` desde que se construyó (nulo si es invitado),
  así que no hizo falta tocar `storefront.checkout` — solo faltaba una pantalla que leyera ese
  dato.
- El link "Dashboard" del nav de cuenta (`layout/navigation.blade.php`) se renombró a
  "Mis pedidos", en escritorio y en el menú responsive — es literalmente lo que ahora muestra.
- **Actualizar datos** (nombre, email, contraseña, eliminar cuenta) ya existía en `/profile` sin
  cambios — viene de Breeze tal cual, no es parte de este trabajo.

## Auth: registro accesible y mensajes en español

Dos problemas reales encontrados al revisar el flujo de autenticación (heredado tal cual de
`template-laravel-monolith`/Breeze, nunca antes tocado en este proyecto):

- **`/register` no tenía ningún link que llevara ahí.** La ruta existía y funcionaba, pero
  `storefront-shell.blade.php` solo mostraba "Iniciar sesión" cuando no había sesión — un visitante
  nuevo no tenía forma de encontrar el registro salvo escribiendo la URL a mano. La única pantalla
  que sí enlazaba a `/register` era `resources/views/welcome.blade.php`, el scaffold de Laravel que
  ni siquiera está enrutado (`/` apunta a `storefront.home`) — código muerto. Se agregó el link
  "Registrarse" al header de escritorio y al menú de hamburguesa mobile
  (`components/storefront-shell.blade.php`), y un link cruzado "¿No tienes cuenta?"/"¿Ya tienes
  cuenta?" entre `/login` y `/register`.
- **Los mensajes de error de auth se resolvían en inglés en una app 100% en español.**
  `LoginForm::authenticate()` usaba `trans('auth.failed')`/`trans('auth.throttle', ...)`, y las
  pantallas de contraseña usaban `__('auth.password')`/`__($status)` (con `$status` una constante
  del password broker, ej. `Password::RESET_LINK_SENT`). Este proyecto nunca tuvo un directorio
  `lang/` propio, así que esas llamadas no fallaban silenciosamente — **sí resuelven**, pero contra
  los strings de fallback que trae Laravel 11+ dentro del propio framework
  (`vendor/laravel/framework/.../Translation/lang/en/auth.php`), en inglés. Un intento de login con
  contraseña incorrecta mostraba literalmente *"These credentials do not match our records."* en
  medio de una pantalla completamente en español. Se reemplazaron esas llamadas por strings en
  español directos — igual que cualquier otro mensaje de este proyecto, que nunca usó el sistema de
  traducción de Laravel para nada más.

De paso, las 6 pantallas de auth (`login`, `register`, `forgot-password`, `reset-password`,
`confirm-password`, `verify-email`) se tradujeron por completo a español (venían tal cual las deja
el scaffold de Breeze, en inglés), y `layouts/guest.blade.php` — antes el logo genérico de Laravel
sobre una tarjeta gris de Breeze — ahora usa el wordmark "Torres Shop" (mismo estilo que el header
de la tienda) enlazando al home, y una tarjeta `rounded-lg border border-gray-200` consistente con
el resto del sitio en vez del `shadow-md` por defecto.

## Usuarios de prueba (seed)

| Email | Password | Rol |
| --- | --- | --- |
| `admin@torresshop.com` | `admin12345` | admin |
| `cliente@example.com` | `cliente12345` | customer |

## Tests

`php artisan test` — 118 tests. Cubre: catálogo público solo muestra productos activos, filtro por
categoría, meta tags reales por producto (`/producto/{slug}` en una petición HTTP real, no solo el
componente), agregar al carrito actualiza la sesión, checkout calcula el total desde la base de
datos y descuenta stock, checkout falla limpiamente sin inventario suficiente, un customer recibe
403 en `/admin/productos`, CRUD de productos/categorías registra `old_values`/`new_values`
correctos en la bitácora, CRUD de roles respeta la protección de `admin`/`customer`, un rol
personalizado con solo `activity.view` o `roles.manage` accede a su sección sin ser admin,
cambiar el rol de un usuario queda registrado con el rol anterior y el nuevo, subir un banner de
categoría como archivo real (`Storage::fake`) queda en disco y el accessor arma la URL completa,
curar categorías/productos destacados actualiza `featured_order` correctamente y el nav/home/
ficha de producto reflejan solo lo curado, crear/eliminar dispara un toast de éxito
(`assertDispatched('toast', type: 'success')`), los intentos de acción bloqueada (quitarte tu
propio rol de admin, borrar un rol protegido) disparan un toast de error sin cambiar nada,
eliminar una categoría/producto lo excluye de todas las consultas normales pero el registro sigue
existiendo (`withTrashed()`), aparece en su papelera y se puede restaurar o eliminar
permanentemente, eliminar un producto permanentemente borra sus archivos de imagen del disco,
subir una imagen genera el original más las 3 variantes WebP responsivas, `srcset()` nunca
apunta a una variante que no existe en disco, `ResponsiveImage::delete()` limpia el original y
sus variantes juntos, crear/editar un banner registra `old_values`/`new_values` en la bitácora,
activar/desactivar un banner desde el listado queda registrado, borrar un banner lo elimina junto
con sus 3 imágenes, subir las 3 imágenes de un banner genera variantes responsivas para cada una,
el home solo muestra los banners activos que además ya tienen alguna imagen cargada, en el orden
esperado, `/dashboard` solo lista los pedidos del usuario logueado (nunca los de otro cliente ni
los de un checkout de invitado, que no tiene `user_id`), y `/mis-pedidos/{order}` responde 403 al
intentar ver el detalle de un pedido ajeno o de invitado, el mensaje de contraseña incorrecta en
`/login` está en español (no el fallback en inglés del framework), el header de la tienda enlaza
a `/register` cuando no hay sesión iniciada, un admin puede capturar SKU/material/precio
mayoreo/costo en un producto y el margen (`margin_amount`/`margin_percent`) se calcula
correctamente, dejar vacíos los campos numéricos opcionales (mayoreo/costo) no truena la
validación (`'' !== null` en PHP — ver la sección de arriba), el SKU debe ser único entre
productos, y la ficha pública del producto muestra material pero nunca el precio mayoreo ni el
costo de producción, las variantes de color: un admin puede crear un color con precio/hex propios y
el `effective_price` cae al precio del producto cuando no se define uno propio, editar el color de
otro producto por URL responde 403, subir y borrar fotos de un color genera/limpia sus variantes
responsivas igual que las del producto, borrar un color limpia sus archivos, sus filas de imagen
**y** sus variantes de inventario (sin cascada de por medio — ver el bug de SQL Server más
arriba), un color nuevo nace con su fila de inventario, el primer color de un producto adopta el
stock sin color que ya existía en vez de dejarlo huérfano, un color sin foto muestra su nombre
completo (no las 3 primeras letras) en la ficha pública, y la matriz color × talla: un customer
recibe 403 en `/admin/tallas`, crear una talla duplicada se rechaza en español, borrar una talla
borra las variantes que la usaban, aplicar tallas genera una variante por cada combinación
color × talla y desmarcar una talla elimina las suyas, guardar la rejilla actualiza el stock de
cada celda, un producto con variantes ignora su columna `stock` y uno sin variantes la sigue
usando, un producto cuyas variantes están todas en 0 se reporta agotado, el mismo producto en dos
combinaciones son dos líneas de carrito con su etiqueta `"Rojo / M"`, el precio propio de un color
aplica a todas sus tallas y llega intacto al pedido (con `color_name`/`size_name` por separado),
el checkout descuenta el stock de la variante (no el del producto) y falla limpiamente cuando esa
combinación se queda sin inventario, el selector de talla reporta el stock por talla **del color
seleccionado** y re-elige talla al cambiar de color, y agregar una combinación agotada al carrito
se rechaza.

Verificado también end-to-end contra SQL Server real: catálogo, meta tags reales en la respuesta
cruda (`curl`, no solo el DOM), agregar al carrito, checkout completo (con confirmación de pedido
y stock descontado en la base), bitácora con diffs reales, creación de un rol personalizado y
verificación de que un usuario con ese rol ve solo la sección permitida en el nav y recibe 403 real
al intentar entrar a una sección fuera de su permiso, el modal de confirmación propio (no el
`confirm()` nativo) apareciendo y bloqueando la acción hasta confirmar, el toast de éxito
apareciendo tanto tras un borrado en la misma página como tras un guardado con redirect, el borde
rojo apareciendo en un campo con error de validación real, el ciclo completo de borrado lógico:
eliminar una categoría/producto (desaparece de la tienda al instante), verlo en su papelera,
restaurarlo y confirmar que vuelve a estar disponible, y las imágenes responsivas: subida real
de una foto de 2400×2400px, verificación de que el navegador descarga la variante WebP de
768px (~1KB) en vez del original (~90KB) tanto en viewport de escritorio como en uno móvil
emulado con densidad de píxeles 2x (donde correctamente pide una variante más grande para
compensar), que un producto sin imagen sigue mostrando el placeholder gris sin romper nada, y el
carrusel de banners del home: creación de un banner con subida real de sus 3 imágenes (escritorio/
tablet/mobile) vía inputs de archivo simulados con `DataTransfer`, verificación de que cada
breakpoint (`resize_window` a mobile/tablet/desktop) efectivamente carga el archivo correcto
según el `<picture><source media="...">` correspondiente (`img.currentSrc` distinto en cada uno),
desactivar el banner lo hace desaparecer del home de inmediato, borrarlo vía el modal de
confirmación real elimina también sus archivos de disco sin dejar huérfanos, y la cuenta del
cliente: login como `cliente@example.com`, compra real de principio a fin (tienda → carrito →
checkout), el pedido apareciendo de inmediato en `/dashboard` (ahora renombrado "Mis pedidos" en
el nav) y su detalle mostrando los mismos artículos/total que la pantalla de éxito del checkout,
y — tras cerrar sesión y entrar como `admin@torresshop.com` — confirmación de que ese mismo
pedido responde 403 real (`fetch` con `redirect: 'manual'`, no solo el estado de Livewire) al
intentarlo ver por `/mis-pedidos/{order}`, porque el admin no es su dueño, y el flujo de auth:
el link "Registrarse" visible en el header (escritorio y menú mobile) cuando no hay sesión,
registro real de una cuenta nueva de punta a punta redirigiendo a "Mis pedidos" con el rol
`customer` ya asignado, un intento de login con contraseña incorrecta mostrando el mensaje real en
español ("Estas credenciales no coinciden con nuestros registros.", no el fallback en inglés del
framework), y `/forgot-password` con su texto y botón ya en español, los datos de mercadería del
producto: crear un producto real desde `/admin/productos/nuevo` con SKU/material/precio
mayoreo/costo (inputs llenados vía `DataTransfer`/eventos nativos, no el tool de clicks), viendo el
margen estimado ("$120.00 (40%)") actualizarse en vivo mientras se escribe precio/costo, guardarlo
y verlo en el listado de admin con su SKU, la migración corriendo limpio contra SQL Server real
(incluyendo el índice único filtrado y su rollback), un segundo producto con el mismo SKU
rechazado con el mensaje en español ("Este SKU ya está en uso por otro producto."), y las variantes
de color: crear dos colores reales para un producto ("Negro" con precio propio $950, "Blanco" sin
precio propio) con foto real subida a cada uno, la ficha pública mostrando "Color: Negro"
preseleccionado con el precio $950 (no el $899 base) y la foto correcta, hacer clic en el swatch
"Blanco" y confirmar — sin recargar la página — que la foto, el precio ($899, el del producto) y
la etiqueta de color cambian juntos, agregar el mismo producto en ambos colores al carrito y
verificar que aparecen como dos líneas independientes con su propio precio, completar el checkout
y confirmar que descuenta el stock del **color** correcto (8→7 y 3→2) dejando el `stock` del
producto intacto, que el listado de admin muestra el stock agregado (9, no 40) con la etiqueta
"(2 colores)" en español, y que el pedido resultante muestra "(Blanco)"/"(Negro)" junto al nombre
del producto tanto en la pantalla de éxito como en `/admin/pedidos` y `/mis-pedidos`, y la matriz
color × talla: la migración corriendo contra una base que **ya tenía** colores con stock (10 y 5),
verificando que ese stock aparece intacto en la matriz como combinaciones sin talla y que el
rollback lo devuelve a la columna del color; aplicar M y L desde el catálogo global y ver la
rejilla expandirse a 2 colores × 2 tallas; capturar stock por celda dejando Rojo/L y amarillo/M
en cero a propósito; en la ficha pública, comprobar que el color sin foto ("Rojo") se dibuja como
un chip con su nombre completo en vez del círculo gris con "Roj", que la talla L aparece tachada
con `title="Agotado en este color"` y M seleccionada con "4 disponibles", y que al hacer clic en
el swatch "amarillo" la foto, la etiqueta de color y las tallas se invierten juntas (M agotada, L
seleccionada con "6 disponibles"); agregar ambas combinaciones al carrito y verlas como dos líneas
"amarillo / L" y "Rojo / M"; completar el checkout y confirmar contra la base que descontó
exactamente esas dos celdas (4→3 y 6→4) dejando `products.stock` en 40 sin tocar; y en
`/admin/tallas`, crear una talla nueva, ver el contador "2 variantes" en las que están en uso y
que un nombre duplicado se rechaza con el mensaje en español.
