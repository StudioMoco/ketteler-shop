# Plan: Ketteler-Shop mit Shopware 6

Stand 12.10.2026. Nur Plan, noch nichts davon gebaut. Grundlage: `docs/shop-weclapp-analyse.md` (Branch
`feature/shop-analyse` im Redaktions-Repository), `docs/lizenzen/shop-schnittstelle.md` und `docs/lizenzen/shopware.md`
(Redaktions-Repository, Branch `feature/stichtag`).

**Feste Entscheidungen** (Atilla, 12.10.2026): Shopware 6 ersetzt den NCE-Shop ganz (auch Print), Adresse später
`ketteler-verlag.de`. weclapp so wenig wie möglich: nur die offizielle weclapp-Integration (Artikel, Preise, Bestände
nach Shopware, Aufträge nach weclapp). Lizenzen nur in der Lizenzverwaltung des Redaktionssystems; ein Online-Kauf
vergibt die Lizenz sofort, auch bei Kauf auf Rechnung. Einfache Artikel ohne Varianten. Anmeldung über ZITADEL
(`login.ketteler-verlag.de`), ein Konto für Shop und Bibliothek.

## 0. Shopware-Version

| | |
|---|---|
| weclapp-Doku „Shopware 6“ (Stand 26.08.2025, abgerufen 12.10.2026) | „weclapp unterstützt alle Shopware 6.5 & 6.6-Versionen“; Major-Updates erst nach Freigabe durch weclapp |
| Shopware Store, weclapp-App | Kompatibilität bis 6.7.15.1 angezeigt; das ist die Store-App für Shopware Cloud und keine Freigabe der eingebauten Anbindung |
| aktuelle Shopware-Versionen | 6.7.15.1 (aktuell), **6.6.10.29** (Extended Support: Fehler- und Sicherheitskorrekturen bis 6.8 erscheint), 6.8 noch nicht erschienen |

**Gewählt: 6.6.10.29.** Das ist die neueste Version, die weclapp schriftlich unterstützt. Sobald weclapp 6.7 freigibt
(beim Support schriftlich anfragen), auf 6.7 wechseln, **vor** dem Livegang. Der Wechsel ist klein, solange nur
Theme, Einrichtung und zwei kleine Plugins bestehen. Nach 6.8 bekommt 6.6 nur noch Sicherheitskorrekturen über das
Shopware-Sicherheits-Plugin.

## 1. Lizenz-Plugin (`KettelerLizenzen`)

### Ablauf beim Kauf

```
Kunde bestellt (Rechnung)            Shopware                         Redaktionssystem
  └─ Bestellung abgeschlossen ──▶ Ereignis checkout.order.placed
                                   je Position mit ketteler_produkt
                                   (Online oder Bundle)
                                   └─ Nachricht in die Warteschlange
                                      (Message Queue, eigener Worker)
                                          └─ POST /api/shop/ereignisse ──▶ Kunde finden/anlegen,
                                             HMAC-signiert                   ZITADEL-Konto (Einladung),
                                                                             Lizenz anlegen, Mail
                                                                             „Zugang freigeschaltet“
```

- **Auslöser:** „Bestellung abgeschlossen“ (`checkout.order.placed`), nicht „bezahlt“: Rechnungskauf ist der
  Normalfall (≈ 85 %), die Lizenz kommt laut Entscheidung sofort. Dazu eine eigene Flow-Builder-Aktion „Ketteler: Lizenz
  vergeben“, damit die Verwaltung bei Bestellungen aus der Shopware-Verwaltung denselben Weg auslösen kann.
- **Welche Positionen:** Artikel-Zusatzfeld `ketteler_produkt` (heute `avr` oder `eichstaetter`, im Grundaufbau angelegt).
  Print-Artikel haben es leer. Bundle (Buch und Zugang) hat es gesetzt: Lizenz sofort, Buch geht über weclapp in den Versand.
- **Anfrage** genau nach `shop-schnittstelle.md`:
  `ereignis_id = order-<orderId>-<lineItemId>-lizenz`, `art = lizenz_anlegen`, `email` = E-Mail des Shop-Kontos (= ZITADEL-
  Anmeldename), `name`, `produkt`, `typ = einzel`, `bestellnummer` (SW100000 ff.). `weclapp_kundennummer` ist beim
  Bestellen noch nicht bekannt und bleibt leer.
