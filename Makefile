.PHONY: up down build migrate test logs reset
up:      ; docker compose up -d --build
down:    ; docker compose down
build:   ; docker compose build
migrate: ; docker compose run --rm app php bin/migrate.php
test:    ; php backend/tests/run.php
logs:    ; docker compose logs -f app web
reset:   ; docker compose down -v
