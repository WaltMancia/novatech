# Guía de contribución

## Flujo
1. `git switch main && git pull`
2. `git switch -c feat/<tema>` (`fix/`, `docs/`, `chore/`, `security/`)
3. Commits pequeños con Conventional Commits: `tipo(ámbito): resumen en imperativo` (máx. ~72 caracteres).
4. `php backend/tests/run.php` y `npm run build` (en `frontend/`) deben pasar antes del push.
5. Pull Request a `main`, con al menos una revisión. Merge commit (sin squash).

## Tipos de commit
`feat` · `fix` · `security` · `refactor` · `test` · `docs` · `build` · `ci` · `chore`

## Reglas de código
- PHP: `declare(strict_types=1)`, SQL solo en `Repositories/` y siempre con parámetros enlazados.
- Toda entrada externa pasa por `Validator` (lista blanca).
- Los controladores no contienen SQL ni reglas de negocio.
- Comentarios solo para explicar el porqué de una decisión de seguridad.
- Nunca subir `.env`, claves ni volcados de base de datos.
