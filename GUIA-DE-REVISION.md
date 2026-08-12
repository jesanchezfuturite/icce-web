# Guía de revisión — ICCE

Cómo levantar el sitio en local y recorrerlo con los tres perfiles del sistema.

---

## 1. Levantar el entorno

Desde `/Users/futuriteadmin/projects/icce`:

```bash
UID=$(id -u) GID=$(id -g) docker compose up -d
```

Espera unos 15 segundos a que MariaDB pase su healthcheck. Para comprobar:

```bash
docker compose ps
```

Los cinco servicios (`app`, `nginx`, `db`, `redis`, `mailpit`) deben decir `running`.

### URLs

| Qué | Dónde |
|---|---|
| **Sitio** | **http://localhost** |
| Sitio (puerto alterno) | http://localhost:8080 |
| Backoffice | http://localhost/admin |
| Bandeja de correo (Mailpit) | http://localhost:8025 |
| Base de datos | `127.0.0.1:3307` · usuario `icce` · contraseña `secret` |

> Ambos puertos, 80 y 8080, sirven el mismo sitio. Si algo en tu Mac ya ocupa el
> 80, el contenedor no arranca: usa entonces el 8080 y listo.

### Si prefieres un dominio en vez de `localhost`

Ejecuta esto en la terminal (pide tu contraseña de macOS):

```bash
echo "127.0.0.1 icce.test" | sudo tee -a /etc/hosts
```

Y el sitio queda también en **http://icce.test**.

---

## 2. Los tres perfiles

Contraseña **`Icce2026`** en las dos cuentas.

| Perfil | Cómo se entra | Dónde aterriza |
|---|---|---|
| **Visitante** | Sin hacer nada | Todo el sitio público |
| **Cliente registrado** | http://localhost/ingresar · `registrado@icce.com` | Portal de cliente |
| **Administrador** | http://localhost/admin · `admin@icce.com` | Backoffice |

Hay una tercera cuenta, `ventas@icce.com` (misma contraseña), con rol de agente:
entra al backoffice igual que el administrador. Sirve para ver el CRM desde la
óptica de quien atiende cotizaciones.

> **Usa ventanas de incógnito distintas** para revisar dos perfiles a la vez.
> La sesión es la misma cookie para el sitio y el backoffice.

---

## 3. Recorrido sugerido — Visitante

Abre **http://localhost** sin iniciar sesión.

1. **Home.** El hero rota entre tres banners cada 7 segundos; los puntos de
   abajo permiten saltar. Baja y verás las seis secciones del sitemap: accesos
   por categoría, el bloque B2B de cotización masiva, productos con existencia,
   la marquesina de marcas, casos de éxito y blog técnico.
2. **Catálogo.** Pasa el cursor sobre "Catálogo" en el menú: se despliega el
   mega menú con las cinco familias y sus subcategorías.
3. **Buscador.** Da clic en la lupa del encabezado: se abre en cualquier página.
   Prueba con `llana`, `somero`, `sika` o directamente un SKU como `KRASK401`.
   Busca por nombre, descripción, SKU y marca a la vez.
4. **Filtros.** Dentro del catálogo tienes familia, disponibilidad y marca (con
   el conteo de cada una). Lo importante: **todo queda en la URL**. Filtra algo,
   copia la barra de direcciones y ábrela en otra pestaña — el estado se
   reconstruye igual. Eso hace los resultados compartibles e indexables.
   Prueba directo: `http://localhost/catalogo?q=llana&disp=disponible&orden=precio-desc`
5. **Ficha de producto.** Entra a cualquier producto. Fíjate en el recuadro que
   dice **"Compra directa"** o **"Solicitud de cotización"**: ahí se ve en vivo
   la regla del carrito híbrido (REQ-01/REQ-02).
   - Un producto con existencia y hasta 10 unidades → se cobraría en línea.
   - Uno marcado **"Bajo pedido"** → siempre pasa a cotización.
   - Prueba `/catalogo/control-de-juntas`, ahí hay varios de los dos tipos.
   - Si el producto tiene ficha técnica, aparece el botón de descarga del PDF.
