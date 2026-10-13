// Ende-zu-Ende-Test der Startseite im echten Browser (eigenes Headless-Chrome, leeres Profil):
//   node tests/browser/startseite.mjs [--staging] [--bilder <ordner>]
// Prüft: Blöcke sichtbar, genau ein h1, kein „[Preis]“, alle Links erreichbar, keine Anfrage an fremde Server,
// Videodialog per Tastatur (Enter öffnet, Escape schließt, Fokus zurück), „In den Warenkorb“ ohne Seitenwechsel mit
// Rückmeldung, mobil (390 px): keine waagerechte Scrollleiste, Kacheln untereinander, Kategorien als scrollbare Reihe,
// Menü-Schublade. Mit --bilder: ganze Seite bei 1440 und 390 px, jeweils neben der Vorlage.
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { starten } from './cdp.mjs';

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const STAGING = process.argv.includes('--staging');
const bilderIndex = process.argv.indexOf('--bilder');
const BILDER = bilderIndex > 0 ? resolve(process.argv[bilderIndex + 1]) : null;

function env() {
    const text = STAGING
        ? execFileSync('ssh', [process.env.KETTELER_SERVER || 'ketteler', 'cat /opt/ketteler-shop/.env'], { encoding: 'utf8' })
        : readFileSync(join(WURZEL, '.env'), 'utf8');
    const werte = {};
    for (const zeile of text.split('\n')) {
        const i = zeile.indexOf('=');
        if (i > 0 && !zeile.startsWith('#')) { werte[zeile.slice(0, i)] = zeile.slice(i + 1).replace(/^'|'$/g, ''); }
    }
    return werte;
}

const ENV = env();
const SHOP = (ENV.APP_URL || 'http://localhost:8000').replace(/\/$/, '');
const AUTH = STAGING ? { benutzer: ENV.STG_BASICAUTH_BENUTZER, passwort: ENV.STG_BASICAUTH_PASSWORT } : {};
const KOPF = STAGING ? { Authorization: 'Basic ' + Buffer.from(AUTH.benutzer + ':' + AUTH.passwort).toString('base64') } : {};

let gut = 0;
const schlecht = [];
const pruefe = (bedingung, name, info = '') => {
    if (bedingung) { gut += 1; console.log('  ok    ' + name); } else { schlecht.push(name); console.log('  FEHLER ' + name + (info ? '  (' + info + ')' : '')); }
};

