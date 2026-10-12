# Plan: Ketteler-Shop mit Shopware 6

Stand 12.10.2026 (zweite Runde). Grundlage: `docs/shop-weclapp-analyse.md` (Branch `feature/shop-analyse` im
Redaktions-Repository), `docs/lizenzen/shop-schnittstelle.md` und `docs/lizenzen/shopware.md` (Redaktions-Repository,
Branch `feature/stichtag`).

**Lokal gebaut** (nur auf den Entwicklungsrechnern, keine Verbindung zu Staging, Live oder weclapp): Grundaufbau, Theme,
Anmelde-Plugin `KettelerAnmeldung` mit eigenem ZITADEL-Container, Lizenz-Plugin `KettelerLizenzen` gegen eine Attrappe der
Lizenz-Schnittstelle, „Jetzt lesen“. Tests: `make test` (Unit-Tests) und `make test-e2e` (Ende-zu-Ende).

**Feste Entscheidungen** (Atilla, 12.10.2026):

- Shopware 6 ersetzt den NCE-Shop ganz (auch Print), Adresse später `ketteler-verlag.de`.
- weclapp so wenig wie möglich: nur die offizielle weclapp-Integration (Artikel, Preise, Bestände nach Shopware, Aufträge
  nach weclapp).
- Lizenzen nur in der Lizenzverwaltung des Redaktionssystems; ein Online-Kauf vergibt die Lizenz sofort, auch bei Kauf auf
  Rechnung. Einfache Artikel ohne Varianten.
- **Shopware 6.6 bleibt, bis weclapp 6.7 freigibt.**
- **Eigenes Anmelde-Plugin für ZITADEL** (`login.ketteler-verlag.de`), ein Konto für Shop und Bibliothek.
- **Storno:** neue Art `stornieren` in der Lizenz-Schnittstelle (Beschreibung unten). Ein Storno im Shop beendet die Lizenz
  und hinterlässt einen Eintrag im Protokoll. Die Änderung am Redaktionssystem macht die andere Sitzung.
- **Mehrplatz läuft zum Start über die Verwaltung.**
- **Gastbestellung nur für Print.** Mit Online-Artikel im Warenkorb braucht es ein Konto.
- Staging-Server wird vergrößert, wenn das Shop-Staging ansteht (Bestellung bei Hetzner macht Atilla). Adresse
  `staging-shop.mocodev.de`.

## 0. Shopware-Version

| | |
|---|---|
| weclapp-Doku „Shopware 6“ (Stand 26.08.2025, abgerufen 12.10.2026) | „weclapp unterstützt alle Shopware 6.5 & 6.6-Versionen“; Major-Updates erst nach Freigabe durch weclapp |
| Shopware Store, weclapp-App | Kompatibilität bis 6.7.15.1 angezeigt; das ist die Store-App für Shopware Cloud und keine Freigabe der eingebauten Anbindung |
| aktuelle Shopware-Versionen | 6.7.15.1 (aktuell), **6.6.10.29** (Extended Support), 6.8 noch nicht erschienen |

**Gewählt: 6.6.10.29.** Sobald weclapp 6.7 freigibt (Ketteler fragt beim Support schriftlich an), auf 6.7 wechseln,
möglichst **vor** dem Livegang.