6. **Centro de descargas.** En `/descargas` están las **97 fichas técnicas
   reales** que recuperé del sitio actual, filtrables por marca y buscables.
   Son los PDF originales del fabricante.
7. **Carrito híbrido — la prueba clave.** Arma un carrito mezclado a propósito:
   - Entra a un producto **con existencia** y agrégalo con cantidad **1**.
     El recuadro dice «Compra directa».
   - Vuelve a ese mismo producto y sube la cantidad a **40**: el recuadro cambia
     solo a «Solicitud de cotización» *antes* de que presiones nada.
   - Agrega ahora un producto **bajo pedido** (filtra por disponibilidad).
   - Ve al carrito: verás dos bloques separados, **«Se cobra en línea»** y
     **«Pasa a cotización»**, cada partida con el motivo por el que cayó ahí, y
     el resumen distinguiendo «Total hoy» de «Por cotizar (estimado)».
8. **Checkout.** Llena los datos. La pasarela está en **modo simulado**:
   cualquier tarjeta se aprueba; usa `4000 0000 0000 0002` para ver el camino
   del rechazo, y elige SPEI para ver el de pago pendiente.
   Al confirmar, un carrito mixto genera **dos folios**: uno `VD-` cobrado y
   uno `COT-` para que un agente lo trabaje.
9. **Correos.** Abre **http://localhost:8025** (Mailpit): ahí están los cuatro
   correos que dispara el pedido — confirmación al cliente y aviso a ventas,
   por cada una de las dos órdenes.
10. **Renta de equipos (REQ-06 / REQ-07).** Verás la separación entre cobertura
   nacional (reglas láser Somero) y local (equipo menor de Monterrey). Ningún
   equipo de renta tiene botón de compra: solo captación de lead.
   Entra a un equipo y usa **Solicitar este equipo**: el formulario abre con el
   equipo precargado. Cambia la cobertura a *Nacional* y verás que **se reescribe
   solo** — en local pregunta si entregamos o el cliente recoge; en nacional
   pregunta por flete, maniobra y acceso a la obra. Al enviar recibes un folio
   `RNT-` y dos correos en Mailpit.
10b. **Formulario de contacto.** En `/contacto`, envíalo: acuse en pantalla, dos
   correos, y el mensaje guardado en el backoffice para que no se pierda un
   prospecto si el correo falla.
11. **Blog y proyectos.** Cinco artículos técnicos y cinco casos de obra con sus
   metros cuadrados.
12. **Prueba el bloqueo.** Escribe `http://localhost/portal` en la barra: te
   manda al formulario de acceso.
13. **Migración SEO — pega una URL del sitio viejo.** Estas rutas ya no existen,
   pero el sitio las resuelve como si nada:
   - `http://localhost/Llanas-Herramientas-Para-Concreto.html` → 301 a `/catalogo/llanas`
   - `http://localhost/index.html` → 301 a la portada
   - `http://localhost/TiltUp.html` → **410 Gone** (es una de las secuestradas:
     redirigirla trasladaría su señal de spam a la estructura nueva)
   - `http://localhost/esto-nunca-existio.html` → 404 normal

   Y `http://localhost/sitemap.xml` declara **242 URLs**, contra la única que
   declaraba el sitio anterior teniendo 269 páginas reales.

**En móvil:** angosta la ventana a menos de 1024 px y el menú se convierte en un
panel lateral.

---

## 4. Recorrido sugerido — Cliente registrado

Entra en **http://localhost/ingresar** con `registrado@icce.com` / `Icce2026`.

Aterrizas en **Mi cuenta**. La cuenta viene con seis pedidos sembrados, uno por
cada estatus, para que veas la barra de seguimiento en todas sus posiciones:

| Folio | Tipo | Estatus |
|---|---|---|
| `VD-2026-00001` | Venta directa | Pagado |
| `VD-2026-00002` | Venta directa | En almacén |
| `VD-2026-00003` | Venta directa | En tránsito |
| `VD-2026-00004` | Venta directa | Entregado |
| `COT-2026-00001` | Cotización | Pendiente |
| `COT-2026-00002` | Cotización | Cotizado |