const b = await starten(AUTH);
try {
    console.log('Startseite ' + SHOP + ' (1440 px)');
    await b.groesse(1440, 900);
    await b.laden(SHOP + '/');
    const blocke = await b.werte(`(() => {
        const sichtbar = (s) => [...document.querySelectorAll(s)].filter((e) => e.getBoundingClientRect().height > 0).length;
        return { schaufenster: sichtbar('.kt-schaufenster-haupt'), kacheln: sichtbar('[data-kt="kachel"]'), produkt: sichtbar('[data-kt="schaufenster-produkt"]'),
                 kategorien: sichtbar('[data-kt="kategorie"]'), werke: sichtbar('[data-kt="werk"]'), hinweis: sichtbar('.kt-hinweis'),
                 beitraege: sichtbar('[data-kt="beitrag"]'), liste: sichtbar('[data-kt="liste-eintrag"]'), buecher: sichtbar('[data-kt="buch"]'),
                 h1: document.querySelectorAll('h1').length, preisPlatzhalter: document.body.innerText.includes('[Preis]'),
                 ueberschriften: [...document.querySelectorAll('main h1, main h2, main h3')].map((h) => h.tagName).join(''),
                 strich: /[–—]/.test(document.querySelector('main').innerText),
                 servicezeile: !!document.querySelector('.kt-servicezeile'), fussSpalten: document.querySelectorAll('.kt-fuss-spalte').length };
    })()`);
    pruefe(blocke.schaufenster === 1 && blocke.kacheln === 2 && blocke.produkt === 1, 'Schaufenster mit Produkt und zwei Nebenkacheln', JSON.stringify(blocke));
    pruefe(blocke.kategorien === 5, 'Im Sortiment: fünf Kategorien', String(blocke.kategorien));
    pruefe(blocke.werke === 3, 'Drei Werke', String(blocke.werke));
    pruefe(blocke.hinweis === 1, 'Hinweis mit Zeichen');
    pruefe(blocke.beitraege === 3, 'Nachrichten und Aktuelles: drei Beiträge');
    pruefe(blocke.liste === 5, 'MAV Einstieg: fünf Einträge');
    pruefe(blocke.buecher === 5, 'Weitere Empfehlungen: fünf Produktkarten');
    pruefe(blocke.h1 === 1, 'genau eine Hauptüberschrift (h1)', String(blocke.h1));
    pruefe(blocke.ueberschriften.startsWith('H1H2') && !/H1.*H1/.test(blocke.ueberschriften) && !/H2H4/.test(blocke.ueberschriften), 'Überschriften in richtiger Reihenfolge', blocke.ueberschriften);
    pruefe(!blocke.preisPlatzhalter, 'nirgends „[Preis]“');
    pruefe(!blocke.strich, 'keine Gedankenstriche im Inhalt');
    pruefe(blocke.servicezeile && blocke.fussSpalten === 3, 'Servicezeile und Fuß mit drei Spalten');

    console.log('Links');
    const links = await b.werte(`[...new Set([...document.querySelectorAll('a[href]')].map((a) => a.href))]`);
    const fremd = links.filter((l) => /^https?:/.test(l) && !l.startsWith(SHOP) && !l.startsWith(ENV.KETTELER_BIBLIOTHEK_URL || '~'));
    pruefe(fremd.length === 0, 'keine Links zu fremden Seiten (außer Bibliothek)', fremd.join(' '));
    const kaputt = [];
    for (const l of links.filter((x) => x.startsWith(SHOP) && !x.includes('#'))) {
        const r = await fetch(l, { headers: KOPF, redirect: 'follow' });
        if (r.status !== 200) { kaputt.push(r.status + ' ' + l); }
    }
    pruefe(kaputt.length === 0, 'alle %d Links im Shop führen auf eine Seite (200)'.replace('%d', links.filter((x) => x.startsWith(SHOP)).length), kaputt.join(', '));

    console.log('Videodialog per Tastatur');
    const video = await b.werte(`!!document.querySelector('[data-kt="video-knopf"]')`);
    if (video) {
        await b.werte(`document.querySelector('[data-kt="video-knopf"]').focus()`);
        await b.taste('Enter', 'Enter', 13);
        await b.warte(300);
        const offen = await b.werte(`(() => { const d = document.querySelector('[data-kt="video-dialog"]'); return { offen: d.open, fokusDrin: d.contains(document.activeElement), untertitel: !!d.querySelector('track') }; })()`);
        pruefe(offen.offen && offen.fokusDrin, 'Enter öffnet den Dialog, Fokus im Dialog', JSON.stringify(offen));
        pruefe(offen.untertitel, 'Untertitel eingebunden (track)');
        await b.taste('Escape', 'Escape', 27);
        await b.warte(300);
        const zu = await b.werte(`(() => ({ offen: document.querySelector('[data-kt="video-dialog"]').open, fokusZurueck: document.activeElement === document.querySelector('[data-kt="video-knopf"]') }))()`);
        pruefe(!zu.offen && zu.fokusZurueck, 'Escape schließt den Dialog, Fokus zurück auf dem Knopf', JSON.stringify(zu));
    } else {
        pruefe(false, 'Knopf mit Video vorhanden');
    }

    console.log('In den Warenkorb aus der Produktreihe');
    const vorher = await b.werte(`(document.querySelector('[data-kt="warenkorb-zahl"]')?.textContent || '').trim()`);
    const adresse = await b.werte('location.href');
    await b.werte(`document.querySelector('[data-kt="in-den-warenkorb"]').click()`);
    let nachher = vorher;
    let seitlich = false;
    for (let i = 0; i < 40 && (nachher === vorher || nachher === '' || !seitlich); i += 1) {
        await b.warte(300);
        nachher = await b.werte(`(document.querySelector('[data-kt="warenkorb-zahl"]')?.textContent || '').trim()`);
        seitlich = await b.werte(`!!document.querySelector('.offcanvas.show, .offcanvas.is-open, .offcanvas-cart.show')`);
    }
    pruefe(Number(nachher) === Number(vorher) + 1, 'Zähler im Kopf steigt um eins', vorher + ' -> ' + nachher);
    pruefe(seitlich, 'Rückmeldung: Warenkorb öffnet seitlich');
    pruefe((await b.werte('location.href')) === adresse, 'kein Seitenwechsel');

    console.log('Anfragen an fremde Server (Startseite, Warenkorb seitlich, Kategorie, Artikelseite)');
    await b.laden(SHOP + '/Praxishandbuecher/');
    const artikel = await b.werte(`(document.querySelector('.product-name') || {}).href || ''`);
    if (artikel) { await b.laden(artikel); }
    const hosts = [...new Set(b.anfragen.filter((u) => /^https?:/.test(u)).map((u) => new URL(u).host))];
    pruefe(hosts.length === 1 && hosts[0] === new URL(SHOP).host, 'nur der eigene Server (' + hosts.join(', ') + ')');


    console.log('Texte aus der Vorlage (docs/design/startseite-entwurf.dc.html)');
    const vorschau = join(dirname(fileURLToPath(import.meta.url)), '.vorlage-vorschau.html');
    execFileSync('python3', [join(WURZEL, 'tests/browser/vorlage_vorschau.py'), vorschau]);
    await b.groesse(1440, 900);
    await b.laden('file://' + vorschau);
    const vorlageTexte = await b.werte(`(() => {
        // Sichtbare Texte ohne Platzhalter-Cover und ohne Zierzahlen; Preise kommen aus dem Produkt und zählen nicht
        document.querySelectorAll('[data-cover], svg').forEach((e) => e.remove());
        const zeilen = document.body.innerText.split('\\n').map((z) => z.replace(/\\s+/g, ' ').trim()).filter(Boolean);
        const ohnePreis = zeilen.map((z) => z.replace(/(ab )?\\d{1,3}(\\.\\d{3})*,\\d{2} €/g, '').trim()).filter((z) => z && !/^\\d+$/.test(z) && z !== 'ab');
        return [...new Set(ohnePreis)];
    })()`);
    await b.laden(SHOP + '/');
    const seitenText = await b.werte(`document.body.innerText.replace(/\\s+/g, ' ')`);
    const fehlen = vorlageTexte.filter((t) => !seitenText.includes(t));
    const platzhalter = await b.werte(`document.querySelector('.header-search-input')?.getAttribute('placeholder')`);
    pruefe(fehlen.length === 0, `Textvergleich: ${vorlageTexte.length - fehlen.length} von ${vorlageTexte.length} Texten der Vorlage auf der Startseite`, fehlen.join(' | ').slice(0, 900));
    pruefe(platzhalter === 'Suchen', 'Suchfeld: Platzhalter „Suchen“', String(platzhalter));
    const verboten = ['Serviceunternehmen der KAB', 'Wir sind für Sie da', 'Drei Werke, die jede MAV kennt', 'Aus dem Verlag', 'Neu gewählt in die MAV',
        'Für den Schreibtisch', 'als Sachmittel vorgesehen', 'Print mit Online-Zugang', 'Wir machen Ihnen ein Angebot',
        'Reihe zum kirchlichen Arbeitsrecht', 'Neu in der MAV', 'ab 9,80'];
    const attribute = await b.werte(`[...document.querySelectorAll('[alt],[title],[aria-label]')].map((e) => [e.getAttribute('alt'), e.getAttribute('title'), e.getAttribute('aria-label')].join(' ')).join(' ')`);
    const gefunden = verboten.filter((w) => (seitenText + ' ' + attribute).replace(/\\u00a0/g, ' ').includes(w));
    pruefe(gefunden.length === 0, `Negativliste: ${verboten.length - gefunden.length} von ${verboten.length} Wendungen nicht vorhanden`, gefunden.join(' | '));
    const preise = await b.werte(`[...document.querySelectorAll('[data-kt="werk-preis"]')].map((e) => e.textContent.replace(/\\s+/g, ' ').trim())`);
    pruefe(JSON.stringify(preise) === JSON.stringify(['ab 129,80 €', '89,90 €', '198,90 €']), 'Werke-Preise: ab 129,80 €, 89,90 €, 198,90 €', JSON.stringify(preise));

    console.log('Logo');
    const logo = await b.werte(`(() => { const i = document.querySelector('[data-kt="logo"]'); const r = i.getBoundingClientRect(); return { src: i.currentSrc, nw: i.naturalWidth, nh: i.naturalHeight, w: Math.round(r.width), h: Math.round(r.height), alt: i.alt }; })()`);
    // Logo in doppelter Auflösung für 240 × 47 (docs/design/logo/ketteler-logo-fachverlag.png, 480 × 94)
    pruefe(logo.src.includes('ketteler-logo-fachverlag.png') && logo.nw === 480 && logo.nh === 94, 'Logo-Datei ausgeliefert (480 × 94, doppelte Auflösung)', JSON.stringify(logo));
    pruefe(logo.w === 240 && Math.abs(logo.h - 47) <= 1, 'Logo am Desktop 240 × 47 px', logo.w + ' × ' + logo.h);
    pruefe(logo.alt === 'KETTELER-Verlag', 'Logo: Alt-Text „KETTELER-Verlag“', logo.alt);

    console.log('Produktkarten (Weitere Empfehlungen)');
    const karten = await b.werte(`(() => {
        const k = [...document.querySelectorAll('.kt-produktreihe [data-kt="produktkarte"]')];
        const reihen = {};
        k.forEach((e) => { const r = e.getBoundingClientRect(); (reihen[Math.round(r.top)] ||= []).push(Math.round(r.height)); });
        return { anzahl: k.length, gleich: Object.values(reihen).every((h) => new Set(h).size === 1), proReihe: Math.max(...Object.values(reihen).map((h) => h.length)),
                 preisUndKnopf: k.every((e) => e.querySelector('.kt-karte-preis')?.getBoundingClientRect().height > 0 && e.querySelector('.kt-pille')?.getBoundingClientRect().height > 0),
                 labels: k.every((e) => (e.querySelector('[data-kt="in-den-warenkorb"]')?.getAttribute('aria-label') || '').includes(e.querySelector('.kt-karte-titel').textContent.trim())) };
    })()`);
    pruefe(karten.anzahl === 5 && karten.proReihe === 5, 'fünf Karten in einer Reihe (1440 px)', JSON.stringify(karten));
    pruefe(karten.gleich, 'Karten einer Reihe gleich hoch');
    pruefe(karten.preisUndKnopf, 'Preis und Knopf auf jeder Karte sichtbar');
    pruefe(karten.labels, 'Warenkorb-Knopf mit aria-label samt Produktname');
    await b.laden(SHOP + '/Zeitschrift-ZMV/ZMV-Print/');
    const abo = await b.werte(`[...document.querySelectorAll('[data-kt="produktkarte"]')].filter((k) => k.querySelector('.kt-abo')).map((k) => !!k.querySelector('[data-kt="zum-angebot"]') && !k.querySelector('[data-kt="in-den-warenkorb"]'))`);
    pruefe(abo.length > 0 && abo.every(Boolean), 'Abo-Karten zeigen „Zum Angebot“ statt „In den Warenkorb“', JSON.stringify(abo));

    console.log('Kategorien: Titel ohne Umbruch (1440 und 1280 px)');
    for (const breite of [1440, 1280]) {
        await b.groesse(breite, 900);
        await b.laden(SHOP + '/');
        const kat = await b.werte(`[...document.querySelectorAll('.kt-kategorie-name')].map((e) => ({ t: e.textContent, zeilen: Math.round(e.getBoundingClientRect().height / parseFloat(getComputedStyle(e).lineHeight)), ab: e.scrollWidth > e.clientWidth }))`);
        pruefe(kat.length === 5 && kat.every((k) => k.zeilen === 1 && !k.ab), `fünf Kategorien, alle Titel einzeilig (${breite} px)`, JSON.stringify(kat.filter((k) => k.zeilen !== 1 || k.ab)));
    }

    console.log('Dropdown in der Hauptnavigation');
    for (const breite of [1440, 1280]) {
        await b.groesse(breite, 900);
        await b.laden(SHOP + '/');
        const punkte = await b.werte(`[...document.querySelectorAll('.kt-hat-dropdown')].map((p) => p.querySelector('.kt-hauptnav-link').textContent.trim())`);
        let lage = [];
        for (let i = 0; i < punkte.length; i += 1) {
            const r = await b.werte(`(() => { const p = document.querySelectorAll('.kt-hat-dropdown')[${i}]; const q = p.querySelector('.kt-hauptnav-link').getBoundingClientRect(); return { x: q.x + q.width / 2, y: q.y + q.height / 2 }; })()`);
            await b.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r.x - 2, y: r.y });
            await b.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r.x, y: r.y });
            for (let t = 0; t < 10 && !(await b.werte(`!document.querySelectorAll('.kt-hat-dropdown')[${i}].querySelector('.kt-dropdown').hidden`)); t += 1) { await b.warte(100); }
            lage.push(await b.werte(`(() => { const p = document.querySelectorAll('.kt-hat-dropdown')[${i}]; const a = p.querySelector('.kt-hauptnav-link'); const l = p.querySelector('.kt-dropdown');
                const ra = a.getBoundingClientRect(), rl = l.getBoundingClientRect();
                return { name: a.textContent.trim(), offen: !l.hidden, aria: a.getAttribute('aria-expanded'), links: Math.round(rl.left - ra.left), rechts: Math.round(document.documentElement.clientWidth - rl.right) }; })()`));
            await b.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 5, y: 600 });
            await b.warte(400);
        }
        const verschoben = lage.filter((l) => l.links !== 0);
        pruefe(lage.length > 0 && lage.every((l) => l.offen && l.aria === 'true'), `Maus: jedes Dropdown öffnet, aria-expanded=true (${breite} px)`, JSON.stringify(lage.filter((l) => !l.offen)));
        pruefe(lage.every((l) => Math.abs(l.links) <= 2 || l.rechts >= 0) && lage.every((l) => l.rechts >= 0), `Position: linke Kante bündig (±2 px), rechter Rand im Bild (${breite} px)`, JSON.stringify(lage));
        if (breite === 1440) { console.log('  verschoben am rechten Rand: ' + (verschoben.map((l) => l.name + ' ' + l.links + ' px').join(', ') || 'keins')); }
    }
    const ohneKinder = await b.werte(`[...document.querySelectorAll('.kt-hauptnav-punkt:not(.kt-hat-dropdown)')].every((p) => !p.querySelector('.kt-pfeil, .kt-dropdown'))`);
    const knoepfe = await b.werte(`document.querySelectorAll('.kt-dropdown button, .js-close-flyout-menu, .navigation-flyout, .kt-flyout-schliessen').length`);
    pruefe(ohneKinder, 'Menüpunkte ohne Unterkategorien: kein Pfeil, kein Dropdown');
    pruefe(knoepfe === 0, 'kein Schließen-Knopf und kein Shopware-Flyout im DOM', String(knoepfe));
    await b.groesse(1440, 900);
    await b.laden(SHOP + '/');
    await b.werte(`document.querySelector('.kt-hauptnav-link').focus()`);
    await b.taste('Tab', 'Tab', 9);
    await b.warte(250);
    const tastatur = await b.werte(`(() => { const a = document.activeElement; const p = a.closest('.kt-hat-dropdown'); return { name: a.textContent.trim(), offen: p ? !p.querySelector('.kt-dropdown').hidden : null }; })()`);
    await b.werte(`(() => { const p = document.querySelector('.kt-hat-dropdown'); p.querySelector('.kt-hauptnav-link').focus(); })()`);
    await b.taste('ArrowDown', 'ArrowDown', 40);
    await b.warte(150);
    const erster = await b.werte(`(() => { const p = document.querySelector('.kt-hat-dropdown'); return document.activeElement === p.querySelector('.kt-dropdown a'); })()`);
    await b.taste('ArrowDown', 'ArrowDown', 40);
    const zweiter = await b.werte(`(() => { const p = document.querySelector('.kt-hat-dropdown'); return document.activeElement === p.querySelectorAll('.kt-dropdown a')[1]; })()`);
    await b.taste('Escape', 'Escape', 27);
    await b.warte(150);
    const esc = await b.werte(`(() => { const p = document.querySelector('.kt-hat-dropdown'); const a = p.querySelector('.kt-hauptnav-link'); return { zu: p.querySelector('.kt-dropdown').hidden, fokus: document.activeElement === a, aria: a.getAttribute('aria-expanded') }; })()`);
    pruefe(tastatur.offen !== false, 'Tastatur: Fokus per Tab öffnet das Dropdown', JSON.stringify(tastatur));
    pruefe(erster && zweiter, 'Pfeil nach unten öffnet und wandert durch die Einträge');
    pruefe(esc.zu && esc.fokus && esc.aria === 'false', 'Escape schließt, Fokus zurück auf dem Menüpunkt, aria-expanded=false', JSON.stringify(esc));
    if (BILDER) {
        const ziel = await b.werte(`[...document.querySelectorAll('.kt-hat-dropdown')].findIndex((p) => /Praxishandbücher/.test(p.textContent))`);
        const index = ziel >= 0 ? ziel : 0;
        const r = await b.werte(`(() => { const q = document.querySelectorAll('.kt-hat-dropdown')[${index}].querySelector('.kt-hauptnav-link').getBoundingClientRect(); return { x: q.x + q.width / 2, y: q.y + q.height / 2 }; })()`);
        await b.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r.x, y: r.y });
        await b.warte(400);
        const bild = await b.cdp('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: 1440, height: 520, scale: 1 } });
        mkdirSync(BILDER, { recursive: true });
        writeFileSync(join(BILDER, 'dropdown-1440.png'), Buffer.from(bild.data, 'base64'));
        await b.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 5, y: 700 });
    }

    console.log('Mobil (390 px)');
    await b.groesse(390, 844, true);
    await b.laden(SHOP + '/');
    const mobil = await b.werte(`(() => {
        const r = (s) => document.querySelector(s).getBoundingClientRect();
        const reihe = document.querySelector('.kt-kategorien-reihe');
        const cover = document.querySelector('.kt-schaufenster-produkt .kt-cover').getBoundingClientRect();
        const text = document.querySelector('.kt-schaufenster-text').getBoundingClientRect();
        return { breite: document.documentElement.scrollWidth, untereinander: r('.kt-schaufenster-neben').top >= r('.kt-schaufenster-haupt').bottom - 1,
                 scrollbar: reihe.scrollWidth > reihe.clientWidth && getComputedStyle(reihe).overflowX === 'auto',
                 ueberlappt: !(cover.top >= text.bottom - 1 || cover.right <= text.left || cover.left >= text.right),
                 menue: [...document.querySelectorAll('.nav-main-toggle-btn')].some((k) => k.getBoundingClientRect().width > 0) };
    })()`);
    pruefe(mobil.breite <= 390, 'keine waagerechte Scrollleiste', String(mobil.breite));
    pruefe(mobil.untereinander, 'Kacheln des Schaufensters untereinander');
    pruefe(!mobil.ueberlappt, 'Cover im Schaufenster überlappt den Text nicht');
    pruefe(mobil.scrollbar, 'Kategorien als waagerecht scrollbare Reihe');
    pruefe(mobil.menue, 'Menü-Knopf für die Schublade sichtbar');

    if (BILDER) {
        mkdirSync(BILDER, { recursive: true });
        const name = STAGING ? 'staging' : 'lokal';
        const vorlage = join(BILDER, 'vorlage-vorschau.html');
        execFileSync('python3', [join(WURZEL, 'tests/browser/vorlage_vorschau.py'), vorlage]);
        // Produktreihe allein (1440 und 390 px)
        for (const [breite, mobilAn] of [[1440, false], [390, true]]) {
            await b.groesse(breite, 900, mobilAn);
            await b.laden(SHOP + '/');
            const r = await b.werte(`(() => { const e = document.querySelector('.kt-produktreihe'); e.scrollIntoView(); const q = e.getBoundingClientRect(); return { x: q.left + scrollX, y: q.top + scrollY, w: q.width, h: q.height }; })()`);
            const bild = await b.cdp('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: r.x, y: r.y, width: r.w, height: r.h, scale: 1 } });
            writeFileSync(join(BILDER, `produktkarten-${breite}.png`), Buffer.from(bild.data, 'base64'));
        }
        // Bilder wie für einen wiederkehrenden Besucher: Cookie-Leiste schon beantwortet (nur technisch notwendige)
        await b.cdp('Network.setCookie', { name: 'cookie-preference', value: '1', url: SHOP + '/' });
        for (const [breite, mobilAn] of [[1440, false], [390, true]]) {
            await b.groesse(breite, 900, mobilAn);
            await b.laden(SHOP + '/');
            await b.ganzeSeite(join(BILDER, `${name}-startseite-${breite}.png`));
            if (!existsSync(join(BILDER, `vorlage-startseite-${breite}.png`))) {
                await b.laden('file://' + vorlage);
                await b.ganzeSeite(join(BILDER, `vorlage-startseite-${breite}.png`));
            }
            const vergleich = join(BILDER, `vergleich-${name}-${breite}.html`);
            writeFileSync(vergleich, `<!doctype html><meta charset="utf-8"><body style="margin:0;background:#ddd;font:14px sans-serif">
                <div style="display:flex;gap:24px;align-items:flex-start;padding:16px">
                <figure style="margin:0"><figcaption>Vorlage (${breite} px)</figcaption><img src="vorlage-startseite-${breite}.png" style="display:block;width:${breite}px"></figure>
                <figure style="margin:0"><figcaption>${name === 'staging' ? 'Staging' : 'Lokal'} (${breite} px)</figcaption><img src="${name}-startseite-${breite}.png" style="display:block;width:${breite}px"></figure>
                </div></body>`);
            await b.groesse(breite * 2 + 56, 900, false);
            await b.laden('file://' + vergleich);
            await b.ganzeSeite(join(BILDER, `vergleich-${name}-${breite}.png`));
        }
        console.log('Bilder: ' + BILDER);
    }
} finally {
    await b.beenden();
}
console.log(`\n${gut} bestanden, ${schlecht.length} fehlgeschlagen`);
schlecht.forEach((s) => console.log('  - ' + s));
process.exit(schlecht.length ? 1 : 0);
