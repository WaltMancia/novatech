# Operaciones y monitoreo (Fase 6)

Flujo de tráfico y de señales de seguridad:

```
Cliente ─► WAF (ModSecurity + OWASP CRS) ─► Nginx (SPA + proxy) ─► PHP-FPM (API + RASP) ─► MariaDB
              │ audit.log (JSON)                                      │ app.log (JSON)
              └──────────────────────┬────────────────────────────────┘
                                     ▼
                         Wazuh (manager + indexador + panel): reglas, correlación, alertas
```

> **Alcance de la verificación.** WAF: probado con nginx + ModSecurity v3 + OWASP CRS 4.30.0 y tráfico real. RASP: pruebas automatizadas y servidor real con MariaDB. Reglas de Wazuh: validadas contra logs reales con un simulador offline (las de correlación requieren `wazuh-logtest`). Kubernetes: validación estática (kubeconform y Checkov). La pila Docker completa, Wazuh en ejecución y un clúster real no se han ejecutado todavía: el primer arranque lo validará `security-scan.yml`.

## 5.1 WAF

Servicio `waf` en `docker-compose.yml` (imagen oficial `owasp/modsecurity-crs:nginx`). Es la **única entrada pública** (puerto 8080); `web` solo es accesible desde él.

| Ajuste | Valor | Motivo |
|---|---|---|
| `MODSEC_RULE_ENGINE` | `On` | Bloquea. Para afinar sin bloquear: `DetectionOnly` |
| `BLOCKING_PARANOIA` | `1` | Menos falsos positivos; subir a 2 tras revisar el log |
| `ANOMALY_INBOUND` / `OUTBOUND` | `5` / `4` | Umbral estándar de CRS |
| `ALLOWED_METHODS` | `GET HEAD POST OPTIONS PUT PATCH DELETE` | **Imprescindible**: CRS solo permite GET/HEAD/POST/OPTIONS por defecto y la API usa PUT, PATCH y DELETE |
| Exclusiones | `ops/waf/REQUEST-900-EXCLUSION-RULES-BEFORE-CRS.conf` | Ver abajo |

**Exclusión justificada.** El campo `password` de `/api/auth/login` y `/api/auth/register` se excluye de las familias de inyección. Una contraseña legítima puede parecerse a un ataque (por ejemplo `' OR '1'='1' --`) y se bloquearía a un usuario real. Es seguro porque la contraseña nunca se concatena en SQL (consultas preparadas) ni se muestra en HTML (solo se compara con Argon2id). Cualquier otro campo, incluso del mismo endpoint, sigue inspeccionado.

**Evidencia** (`node scripts/waf-check.mjs`, 18 comprobaciones):
- Bloquea los 10 ataques probados: SQLi (query, `UNION SELECT` y dentro de JSON), XSS (query y JSON), recorrido de directorios, inyección de comandos, Log4Shell, escáner (`sqlmap`) y método no permitido.
- Deja pasar el tráfico legítimo, incluido un correo con apóstrofo, contraseñas con símbolos y los métodos PUT/PATCH/DELETE.
- Sin la exclusión, el WAF bloqueaba la contraseña que se parece a una inyección SQL: el falso positivo que motivó la regla.
- Los scripts de compra y administración (`smoke*.mjs`) pasan completos a través del WAF.

**Cómo afinar falsos positivos:** poner `DetectionOnly`, usar la aplicación, revisar `audit.log` (JSON) buscando `ruleId` y el campo (`ARGS:json.xxx`), y añadir una exclusión **lo más estrecha posible** (ruta + campo + familia). Nunca bajar el nivel de paranoia de forma global.

**Límite del WAF:** no entiende la lógica de negocio. Por eso existe el RASP (siguiente sección). TLS se termina fuera de este contenedor (Cloudflare o Ingress; ver [CLOUDFLARE.md](CLOUDFLARE.md)).

## 5.2 RASP: protección en tiempo de ejecución

