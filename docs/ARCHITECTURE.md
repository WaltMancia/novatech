# Arquitectura de NovaTech

## 1. Visión general

```
Navegador ──HTTPS──► [Cloudflare/WAF*] ──► Nginx (SPA + proxy) ──FastCGI──► PHP-FPM (API) ──► MariaDB
                                           red "edge"                        red "edge"+"data"   red "data" (interna)
```
\* Capa de Fase 6.

- **SPA** (React) servida por Nginx; llama a `/api/*` en el mismo origen (sin CORS).
- **API JSON** en PHP puro. Autenticación por **sesión con cookie** (`HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS) + **token CSRF**.
- **MariaDB** sin puertos publicados, en una red Docker `internal` (sin salida a Internet).

## 2. Patrón de diseño

**Front Controller + capas (Controller → Service → Repository) con inyección de dependencias.**

| Capa | Responsabilidad | Regla |
|---|---|---|
| Middleware | Aspectos transversales: contexto, cabeceras, errores, rate limit, CSRF, autenticación, autorización | Orden fijo y probado |
| Controller | Traducir HTTP ⇄ casos de uso. Valida entrada con `Validator` | Sin SQL ni reglas de negocio |
| Service | Reglas de negocio y autorización a nivel de objeto (dueño del pedido) | Sin HTTP |
| Repository | Único lugar con SQL; solo consultas preparadas | Sin reglas de negocio |
| Domain | Entidades y enums inmutables | Sin dependencias |

Por qué: separa responsabilidades para poder auditarlas (SAST), probarlas y asignar controles por capa. Las dependencias se resuelven con el `Container` (autowiring), lo que permite cambiar implementaciones (`RateLimitStore`: archivos → Redis; `Session`: nativa → BD) sin tocar el resto.

## 3. Ciclo de una petición

```
index.php → RequestContext → SecurityHeaders → HandleErrors → throttle(120/min) → VerifyCsrf → Router → [middleware de ruta] → Controller
```
`HandleErrors` convierte cualquier excepción en JSON uniforme sin filtrar detalles; `SecurityHeaders` y `RequestContext` envuelven también las respuestas de error.

## 4. Controles implementados en la Fase 0 (mapa a las 7 capas)

| Capa | Control |
|---|---|
| 1. Datos | Esquema con restricciones (`CHECK`, FK, `UNIQUE`); precios en centavos (enteros); `password_hash` previsto con Argon2id (Fase 1) |
| 2a. Código | `declare(strict_types=1)`; `Validator` por lista blanca (anti mass assignment, tipos estrictos, rechazo de caracteres de control); consultas preparadas con `EMULATE_PREPARES=false`; log con redacción de secretos |
| 2b. Aplicación | CSRF (token sincronizado + validación de `Origin`); sesión con expiración por inactividad (30 min) y absoluta (8 h); errores sin fuga de información |
| 2c. Implementación | `composer.json` mínimo (sin dependencias de runtime); `package-lock.json` fijado; `npm ci` en la imagen |
| 2d. Perímetro app | Rutas explícitas; 404/405 controlados; `Content-Type` JSON obligatorio; límite de cuerpo (1 MB); límite de tasa por IP y ruta |
| 3. Host | PHP: `expose_php=Off`, `disable_functions`, `open_basedir`, `allow_url_fopen=Off`; contenedores `read_only`, `cap_drop: ALL`, `no-new-privileges`, usuario no root, Nginx unprivileged |
| 4. Red interna | Redes `edge` y `data` (interna); BD sin puertos al host |
| 5. Perímetro cloud | Cabeceras CSP/HSTS/frame-ancestors; WAF/CDN en Fase 6 |
| 6. Física | Delegada al proveedor cloud (documentar certificaciones en el informe) |
| 7. Políticas | Guardarraíl: `APP_DEBUG=true` imposible en producción; secretos solo por entorno (`.env` fuera del repo) |

## 5. Autenticación y autorización (Fase 1)

| Endpoint | Acceso | Límite de tasa |
|---|---|---|
| `POST /api/auth/register` | Público | 5 por 10 min por IP |
| `POST /api/auth/login` | Público | 10 por min por IP |
| `POST /api/auth/logout` | Sesión | — |
| `GET /api/auth/me` | Sesión | — |

Controles: Argon2id (19 MiB, 2 iteraciones); bloqueo de cuenta 15 min tras 5 fallos; respuesta idéntica y tiempo equivalente para usuario inexistente, clave errónea y cuenta bloqueada; regeneración del ID de sesión y rotación del token CSRF al autenticar; el usuario se recarga desde la BD en cada petición; el registro rechaza campos no declarados (`role`); toda acción relevante queda en `audit_logs`.

Riesgos aceptados y a documentar en el informe: el registro revela si un correo existe (mitigado con límite de tasa); el bloqueo por cuenta permite a un atacante bloquear a una víctima (mitigado con límite por IP y duración corta).

## 6. Catálogo, carrito y pedidos (Fase 2)

| Endpoint | Acceso | Notas |
|---|---|---|
| `GET /api/products`, `GET /api/products/{id}` | Público | Solo productos activos |
| `GET/DELETE /api/cart`, `POST /api/cart/items`, `PUT/DELETE /api/cart/items/{productId}` | Sesión | Carrito en la sesión del servidor |
| `POST /api/orders` | Sesión | 10 por min por IP. Cuerpo: `{ "paymentMethod": "simulated_success" \| "simulated_decline" }` |
| `GET /api/orders`, `GET /api/orders/{id}` | Sesión | Solo pedidos propios |

Controles:
- **Precios solo del servidor.** El carrito guarda `productId` y cantidad; el total se calcula desde la base de datos. Cualquier campo extra (`priceCents`, `totalCents`) se rechaza con 422.
- **BOLA/IDOR.** `OrderRepository::findForUser` filtra por `id` **y** `user_id` en el SQL; un pedido ajeno devuelve 404 (no 403) para no revelar su existencia.
- **Integridad de inventario.** El checkout bloquea las filas (`SELECT ... FOR UPDATE`, orden por id), valida stock, crea pedido e ítems y descuenta con `UPDATE ... WHERE stock >= ?` dentro de una transacción. Probado con compradores simultáneos de la última unidad: nunca hay sobreventa.
- **Snapshot de precio** en `order_items.unit_price_cents`.
- **Pago simulado fuera de la transacción**, como una pasarela real: si se rechaza, se libera el stock, el pedido queda `failed` y el carrito se conserva (402). Nunca se reciben datos de tarjeta, por lo que PCI DSS queda fuera de alcance.
- **Límites:** 1 a 10 unidades por producto y nunca más que el stock; el carrito no se hereda entre cuentas.
- **Auditoría:** `order.created`, `order.paid`, `order.payment_failed`.

Pendiente a documentar como riesgo: no hay clave de idempotencia en `POST /api/orders` (un doble envío concurrente podría crear dos pedidos; el límite de tasa lo mitiga).

## 7. Administración (Fase 3)

Toda la superficie `/api/admin/*` pasa por `Authenticate` y luego `role:admin` (deniega por defecto).

| Endpoint | Función |
|---|---|
| `GET/POST /api/admin/products`, `GET/PATCH/DELETE /api/admin/products/{id}` | Catálogo completo (incluye inactivos). `DELETE` es baja lógica |
| `GET /api/admin/orders`, `GET /api/admin/orders/{id}` | Pedidos de todos los clientes, filtro `?status=` |
| `POST /api/admin/orders/{id}/cancel` | Cancela un pedido **pagado** y devuelve el stock |
| `GET /api/admin/users` | Usuarios sin datos sensibles |
| `GET /api/admin/audit-logs` | Eventos, filtros `?action=` y `?userId=` |

Controles:
- **Validación por campo** (SKU `[A-Z0-9-]{3,32}`, precio y stock con rangos, `imagePath` solo `/images/<nombre>.png|jpg|jpeg|webp`): bloquea URLs externas, esquemas `javascript:` y recorrido de directorios. Sin subida de archivos (decisión de alcance).
- **Lista blanca de campos**: crear y editar rechazan campos extra; el `UPDATE` construye las columnas desde un mapa fijo, nunca desde la entrada.
- **Concurrencia**: la cancelación usa una transición condicional (`UPDATE ... WHERE status = 'paid'`) dentro de una transacción; dos cancelaciones simultáneas no devuelven el stock dos veces.
- **Paginación acotada** (`perPage` ≤ 100) con valores tipados.
- **Trazabilidad**: `admin.product_created`, `admin.product_updated` (con los campos modificados), `admin.product_deactivated`, `admin.order_cancelled`.

Riesgos a documentar: la cuenta administradora es un objetivo de alto valor (sin MFA en el alcance; mitigar con contraseña larga, bloqueo por intentos y auditoría) y no existe endpoint para cambiar roles (se crean solo por CLI).

## 8. Frontend (Fase 4)

SPA con React 19, TypeScript estricto, Tailwind 4 y `react-router-dom`.

| Ruta | Acceso |
|---|---|
| `/`, `/products/:id`, `/login`, `/register` | Público |
| `/cart`, `/checkout`, `/orders`, `/orders/:id` | Sesión (`RequireAuth`) |
| `/admin/products`, `/admin/orders`, `/admin/users`, `/admin/audit` | Rol admin (`RequireRole`) |

Estructura: `lib/` (cliente de API, autenticación, carrito, formato), `components/` (layout, guardas, UI), `pages/` y `pages/admin/`.

Decisiones y controles:
- **Las guardas de ruta son solo experiencia de usuario.** La autorización real la aplica el servidor; un cliente que salte la guarda recibe 401/403 de la API.
- **Cliente de API centralizado:** token CSRF obtenido una vez y enviado en cada petición que cambia estado, un único reintento ante `csrf_mismatch`, errores tipados (`ApiError`) y reinicio del token tras login/logout.
- **Sin HTML inyectado:** React escapa todo el contenido; no se usa `dangerouslySetInnerHTML`. Los detalles de auditoría se muestran como texto.
- **El carrito del cliente es un reflejo del servidor:** los precios y totales nunca se calculan en el navegador.
- **Imágenes locales** (`/images/<sku>.webp`, servidas por Nginx): cumplen la CSP `img-src 'self'` y el patrón validado en el backend; sin recursos de terceros. Si una imagen falla, se muestra un marcador.
- **Pruebas (Vitest + Testing Library):** cliente de API (CSRF, reintento, errores) y guardas de ruta (anónimo, cliente, administrador, estado de carga).

Riesgo a documentar: el estado de sesión del frontend (usuario, rol) es informativo; manipularlo en el navegador no concede acceso.

## 9. Pipeline DevSecOps (Fase 5)

Dos workflows en `.github/workflows/`: `ci.yml` (cada push y PR: PHPStan nivel 8, pruebas, SAST con Semgrep y reglas propias, gitleaks, Checkov, actionlint, `npm audit`) y `security-scan.yml` (manual, semanal y por tag: pila completa, pruebas de humo, ZAP baseline, Trivy). Detalle, evidencia y comandos locales en [DEVSECOPS.md](DEVSECOPS.md).

Decisión: las reglas de Semgrep del proyecto convierten las normas de arquitectura (SQL solo con parámetros, entrada solo vía `Request`, cliente de API único en el frontend) en controles automáticos.

## 10. Decisiones (ADR resumidas)

1. **PHP puro + Composer solo para autoload.** Cumple "sin frameworks"; Composer no añade código de runtime.
2. **SPA + API JSON** (en vez de HTML en servidor): permite evaluar OWASP API Top 10 (BOLA/IDOR) y encaja con el ciclo de seguridad de APIs.
3. **Sesión con cookie + CSRF** en vez de JWT en `localStorage`: evita exponer tokens a XSS. Si el catedrático pide JWT, se añade como variante documentada.
4. **Seguro por defecto, vulnerable por rama.** `main` es la versión endurecida. La versión base para el pentest (Fase 7) será una rama/etiqueta `baseline` creada quitando controles de forma deliberada y documentada, solo para el laboratorio local.
5. **Dinero en centavos (INT)**, snapshot del precio en `order_items` y stock descontado dentro de una transacción.

## 11. Plan por fases

| Fase | Entrega | Estado |
|---|---|---|
| **0** | Estructura, Docker, núcleo, controles transversales, esquema BD, scaffold React, pruebas | ✅ |
| **1** | Autenticación: registro, login, logout, `/me`; Argon2id; bloqueo por intentos; rotación de sesión y CSRF; roles; middleware `Authenticate` y `role` | ✅ |
| **2** | Catálogo (10 productos, seed), carrito, pedidos, pago simulado; autorización por objeto; transacciones | ✅ |
| **3** | Panel de administración (CRUD de productos, pedidos, usuarios), consulta de auditoría | ✅ |
| **4** | Frontend completo: catálogo, carrito, checkout, cuenta, admin | ✅ |
| **5** | CI/CD: PHPStan, Semgrep, gitleaks, Checkov, `composer`/`npm audit`, Trivy, ZAP baseline, Dependabot | ✅ |
| **6** | Operaciones: WAF/ModSecurity, Cloudflare, SIEM (Wazuh/ELK), manifiestos K8s mínimos | ⏳ |
| **7** | Rama `baseline` vulnerable, pentest caja negra/gris, casos de abuso, heatmap antes/después | ⏳ |
