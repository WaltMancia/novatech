# NovaTech

E-commerce de tecnología para el proyecto final de **Seguridad en Aplicaciones** (UMG, Maestría en Seguridad Informática).
Backend en **PHP 8.3 puro (sin frameworks)**, frontend **React + TypeScript + Tailwind**, despliegue con **Docker**.

Alcance: 10 productos · 2 roles (cliente, administrador) · pagos simulados.

## Inicio rápido

```bash
cp .env.example .env        # cambia las contraseñas
make up                     # construye y levanta web + app + db
make migrate                # crea el esquema de base de datos
open http://localhost:8080  # panel de estado de la Fase 0
make test                   # pruebas del núcleo (requiere PHP 8.3 local)
```

Desarrollo del frontend con recarga en caliente: `cd frontend && npm install && npm run dev` (puerto 5173, proxy a `/api`).

## Estructura

```
backend/            API PHP (public/index.php = único punto de entrada)
  src/Core/         Núcleo: Container, Router, Request/Response, Validator, Logger, Database
  src/Http/         Controllers y Middleware
  src/Services/     Reglas de negocio (Fase 1+)
  src/Repositories/ Acceso a datos con PDO (Fase 1+)
  src/Domain/       Entidades y enums (Fase 1+)
  database/migrations/  SQL versionado
  tests/            Pruebas
frontend/           SPA React (Vite + Tailwind)
docker/             Dockerfiles y configuración de Nginx/PHP
docs/               Arquitectura y decisiones
```

Ver [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
