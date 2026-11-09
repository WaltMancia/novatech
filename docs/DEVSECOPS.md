# Pipeline DevSecOps (Fase 5)

## Visión general

```
git push / PR ─► ci.yml (rápido, cada push) ───────────────────────────────────────────┐
                 ├─ backend    PHPStan nivel 8 · pruebas · composer validate/audit      │
                 ├─ frontend   pruebas · build estricto · npm audit                     ├─► ✅ / ❌
                 ├─ sast       Semgrep (reglas propias bloqueantes + comunidad)         │
                 ├─ secrets    gitleaks sobre todo el historial                         │
                 └─ iac        Checkov (Dockerfiles, workflows) · actionlint            ┘

manual · lunes 06:00 UTC · tag v* ─► security-scan.yml (pesado, pila completa)
                 build + compose up ─► migraciones/semilla ─► pruebas de humo (compra y admin)
                 ─► DAST: ZAP baseline ─► Trivy repo ─► Trivy imágenes ─► informes (artefactos)
```

## Herramientas y qué cubren

| Etapa del ciclo | Herramienta | Qué verifica | ¿Bloquea? |
|---|---|---|---|
| SAST | **PHPStan** nivel 8 | Tipos, nulos, contratos entre capas | Sí |
| SAST | **Semgrep** con `semgrep-rules/` | Reglas del proyecto: SQL dinámico, funciones peligrosas, hash débil, superglobales, `dangerouslySetInnerHTML`, `eval`, `fetch` fuera del cliente de API | Sí |
| SAST | Semgrep `p/php`, `p/typescript`, `p/react`, `p/owasp-top-ten` | Reglas de la comunidad | No (informe) |
| SCA | `composer audit`, `npm audit` | CVE en dependencias (producción: bloquea en severidad alta) | Sí (producción) |
| SCA | **Trivy** `fs` | Vulnerabilidades en lockfiles | Sí (alta/crítica) |
| Secretos | **gitleaks** | Secretos en el árbol y en todo el historial | Sí |
| IaC | **Checkov** | Dockerfiles y workflows de GitHub Actions | Sí |
| IaC | **Trivy** `fs` (misconfig) | Configuración de Dockerfile | Sí (alta/crítica) |
| Contenedores | **Trivy** `image` | CVE del SO base y paquetes (con corrección disponible); imagen del WAF solo informativa | Sí (alta/crítica) |
| IaC (Kubernetes) | **kustomize + kubeconform + Checkov** | Esquema y seguridad de `deploy/k8s` | Sí |
| WAF | `scripts/waf-check.mjs` | Ataques bloqueados y tráfico legítimo permitido a través del WAF | Sí (en el escaneo completo) |
| SIEM | `scripts/check_wazuh_rules.py` | Reglas de Wazuh contra logs reales | Sí |
| DAST | **OWASP ZAP** baseline | Escaneo pasivo + araña de la SPA y la API en ejecución | Solo FAIL (ver `.zap/rules.tsv`) |
| Cadena de suministro | **Dependabot** | Actualizaciones semanales de npm, composer, Docker y Actions | PRs |
| Integración | `scripts/smoke*.mjs` en la pila real | Flujo de compra y panel admin sobre contenedores | Sí |

Buenas prácticas aplicadas al pipeline: acciones fijadas por SHA de commit, permisos mínimos (`contents: read`), `persist-credentials: false`, binarios descargados con checksum verificado, secretos efímeros generados en cada ejecución, `npm ci --ignore-scripts` y cancelación de ejecuciones obsoletas.

## Evidencia obtenida al construirlo