**Wie lange bekommt 6.6 noch Korrekturen?** Quelle: Shopware Release Policy, Versionsübersicht
(https://developer.shopware.com/release-notes/, Grafik
https://shopware-platform-assets.s3.eu-central-1.amazonaws.com/release-schedule/schedule.svg, abgerufen 12.10.2026):

| Zeitraum (laut Grafik) | Stand von 6.6.10 |
|---|---|
| bis etwa **Ende Februar 2027** | Extended Support: ausgewählte Fehlerkorrekturen und Sicherheitskorrekturen als normale Updates |
| bis etwa **Ende Februar 2028** | nur Sicherheitskorrekturen, und zwar über das Shopware-Sicherheits-Plugin, nicht als Update |
| danach | keine Korrekturen mehr |

Shopware schreibt dazu, dass die Übergänge an die großen Versionen gebunden sind, nicht an feste Termine: Extended Support
endet, wenn 6.8 erscheint. Die Daten oben sind aus der Grafik abgelesen (Balkenlänge), also auf wenige Wochen genau.
**Sicherheitskorrekturen gibt es damit bis etwa Februar 2028, also über Mitte 2027 hinaus.** Ab etwa März 2027 kommen
sie aber nur noch über das Sicherheits-Plugin. Deshalb sollten wir möglichst bis Anfang 2027 auf 6.7 sein.

## 1. Lizenz-Plugin (`KettelerLizenzen`, lokal gebaut)

### Ablauf beim Kauf

```
Kunde bestellt (Rechnung)            Shopware                          Redaktionssystem
  └─ Bestellung abgeschlossen ──▶ Ereignis checkout.order.placed
                                   je Position mit Online-Werk
                                   (Online-Zugang oder Bundle)
                                   └─ Nachricht in die Warteschlange
                                      ketteler_lizenz (Worker)
                                          └─ POST /api/shop/ereignisse ──▶ Kunde finden/anlegen,
                                             HMAC-signiert                   ZITADEL-Konto (Einladung),
                                                                             Lizenz anlegen, Mail
                                                                             „Zugang freigeschaltet“
```

- **Auslöser:** „Bestellung abgeschlossen“, nicht „bezahlt“: Rechnungskauf ist der Normalfall, die Lizenz kommt sofort.
- **Welche Positionen:** Zusatzfelder am Artikel `ketteler_artikelart` (online oder bundle) und `ketteler_produkt`
  (`avr`, `eichstaetter`; das ist zugleich der Adressteil der Bibliothek `/bibliothek/<werk>`). Beide Felder sind für den
  Warenkorb freigegeben, damit sie an der Bestellposition stehen.
- **Anfrage** genau nach `shop-schnittstelle.md`: `ereignis_id = order-<orderId>-<positionId>-lizenz`,
  `art = lizenz_anlegen`, `email` = E-Mail des Kontos (= ZITADEL-Anmeldename), `name`, `produkt`, `typ = einzel`,
  `beginn` = Bestelldatum, `bestellnummer` (SW100000 ff.). `weclapp_kundennummer` ist beim Bestellen nicht bekannt.
- **Stand je Position** im Zusatzfeld `ketteler_lizenz` der Bestellposition: `offen` → `angelegt` (mit `lizenz_id`),
  `wartet` (Schnittstelle nicht erreichbar, wird wiederholt, mit `versuche` und `fehler`), `fehler` (abgelehnt oder alle
  Versuche erfolglos), `storniert`.
- **Wiederholung:** eigene Warteschlange `ketteler_lizenz` (Doctrine) mit Wiederholung nach 1, 5, 25 Minuten, dann
  stündlich, 27 Versuche ≈ 24 Stunden, immer mit derselben `ereignis_id`. Danach Stand `fehler` und Mail an die Verwaltung.
- **Ablehnung (4xx):** keine Wiederholung, Stand `fehler` mit Grund, Mail an die Verwaltung (`KETTELER_VERWALTUNG_EMAIL`).
- **Doppelte Zustellung:** Gilt die Position schon als `angelegt`, sendet das Plugin nichts. Wurde gesendet, aber der Stand
  nicht mehr gespeichert (Absturz), schickt die zweite Zustellung dieselbe `ereignis_id`; die Schnittstelle antwortet mit
  dem gespeicherten Ergebnis (`"wiederholt": true`), es entsteht keine zweite Lizenz.
- **Menge größer 1:** Online-Zugänge haben im Shop Höchstmenge 1. Kommt doch eine größere Menge an (z. B. Bundle), wird
  eine Einzellizenz angelegt und die Verwaltung per Mail um die weiteren Plätze gebeten (Mehrplatz über die Verwaltung).
- **Erneut senden** (z. B. nachdem die Verwaltung einen abgelehnten Fall geklärt hat):
  `bin/console ketteler:lizenz:erneut <Bestellnummer>` (Optionen `--stornieren`, `--erzwingen`).
- **Geheimnis:** `KETTELER_LIZENZ_GEHEIMNIS` und `KETTELER_LIZENZ_URL` nur in der `.env` des jeweiligen Servers. Lokal
  zeigt die Adresse auf die Attrappe `tools/lizenz-attrappe` (eigener Container), die Format, Signatur, Zeitfenster und
  Wiederholungen wie das Original prüft.
- **Offen:** Bestellungen, die die Verwaltung in der Shopware-Verwaltung anlegt, lösen `checkout.order.placed` nicht aus.
  Dafür eine Flow-Builder-Aktion „Ketteler: Lizenz vergeben“ ergänzen, sobald das gebraucht wird.

### Storno: neue Art `stornieren` (für die Umsetzung im Redaktionssystem)

Auslöser im Shop: Bestellstatus wechselt auf „storniert“ (in der Shopware-Verwaltung oder später aus weclapp
zurückgemeldet). Das Plugin sendet für jede Position mit angelegter Lizenz ein Ereignis. Ist die Lizenz noch nicht
angelegt (Stand `offen` oder `wartet`), sendet es nichts und verhindert die spätere Anlage.

**Anfrage:** wie alle Ereignisse `POST /api/shop/ereignisse`, gleiche Kopfzeilen (`X-Ketteler-Zeit`,
`X-Ketteler-Signatur`), gleiche Signatur, gleiches Zeitfenster.

| Feld | Pflicht | Bedeutung |
|---|---|---|
| `ereignis_id` | ja | `order-<orderId>-<positionId>-storno`, eindeutig; Wiederholung derselben ID wirkt nicht erneut |
| `art` | ja | `stornieren` |
| `email` | ja | E-Mail des Kontos; muss zur Lizenz passen |
| `produkt` | ja | `avr` oder `eichstaetter` |
| `bestellnummer` | ja | Shopware-Bestellnummer; mit `produkt` wird die Lizenz gefunden (Herkunft *Shop*) |
| `grund` | nein | kurzer Text für das Protokoll, höchstens 200 Zeichen (der Shop sendet „Storno der Bestellung im Shop“) |

**Wirkung:** Lizenz sofort beenden (Status *beendet*, `ende` = heute), auch wenn sie *gekündigt* oder *gesperrt* ist.
Eintrag im Protokoll („Shop-Ereignis: Storno“, Person *System*, mit Lizenz, Bestellnummer, vorherigem Status und Grund).
Das Konto bleibt bestehen, andere Lizenzen des Kunden bleiben unberührt. Vorschlag: keine Mail an den Kunden (er hat
selbst storniert oder wurde von der Verwaltung informiert); falls doch gewünscht, eine neue Mail „Zugang beendet“, nicht
die Mail „gekündigt“.

**Antworten:**

| Status | Wann | Körper |
|---|---|---|
| 200 | Lizenz beendet | `{"lizenz_id":9,"kunde_id":7,"status":"beendet","ende":"2026-10-12"}` |
| 200 | Lizenz war schon beendet (z. B. zweiter Storno mit anderer ID) | wie oben, keine erneute Wirkung, kein neuer Protokolleintrag |
| 200 | Wiederholung derselben `ereignis_id` | gespeichertes Ergebnis + `"wiederholt":true` |
| 401 | Signatur falsch, fehlt, Zeitstempel außerhalb von 5 Minuten | |
| 404 | zu `bestellnummer` und `produkt` keine Lizenz | `{"message":"…"}` |
| 422 | Felder ungültig, `grund` zu lang, E-Mail passt nicht zur Lizenz | `errors` / `message` |
| 503 | Geheimnis nicht eingerichtet | |

**Verhalten des Shops:** 404 gilt als erledigt (Stand `storniert` mit Hinweis), andere 4xx gehen per Mail an die
Verwaltung, 5xx und Zeitüberschreitung werden wie bei der Anlage wiederholt.

### Weitere Fälle

| Fall | Wirkung |
|---|---|
| Widerruf (Privatkunden, 14 Tage) | Im Checkout für Privatkunden Zustimmung „sofortiger Beginn, Widerrufsrecht erlischt“ (§ 356 Abs. 5 BGB) abfragen (noch nicht gebaut, Text von Ketteler). Widerruft trotzdem jemand, storniert die Verwaltung die Bestellung (dann greift `stornieren`) oder beendet die Lizenz in „Kunden & Lizenzen“. |
| Rückgabe des Buchs aus einem Bundle | Lizenz bleibt, außer die Verwaltung storniert. Rückgaben bucht weclapp. |
| Rechnung nicht bezahlt | Verwaltung sperrt die Lizenz in „Kunden & Lizenzen“. Kein automatischer Weg aus weclapp. |
| Abo-Kündigung (AVR Digital, später ZMV) | wie heute: Kündigung in „Kunden & Lizenzen“ mit Ende der bezahlten Laufzeit. |

### Mehrplatz

Zum Start über die Verwaltung (Entscheidung): Online-Zugänge im Shop einzeln (Höchstmenge 1); Mehrplatz-Artikel (z. B.
„Zusätzliche digitale Lizenzen“, Eichstätter ab 3 oder 5 Zugängen) bekommen später ein Kennzeichen, bei dem das Plugin
keine Lizenz anlegt, sondern die Verwaltung per Mail informiert. Die Verwaltung vergibt Einzel- oder IP-Lizenzen in
„Kunden & Lizenzen“. Später möglich: Leser-E-Mails im Checkout, je Leser ein `lizenz_anlegen`; dafür müsste die
Schnittstelle mehrere Lizenzen je Bestellung und Produkt zulassen.

## 2. Anmeldung über ZITADEL (`KettelerAnmeldung`, lokal gebaut)

### Plugin

Shopware 6.6 hat für Kunden kein OpenID Connect. Geprüft wurden `tinect/oauth2-storefront-login` (MIT), `MartinKuhl/
shopware6-oidc-sso` (MIT, Version 0.1), moorl (≈ 189 €/Jahr), PremSoft (≈ 361 €/Jahr). Entschieden: **eigenes Plugin**.

Gebaut (lokal):

- **Ablauf:** `/account/oidc/anmelden` → ZITADEL (Authorization Code mit **PKCE S256**, `state`, `nonce`, Scope
  `openid profile email urn:zitadel:iam:org:id:<Kunden-Organisation>`) → `/account/oidc/rueckkehr`. Die Anwendung „Shop“
  hat kein Client-Geheimnis (öffentlicher Client mit PKCE).
- **ID-Token-Prüfung:** Signatur RS256 mit dem Schlüssel aus dem JWKS, Aussteller, Empfänger, Ablauf, `nonce`.
- **Konto finden:** zuerst über die ZITADEL-Kennung (`sub`, Zusatzfeld `ketteler_zitadel_sub` am Kunden), sonst über die
  E-Mail, aber nur wenn ZITADEL sie als bestätigt meldet und nur bei Kunden mit Konto (keine Gäste), die noch mit keinem
  anderen ZITADEL-Konto verknüpft sind. Gibt es keinen Kunden, fragt der Shop einmal die Rechnungsanschrift ab
  („Kundenkonto vervollständigen“) und legt das Konto an. Name und E-Mail kommen aus ZITADEL.
- **Keine Shopware-Passwörter:** Passwort-Anmeldung, „Passwort vergessen“ und Registrierung von Shopware leiten zu
  ZITADEL; E-Mail und Passwort ändert der Kunde im ZITADEL-Konto (Link im Profil).
- **Abmelden** im Shop beendet auch die ZITADEL-Sitzung (`end_session` mit `id_token_hint`).
- **Gastbestellung nur für Print:** Mit Online-Zugang oder Bundle im Warenkorb zeigt die Kasse kein Gastformular, sondern
  „Anmelden“ und „Konto anlegen“. Zusätzlich blockiert eine Prüfung im Warenkorb jede Gastbestellung mit Online-Artikel,
  auch wenn jemand das Formular von Hand abschickt.
- **Einstellungen:** Adressen in der `.env` (`KETTELER_ZITADEL_URL` für den Browser, `KETTELER_ZITADEL_INTERN` für
  Anfragen vom Server), Client-ID und Organisation in der Plugin-Konfiguration.
- **Lokal:** eigener ZITADEL v4.19.4 (dieselbe Version wie das Redaktionssystem) mit Postgres, aufgebaut von `make setup`.
  `bin/console ketteler:zitadel:einrichten` legt Organisation „KETTELER Kunden“ (Selbstregistrierung an), Projekt,
  Anwendung „Shop“, Mailversand an Mailpit und drei Testkunden an. Lokal läuft die eingebaute Anmeldeseite von ZITADEL;
  auf Staging und Live ist es die Ketteler-Anmeldeseite (Login v2). Das Protokoll ist dasselbe, das Plugin merkt keinen
  Unterschied.

### Für Staging und Live

1. Im ZITADEL des Servers in der Organisation der Kunden eine Anwendung „Shop“ anlegen: Web, Authorization Code, PKCE,
   **ohne** Client-Geheimnis, „Benutzerinfo im ID-Token“ an, Redirect `https://<shop>/account/oidc/rueckkehr`,
   Post-Logout `https://<shop>/`. Client-ID und Organisations-ID in die Plugin-Konfiguration.
2. Die Anmelde-Richtlinie der Kunden-Organisation braucht Gültigkeitsdauern (Passwortprüfung usw.); fehlen sie, fragt
   ZITADEL nach jeder Passworteingabe erneut (lokal so aufgefallen und behoben).
3. Bestehende Kunden: Die Verknüpfung über die bestätigte E-Mail übernimmt Bestandskunden beim ersten Anmelden.

### „Jetzt lesen“ ohne neue Anmeldung

- Gebaut: Knopf „Jetzt lesen“ in der Bestellbestätigung (je Online-Werk der Bestellung) und im Kundenkonto
  („Meine Online-Werke“, aus den Bestellungen mit angelegter oder gerade entstehender Lizenz; nach Storno nicht mehr).
  Ziel: `KETTELER_BIBLIOTHEK_URL/<werk>`, z. B. `https://ketteler-verlag.de/bibliothek/eichstaetter`. Lokal ist die
  Adresse ein Platzhalter. Der Link „Bibliothek“ im Kopf nutzt dieselbe Adresse.
- Shop und Bibliothek melden über `login.ketteler-verlag.de` an. Wer im Shop angemeldet ist, hat eine ZITADEL-Sitzung;
  die Bibliothek leitet zu ZITADEL, ZITADEL erkennt die Sitzung und leitet ohne Passwortabfrage zurück.
- Die Lizenz entsteht einige Sekunden nach der Bestellung. Für den Fall, dass jemand schneller in der Bibliothek ist,
  braucht die Bibliothek einen Hinweis „Ihr Zugang wird eingerichtet“ mit neuem Versuch (Ergänzung im Redaktionssystem).

## 3. weclapp-Integration

### In Shopware einrichten (wir)

1. Shopware-Zugang für weclapp: laut weclapp-Doku über einen Admin-Benutzer (Benutzername und Passwort), nicht über eine
   Integration. Eigener Benutzer `weclapp` mit starkem Passwort, nur dafür.
2. Steuersätze 7 % und 19 % (angelegt), Währung Euro, Einheiten (Stück) müssen vorhanden sein, bevor weclapp verbindet.
3. Lagerführung: Bestände kommen aus weclapp, Shopware-Bestandsbewegungen abschalten (laut weclapp-Doku).
4. Nummernkreise: Bestellungen `SW100000` ff., Kunden `SK100000` ff. (angelegt). weclapp übernimmt diese Nummern, sie
   dürfen nicht mit weclapp-Nummern (4- und 5-stellig) zusammenfallen.
5. Zusatzfelder in Shopware, die weclapp befüllen soll: `ketteler_artikelart` (print, online, bundle) und
   `ketteler_produkt` (avr, eichstaetter) sind angelegt.
6. Kategorien pflegen wir in Shopware; dass weclapp Kategorien überträgt, ist nicht dokumentiert (im Test prüfen).
7. Der Shop muss für weclapp erreichbar sein. Auf Staging statt Passwortschutz eine IP-Freigabe für die Adressen des
   weclapp-Supports.

### In weclapp einrichten (Ketteler mit uns)

Einstellungen → Integration → Shops → Shopware 6: Shop-Adresse und Zugang; weclapp führend für Artikel, Preise,
Bestände; Aufträge aus dem Shop abholen (Status „neu“, Sicherheitstage 3); Zuordnungen: Zahlart Rechnung → „Auf
Rechnung“ (Ziel 10 Tage), Versandart „Versand per Post“ → Versandkosten-Artikel, Kundengruppe → Vertriebsweg `GROSS1`,
Lager Hauptlager. Zuerst nur Artikel übertragen, Auftragsabruf erst nach dem Test einschalten.

### In weclapp aufräumen (Ketteler, vor dem Anschluss)

1. **Neue Artikel-Zusatzfelder** „Artikelart“ (Print, Online, Bundle) und „Online-Werk“ (AVR, Eichstätter) anlegen und
   für alle Shop-Artikel füllen. Heute erkennt man Online-Artikel nur an Nummer und Name.
2. **Eichstätter Online-Zugang (608082)** von Lagerartikel auf `BASIC` umstellen, sonst erscheint er mit Bestand und
   Lieferzeit.
3. **Bundles** als eigene, aktive Artikel anlegen (608042 ist inaktiv).
4. **Mehrplatz-Artikel** klar benennen (Zusätzliche digitale Lizenzen, Staffeln beim Eichstätter).
5. **Artikelnummern** ohne Leerzeichen und Sonderzeichen (z. B. „ZMV Einzelartikel“).
6. **„Auf Website anzeigen“** als einziges Shop-Kennzeichen pflegen; altes Feld „viit_shop“ klären und entfernen.
7. **Beschreibungen** (189 ohne Langtext), **Bilder** (154 ohne Bild), **Gewichte** (51 Lagerartikel mit 0) nachtragen.
8. **Steuersatz** je Artikel prüfen (ermäßigt 199, voll 142, 0 % 2).
9. **Versandkosten**: alte Pauschalen (`VA1`, `VA500`, `VA19-…`) und Gewichtszonen auf ein Schema bringen, das der Shop
   abbilden kann.
10. Doppelte Kundenkategorie „Ökumenisch/Oekumenisch“ zusammenführen.

## 4. Weg zu Staging und Live

### Staging (Tests mit Ketteler, Anbindung an Staging-Redaktionssystem und weclapp)

- **Wann:** nachdem `stornieren` im Redaktionssystem umgesetzt ist und der Ketteler-Test des Redaktionssystems
  abgeschlossen ist. Den Server vergrößert Atilla vorher bei Hetzner.
- **Server:** der Staging-Server ist mit CPX22 (2 vCPU, 4 GB) heute schon voll. Vorher auf **CPX32** (4 vCPU, 8 GB)
  umskalieren, „nur CPU und RAM“, damit ein Zurückstufen möglich bleibt; und laut `live-server-plan.md` alte
  Analyse-Datenbanken und Docker-Bilder aufräumen.
- **Aufbau:** eigener Stack `ketteler-shop-stg` (PHP-FPM, Worker für Warteschlange und geplante Aufgaben, MySQL 8.4 wie
  Live, ohne OpenSearch, ohne Redis) hinter dem vorhandenen Caddy; Adresse `staging-shop.mocodev.de`.
- **Verbindungen:** Lizenz-Schnittstelle von Staging (eigenes Geheimnis), Staging-ZITADEL (Anwendung „Shop“), weclapp
  (IP-Freigabe statt Passwortschutz; zuerst nur Artikel, ob mit Testmandant ist offen).
- **Einspielen:** nur aus GitHub (Deploy-Key nur lesend), Ablauf wie beim Redaktionssystem; Shopware
  `deployment-helper` für Migrationen, Plugins und Theme; Theme und Verwaltung beim Bauen übersetzen, nicht auf dem Server.

### Live

- **Wann:** nach dem Staging-Test mit Ketteler und der weclapp-Freigabe; gemeinsam mit Bibliothek, Lizenzen und
  Bestandskunden (Reihenfolge laut `live-server-plan.md`).
- **Server:** Stufe 2 aus `live-server-plan.md`: Live-Server auf **CPX42** (8 vCPU, 16 GB) umskalieren. Shopware-PHP 3 GB,
  Worker 512 MB, MySQL auf 4 GB. OpenSearch erst, wenn die eingebaute Suche nicht reicht (dann CPX52 oder CCX33).
- **Speicher:** Datenbank klein (unter 1 GB im ersten Jahr). Bilder: ≈ 350 Artikel mit 1 bis 3 Bildern plus
  Vorschaubilder, rechnerisch 2 bis 5 GB. Mit Protokollen, Sicherungen auf dem Server und Reserve sind **20 GB** für den
  Shop eingeplant; die 160 GB der Platte reichen.
- **Sicherung:** wie die Redaktion über restic auf die Storage Box: Datenbank stündlich (Bestellungen), Medien täglich.
- **Adressen:** `ketteler-verlag.de` und `www.ketteler-verlag.de` (Shop), `ketteler-verlag.de/bibliothek` (Bibliothek),
  `login.ketteler-verlag.de` (ZITADEL), `redaktion.ketteler-verlag.de`. DNS-Zone liegt bei Hetzner DNS. Umstellung vom
  NCE-Shop: Weiterleitungen (301) der alten Pfade (`/digibib`, `/bookreader`, `/?listview&link=…`, `/eichstaetterkommentar`
  usw.) auf die neuen Kategorien und Artikel; Rechtstexte (Impressum, AGB, Widerruf, Datenschutz) vorher von Ketteler.
- **Mail:** Bestellmails über denselben Versandweg wie die Redaktion (Entscheidung zum Mail-Anbieter steht noch aus).

### Reihenfolge

1. Lokal: Grundaufbau, Anmelde-Plugin mit lokalem ZITADEL, Lizenz-Plugin mit Attrappe, „Jetzt lesen“ (fertig).
2. Redaktionssystem: Schnittstelle um `stornieren` erweitern (und später Mehrplatz), Bibliothek „Zugang wird eingerichtet“.
3. Staging-Server vergrößern, Shop-Staging aufbauen, Plugins gegen Staging-Redaktionssystem und Staging-ZITADEL.
4. weclapp an Shop-Staging: zuerst Artikel, dann Aufträge. Parallel räumt Ketteler weclapp auf.
5. Test mit Ketteler (Bestellen, Rechnung, Lizenz, Lesen, Storno).
6. Live-Server auf CPX42, Shop live unter vorläufiger Adresse prüfen, dann DNS von NCE auf den neuen Shop umstellen.

## 5. Offene Fragen

### An Atilla

1. Mail an den Kunden bei Storno: keine (Vorschlag) oder eine neue Mail „Zugang beendet“?
2. Bestellungen, die die Verwaltung in der Shopware-Verwaltung anlegt: sollen sie auch automatisch eine Lizenz bekommen
   (Flow-Builder-Aktion)?
3. Rechnungsanschrift beim ersten Anmelden: Reicht Deutschland, oder sollen Österreich und die Schweiz gleich mit?

### An Ketteler

Gesammelt in [fragen-ketteler.md](fragen-ketteler.md) (zum Weitergeben).
