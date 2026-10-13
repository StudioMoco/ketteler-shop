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
    pruefe(blocke.beitraege === 3, 'Aus dem Verlag: drei Beiträge');
    pruefe(blocke.liste === 5, 'Neu gewählt in die MAV: fünf Einträge');
    pruefe(blocke.buecher === 5, 'Für den Schreibtisch: fünf Bücher');
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
    const vorher = await b.werte(`document.querySelector('[data-kt="warenkorb-zahl"]').textContent.trim()`);
    const adresse = await b.werte('location.href');
    await b.werte(`document.querySelector('[data-kt="in-den-warenkorb"]').click()`);
    let nachher = vorher;
    let seitlich = false;
    for (let i = 0; i < 30 && (nachher === vorher || !seitlich); i += 1) {
        await b.warte(300);
        nachher = await b.werte(`document.querySelector('[data-kt="warenkorb-zahl"]').textContent.trim()`);
        seitlich = await b.werte(`!!document.querySelector('.offcanvas.show, .offcanvas.is-open, .offcanvas-cart.show')`);
    }
    pruefe(Number(nachher) === Number(vorher) + 1, 'Zähler im Kopf steigt um eins', vorher + ' -> ' + nachher);
    pruefe(seitlich, 'Rückmeldung: Warenkorb öffnet seitlich');
    pruefe((await b.werte('location.href')) === adresse, 'kein Seitenwechsel');

    console.log('Anfragen an fremde Server');
    const hosts = [...new Set(b.anfragen.filter((u) => /^https?:/.test(u)).map((u) => new URL(u).host))];
    pruefe(hosts.length === 1 && hosts[0] === new URL(SHOP).host, 'nur der eigene Server (' + hosts.join(', ') + ')');

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