- **Zuverlässigkeit:** Senden immer über die Warteschlange. Bei 5xx oder Zeitüberschreitung mit derselben `ereignis_id`
  nach 1, 5, 30 Minuten und dann stündlich bis 24 Stunden erneut; die Schnittstelle ist idempotent. Bei 4xx nicht
  wiederholen, sondern Mail an die Verwaltung und Vermerk an der Bestellung (Zusatzfeld `ketteler_lizenz_status`:
  offen, angelegt, fehler). Ergebnis (`lizenz_id`) an der Bestellposition speichern.
- **Geheimnis:** `KETTELER_LIZENZ_GEHEIMNIS` und `KETTELER_LIZENZ_URL` nur in der `.env` des jeweiligen Servers.
- **Bestellbestätigung:** Hinweis „Ihr Online-Zugang wird freigeschaltet, Sie erhalten eine Mail“ und Knopf „Jetzt lesen“
  (siehe Abschnitt 2).
- **Lokal testen:** eine kleine Attrappe der Schnittstelle als eigener Container (prüft Signatur, antwortet wie das
  Original), keine Verbindung zu Staging.

### Storno und Rückgabe

Die Schnittstelle kennt heute `lizenz_anlegen`, `probe_anlegen` und `kuendigen` (mit Enddatum). Vorschlag:

| Fall | Woher | Wirkung |
|---|---|---|
| Storno vor oder kurz nach der Bestellung (Bestellstatus „storniert“, in Shopware oder aus weclapp zurückgemeldet) | Ereignis `state_enter.order.state.cancelled` | **neue Art `stornieren`** an die Schnittstelle: Lizenz sofort beenden, Mail „Zugang beendet“ statt „gekündigt“. Braucht eine kleine Erweiterung im Redaktionssystem (heute würde `kuendigen` mit Ende heute die falsche Mail schicken). |
| Widerruf (Privatkunden, 14 Tage) | Verwaltung | Online-Zugang: Im Checkout für Privatkunden Zustimmung „sofortiger Beginn, Widerrufsrecht erlischt“ (§ 356 Abs. 5 BGB) abfragen. Widerruft trotzdem jemand, beendet die Verwaltung die Lizenz in „Kunden & Lizenzen“. |
| Rückgabe des Buchs aus einem Bundle | Verwaltung | Lizenz bleibt, außer die Verwaltung beendet sie. Rückgaben bucht weclapp; Shopware Community kennt keine Retourenverwaltung. |
| Rechnung nicht bezahlt | Verwaltung | Lizenz **sperren** in „Kunden & Lizenzen“ (Status gesperrt gibt es schon). Kein automatischer Weg aus weclapp, weil wir nichts aus weclapp lesen. |
| Abo-Kündigung (AVR Digital, später ZMV) | Verwaltung | wie heute: Kündigung in „Kunden & Lizenzen“ mit Ende der bezahlten Laufzeit. Der Vertrag liegt in weclapp. |

### Mehrplatz

Die Schnittstelle vergibt vom Shop aus nur **Einzellizenzen**, höchstens eine je Bestellung und Produkt; IP-Lizenzen
vergibt nur die Verwaltung. Vorschlag in zwei Stufen:

1. **Zum Start:** Online-Artikel im Shop mit Höchstmenge 1. Mehrplatz (z. B. „Zusätzliche digitale Lizenzen“ 05029-M,
   Eichstätter ab 3 oder 5 Zugängen) als eigener Artikel mit Zusatzfeld `ketteler_lizenz = verwaltung`: Das Plugin legt
   dafür **keine** Lizenz an, sondern meldet die Bestellung per Mail an die Verwaltung. Die Verwaltung vergibt Einzel- oder
   IP-Lizenzen in „Kunden & Lizenzen“. Einrichtungen mit festen Netzen bekommen weiter IP-Lizenzen.
2. **Später:** Leser-E-Mails im Checkout (eine je Platz). Das Plugin sendet je Leser ein `lizenz_anlegen` mit
   `ereignis_id …-lizenz-<n>`. Dafür muss das Redaktionssystem mehrere Lizenzen je Bestellung und Produkt zulassen
   (Schlüssel Bestellnummer, Produkt, laufende Nummer). Erst bauen, wenn Ketteler das will (Frage unten).

