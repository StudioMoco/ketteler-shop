# Bedienung der lokalen Entwicklungsumgebung. Übersicht: make hilfe

.DEFAULT_GOAL := hilfe
export PATH := $(PATH):/usr/local/bin:$(HOME)/.docker/bin:/Applications/Docker.app/Contents/Resources/bin
.PHONY: hilfe setup start stop status einrichten konsole logs neu

hilfe: ## Diese Übersicht
	@grep -E '^[a-z]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  make %-12s %s\n", $$1, $$2}'

setup: ## Alles aufbauen (frischer Rechner oder nach git pull)
	@./scripts/setup.sh

start: ## Container starten
	@docker compose up -d
	@echo "Shop: http://localhost:$$(grep -E '^SHOP_PORT=' .env | cut -d= -f2)"

stop: ## Container anhalten (Daten bleiben)
	@docker compose stop

status: ## Zeigt, welche Container laufen
	@docker compose ps

einrichten: ## Grundaufbau erneut anwenden (nach Änderungen an Kategorien oder Testartikeln)
	@docker compose exec -T web bin/console ketteler:einrichten
	@docker compose exec -T web bin/console cache:clear >/dev/null

konsole: ## Kommandozeile im Shop-Container öffnen
	@docker compose exec web bash

logs: ## Protokolle der Container ansehen (Strg+C beendet)
	@docker compose logs -f --tail=100

neu: ## Datenbank verwerfen und alles frisch aufbauen
	@read -p "Datenbank und hochgeladene Dateien dieses Rechners löschen? (ja/nein) " a; [ "$$a" = "ja" ]
	@docker compose down -v
	@rm -rf files public/media public/thumbnail public/theme var/cache install.lock
	@./scripts/setup.sh
