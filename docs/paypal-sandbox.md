# PayPal im Testmodus (Sandbox) einrichten

Mit der Sandbox prüfen Sie Zahlungen über PayPal, ohne dass echtes Geld fließt. Dafür legt Studio Moco bei PayPal einen
eigenen Testzugang an. **Nie** die Zugangsdaten von Ketteler verwenden und **nie** die Live-Umgebung von PayPal: Kettelers
echtes Händlerkonto wird erst auf Staging bzw. Live verbunden.

Dauer: etwa 15 Minuten. Sie brauchen ein PayPal-Konto von Studio Moco (ein normales PayPal-Konto genügt).

## 1. Testzugang bei PayPal anlegen

1. Im Browser **https://developer.paypal.com** öffnen und oben rechts mit dem PayPal-Konto von Studio Moco anmelden.
2. Oben auf der Seite gibt es einen Schalter **Sandbox / Live**. Er muss auf **Sandbox** stehen.
3. Links **Apps & Credentials** wählen, dann **Create App**.
   - Name: `Ketteler-Shop lokal`
   - Typ: **Merchant**
   - Als Sandbox-Händlerkonto das vorgeschlagene Testkonto übernehmen (PayPal legt es automatisch an).
   - **Create App** klicken.
4. Auf der Seite der neuen App stehen zwei Werte:
   - **Client ID**: lange Zeichenfolge, gut sichtbar.
   - **Secret**: erscheint nach Klick auf **Show** (oder „Secret anzeigen“).
5. Die Händler-ID des Testkontos (optional, empfohlen): links **Testing Tools** > **Sandbox Accounts**, beim Konto vom Typ
   **Business** auf die drei Punkte bzw. **View/Edit account**, dort steht die **Account ID** (auch „Payer ID“ genannt).
6. Im selben Bereich gibt es ein Testkonto vom Typ **Personal**. Das ist Ihr **Test-Käufer** für Zahlungen im Browser.
   E-Mail und Passwort dieses Kontos zeigt PayPal dort an. Diese Angaben nur in Ihrem Passwortmanager ablegen.

## 2. Werte in die .env eintragen

Die Werte gehören nur in die `.env`, nie ins Repository und nie in einen Chat. Das Skript fragt sie nacheinander ab (das
Secret ohne Anzeige), legt vorher eine Kopie der `.env` an und ändert nur die drei PayPal-Zeilen.

- Auf Ihrem Mac (lokaler Shop):

  ```bash
  ~/Developer/ketteler-shop/ops/staging/paypal-eintragen.sh
  ```

- Auf Staging (Shop auf ketteler-shop.mocodev.de):

  ```bash
  ssh -t ketteler '/opt/ketteler-shop/repo/ops/staging/paypal-eintragen.sh'
  ```

Danach Bescheid geben: Die Werte werden dann in den Shop übernommen (Container neu starten, `ketteler:einrichten`).

## 3. Im Shop übernehmen und prüfen

1. Im Terminal (Container lesen die .env neu, dann Einrichtung):

   ```bash
   cd ~/Developer/ketteler-shop && docker compose up -d web worker && make einrichten
   ```

2. Automatischer Test (prüft: Testmodus an, PayPal in der Kasse wählbar, der Shop kann bei PayPal eine Zahlung anlegen):

   ```bash
   cd ~/Developer/ketteler-shop && python3 tests/e2e/paypal_test.py
   ```

   Ohne Werte in der `.env` meldet der Test „übersprungen“. Das ist kein Fehler.

3. Zahlung von Hand im Browser durchspielen:
   - http://localhost:8000 öffnen, einen gedruckten Testartikel in den Warenkorb legen, zur Kasse.
   - Zahlart **PayPal** wählen, AGB bestätigen, bestellen.
   - Im PayPal-Fenster (es steht „Sandbox“ darauf) mit dem **Test-Käufer** aus Schritt 1.6 anmelden und bezahlen.
   - Zurück im Shop erscheint die Bestellbestätigung. In der Verwaltung (http://localhost:8000/admin) steht die Bestellung
     mit Zahlstatus „Bezahlt“. Die Bestellbestätigung liegt in Mailpit (http://localhost:8025).

## Wenn etwas nicht klappt

- **PayPal erscheint nicht in der Kasse:** Werte in der `.env` prüfen (kein Leerzeichen, vollständig kopiert), dann
  erneut `make einrichten`.
- **Der Test meldet einen Fehler beim Anlegen der Zahlung:** Steht der Schalter bei PayPal auf **Sandbox**? Gehören Client ID
  und Secret zur selben App?
- **Zugangsdaten versehentlich weitergegeben:** In developer.paypal.com bei der App ein neues Secret erzeugen, das alte
  löschen und die `.env` anpassen.