## 2. Anmeldung über ZITADEL

### Plugin

Shopware 6.6 hat für Kunden (Storefront) kein OpenID Connect. Geprüfte Möglichkeiten:

| Plugin | Lizenz, Preis | Eignung |
|---|---|---|
| `tinect/oauth2-storefront-login` (GitHub, v2.3.1, 6.6 und 6.7) | MIT, kostenlos | allgemeines OIDC mit Discovery, Verknüpfung über `sub`, sonst E-Mail; PKCE und Abmelden bei ZITADEL (`end_session`) nicht dokumentiert |
| `MartinKuhl/shopware6-oidc-sso` | MIT, kostenlos | PKCE und Abmelden vorhanden, aber Version 0.1, nur 6.7, kaum erprobt |
| moorl „Login with … Keycloak and more“ (Store) | ≈ 189 €/Jahr | eigener OAuth2-Anbieter möglich, Verknüpfung über E-Mail, PKCE und Abmelden nicht beschrieben |
| PremSoft „SSO Single Sign-On Login“ (Store) | ≈ 361 €/Jahr | PKCE, Abmelde-Adresse; ab 6.6.6 |

**Empfehlung: eigenes kleines Plugin `KettelerAnmeldung`**, im Aufbau angelehnt an das MIT-Plugin von tinect. Gründe: Es
geht um Anmeldung und damit um Sicherheit, der Umfang ist klein (Weiterleitung, Rückkehr, Konto finden oder anlegen,
Abmelden), und wir brauchen ZITADEL-Eigenheiten, die kein fertiges Plugin abdeckt: Organisation „KETTELER Kunden“ per
Scope `urn:zitadel:iam:org:id:<id>`, PKCE, `end_session`, nur bestätigte E-Mails, keine Shopware-Passwörter. Keine
laufenden Kosten, kein fremder Code im Anmeldeweg, den wir nicht geprüft haben.

### Shop-Konto und ZITADEL-Konto

1. In ZITADEL im Projekt „Redaktionssystem“ eine Anwendung „Shop“ (Web, Authorization Code mit PKCE), Redirect
   `https://ketteler-verlag.de/account/oidc/callback`, Post-Logout `https://ketteler-verlag.de/`. Gleiche Organisation
   „KETTELER Kunden“ wie die Bibliothek, also dieselben Konten.
2. „Anmelden“ und „Registrieren“ im Shop leiten zu ZITADEL. Die Shopware-Passwortanmeldung für Kunden wird abgeschaltet
   (Routen für Passwort-Login und Passwort vergessen leiten auf ZITADEL).
3. Nach der Rückkehr: Kunde über `sub` finden (Zusatzfeld `ketteler_zitadel_sub` am Kunden). Sonst über die E-Mail, aber
   nur bei `email_verified = true`, dann `sub` speichern. Sonst Kunde neu anlegen (Name aus ZITADEL, Anschrift fragt der
   Checkout). Das Shopware-Passwort ist ein unbrauchbarer Zufallswert.
4. Registrieren: Selbstregistrierung der Kunden-Organisation in ZITADEL mit E-Mail-Bestätigung (Seite im Ketteler-Design
   gibt es schon, auf Staging aus).
5. Abmelden im Shop beendet auch die ZITADEL-Sitzung (`end_session`).
6. Gastbestellung: Für Print möglich lassen (wie heute). **Online-Artikel nur mit Konto**, damit die Lizenz am richtigen
   Konto hängt (Frage unten). Bestellt ein Gast doch, legt die Lizenz-Schnittstelle das Konto an und schickt die Einladung.

### „Jetzt lesen“ ohne neue Anmeldung

- Shop und Bibliothek liegen unter derselben Adresse: `ketteler-verlag.de` (Shopware) und `ketteler-verlag.de/bibliothek`
  (Redaktionssystem, Caddy leitet den Pfad weiter). Beide melden über `login.ketteler-verlag.de` an.
- Wer im Shop angemeldet ist, hat eine ZITADEL-Sitzung (Cookie auf `login.ketteler-verlag.de`). Der Knopf „Jetzt lesen“
  (Bestellbestätigung, Kundenkonto „Meine Online-Werke“, Artikelseite bei vorhandener Lizenz) führt auf
  `/bibliothek/<werk>`. Die Bibliothek hat noch keine eigene Sitzung, leitet zu ZITADEL, ZITADEL erkennt die Sitzung und
  leitet ohne Passwortabfrage zurück. Für den Leser sieht das wie ein kurzer Seitenwechsel aus.
