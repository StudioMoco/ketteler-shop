# Plan: Ketteler-Shop mit Shopware 6

Stand 12.10.2026 (dritte Runde). Grundlage: `docs/shop-weclapp-analyse.md` (Branch `feature/shop-analyse` im
Redaktions-Repository), `docs/lizenzen/shop-schnittstelle.md` und `docs/lizenzen/shopware.md` (Redaktions-Repository,
Branch `feature/stichtag`).

**Lokal gebaut** (nur auf den Entwicklungsrechnern, keine Verbindung zu Staging, Live oder weclapp): Grundaufbau, Theme,
Anmelde-Plugin `KettelerAnmeldung` mit eigenem ZITADEL-Container, Lizenz-Plugin `KettelerLizenzen` gegen eine Attrappe der
Lizenz-Schnittstelle, „Jetzt lesen“, Mehrplatz-Anfrage (`KettelerMehrplatz`), Mails im Ketteler-Ton, Lieferländer mit
Steuerregeln, Versand nach Gewicht, Rechnung und PayPal (Testmodus), Abo-Kennzeichnung, Widerruf-Häkchen, Rechtstexte als
Entwurf. Tests: `make test` (Unit-Tests) und `make test-e2e` (Ende-zu-Ende).

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

**Entscheidungen der dritten Runde** (Atilla mit dem Wissen von Ketteler, 12.10.2026):

- Storno: keine zusätzliche Mail aus dem Redaktionssystem (die Stornomail des Shops genügt).
- Bestellungen aus der Shopware-Verwaltung bekommen ebenfalls automatisch eine Lizenz (geprüft: sie laufen über denselben
  Bestellweg, das Lizenz-Plugin greift).
- Shopware-Version: Atilla fragt beim weclapp-Support selbst nach 6.7. Bis dahin 6.6.
- weclapp-Test: Der Shop auf Staging wird später lesend und schreibend an das echte weclapp angebunden. Testbestellungen nur
  über einen eigenen Testkunden „TEST Studio Moco“, Ketteler wird vorher informiert. Der lokale Shop wird nie angebunden.
- Artikel in weclapp: Ketteler räumt vorerst nichts auf. Artikelart und Online-Werk pflegen wir im Shop. Der Eichstätter
  Online-Zugang (608082) wird ohne Bestandsprüfung verkauft. Pakete aus Buch und Online gibt es schon in weclapp (vor allem
  ZMV), welche das sind, prüfen wir beim Anbinden. Artikelnummern mit Leerzeichen oder Sonderzeichen prüfen wir beim
  Anbinden und geben Atilla eine Liste.
- Zahlarten: Rechnung und PayPal (offizielles Shopware-Plugin). Ketteler verbindet sein Händlerkonto später selbst; lokal
  nur Testmodus ohne Kettelers Zugangsdaten.
- Versand nach Gewicht wie in weclapp; Lieferländer wie heute (Deutschland, Österreich, Schweiz, Italien, Großbritannien, EU).
  Online-Zugänge an Privatkunden im EU-Ausland mit der Umsatzsteuer des Kundenlandes.
- Abos: ZMV (gedruckt und digital) und AVR Digital im Shop bestellbar, Vertrag legt die Verwaltung in weclapp an, AVR Digital
  bekommt die Lizenz sofort, im Shop klar als Abo gekennzeichnet.
- Buchhandel: keine eigenen Preise oder Rabatte.
- Mehrere Lizenzen: Online-Artikel nur einzeln, dazu „Mehrere Lizenzen anfragen“ (keine Bestellung, keine Lizenz).
- Widerruf: Pflicht-Häkchen bei Online-Artikeln für Privatkunden (Text von Ketteler zu prüfen).
- Rechtstexte aus dem heutigen Shop übernehmen, als „von Ketteler zu prüfen“ markieren, Anpassungsbedarf nur auflisten.
- Inhalte und Design: eigene Startseite, Werkseiten für AVR, ZMV und Eichstätter, Storytelling-Seiten. Design und UX zuerst,
  gebaut wird danach.

## 0. Shopware-Version

