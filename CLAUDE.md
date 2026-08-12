# ICCE Rentas y Servicios — contexto del proyecto

Plataforma a la medida para **icce.com.mx**: sitio corporativo, e-commerce híbrido
(venta directa y cotización B2B), captación de renta de equipos, portal de cliente
con seguimiento logístico y CRM propio.

El cliente es ICCE Rentas y Servicios, distribuidor de herramienta, materiales y
maquinaria para **pisos industriales y pisos superplanos de concreto** en México.
Distribuye Somero, Kraft Tool, Husqvarna, CTS Rapid Set, W. R. Meadows y otras.

Todo el código, los comentarios, los nombres de prueba y la interfaz están **en
español**. Es la lengua del cliente y del equipo que va a operar el sistema.

---

## Stack

| Capa | Tecnología |
|---|---|
| Framework | Laravel **13** (PHP 8.4) |
| Frontend | Blade + **Livewire 4** + Alpine + Tailwind CSS 4 |
| Backoffice | **Filament 5** en `/admin` |
| Base de datos | MariaDB 11.8 |
| Caché, sesiones, colas | Redis |
| PDF | `barryvdh/laravel-dompdf` |
| Correo en local | Mailpit |

> El documento original del cliente pedía Laravel 11 y MySQL. Se acordó con el
> usuario subir a la última estable; MariaDB fue decisión suya. Si algo del PRD
> menciona Laravel 11, la versión vigente manda.

---

## Cómo trabajar aquí

**Todo corre en Docker.** No hay PHP ni Composer en el host.

```bash
UID=$(id -u) GID=$(id -g) docker compose up -d      # levantar
docker compose exec app php artisan <lo-que-sea>    # artisan
docker compose exec app php artisan test            # 123 pruebas
docker compose exec app ./vendor/bin/pint           # formato, correr siempre
docker compose run --rm node npm run build          # recompilar CSS/JS
docker compose exec app php artisan migrate:fresh --seed
```

El sitio queda en `http://localhost` (y `:8080` como respaldo), el backoffice en
`/admin` y Mailpit en `:8025`.

**Después de tocar Blade, rutas o config:** `php artisan optimize:clear`.
**Después de tocar clases de Tailwind:** recompilar con el contenedor `node`.

### Puesta en marcha en una máquina nueva

```bash
cp .env.example .env
UID=$(id -u) GID=$(id -g) docker compose build app
UID=$(id -u) GID=$(id -g) docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose run --rm node npm install && docker compose run --rm node npm run build
docker compose exec app php artisan icce:fetch-fichas   # 96 PDF, ~88 MB
```

`public/fichas` está en `.gitignore` a propósito: son 88 MB de fichas técnicas del
fabricante. El comando las repone desde el mapa que sí está versionado
(`database/data/icce_datasheets.json`). En producción van a S3.

---

## Decisiones que no hay que romper

Cada una resuelve un problema concreto. Si algo parece redundante, léase la razón
antes de simplificarlo.

### El motor Comprar vs. Cotizar (REQ-01 / REQ-02)

`Product::purchaseModeFor(int $qty)` es la **única** fuente de verdad. Devuelve
`PurchaseMode::Quote` si el producto no es de venta, está bajo pedido, la cantidad
excede `max_direct_purchase`, o excede la existencia. Toda la interfaz —ficha,
carrito, checkout— consulta ese método; nadie reimplementa la regla.

### El carrito guarda lo mínimo

`App\Support\Cart\Cart` guarda en sesión sólo `producto => cantidad`. Precio,
disponibilidad y modo se resuelven contra la base **en cada lectura**, así que el
carrito nunca sirve un precio viejo ni promete existencia que ya no hay. Cachea
las líneas dentro de una misma petición (por eso es `scoped`, no `singleton`).

### Un carrito mixto produce dos órdenes

`App\Actions\Checkout\PlaceOrders` crea una venta y una cotización por separado.
Forzar todo a un camino sería peor para ambas partes: el cliente esperaría días por
material que estaba en almacén, o ICCE cobraría en línea un pedido de volumen sin
margen de negociación. La existencia se aparta con bloqueo pesimista dentro de la
transacción; si el cobro falla, `releaseStock()` la devuelve.

### El estatus de una orden cambia por un solo lugar

`App\Actions\Orders\ChangeOrderStatus`. Concentra cuatro cosas que ocurren juntas o
no ocurren: el estatus, la marca de tiempo del hito, el asiento en
`order_status_histories` y el aviso al cliente. **Nunca** cambiar `status` con un
`update()` suelto: el timeline del portal quedaría mintiendo, que es justo el
problema que el portal venía a resolver.

