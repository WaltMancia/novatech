## Qué cambia
<!-- Resumen breve -->

## Por qué
<!-- Fase / issue relacionado -->

## Verificación
- [ ] `php backend/tests/run.php` pasa
- [ ] `npm run build` pasa (si toca el frontend)
- [ ] Probado en Docker (`docker compose up -d --build`)

## Seguridad
- [ ] Entrada validada con `Validator`
- [ ] Consultas con parámetros enlazados
- [ ] Autorización comprobada (rol y propiedad del objeto)
- [ ] Sin secretos en el código ni en los logs
