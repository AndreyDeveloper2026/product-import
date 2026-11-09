.PHONY: init up down logs migrate seed test stan cs cs-fix e2e-ui shell

COMPOSE = docker compose

init:            ## Создать .env из шаблона (если нет)
	@test -f .env || cp .env.example .env

up: init         ## Собрать и запустить всё (миграции применяются при старте app)
	$(COMPOSE) up -d --build
	@echo "UI:      http://localhost:8080  (admin / admin123)"
	@echo "Swagger: http://localhost:8080/api/docs"
	@echo "Health:  http://localhost:8080/api/health"
	@echo "RabbitMQ: http://localhost:15672 (guest / guest)"

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f app worker

migrate:
	$(COMPOSE) exec app vendor/bin/doctrine-migrations migrate --no-interaction --configuration=migrations.php --db-configuration=migrations-db.php

seed:            ## Загрузить демо-данные (fixtures) в БД
	$(COMPOSE) exec app php bin/seed.php

test:            ## PHPUnit (unit + integration + API e2e)
	$(COMPOSE) exec -e APP_ENV=test app vendor/bin/phpunit

stan:
	$(COMPOSE) exec app vendor/bin/phpstan analyse --memory-limit=512M

cs:
	$(COMPOSE) exec app vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(COMPOSE) exec app vendor/bin/php-cs-fixer fix

e2e-ui:          ## Playwright e2e фронтенда (нужен node локально)
	cd frontend && npm install && npx playwright install --with-deps chromium && npm run e2e

shell:
	$(COMPOSE) exec app sh