- Die Lizenz entsteht in der Warteschlange einige Sekunden nach der Bestellung. Kommt jemand schneller in der Bibliothek an,
  zeigt sie „Ihr Zugang wird eingerichtet“ und lädt nach (kleine Ergänzung in der Bibliothek).
- Kopf und Fuß: Die Bibliothek hat bewusst austauschbare Bausteine (`rahmen.jsx`). Ziel ist derselbe Kopf und Fuß im Shop
  und in der Bibliothek; das Theme in diesem Repository übernimmt dafür schon Farben, Schrift und Maße.

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

- **Wann:** nachdem Lizenz- und Anmelde-Plugin lokal laufen und der Ketteler-Test des Redaktionssystems abgeschlossen ist.
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

1. Lokal: Grundaufbau (fertig), dann Lizenz-Plugin mit Attrappe, dann Anmelde-Plugin gegen einen lokalen ZITADEL-Container.
2. Redaktionssystem: Schnittstelle um `stornieren` erweitern (und später Mehrplatz), Bibliothek „Zugang wird eingerichtet“.
3. Staging-Server vergrößern, Shop-Staging aufbauen, Plugins gegen Staging-Redaktionssystem und Staging-ZITADEL.
4. weclapp an Shop-Staging: zuerst Artikel, dann Aufträge. Parallel räumt Ketteler weclapp auf.
5. Test mit Ketteler (Bestellen, Rechnung, Lizenz, Lesen, Storno).
6. Live-Server auf CPX42, Shop live unter vorläufiger Adresse prüfen, dann DNS von NCE auf den neuen Shop umstellen.

## 5. Offene Fragen

### An Atilla

1. Shopware 6.6 bis zur weclapp-Freigabe von 6.7 einverstanden? (Alternative: gleich 6.7 und Risiko bei weclapp.)
2. Eigenes Anmelde-Plugin statt fertigem Plugin einverstanden?
3. Neue Schnittstellen-Art `stornieren` im Redaktionssystem: einverstanden?
4. Mehrplatz zum Start über die Verwaltung (Stufe 1) einverstanden?
5. Gastbestellung: für Print erlauben, für Online-Artikel nur mit Konto?
6. Staging-Server auf CPX32 vergrößern, wenn Shop-Staging ansteht?
7. Adresse für Shop-Staging: `staging-shop.mocodev.de` passend?

### An Ketteler

1. Bitte bei weclapp schriftlich anfragen: Ab wann unterstützt die Shopware-6-Anbindung Version 6.7?
2. Mehrplatz: Wie viele Einrichtungen kaufen mehrere Zugänge, sollen sie im Shop bestellen können oder weiter per Anfrage?
3. Storno und Widerruf bei Online-Zugängen: sofort sperren oder erst nach Prüfung durch die Verwaltung?
4. Abos im Shop (ZMV Print und Digital, AVR Digital): bestellbar wie heute, Vertrag legt die Verwaltung in weclapp an?
5. Wer räumt die Artikel in weclapp auf (Liste in Abschnitt 3), bis wann?
6. Zahlarten: Nur Rechnung zum Start? Heute nennt die Versandseite auch Bankeinzug und Nachnahme; in weclapp kommen
   PayPal-Aufträge vor.
7. Versand: Länder (heute Deutschland, Österreich, Schweiz, Italien, Großbritannien, EU) und Versandkosten nach Gewicht
   oder pauschal?
8. Darf weclapp an einen Testshop angebunden werden (Testmandant oder nur Artikel lesen)?
9. Inhalte: Was wird aus den Inhaltsseiten „MAV Einstieg“, „ZMV aktuell“, „ZMV Fachtagung“, „Interviews Autoren“? Im
   neuen Shop als Erlebniswelten (Inhaltsseiten) oder entfallen sie?
10. Rechtstexte für den neuen Shop (AGB, Widerruf für digitale Inhalte, Datenschutz mit ZITADEL und weclapp) und
    Geschäftsführung fürs Impressum.
11. Buchhandel: eigene Kundengruppe mit Rabatt im Shop nötig?
