# Bedienung der lokalen Entwicklungsumgebung. Übersicht: make hilfe

.DEFAULT_GOAL := hilfe
export PATH := $(PATH):/usr/local/bin:$(HOME)/.docker/bin:/Applications/Docker.app/Contents/Resources/bin
# Docker Desktop legt "docker" je nach Einstellung an verschiedene Stellen
DOCKER := $(firstword $(wildcard $(addsuffix /docker,$(subst :, ,$(PATH)))) docker)
.PHONY: hilfe setup start stop status einrichten konsole logs neu test test-e2e test-browser test-staging

hilfe: ## Diese Übersicht
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  make %-12s %s\n", $$1, $$2}'

setup: ## Alles aufbauen (frischer Rechner oder nach git pull)
	@./scripts/setup.sh

start: ## Container starten
	@$(DOCKER) compose up -d
	@echo "Shop: http://localhost:$$(grep -E '^SHOP_PORT=' .env | cut -d= -f2)"

stop: ## Container anhalten (Daten bleiben)
	@$(DOCKER) compose stop

status: ## Zeigt, welche Container laufen
	@$(DOCKER) compose ps

einrichten: ## Grundaufbau anwenden: nur Fehlendes anlegen (UEBERSCHREIBEN=1 schreibt alles, STARTSEITE_NEU=1 legt die Startseite neu an)
	@$(DOCKER) compose exec -T web bin/console ketteler:einrichten $(if $(UEBERSCHREIBEN),--ueberschreiben,) $(if $(STARTSEITE_NEU),--erlebniswelten-neu,)
	@$(DOCKER) compose exec -T web bin/console cache:clear >/dev/null

test: ## Unit-Tests der eigenen Plugins (phpunit, ohne Datenbank)
	@$(DOCKER) compose exec -T web vendor/bin/phpunit

test-e2e: ## Ende-zu-Ende-Tests gegen die laufende Umgebung (Anmeldung, Lizenzen, Kasse, Widerruf, Steuern, PayPal; etwa 8 Minuten)
	@python3 tests/e2e/anmeldung_test.py
	@python3 tests/e2e/lizenz_test.py
	@python3 tests/e2e/shop_test.py
	@python3 tests/e2e/widerruf_test.py
	@python3 tests/e2e/steuern_test.py
	@python3 tests/e2e/paypal_test.py
	@python3 tests/e2e/werkseiten_test.py

test-browser: ## Startseite, Werkseiten, Storytelling, Unterstreichen und Verwaltung im echten Browser (eigenes Headless-Chrome; BILDER=<ordner> für Bildschirmfotos)
	@node tests/browser/startseite.mjs $(if $(BILDER),--bilder $(BILDER),)
	@node tests/browser/werkseiten.mjs $(if $(BILDER),--bilder $(BILDER),)
	@node tests/browser/storytelling.mjs $(if $(BILDER),--bilder $(BILDER),)
	@node tests/browser/unterstreichen.mjs
	@node tests/browser/verwaltung.mjs

test-staging: ## Dieselben Ende-zu-Ende-Tests gegen Staging (SSH-Zugang ketteler nötig; Zugangsdaten nur zur Laufzeit)
	@rc=0; for t in anmeldung lizenz shop widerruf steuern paypal werkseiten; do echo "== $$t"; KETTELER_ZIEL=staging python3 tests/e2e/$${t}_test.py || rc=1; done; \
	 echo "== startseite (Browser)"; node tests/browser/startseite.mjs --staging $(if $(BILDER),--bilder $(BILDER),) || rc=1; \
	 echo "== werkseiten (Browser)"; node tests/browser/werkseiten.mjs --staging $(if $(BILDER),--bilder $(BILDER),) || rc=1; \
	 echo "== storytelling (Browser)"; node tests/browser/storytelling.mjs --staging $(if $(BILDER),--bilder $(BILDER),) || rc=1; \
	 echo "== unterstreichen (Browser)"; node tests/browser/unterstreichen.mjs --staging || rc=1; \
	 echo "== verwaltung (Browser)"; node tests/browser/verwaltung.mjs --staging || rc=1; exit $$rc

konsole: ## Kommandozeile im Shop-Container öffnen
	@$(DOCKER) compose exec web bash

logs: ## Protokolle der Container ansehen (Strg+C beendet)
	@$(DOCKER) compose logs -f --tail=100

neu: ## Datenbank verwerfen und alles frisch aufbauen
	@read -p "Datenbank und hochgeladene Dateien dieses Rechners löschen? (ja/nein) " a; [ "$$a" = "ja" ]
	@$(DOCKER) compose down -v
	@rm -rf files public/media public/thumbnail public/theme var/cache install.lock
	@./scripts/setup.sh