| Herramienta | Resultado inicial | Acción | Resultado final |
|---|---|---|---|
| PHPStan nivel 8 | 59 hallazgos | Tipos precisos en router, contenedor y repositorios; contrato de `transaction()` corregido; `json_encode` con `JSON_THROW_ON_ERROR` | 0 |
| Semgrep (reglas propias) | 10 puntos de SQL dinámico | Revisados uno a uno: fragmentos constantes, marcadores `?` o enteros tipados. Documentados con `nosemgrep` y motivo | 0 sin excepciones ocultas |
| Checkov | 3 hallazgos en Dockerfiles (`HEALTHCHECK`, `USER`) | Corregidos | 86/86 y 208/208 |
| npm audit | 1 vulnerabilidad **alta** en `vite` (servidor de desarrollo) | `npm audit fix` | 0 |
| gitleaks | 4 falsos positivos (contraseñas ficticias de pruebas) | Allowlist acotado a esos valores; se verificó que aún detecta secretos reales | 0 |
| Pruebas de reglas Semgrep | — | 10/10 casos (reglas detectan lo malo y no lo bueno) | 10/10 |
| Checkov (Kubernetes) | 19 fallos en los manifiestos | Secretos como archivos, `imagePullPolicy`, y 14 exclusiones justificadas en el propio manifiesto | 441 superadas, 0 fallos |
| WAF (CRS 4.30.0) | Falso positivo: contraseña con aspecto de SQLi bloqueada | Exclusión estrecha solo para `password` en login/registro | 18/18 |

Excepciones vigentes: 10 comentarios `nosemgrep` (`grep -rn nosemgrep backend`), 2 reglas ZAP ignoradas (`.zap/rules.tsv`), 5 contraseñas de prueba en `.gitleaks.toml`, ningún `skip-check` de Checkov.

## Ejecución del escaneo completo
El escaneo levanta la pila **con el WAF como entrada**: las pruebas de humo y `waf-check` pasan por él; ZAP escanea la aplicación **sin** pasar por el WAF (por la red interna de Docker) para evaluar la propia aplicación y no los bloqueos del WAF.

## Primera ejecución (checklist)
1. Haz `push`; abre **Actions → CI** y confirma que los 5 jobs pasan.
2. Ejecuta **Actions → Security scan → Run workflow** una vez para validar la pila Docker completa.
3. Descarga el artefacto `security-reports` (informe HTML de ZAP, JSON de Trivy) y revisa las alertas.
4. Si Trivy falla por CVE del SO base con corrección disponible: reconstruye con la imagen base más reciente o, si es un falso positivo, regístralo en un `.trivyignore` con motivo y fecha de revisión.
5. Si Trivy no puede descargar su base de datos por límite de peticiones, reintenta más tarde o define `TRIVY_DB_REPOSITORY=public.ecr.aws/aquasecurity/trivy-db:2`.

## Ejecutar las verificaciones en local (PowerShell, Docker)
```powershell
# SAST - reglas propias y sus pruebas
docker run --rm -v "${PWD}:/src" semgrep/semgrep semgrep --test --metrics=off semgrep-rules
docker run --rm -v "${PWD}:/src" semgrep/semgrep semgrep scan --config semgrep-rules --error --metrics=off backend frontend/src

# SAST - PHPStan (descarga el phar una vez)
New-Item -ItemType Directory -Force tools | Out-Null
Invoke-WebRequest https://github.com/phpstan/phpstan/releases/download/2.3.4/phpstan.phar -OutFile tools\phpstan.phar
docker run --rm -v "${PWD}:/app" -w /app php:8.3-cli php tools/phpstan.phar analyse -c phpstan.neon --no-progress

# IaC y secretos
docker run --rm -v "${PWD}:/src" -w /src bridgecrew/checkov --config-file .checkov.yaml
docker run --rm -v "${PWD}:/repo" ghcr.io/gitleaks/gitleaks:v8.30.1 dir /repo -c /repo/.gitleaks.toml --redact --no-banner

# SCA del frontend
cd frontend; npm audit --omit=dev --audit-level=high; cd ..
```

## Notas
- Con desarrollo directo a `main`, el CI actúa como red de seguridad posterior al `push`. Al incorporar más personas, activa la protección de rama exigiendo estos checks en Pull Requests.
- ZAP baseline es un escaneo pasivo: no ataca activamente. El pentest de caja negra y gris (ataques activos, casos de abuso) corresponde a la Fase 7.
- El lockfile de Composer está vacío porque el backend no usa paquetes de terceros; el job de SCA lo detecta y se activará solo cuando se agreguen dependencias.
