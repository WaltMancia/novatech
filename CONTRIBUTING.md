# Guía de contribución

## Flujo
Mientras el proyecto lo desarrolla una sola persona, se trabaja directamente sobre `main`:
1. `git switch main && git pull`
2. Commits pequeños con Conventional Commits: `tipo(ámbito): resumen en imperativo` (máx. ~72 caracteres).
3. `php backend/tests/run.php` y `npm run build` (en `frontend/`) deben pasar antes del push.
4. `git push` y, al cerrar cada fase, un tag `vX.Y.0`.

Cuando se sumen más colaboradores: una rama por tema (`feat/`, `fix/`, `docs/`, `chore/`, `security/`) y Pull Request a `main` con al menos una revisión (merge commit, sin squash).

## Tipos de commit
`feat` · `fix` · `security` · `refactor` · `test` · `docs` · `build` · `ci` · `chore`

## Reglas de código
- PHP: `declare(strict_types=1)`, SQL solo en `Repositories/` y siempre con parámetros enlazados.
- Toda entrada externa pasa por `Validator` (lista blanca).
- Los controladores no contienen SQL ni reglas de negocio.
- Comentarios solo para explicar el porqué de una decisión de seguridad.
- Nunca subir `.env`, claves ni volcados de base de datos.