1. **Historial (7.1).** Cada tarjeta lleva su propio timeline. Compara
   `VD-2026-00001` con `VD-2026-00004` para ver la barra vacía y la completa.
2. **Timeline de rastreo (7.2).** Entra a `VD-2026-00003`: barra en "En
   tránsito", fecha estimada de entrega, número de guía y la bitácora con quién
   movió cada estatus y si se notificó al cliente.
3. **Detalle del pedido.** Partidas con SKU, precio unitario e importe; resumen
   con subtotal, IVA y total; dirección de entrega.
4. **Prueba el aislamiento.** Escribe `http://localhost/admin`: recibes un 403.
   Un cliente no toca el backoffice.

---

## 5. Recorrido sugerido — Administrador

Entra en **http://localhost/admin** con `admin@icce.com` / `Icce2026`.

El **escritorio** abre con cinco colas de trabajo —cotizaciones por atender,
pedidos en proceso, entregas vencidas, productos con stock bajo y leads de renta
nuevos— más la tabla de existencia por reponer. No son métricas de vanidad: son
lo que está esperando a alguien.

El menú lateral tiene tres grupos:

**Operación**
- **Pedidos y cotizaciones** — con pestañas por cola de trabajo y su contador.
  - **Cambiar estatus** mueve el pedido, sella la fecha del hito, escribe en la
    bitácora y —si dejas marcada la casilla— manda el correo al cliente. Prueba:
    mueve `VD-2026-00002` a «En tránsito», ponle fecha de entrega y una guía;
    luego abre el portal del cliente en otra ventana y verás la barra avanzada
    con esa fecha, y el correo en Mailpit.
  - **Enviar cotización** recalcula con los precios ajustados, aplica el
    descuento, fija vigencia y manda la propuesta **en PDF** al cliente.
    Los precios por partida se ajustan en «Ver detalle» → sección Partidas:
    escribe un «precio cotizado» y el de lista se conserva para poder auditar
    cuánto descuento se dio.
  - **Descargar PDF** te da la propuesta sin enviarla.
- **Solicitudes de renta** — los leads con su folio `RNT-`, asignación de agente
  y sellado automático de la fecha de primer contacto. Llegan del formulario
  adaptativo de `/renta/solicitar`.
- **Mensajes de contacto** — la bandeja del formulario general. El mensaje se
  guarda antes de enviarse por correo, así que un fallo de SMTP no cuesta un
  prospecto. La acción «Responder» abre el correo con el asunto ya armado.
- **Redirecciones SEO** — el mapa de migración del sitio anterior. Filtra por
  «Pendientes de mapeo» para cerrar las 27 rutas huérfanas, y por «Con visitas
  registradas» para ver qué enlaces externos siguen apuntando al sitio viejo.

**Catálogo**
- **Inventario** — ajuste de existencia por producto o **masivo** (sumar por
  entrada de mercancía, restar por merma, o fijar por conteo físico), umbral de
  alerta y filtros de stock bajo o agotado.
- **Categorías** — el árbol de dos niveles, con el conteo de productos directos.
- **Marcas** — las 19, con su logo y cuántos productos tiene cada una.

**Contenido**
- **Banners del home** — los tres del hero. Puedes reordenarlos arrastrando,
  cambiar textos y botones, o desactivar uno y ver cómo desaparece del sitio.
- **Blog técnico** — los cinco artículos. Al escribir un título nuevo el slug se
  propone solo. Vacía `Fecha de publicación` y el artículo desaparece del blog
  público: así se maneja un borrador. Hay un filtro "Sólo borradores".
- **Casos de éxito** — los cinco proyectos, reordenables.

**Prueba sugerida de punta a punta:** desactiva un banner en el backoffice,
recarga http://localhost en otra pestaña y comprueba que el hero ahora rota
entre dos.

---

## 6. Qué NO está construido todavía

