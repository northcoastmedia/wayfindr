SERVER_DIR := apps/server

.PHONY: help services-up services-down server-install server-migrate server-serve server-test

help:
	@printf '%s\n' 'Wayfindr development commands:'
	@printf '%s\n' '  make services-up      Start Postgres and Redis'
	@printf '%s\n' '  make services-down    Stop local services'
	@printf '%s\n' '  make server-install   Install Laravel dependencies and create .env'
	@printf '%s\n' '  make server-migrate   Run Laravel migrations'
	@printf '%s\n' '  make server-test      Run the Laravel Pest suite'
	@printf '%s\n' '  make server-serve     Serve Laravel on http://localhost:8000'

services-up:
	docker compose up -d postgres redis

services-down:
	docker compose down

server-install:
	cd $(SERVER_DIR) && composer install
	test -f $(SERVER_DIR)/.env || cp $(SERVER_DIR)/.env.example $(SERVER_DIR)/.env
	cd $(SERVER_DIR) && php artisan key:generate --ansi

server-migrate:
	cd $(SERVER_DIR) && php artisan migrate

server-serve:
	cd $(SERVER_DIR) && php artisan serve --host=127.0.0.1 --port=8000

server-test:
	cd $(SERVER_DIR) && composer test