### La pasarela vive tras una interfaz

`App\Payments\Contracts\PaymentGateway`. El driver `simulado` recorre el checkout
sin credenciales y **se niega a operar en producción**. `StripeGateway` y
`OpenpayGateway` están esbozados con notas de implementación y **lanzan excepción**:
fallar de forma ruidosa es preferible a fingir un cobro. Se elige con
`ICCE_PAYMENT_DRIVER`.

### Las redirecciones van en `Route::fallback()`

No en un middleware global. Así el mapa de `url_redirects` sólo se consulta cuando
ninguna ruta real coincidió, y el tráfico normal no paga una consulta por petición.
Ver `LegacyUrlController`.

### El caché guarda arreglos, nunca modelos

Laravel 13 **no deserializa clases desde caché** por omisión
(`config/cache.php: serializable_classes => false`, defensa contra gadget chains si
se filtra el `APP_KEY`). Se mantiene ese valor. `AppServiceProvider::shareNavigation`
cachea arreglos de primitivas. Guardar una colección de Eloquent revienta el sitio
entero con `__PHP_Incomplete_Class`.

### `preventLazyLoading` está activo fuera de producción

Es intencional: convierte un N+1 silencioso en excepción. Si una tabla de Filament
o una vista truena por esto, la solución es precargar la relación, no desactivar la
protección.

---

## Trampas de Filament 5 (costaron tiempo, no repetirlas)

**Los closures se inyectan por nombre de parámetro.** Un
`fn (Builder $q) => …` en un filtro, pestaña o `modifyQueryUsing` hace que Filament
intente resolver `Eloquent\Builder` del contenedor y reviente con
`newQueryWithoutRelationships() on null`. **El parámetro debe llamarse `$query`.**

**No tipar enums en los closures de columna.** `fn (OrderStatus $state) => …` falla
con «Target … is not instantiable». Usar `fn ($state) => $state?->label()`.

**`SelectFilter::relationship()` exige que el nombre del filtro sea el de la
relación.** Con `assigned_to` apuntando a `agent` hay que usar `->options()`.

**Las pestañas de un listado usan `Filament\Schemas\Components\Tabs\Tab`**, no
`Resources\Pages\ListRecords\Tab`.

**`$recordRouteKeyName = 'id'`** en todo recurso cuyo modelo sobrescribe
`getRouteKeyName()` (Post, Project, Category, Brand, Order, Product…). Si no, el
backoffice direcciona por slug y renombrarlo mueve la URL de edición.

**Los assets del CMS usan el disco `site`** (`config/filesystems.php`), que apunta a
`public_path()`. Así una misma ruta `images/…` sirve desde Blade con `asset()` y
desde `ImageColumn`/`FileUpload`. Migrar a S3 es cambiar ese disco.

---

## Convenciones de Livewire 4

Componentes de **un solo archivo** en `resources/views/components/<grupo>/⚡<nombre>.blade.php`
(sí, con el emoji ⚡ en el nombre; lo genera `make:livewire`). Se referencian como
`<livewire:grupo.nombre />`, **no** como `<x-...>`.

Existentes: `catalogo.explorador`, `descargas.centro`, `carrito.agregar`,
`carrito.detalle`, `carrito.contador`, `renta.solicitud`.

El estado que debe ser compartible viaja en la URL con `#[Url]` — el catálogo entero
funciona así, y es requisito de SEO tanto como comodidad.

---

## Pruebas

123 pruebas, todas en `tests/Feature` salvo `PurchaseModeTest`. **Nombres en
español**, describiendo la regla de negocio, no el método.

Las pruebas de reglas (carrito, buscador, CRM) arman un **catálogo mínimo y
controlado**; no siembran los 173 productos reales. Las aserciones deben depender de
la regla, no del contenido del cliente. Las de humo sí usan los seeders.

Cuando una prueba falle, considerar primero que el comportamiento real puede ser
mejor que el supuesto: ya pasó dos veces (existencia que baja → la línea pasa a
cotización en vez de fallar; y el carrito recalculando el modo en una petición nueva).

---

## Datos: de dónde salen y qué es relleno

Todo el catálogo se extrajo rastreando **icce.com.mx** con autorización del usuario.
Las fuentes están en `database/data/`:

| Archivo | Qué es |
|---|---|
| `icce_catalog.json` | 200 fichas extraídas del sitio |
| `icce_datasheets.json` | Mapa página → PDF del fabricante |
| `icce_legacy_urls.txt` | 269 URLs, base del mapeo 301 |
| `icce_compromised_urls.json` | Páginas secuestradas (ver abajo) |