| | |
|---|---|
| weclapp-Doku „Shopware 6“ (Stand 26.08.2025, abgerufen 12.10.2026) | „weclapp unterstützt alle Shopware 6.5 & 6.6-Versionen“; Major-Updates erst nach Freigabe durch weclapp |
| Shopware Store, weclapp-App | Kompatibilität bis 6.7.15.1 angezeigt; das ist die Store-App für Shopware Cloud und keine Freigabe der eingebauten Anbindung |
| aktuelle Shopware-Versionen | 6.7.15.1 (aktuell), **6.6.10.29** (Extended Support), 6.8 noch nicht erschienen |

**Gewählt: 6.6.10.29.** Sobald weclapp 6.7 freigibt (Atilla fragt beim Support), auf 6.7 wechseln, möglichst **vor** dem
Livegang. Hinweis für die Anfrage: Die weclapp-Übersichtsseite der Shop-Anbindungen
(https://doc.weclapp.com/documentation/e-commerce/01-shops/, laut Recherche Stand Dezember 2025) nennt bereits „alle
Shopware 6.7 & 6.6 Versionen“, die Seite zu Shopware 6 (Stand August 2025) noch 6.5 und 6.6. Bitte die Freigabe für 6.7
schriftlich bestätigen lassen.

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
| Widerruf (Privatkunden, 14 Tage) | Gebaut: Pflicht-Häkchen in der Kasse für Privatkunden mit Online-Zugang oder Bundle: „Ich stimme zu, dass der Online-Zugang sofort beginnt, und weiß, dass ich damit mein Widerrufsrecht verliere.“ (von Ketteler zu prüfen). Ohne Häkchen keine Bestellung (auch serverseitig geprüft); Zeitpunkt und Wortlaut stehen an der Bestellung und in der Bestellbestätigung. Firmenkunden und Bestellungen aus der Verwaltung sind ausgenommen. Widerruft trotzdem jemand, storniert die Verwaltung die Bestellung (dann greift `stornieren`). |
| Widerrufsfunktion (EU, seit 19.06.2026) | Shopware 6.6.10 bringt eine Einstellung „Widerrufsanfrage“ mit (`core.basicInformation.revocationRequestPage`, Mails `revocation_request.*`). Noch nicht eingerichtet: mit Ketteler und der Rechtsberatung klären, ob und wie die Widerrufsfunktion im Shop angeboten werden muss. |
| Rückgabe des Buchs aus einem Bundle | Lizenz bleibt, außer die Verwaltung storniert. Rückgaben bucht weclapp. |
| Rechnung nicht bezahlt | Verwaltung sperrt die Lizenz in „Kunden & Lizenzen“. Kein automatischer Weg aus weclapp. |
| Abo-Kündigung (AVR Digital, später ZMV) | wie heute: Kündigung in „Kunden & Lizenzen“ mit Ende der bezahlten Laufzeit. |

### Mehrplatz

Gebaut (`KettelerMehrplatz`): Online-Zugänge lassen sich nur einzeln kaufen (Höchstmenge 1). Auf jeder Artikelseite eines
Online-Zugangs oder Bundles steht deutlich sichtbar „Mehrere Lizenzen anfragen“, ebenso auf der Seite „Meine
Online-Zugänge“. Das Formular fragt Einrichtung, Ansprechperson, E-Mail, Telefon, Anzahl der Lizenzen (ab 2) und Nachricht
ab, ist mit dem Shopware-Captcha geschützt, speichert die Anfrage (Verwaltung: Kunden > Mehrplatz-Anfragen) und schickt sie
an `KETTELER_KONTAKT_EMAIL` (Live: kontakt@ketteler-verlag.de, lokal Mailpit). Keine Bestellung, keine Lizenz. Die
Verwaltung vergibt die Lizenzen in „Kunden & Lizenzen“.

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

### Was die Anbindung laut Doku tut (Stand der Recherche 12.10.2026)

Quellen: Shopware 6 (https://doc.weclapp.com/documentation/e-commerce/01-shops/01-2-shopware-6/), Shopware 5 (gleiche
Anbindung, ausführlicher: .../01-1-shopware-5/), häufige Fehler (.../01-7-die-haeufigsten-fehler-shops/), FAQ Versandkosten
(https://doc.weclapp.com/knowledgebase/wie-werden-versandkosten-vom-shop-zu-weclapp-uebertragen/).

| Frage | Befund | Folge für uns |
|---|---|---|
| Überschreibt weclapp unsere Felder `ketteler_artikelart`, `ketteler_produkt`, `ketteler_abo`? | Artikelfelder werden über eine Zuordnungstabelle übertragen („immer oder nur bei der Artikelanlage“); Zusatzfelder nur, wenn sie einzeln zugeordnet sind. Ob nicht zugeordnete Shopware-Zusatzfelder unberührt bleiben oder das ganze Zusatzfeld-Paket ersetzt wird, steht **nicht** in der Doku. | Unsere Felder in weclapp nicht zuordnen. Auf Staging als erstes prüfen: Artikel mit gesetzten Feldern, Abgleich aus weclapp laufen lassen, Felder vergleichen. Falls weclapp sie löscht: Felder aus einer eigenen Tabelle je Artikelnummer nachtragen (kleines Plugin) oder weclapp-Support fragen. |
| Überträgt weclapp Versandarten oder Versandkosten in den Shop? | **Nein**, davon steht nichts in der Doku. Versandkosten gehen nur vom Shop nach weclapp, mit dem Auftrag (als Versandkosten-Artikel, als Artikel oder als Textzeile). | Gewichtsstaffeln in Shopware nachgebaut (gebaut, Werte Platzhalter, `daten/versand.json`). Echte Werte aus den weclapp-Versandkosten-Artikeln (`DE_…`, `EU_…`, Schweiz, Ausland) übernehmen. |
| Bestand | weclapp rechnet den verkaufbaren Bestand und schreibt ihn alle 5 Minuten in den Shop; die Lagerbewegungen in Shopware abschalten. Zum Verkauf ohne Bestandsprüfung steht nichts in der Doku. | 608082 und alle Online-Artikel: in Shopware „Abverkauf“ aus (gebaut: verkäuflich auch bei Bestand 0). Auf Staging prüfen, ob weclapp das Feld überschreibt. |
| Aufträge | Auftrags- und Kundennummer kommen aus dem Shop. Zuordnung der Kunden über Kundennummer oder E-Mail, Gastbestellung ergibt jeweils einen neuen weclapp-Kunden. Zahlarten werden je Shopware-Zahlart auf Auftragsart, Zahlungsart und Zahlungsbedingung abgebildet (PayPal empfohlen als Auftragsart „Vorkasse“). Wählbar, welche Status importiert werden; mindestens 2 Sicherheitstage. Jeder Artikel eines Auftrags muss in weclapp existieren. | Zuordnung Rechnung → „Auf Rechnung“, PayPal → „Vorkasse“/„PayPal“ beim Einrichten festlegen. Testartikel (`TEST-…`) gibt es nicht in weclapp: Testbestellungen auf Staging nur mit echten Artikeln und dem Testkunden „TEST Studio Moco“. |
| Artikelnummern | „Shopware lässt nur a-Z, 0-9, Punkt, Bindestrich und Unterstrich in Artikelnummern zu.“ | Beim Anbinden alle Artikel mit Leerzeichen oder Sonderzeichen auflisten (Liste an Atilla); bekannt ist z. B. „ZMV Einzelartikel“. |
| Abos, digitale Artikel | nichts dokumentiert; Artikel, die aus dem Shop nach weclapp kommen, werden immer Lagerartikel. | Abos als normale Bestellung, Vertrag von Hand (entschieden). |

### In Shopware einrichten (wir, auf Staging)

1. Shopware-Zugang für weclapp: laut weclapp-Doku über einen Admin-Benutzer (Benutzername und Passwort). Eigener Benutzer
   `weclapp` mit starkem Passwort, nur dafür.
2. Steuern (19 %, 7 %, „Online“ mit Länderregeln), Währung Euro, Einheiten müssen vorhanden sein, bevor weclapp verbindet.
   Welcher weclapp-Steuersatz auf welche Shopware-Steuer zeigt, beim Einrichten festlegen; Online-Artikel brauchen die
   Steuer „Online“.
3. Lagerbewegungen in Shopware abschalten (laut weclapp-Doku).
4. Nummernkreise: Bestellungen `SW100000` ff., Kunden `SK100000` ff. (angelegt), damit sie nicht mit weclapp-Nummern
   (4- und 5-stellig) zusammenfallen.
5. Zusatzfelder `ketteler_artikelart`, `ketteler_produkt`, `ketteler_abo` pflegen wir im Shop (in weclapp nicht zuordnen).
6. Kategorien pflegen wir in Shopware.
7. Der Shop muss für weclapp erreichbar sein: auf Staging statt Passwortschutz eine IP-Freigabe für die weclapp-Adressen.

### In weclapp einrichten (mit Ketteler)

Einstellungen > Integration > Shops > Shopware 6: Shop-Adresse und Zugang; weclapp führend für Artikel, Preise, Bestände;
Aufträge aus dem Shop abholen (Status „offen“, Sicherheitstage 3); Zuordnungen: Rechnung → „Auf Rechnung“ (Ziel 10 Tage),
PayPal → Vorkasse/PayPal, Versandkosten → Versandkosten-Artikel, Kundengruppe → Vertriebsweg `GROSS1`, Lager Hauptlager.
Zuerst nur Artikel übertragen, Auftragsabruf erst nach dem Test einschalten. Testbestellungen nur mit dem Testkunden
„TEST Studio Moco“; Ketteler vorher informieren.

### Gewicht fehlt (aus der vorhandenen Analyse, kein neuer Zugriff)

Laut `shop-weclapp-analyse.md` (Stand 12.10.2026) haben von den **343** aktiven, für die Website markierten Artikeln **330**
ein Gewicht eingetragen, **13** also keines. Zusätzlich steht bei **51 von 242 Lagerartikeln** das Gewicht auf **0**. Ob sich
beide Gruppen überschneiden, gibt die Analyse nicht her; es sind also **mindestens 51 und höchstens 64 Artikel** ohne
brauchbares Gewicht. Für reine Online-Artikel (ohne Lager) ist das egal, für Print führt es zu zu niedrigen Versandkosten.
Beim Anbinden die Liste erzeugen und an Ketteler geben.

### Offen für später

- **ZMV digital:** Was bekommt ein Käufer beim Online-Teil der ZMV (ZMV Digital, Kombi, Zusatz-Abo), und wo (heute
  `/digibib` im NCE-Shop)? Die ZMV gehört bisher nicht zur neuen Bibliothek. Der Testartikel „ZMV Abonnement Digital“ vergibt
  deshalb keine Lizenz; die Bestellbestätigung sagt „Wie Sie die digitale Ausgabe erhalten, teilen wir Ihnen gesondert mit.“
- Welche Pakete aus Buch und Online es in weclapp gibt (vor allem ZMV) und welches Online-Werk sie freischalten.

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
  (echtes weclapp, lesend und schreibend; IP-Freigabe statt Passwortschutz; zuerst nur Artikel; Testbestellungen nur
  mit dem Testkunden „TEST Studio Moco“, Ketteler wird vorher informiert).
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

1. Lokal: Grundaufbau, Anmeldung, Lizenzen, Kasse, Mails, Mehrplatz-Anfrage, Rechtstext-Entwürfe (fertig).
1a. Design und UX für Startseite, Werkseiten und Storytelling-Seiten (Grundlage: `content/`), danach bauen (Abschnitt 5).
2. Redaktionssystem: Schnittstelle um `stornieren` erweitern (und später Mehrplatz), Bibliothek „Zugang wird eingerichtet“.
3. Staging-Server vergrößern, Shop-Staging aufbauen, Plugins gegen Staging-Redaktionssystem und Staging-ZITADEL.
4. weclapp an Shop-Staging: zuerst Artikel (Zusatzfelder prüfen, Liste der Artikelnummern mit Sonderzeichen und ohne
   Gewicht), dann Aufträge mit dem Testkunden „TEST Studio Moco“.
5. Test mit Ketteler (Bestellen, Rechnung, Lizenz, Lesen, Storno).
6. Live-Server auf CPX42, Shop live unter vorläufiger Adresse prüfen, dann DNS von NCE auf den neuen Shop umstellen.

## 5. Startseite, Werkseiten und Storytelling-Seiten (Empfehlung, noch nicht gebaut)

Grundlage für das Design: Bestandsaufnahme der heutigen Inhalte in `content/` (Übersicht `content/README.md`).

**Grundsatz:** alles mit den **Erlebniswelten** von Shopware (Einkaufswelten, im Admin unter Inhalte > Erlebniswelten), damit
Ketteler Texte, Bilder und Produktauswahl selbst pflegt. Wir bauen dafür wenige **eigene Blöcke** im Theme-Plugin, die genau
das Design abbilden; Layouts legen wir an, Inhalte pflegt Ketteler.

| Seite | Umsetzung | Ketteler pflegt |
|---|---|---|
| **Startseite** | Erlebniswelt vom Typ „Shopseite“ an der Hauptkategorie. Standardblöcke (Bild mit Text, Text, Produkt-Slider) plus eigene Blöcke „Werk-Teaser“ (Bild, Titel, kurzer Text, Link auf die Werkseite) und „Aktuelles“. | Texte, Bilder, welche Werke und Produkte erscheinen |
| **Werkseiten** AVR, ZMV, Eichstätter | Die vorhandenen Kategorien „Kompendium AVR Caritas“, „Zeitschrift ZMV“, „Eichstätter Kommentar“ bekommen **ein gemeinsames Layout** „Werkseite“ (Kategorie-Layout). Eigener Block **„Angebote nebeneinander“**: bis zu vier Produkte (Print, Online, Paket, Abo) als Karten mit Kennzeichen, Preis, Kaufen-Knopf, darunter der Mehrplatz-Hinweis mit Link zur Anfrage. Dazu Blöcke „Werk vorstellen“ (Text, Bild, Zitat) und „Jetzt lesen“ für Kunden mit Zugang. Shopware 6.6 erlaubt je Kategorie eigene Inhalte in denselben Layout-Plätzen. | je Werk: Einleitung, Bilder, die vier Produkte (Auswahl im Block), Zitate |
| **Storytelling** (MAV Einstieg, ZMV aktuell, ZMV Fachtagung, Interviews Autoren) | Erlebniswelten vom Typ „Landingpage“ (eigene Adresse, SEO) oder Kategorien vom Typ „Seite“ in einem Bereich „Wissen und Aktuelles“ in der Navigation. Eigene Blöcke „Interview“ (Frage und Antwort, Bild der Person) und „Linkliste“ (für „Hilfreiche Links“). | Texte, Bilder, Reihenfolge, neue Seiten nach Vorlage |

**Technik:** Eigene Blöcke bestehen aus einer Storefront-Vorlage (Twig, SCSS im Theme) und einer Vorschau samt Einstellungen
für die Verwaltung (Admin-JavaScript). Für das Admin-JavaScript brauchen wir einen Bauschritt (z. B. `shopware-cli extension
build` im Docker-Container); die gebauten Dateien liegen im Repository, `make setup` braucht dann keinen Bau. Inhalte der
Erlebniswelten liegen in der Datenbank, nicht im Repository: Für Staging und Live legen wir die Grundlayouts per
Einrichtungsbefehl an (wie die Rechtstexte), danach pflegt Ketteler. Keine Seitenbaukästen von Drittanbietern zum Start.

**Weiterleitungen:** Die heutigen Adressen (`/digibib`, `/bookreader`, `/importraet`, `/kompendiumaktuell`,
`/aktuellinteressant` usw., Liste in `content/README.md`) auf die neuen Seiten umleiten (301).

## 6. Offene Fragen

### An Atilla

1. Rechnungsanschrift beim ersten Anmelden: Gebaut sind alle 29 Lieferländer mit optionaler USt-IdNr. für Einrichtungen. Passt das?
2. Mehrplatz-Anfrage: Telefon ist Pflichtfeld, Nachricht freiwillig. So lassen?
3. PayPal lokal testen: Soll Studio Moco eine eigene PayPal-Sandbox-App anlegen (Zugangsdaten nur in der lokalen `.env`)?
   Ohne sie erscheint PayPal lokal nicht in der Kasse.
4. Steuern: Für Print an Privatkunden im EU-Ausland bleibt die deutsche Umsatzsteuer (Lieferschwelle 10.000 Euro im Jahr).
   Für die Schweiz und Großbritannien sind alle Preise ohne deutsche Umsatzsteuer (Ausfuhr). Bitte mit der Steuerberatung
   von Ketteler bestätigen; die Sätze für Online-Zugänge je Land sind Platzhalter (`daten/steuern.json`).
5. Preise bei Online-Zugängen ins EU-Ausland: Shopware hält den Bruttopreis gleich (89,90 Euro bleiben 89,90 Euro, die
   Steuer darin ändert sich). Gewünscht, oder soll der Nettopreis gleich bleiben?

### An Ketteler

Gesammelt in [fragen-ketteler.md](fragen-ketteler.md) (zum Weitergeben).
