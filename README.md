# ICCE Rentas y Servicios — Plataforma web

Web corporativa, e-commerce híbrido (venta directa + cotización B2B), captación de
renta de equipos y CRM logístico para **icce.com.mx**.

## Stack

| Capa | Tecnología |
|---|---|
| Framework | Laravel 13 (PHP 8.4) |
| Frontend | Blade + Livewire 4 + Alpine + Tailwind CSS 4 |
| Backoffice | Filament 5 (`/admin`) |
| Base de datos | MariaDB 11.8 (InnoDB, utf8mb4) |
| Caché, sesiones y colas | Redis 7 |
| Servidor web | Nginx 1.27 |
| Correo en local | Mailpit |

## Arranque

```bash
cp .env.example .env                      # ajusta credenciales si hace falta
UID=$(id -u) GID=$(id -g) docker compose build app
UID=$(id -u) GID=$(id -g) docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose --profile dev up -d node   # Vite en modo watch
```

| Servicio | URL |
|---|---|
| Sitio | http://localhost (o http://localhost:8080) |
| Backoffice | http://localhost/admin |
| Portal de cliente | http://localhost/ingresar |
| Mailpit | http://localhost:8025 |
| MariaDB | `127.0.0.1:3307` |

### Usuarios de prueba

Contraseña `Icce2026` en los tres. **Solo entorno local**, los crea `UserSeeder`.

| Rol | Correo | Aterriza en |
|---|---|---|
| Administrador | `admin@icce.com` | `/admin` |
| Ventas | `ventas@icce.com` | `/admin` |
| Cliente | `registrado@icce.com` | `/portal` |

Para recorrer el sistema con los tres perfiles, ver **[GUIA-DE-REVISION.md](GUIA-DE-REVISION.md)**.

## Comandos frecuentes

```bash
docker compose exec app php artisan test          # suite de pruebas
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app ./vendor/bin/pint         # formato de código
docker compose exec app php artisan pail          # logs en vivo
```

## Frontend

### Design system

Los tokens viven en `resources/css/app.css` bajo `@theme`. La paleta sale de los
píxeles del logotipo oficial: verde `#84C020` (`brand-500`) y carbón `#0E1100`
(`carbon-950`). El neutro lleva tinte verde (hue 75) para que grises y marca
pertenezcan a la misma familia. Tipografía: **Archivo** para titulares,
**Inter** para lectura, servidas localmente vía `laravel-vite-plugin/fonts`.

Componentes Blade en `resources/views/components/`:

| Grupo | Componentes |
|---|---|
| `layouts` | `app` — layout base con SEO, Open Graph y skip link |
| `site` | `header` (mega menú + móvil), `footer`, `whatsapp-button` |
| `ui` | `container`, `button`, `eyebrow`, `section-heading`, `page-header`, `stock-badge` |
| `cards` | `product`, `project`, `post` |

La utilidad `scale-rule` dibuja la escala graduada que aparece bajo el encabezado
y en los cortes de sección: es el guiño de marca a la regla de nivelación láser.

### Páginas publicadas

Home (las seis secciones del sitemap), Empresa, Servicios, Contacto, Renta de
equipos, Requisitos de renta, Proyectos, Blog técnico, Marcas, Centro de
descargas, portal de cliente y las dos páginas legales.

### Catálogo: búsqueda y filtros (fase 3)

`resources/views/components/catalogo/⚡explorador.blade.php` es un componente
Livewire de un solo archivo. Busca por nombre, SKU, descripción y marca —cada
palabra debe coincidir en algún campo, de modo que «llana kraft» no devuelva
todas las llanas ni todo lo de Kraft Tool— y filtra por familia, disponibilidad
y marca.

Todo el estado se sincroniza con la URL vía `#[Url]`:

```
/catalogo?q=llana&disp=disponible&marca[]=kraft-tool&orden=precio-desc
```

Un resultado filtrado es así compartible, marcable e indexable, lo que sirve al
RNF-03 tanto como a la comodidad de uso.

La búsqueda usa `LIKE` (`Product::scopeSearch`): correcta y suficiente para el
orden de magnitud del catálogo actual. Cuando la carga del ERP lo lleve a
decenas de miles de SKU, el reemplazo natural es un índice FULLTEXT o Scout.

## Backoffice

`/admin`, tematizado con la marca ICCE. Tres grupos:

| Grupo | Recursos |
|---|---|
| **Operación** | Pedidos y cotizaciones, Solicitudes de renta |
| **Catálogo** | Inventario, Categorías, Marcas |
| **Contenido** | Banners del home, Blog técnico, Casos de éxito |

El escritorio abre con colas de trabajo, no con métricas de vanidad: qué está
esperando a alguien y qué existencia hay que reponer.

### Cambio de estatus y cotizaciones (fase 6)

`App\Actions\Orders\ChangeOrderStatus` es el **único** punto por el que cambia el
estatus de una orden. Concentra tres cosas que deben ocurrir juntas o no ocurrir:
la marca de tiempo del hito, el asiento en la bitácora que alimenta el timeline
del cliente y el aviso por correo. Si el estatus se cambiara suelto con un
`update()`, el timeline del portal quedaría mintiendo.

`App\Actions\Orders\SendQuote` recalcula los totales desde el precio que el
agente dejó en cada partida y adjunta el PDF. El precio de lista nunca se
sobrescribe: la orden conserva ambos, para poder auditar cuánto descuento se dio.

> **Trampa de Filament que costó tiempo:** los closures de filtros, pestañas y
> columnas se inyectan **por nombre de parámetro**. Un `fn (Builder $q) => …`
> hace que Filament intente resolver `Eloquent\Builder` del contenedor y reviente
> con «newQueryWithoutRelationships() on null». El parámetro debe llamarse
> `$query`. Lo mismo con type-hints de enums en `formatStateUsing`/`color`:
> hay que dejarlos sin tipo.