`Http/Middleware/RuntimeProtection` observa las respuestas de la API y reacciona por IP: emite un evento estructurado (log JSON + auditoría) y bloquea temporalmente (HTTP 429 `temporarily_blocked` con `Retry-After`).

| Detección | Umbral por defecto | Evento | Variable |
|---|---|---|---|
| Ráfaga de errores de cliente (escáner o fuzzing) | 30 en 60 s | `anomaly.error_burst` | `SECURITY_ERROR_BURST` |
| Enumeración de pedidos ajenos (BOLA/IDOR) | 8 en 5 min | `anomaly.idor_enumeration` | `SECURITY_IDOR_THRESHOLD` |
| Fallos CSRF / Origin | 5 en 5 min | `anomaly.csrf_burst` | `SECURITY_CSRF_THRESHOLD` |
| Duración del bloqueo | 10 min | | `SECURITY_BAN_SECONDS` |

Decisiones: el 401 anónimo de `/auth/me` no cuenta (la SPA lo consulta al cargar); el evento se emite una sola vez por ventana; el bloqueo es por IP. **Compromiso asumido:** usuarios tras una misma IP compartida (NAT) podrían verse afectados si alguien de su red dispara un umbral; los umbrales son altos para legítimos. El almacén de contadores (`RateLimitStore`) es reemplazable por Redis.

## 5.3 SIEM: Wazuh

Se usa el despliegue oficial `single-node` de Wazuh (manager, indexador y panel) con la configuración de NovaTech superpuesta; el repositorio oficial **no se copia**: `scripts/wazuh.mjs` lo clona (versión fijada) y genera la configuración.

```powershell
node scripts/wazuh.mjs up            # clona, genera certificados y configuración, levanta Wazuh y NovaTech con logs a archivo
node scripts/wazuh.mjs logtest       # envía ops/wazuh/samples.log a wazuh-logtest y muestra qué regla dispara
node scripts/wazuh.mjs status
node scripts/wazuh.mjs down          # --purge borra también los datos
```

Requisitos: unos 6 GB de RAM para Docker; en Docker Desktop (WSL2), `wsl -d docker-desktop sysctl -w vm.max_map_count=262144`. El panel queda en `https://localhost` (certificado autofirmado) con las credenciales **por defecto del laboratorio** (`admin` / `SecretPassword`): cámbialas si el equipo es accesible desde otras máquinas.

Fuentes: `/var/log/novatech/app.log` (API: `http_request`, `audit`, `security_event`) y `/var/log/modsecurity/audit/modsec_audit.log` (WAF). Los archivos crecen sin rotación: es un entorno de laboratorio; `node scripts/wazuh.mjs down --purge` y `docker volume rm novatech_applogs novatech_waflogs` los limpian.

### Reglas (`ops/wazuh/local_rules.xml`, IDs 100100-100202)

| ID | Nivel | Qué detecta | MITRE |
|---|---|---|---|
| 100110 | 5 | Inicio de sesión fallido | T1110 |
| 100111 | 10 | **Fuerza bruta**: 8 fallos en 2 min desde la misma IP (correlación) | T1110 |
| 100112 | 8 | Intento sobre una cuenta bloqueada | T1110 |
| 100120 / 100121 | 6 / 9 | Fallo CSRF/Origin / ráfaga de 5 en 5 min | T1190 |
| 100122 | 6 | Límite de tasa superado | |
| 100123 / 100124 | 7 / 10 | Acceso denegado por rol / insistencia del mismo usuario (posible escalamiento) | T1078 |
| 100101 | 8 | Error 5xx de la API | |
| 100130 | 5 | Acción administrativa (trazabilidad) | |
| 100140-100142 | 10-12 | Eventos RASP, incluida la **enumeración de pedidos (BOLA/IDOR)** | T1190, T1595 |
| 100201 / 100202 | 8 / 12 | **WAF** bloqueó una petición / 5 bloqueos de la misma IP (escaneo o ataque) | T1190, T1595 |

