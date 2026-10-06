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

## 5. Decisiones (ADR resumidas)

1. **PHP puro + Composer solo para autoload.** Cumple "sin frameworks"; Composer no añade código de runtime.
2. **SPA + API JSON** (en vez de HTML en servidor): permite evaluar OWASP API Top 10 (BOLA/IDOR) y encaja con el ciclo de seguridad de APIs.
3. **Sesión con cookie + CSRF** en vez de JWT en `localStorage`: evita exponer tokens a XSS. Si el catedrático pide JWT, se añade como variante documentada.
4. **Seguro por defecto, vulnerable por rama.** `main` es la versión endurecida. La versión base para el pentest (Fase 7) será una rama/etiqueta `baseline` creada quitando controles de forma deliberada y documentada, solo para el laboratorio local.
5. **Dinero en centavos (INT)**, snapshot del precio en `order_items` y stock descontado dentro de una transacción.

## 6. Plan por fases

| Fase | Entrega | Estado |
|---|---|---|
| **0** | Estructura, Docker, núcleo, controles transversales, esquema BD, scaffold React, pruebas | ✅ |
| **1** | Autenticación: registro, login, logout, `/me`; Argon2id; bloqueo por intentos; rotación de sesión y CSRF; roles; middleware `auth` y `role` | ⏳ |
| **2** | Catálogo (10 productos, seed), carrito, pedidos, pago simulado; autorización por objeto; transacciones | ⏳ |
| **3** | Panel de administración (CRUD de productos, pedidos), auditoría | ⏳ |
| **4** | Frontend completo: catálogo, carrito, checkout, cuenta, admin | ⏳ |
| **5** | CI/CD: Semgrep, Dependency-Check/`composer audit`, Trivy, Checkov, ZAP baseline; PHPUnit | ⏳ |
| **6** | Operaciones: WAF/ModSecurity, Cloudflare, SIEM (Wazuh/ELK), manifiestos K8s mínimos | ⏳ |
| **7** | Rama `baseline` vulnerable, pentest caja negra/gris, casos de abuso, heatmap antes/después | ⏳ |