Los assets del CMS usan el disco `site` (`config/filesystems.php`), que apunta a
la raíz web para que una misma ruta —`images/...`— sirva desde Blade con
`asset()` y desde los componentes de Filament. Migrar a S3 es cambiar ese disco.

## Reglas de negocio implementadas

### Motor Comprar vs. Cotizar (REQ-01 / REQ-02)

`Product::purchaseModeFor(int $qty)` es la única fuente de verdad. Devuelve
`PurchaseMode::Quote` —es decir, la línea deja de ser cobrable y se convierte en
solicitud de cotización— cuando se cumple cualquiera de estas condiciones:

- el producto no es de venta (`is_for_sale = false`, p. ej. equipo de renta);
- está marcado como **bajo pedido** (`is_on_demand = true`);
- la cantidad excede `max_direct_purchase` (10 por omisión, configurable por
  producto y globalmente vía `ICCE_MAX_DIRECT_PURCHASE`);
- la cantidad excede la existencia (`stock_qty`).

Cubierto por `tests/Unit/PurchaseModeTest.php`.

### Carrito híbrido y checkout (fase 4)

`App\Support\Cart\Cart` guarda en sesión sólo el par producto/cantidad. Precio,
disponibilidad y modo se resuelven contra la base en cada lectura, de forma que
el carrito nunca sirve un precio viejo ni promete existencia que ya no hay.

Un carrito mixto **produce dos órdenes**: forzar todo a un solo camino sería
peor para ambas partes —el cliente esperaría días por una llana que estaba en
almacén, o ICCE cobraría en línea un pedido de volumen sin margen de
negociación—. `App\Actions\Checkout\PlaceOrders` las crea en una transacción y
aparta la existencia con bloqueo pesimista; si el cobro falla después,
`releaseStock()` la devuelve.

La pasarela vive detrás de `App\Payments\Contracts\PaymentGateway`. El driver
`simulado` recorre el checkout completo sin credenciales y **se niega a operar
en producción**; `stripe` y `openpay` están esbozados y lanzan una excepción
hasta implementarse, porque fallar de forma ruidosa es preferible a fingir un
cobro. Se elige con `ICCE_PAYMENT_DRIVER`.

### Timeline logístico (REQ-04)

`OrderStatus::trackingSteps()` define la barra que ve el cliente:
Cotizado → Pagado → En Almacén → En Tránsito → Entregado.
`order_status_histories` guarda cada transición con fecha, autor y si se notificó
al cliente, para que el timeline muestre fechas reales y no inferidas.

## Datos sembrados

Los seeders no usan datos ficticios: salen de un rastreo del sitio actual
(`database/data/`).

| Archivo | Contenido |
|---|---|
| `icce_catalog.json` | 200 fichas extraídas de icce.com.mx |
| `icce_legacy_urls.txt` | 269 URLs del sitio actual, base del mapeo 301 |
| `icce_compromised_urls.json` | URLs secuestradas por spam (ver abajo) |

Resultado: 19 marcas, 27 categorías, **173 productos**, 270 redirecciones,
3 banners, 5 artículos y 5 casos de éxito.

Las imágenes (`public/images/`) también se bajaron del sitio actual: 17 logos de
marca, 77 fotos de producto y las fotos de obra. **Varias traen texto
promocional incrustado** (banners rojos sobre la foto) y algunos logos vienen
con fondo gris en vez de transparente; conviene pedir a ICCE los originales
limpios antes de producción.

> **Precios y existencias son marcadores.** El sitio actual no publica ninguno de
> los dos. `LegacyCatalog` genera valores deterministas por categoría para poder
> ejercitar el motor de decisión; se reemplazan con la carga real del ERP.

## Migración SEO (RNF-03)

El mapa se ejecuta desde `Route::fallback()` y **no** desde un middleware
global: así sólo se consulta cuando ninguna ruta real coincidió, y el tráfico
normal —que es todo el tráfico una vez asentada la migración— no paga ni una
consulta por petición.

Cada acceso a una ruta vieja incrementa su contador. Sirve para saber qué
enlaces externos siguen apuntando al sitio anterior y a quién hay que pedirle
que los actualice.

`/sitemap.xml` se genera del contenido publicado y se cachea 6 horas: **242
URLs** contra la única que declaraba el sitemap anterior, pese a tener 269
páginas reales. Buena parte del catálogo no estaba siendo descubierta.

Datos estructurados JSON-LD: `Organization` y `WebSite` en el layout,
`BreadcrumbList` en cada cabecera y `Product` con precio y disponibilidad en la
ficha. La oferta sólo se declara cuando hay precio real: publicar `$0.00` haría
que el buscador muestre un precio falso.

`url_redirects` mapea cada URL vieja a la nueva estructura. Estado actual:

- **217** redirecciones 301 activas (fichas de producto, categorías, institucionales);
- **26** URLs marcadas `410 Gone` — plantillas del theme que nunca fueron contenido,
  más las páginas comprometidas;
- **27** pendientes de mapeo manual, sembradas inactivas para que el equipo las
  resuelva desde el backoffice antes de salir a producción.

### ⚠️ Páginas comprometidas en el sitio actual

Siete URLs de icce.com.mx están secuestradas y sirven spam de casas de apuestas
(1win, KOKO5000, LINETOGEL, YOWESTOGEL, MANCINGDUIT) con trackers de terceros.
Se listan en `database/data/icce_compromised_urls.json` y se sembraron como **410
Gone**, nunca como 301: redirigirlas trasladaría a la estructura nueva la señal de
spam que Google ya asoció a esas rutas.

Esto requiere atención en el hosting actual, fuera del alcance de este repo.