Validación: `python3 scripts/check_wazuh_rules.py` comprueba, sin Wazuh, que cada una de las 10 líneas reales de `samples.log` dispara la regla esperada (se ejecuta en el CI). Las 4 reglas de correlación (`frequency`) se comprueban con `node scripts/wazuh.mjs logtest`.

## 5.4 Alertas y paneles

En el panel de Wazuh (Threat Hunting → Events), filtra por `rule.groups: novatech` y guarda estas vistas:
- **Autenticación:** `rule.id: (100110 or 100111 or 100112)` (picos = fuerza bruta o *credential stuffing*).
- **Ataques web:** `rule.id: (100201 or 100202)` agrupado por `data.transaction.client_ip`.
- **Control de acceso:** `rule.id: (100123 or 100124 or 100141)` (BOLA y escalamiento).
- **Administración:** `rule.id: 100130` (quién cambió qué).
- **Salud:** `rule.id: 100101` (errores 5xx).

| Nivel | Respuesta |
|---|---|
| 5-7 | Informativo: revisión diaria |
| 8-9 | Revisar el mismo día |
| ≥ 10 | Atender de inmediato (el RASP ya habrá bloqueado la IP) |

Respuesta mínima ante una alerta ≥ 10: 1) confirmar en `audit_logs` (panel de administración → Auditoría) y en el log del WAF; 2) identificar usuario e IP; 3) si hay cuenta comprometida, rotar su contraseña y revisar sus pedidos; 4) registrar el incidente y, si procede, ajustar una regla o un umbral.

## 5.5 Gestión continua de vulnerabilidades

| Fuente | Cadencia |
|---|---|
| CI (SAST, SCA, secretos, IaC) | Cada push y PR |
| Escaneo completo (ZAP, Trivy, pruebas de humo, WAF) | Lunes 06:00 UTC, manual y por cada tag |
| Dependabot (npm, composer, Docker, Actions) | Semanal |

Plazos objetivo de corrección: **crítica 48 h, alta 7 días, media 30 días, baja a la siguiente fase**. Cada hallazgo se registra con evidencia (informe o commit) y, si cambia un riesgo, se actualiza [RISK_REGISTER.md](RISK_REGISTER.md).

## 5.6 Tratamiento del riesgo

Ver [RISK_REGISTER.md](RISK_REGISTER.md): 20 riesgos tratados con Aceptar, Eliminar, Evitar, Mitigar o Transferir; la matriz de calor pasa de **12 riesgos críticos a 0** y quedan 2 en nivel Alto (R13 infraestructura sin validar en un clúster real y R17 administrador sin MFA).

## Kubernetes (despliegue alternativo)

Manifiestos en `deploy/k8s/` (kustomize): espacio de nombres con Pod Security `restricted`, `NetworkPolicy` de denegación por defecto con flujos explícitos (Ingress → WAF → web → app → db), contenedores sin root, de solo lectura y sin capacidades, secretos montados como archivos y el mismo WAF delante de la SPA. Ver [deploy/k8s/README.md](../deploy/k8s/README.md).

Validación estática: `kubeconform` (20 recursos válidos) y Checkov (441 comprobaciones superadas, 0 fallos, 14 exclusiones justificadas en los propios manifiestos).

**Limitación conocida:** la API corre con una réplica porque la sesión y el límite de tasa usan archivos locales. Para escalar horizontalmente hay que implementar `Session` y `RateLimitStore` sobre Redis (las interfaces ya existen).

## Hoja de ruta de seguridad continua
1. MFA (TOTP) para la cuenta de administración (R17).
2. Sesión y límite de tasa en Redis para escalar la API.
3. Copias de seguridad cifradas y prueba de restauración de la base de datos.
4. Validar la pila en un clúster real con un CNI que aplique `NetworkPolicy` (Calico o Cilium).
5. Fijar por *digest* las imágenes de terceros (MariaDB y WAF).