Para que la revisión no confunda un pendiente con un defecto:

| Área | Estado | Fase |
|---|---|---|
| Cobro real (Stripe / Openpay) | Esbozado, falta credenciales | 4 |
| Correo de salida real (Mailgun / SendGrid) | Local con Mailpit | 4 |
| Portal: facturación y direcciones (7.3) | Pendiente | 5 |
| Aprobar y pagar cotización en línea | Pendiente | 5 |

### Datos que son marcadores

- **Precios y existencias.** El sitio actual no publica ninguno de los dos. Se
  generaron valores realistas por categoría para poder ejercitar la regla de
  compra vs. cotización. Se reemplazan con la carga del ERP.
- **Textos del blog.** Los títulos son los reales del sitio actual; los cuerpos
  los redacté sobre esos mismos temas porque los originales son PDFs
  incrustados sin texto recuperable.
- **Proyectos, teléfono y dirección.** Ejemplos. Falta el dato real de ICCE.
- **Imágenes.** Bajadas del sitio actual. Varias traen texto promocional
  incrustado (los recuadros rojos sobre las fotos) y algunos logos vienen con
  fondo gris en vez de transparente. Conviene pedir los originales limpios.

### Sobre las fichas técnicas

Las **97 fichas son reales**, recuperadas del sitio actual. Pesan 88 MB, así que
no se versionan en el repositorio: si clonas el proyecto en otra máquina,
recupéralas con

```bash
docker compose exec app php artisan icce:fetch-fichas
```

De los 173 productos, 97 tienen ficha y **16 de esos no tienen marca asignada**
—el heurístico que deduce la marca desde el nombre no alcanza para todos—. Se
corrige a mano desde el backoffice o con la carga del ERP.

---

## 7. Comandos útiles

```bash
# Detener todo (los datos sobreviven)
docker compose stop

# Volver a arrancar
UID=$(id -u) GID=$(id -g) docker compose up -d

# Regresar los datos a su estado inicial
docker compose exec app php artisan migrate:fresh --seed

# Ver los errores en vivo mientras navegas
docker compose exec app php artisan pail

# Recompilar el CSS tras un cambio de diseño
docker compose run --rm node npm run build

# Correr las pruebas (40 en total)
docker compose exec app php artisan test

# Borrar todo, incluida la base de datos
docker compose down -v
```

---

## 8. Si algo falla

| Síntoma | Causa probable | Solución |
|---|---|---|
| `http://localhost` no responde | El puerto 80 está ocupado | Usa http://localhost:8080 |
| Error 500 en cualquier página | Caché con datos viejos | `docker compose exec app php artisan optimize:clear` |
| El sitio se ve sin estilos | Falta compilar assets | `docker compose run --rm node npm run build` |
| "no such table" | Falta migrar | `docker compose exec app php artisan migrate:fresh --seed` |
| No entra con las credenciales | Se resembró la base | Vuelve a correr `migrate:fresh --seed` |

---

## 9. Aviso de seguridad sobre el sitio actual

Durante el rastreo de `icce.com.mx` encontré **siete páginas secuestradas** que
sirven spam de casas de apuestas (1win, KOKO5000, LINETOGEL, YOWESTOGEL,
MANCINGDUIT) con rastreadores de terceros:

- `/Desbaste-y-Abrillantado-Metales.html`
- `/Perforadoras-Husqvarna-Venta.html`
- `/TiltUp.html`
- `/Materiales-para-Control-de-Juntas/AquaFlex-Techos-Fibrados.html`
- `/Estampado-de-Concreto/Moldes.html`
- `/Estampado-de-Concreto/Color-Hardener.html`
- `/Estampado-de-Concreto/Sellador-Claro.html`

En el proyecto nuevo quedaron marcadas como **410 Gone**, nunca como 301:
redirigirlas trasladaría a la estructura nueva la señal de spam que Google ya
asoció a esas rutas.

Esto requiere atención en el hosting actual —limpieza del servidor y revisión de
acciones manuales en Search Console— y queda fuera del alcance de este
desarrollo.