Resultado sembrado: 19 marcas, 27 categorías, **173 productos**, 97 con ficha
técnica real, 270 redirecciones.

**Son marcadores, no datos reales:**

- **Precios y existencias.** El sitio actual no publica ninguno. `LegacyCatalog`
  genera valores deterministas por categoría para poder ejercitar el motor de
  decisión. Se reemplazan con la carga del ERP.
- **Cuerpos de los artículos.** Los títulos son reales; los textos están redactados
  porque los originales son PDF incrustados sin texto recuperable.
- **Proyectos, teléfono, dirección.** Ejemplos.
- **Imágenes.** Bajadas del sitio actual; varias traen texto promocional incrustado
  y algunos logos vienen con fondo gris. Falta pedir los originales limpios.
- **16 productos con ficha técnica no tienen marca asignada**: el heurístico que la
  deduce del nombre no alcanza para todos.

### ⚠️ Hallazgo de seguridad en el sitio actual

Siete páginas de icce.com.mx están **secuestradas** y sirven spam de casas de
apuestas (1win, KOKO5000, LINETOGEL, YOWESTOGEL, MANCINGDUIT) con rastreadores de
terceros. Están listadas en `database/data/icce_compromised_urls.json` y sembradas
como **410 Gone**, nunca 301: redirigirlas trasladaría a la estructura nueva la
señal de spam que Google ya asoció a esas rutas.

Requiere limpieza del hosting actual y probablemente revisión de acciones manuales
en Search Console. **Está fuera del alcance de este repositorio** y el usuario ya
está enterado.

---

## Diseño

La paleta sale de los píxeles del logotipo oficial: verde `#84C020` (`brand-500`) y
carbón `#0E1100` (`carbon-950`). El neutro lleva tinte verde (hue 75) para que
grises y marca pertenezcan a la misma familia. Tipografía **Archivo** para titulares
e **Inter** para lectura, servidas localmente.

La utilidad `scale-rule` dibuja la escala graduada que aparece bajo el encabezado:
es el guiño de marca a la regla de nivelación láser, el producto insignia de ICCE.

Tokens en `resources/css/app.css` bajo `@theme`. Componentes en
`resources/views/components/` divididos en `ui`, `site`, `cards`, `seo` y `layouts`.

---

## Cuentas de demostración

Contraseña **`Icce2026`**. Las crea `UserSeeder`, **sólo para entorno local**.

| Rol | Correo | Aterriza en |
|---|---|---|
| Administrador | `admin@icce.com` | `/admin` |
| Ventas | `ventas@icce.com` | `/admin` |
| Cliente | `registrado@icce.com` | `/portal` |

---

## Estado por fases

| Fase | Alcance | Estado |
|---|---|---|
| 1 | Docker, esquema, seeders | Completa |
| 2 | Design system, home, institucionales, CMS | Completa |
| 3 | Buscador, filtros, fichas, descargas | Completa |
| 4 | Carrito híbrido, checkout, correos | Completa salvo cobro real |
| 5 | Portal de cliente, timeline | Historial y rastreo listos; falta 7.3 |
| 6 | CRM de pedidos, cotizaciones, inventario | Completa |
| 7 | Migración SEO | 301/410, sitemap y datos estructurados listos |

**Pendiente real:**

1. Credenciales de Stripe u Openpay para el cobro real (bloqueado por el cliente).
2. Datos de facturación y direcciones en el portal (7.3).
3. Aprobar y pagar una cotización en línea — va junto con la pasarela.
4. Conexión al ERP de inventarios.
5. Las **27 rutas sin mapeo 301**, resolubles desde el backoffice.
6. Auditoría de accesibilidad y rendimiento sobre lo construido.

---

## Documentos del repositorio

- `README.md` — arranque y arquitectura, para quien programa.
- `GUIA-DE-REVISION.md` — recorrido técnico por los tres perfiles.
- `docs/manual-de-operacion.html` — manual de operación por rol, pensado para
  mostrarle el sistema al cliente. Publicado como artefacto privado.

---

## Cómo verificar en este proyecto

Hay un patrón que ha funcionado y conviene mantener: **comprobar en un navegador
real, no suponer**. Se usa el contenedor `zenika/alpine-chrome:with-puppeteer`
conectado a la red `icce_default`, apuntando a `http://nginx`. Así se detectaron el
botón de WhatsApp tapando el de pago, los tres fallos de closures de Filament y un
rótulo de CSS que se comía las negritas.

Una trampa recurrente al automatizar: `document.querySelector('form button[type=submit]')`
agarra el **buscador del encabezado**, que va primero en el DOM. Hay que acotar el
selector al formulario correcto.
