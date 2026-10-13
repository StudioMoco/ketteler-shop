// Ende-zu-Ende-Test der Storytelling-Seiten im echten Browser:
//   node tests/browser/storytelling.mjs [--staging] [--bilder <ordner>]
// Seiten mit Vorlage (MAV Einstieg, Grundwissen, Fachtagung, Interviews): genau ein h1, Texte der Vorlage vollständig
// (Hauptbereich ohne Brotkrumen, mit Preisen). Seiten ohne Vorlage (Arbeitshilfen, Themenbezogene Literatur, Hilfreiche
// Links, ZMV aktuell, Verlag, Links): Texte und Linktexte aus content/ vorhanden. Unternavigation auf allen sechs
// MAV-Seiten mit markierter aktiver Seite; mobil (390 px) als waagerecht scrollbare Zeile, Kacheln untereinander, keine
// waagerechte Scrollleiste der Seite; keine Anfrage an fremde Server; alle Links der Seiten erreichbar (200).
// Mit --bilder: ganze Seite bei 1440 und 390 px neben der Vorlage.
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { starten } from './cdp.mjs';

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const STAGING = process.argv.includes('--staging');
const bilderIndex = process.argv.indexOf('--bilder');
const BILDER = bilderIndex > 0 ? resolve(process.argv[bilderIndex + 1]) : null;
const text = STAGING
    ? execFileSync('ssh', [process.env.KETTELER_SERVER || 'ketteler', 'cat /opt/ketteler-shop/.env'], { encoding: 'utf8' })
    : readFileSync(join(WURZEL, '.env'), 'utf8');
const ENV = Object.fromEntries(text.split('\n').filter((z) => z.includes('=') && !z.startsWith('#')).map((z) => [z.slice(0, z.indexOf('=')), z.slice(z.indexOf('=') + 1).replace(/^'|'$/g, '')]));
const SHOP = (ENV.APP_URL || 'http://localhost:8000').replace(/\/$/, '');
const AUTH = STAGING ? { benutzer: ENV.STG_BASICAUTH_BENUTZER, passwort: ENV.STG_BASICAUTH_PASSWORT } : {};

const MAV = '/MAV-Einstieg/';
export const SEITEN = [
    { name: 'mav-einstieg', pfad: MAV, vorlage: 'story-mav-einstieg', h1: 'Liebe neugewählte Mitarbeitervertreterinnen und Mitarbeitervertreter,', aktiv: 'Aktuell & interessant' },
    { name: 'mav-aktuell', pfad: MAV + 'Aktuell-interessant-hilfreiche-Artikel-aus-der-ZMV/', vorlage: 'story-mav-einstieg', h1: 'Liebe neugewählte Mitarbeitervertreterinnen und Mitarbeitervertreter,', aktiv: 'Aktuell & interessant', ohneBilder: true },
    { name: 'mav-grundwissen', pfad: MAV + 'Grundausstattung-Basiswissen-fuer-die-MAV/', vorlage: 'story-mav-literatur', h1: 'Grundwissen/Basisausstattung', aktiv: 'Grundwissen/Basisausstattung' },
    { name: 'mav-arbeitshilfen', pfad: MAV + 'Praktische-Arbeitshilfen/', inhalt: 'storytelling/mav-einstieg-praktische-arbeitshilfen.md', h1: 'Praktische Arbeitshilfen', aktiv: 'Praktische Arbeitshilfen' },
    { name: 'mav-literatur', pfad: MAV + 'Themenbezogene-Literatur/', inhalt: 'storytelling/mav-einstieg-themenbezogene-literatur.md', h1: 'Themenbezogene Literatur', aktiv: 'Themenbezogene Literatur' },
    { name: 'mav-links', pfad: MAV + 'Hilfreiche-Links/', inhalt: 'storytelling/mav-einstieg-hilfreiche-links.md', h1: 'Hilfreiche Links', aktiv: 'Hilfreiche Links' },
    { name: 'fachtagung', pfad: '/Verlag/ZMV-Fachtagung/', vorlage: 'story-fachtagung', h1: '„Streiten, Schlichten, Gewinnen im kirchlichen Arbeitsrecht“' },
    { name: 'interviews', pfad: '/Verlag/Interviews-Autoren-Schriftleiter/', vorlage: 'story-interviews', h1: 'Interviews Autoren & Schriftleiter' },
    { name: 'zmv-aktuell', pfad: '/Zeitschrift-ZMV/ZMV-aktuell/', inhalt: 'storytelling/zmv-aktuell.md', h1: 'Die neueste Ausgabe: ZMV 5/2026' },
    { name: 'verlag', pfad: '/Verlag/', inhalt: 'verlag/verlag.md', h1: 'Was wir tun und wer wir sind: zwei Geschäftsbereiche, eine Philosophie' },
    { name: 'links', pfad: '/Verlag/Links/', inhalt: 'verlag/links.md', h1: 'Links' },
];
const UNTERNAV = ['Aktuell & interessant', 'Grundwissen/Basisausstattung', 'Praktische Arbeitshilfen', 'Themenbezogene Literatur', 'Hilfreiche Links'];

// Prüftexte aus content/: Linktexte und Absätze (ohne Auszeichnung, Silbentrennung am Zeilenende aufgelöst)
function inhaltTexte(datei) {
    let s = readFileSync(join(WURZEL, 'content', datei), 'utf8').split('---').slice(2).join('---').replace(/<!--[\s\S]*?-->/g, '');
    const links = [...s.matchAll(/\[([^\]]+)\]\([^)]+\)/g)].map((m) => m[1]);
    s = s.replace(/\[([^\]]*)\]\([^)]*\)/g, '$1').replace(/\*\*/g, '').replace(/^#+\s*/gm, '');
    // Absätze (Zeilenumbrüche im Original sind nur Umbrüche der alten Tabelle); Zwischenzeilen der alten Seite ausgenommen
    const absaetze = s.split(/\n\s*\n/).map((z) => z.replace(/\s+/g, ' ').trim())
        .filter((z) => z.length > 3 && !/^(LESEPROBEN?:|Aufsätze:|Rechtsprechung:|Leseranfrage:)$/.test(z) && !ABWEICHUNG[z]);
    return [...new Set([...links, ...absaetze])];
}
// Begründete Abweichung: „ZMV aktuell“ zeigt die Aktuelle Ausgabe wie auf der ZMV-Werkseite (freigegebene Vorlage), nicht
// die alte Sammelzeile
const ABWEICHUNG = { 'ZMV aktuell Die neueste Ausgabe: ZMV 5/2026 - September/Oktober 2026': true };
const norm = (t) => t.replace(/­/g, '').replace(/\s+/g, ' ').replace(/(\w)- (?!und |oder )([a-zäöüß])/g, '$1$2');

let gut = 0;
const schlecht = [];
const pruefe = (bedingung, name, info = '') => {
    if (bedingung) { gut += 1; console.log('  ok    ' + name); } else { schlecht.push(name); console.log('  FEHLER ' + name + (info ? '  (' + info + ')' : '')); }
};

const b = await starten(AUTH);
const vorlagen = {};
const alleLinks = new Set();
try {
    await b.groesse(1440, 900);
    await b.cdp('Network.setCookie', { name: 'cookie-preference', value: '1', url: SHOP + '/' });
    for (const s of SEITEN) {
        console.log(`${s.name}: ${SHOP}${s.pfad} (1440 px)`);
        let soll = [];
        if (s.vorlage) {
            vorlagen[s.vorlage] ??= join(dirname(fileURLToPath(import.meta.url)), `.vorlage-${s.vorlage}.html`);
            execFileSync('python3', [join(WURZEL, 'tests/browser/vorlage_vorschau.py'), vorlagen[s.vorlage], join(WURZEL, `docs/design/${s.vorlage}-entwurf.dc.html`)]);
            await b.laden('file://' + vorlagen[s.vorlage]);
            soll = await b.werte(`(() => {
                document.querySelectorAll('[data-cover], svg, nav[aria-label="Brotkrumen"]').forEach((e) => e.remove());
                const start = [...document.querySelectorAll('section')].find((x) => x.previousSibling && x.previousSibling.nodeType === 8 ? true : false);
                const teile = [];
                const kopf = document.querySelector('nav[aria-label]') ? null : null;
                let e = [...document.body.querySelectorAll('section')][0];
                // Hauptbereich: alle Abschnitte nach dem Kopf (header) bis zum Fuß (footer)
                const header = document.querySelector('header');
                for (e = header ? header.nextElementSibling : document.body.firstElementChild; e && e.tagName !== 'FOOTER'; e = e.nextElementSibling) { teile.push(e.innerText); }
                const zeilen = teile.join('\\n').split('\\n').map((z) => z.replace(/\\u00ad/g, '').replace(/\\s+/g, ' ').trim()).filter(Boolean);
                return [...new Set(zeilen.filter((z) => !/^\\[.*\\]$/.test(z)))];
            })()`);
        } else {
            soll = inhaltTexte(s.inhalt);
        }
        b.anfragen.length = 0;
        await b.laden(SHOP + s.pfad);
        const seite = await b.werte(`(() => {
            const main = document.querySelector('main') || document.body;
            const nav = document.querySelector('[data-kt="unternavigation"]');
            return { h1: [...document.querySelectorAll('h1')].map((h) => h.innerText.trim()), text: main.innerText,
                     nav: nav ? [...nav.querySelectorAll('a')].map((a) => a.innerText.trim()) : [],
                     aktiv: nav ? [...nav.querySelectorAll('a[aria-current="page"]')].map((a) => a.innerText.trim()) : [],
                     links: [...main.querySelectorAll('a[href]')].map((a) => a.href) };
        })()`);
        seite.links.forEach((l) => alleLinks.add(l));
        pruefe(seite.h1.length === 1 && norm(seite.h1[0]) === norm(s.h1), 'genau ein h1: „' + s.h1 + '“', seite.h1.join(' | '));
        const text = norm(seite.text);
        const fehlen = soll.filter((t) => !text.includes(norm(t)));
        pruefe(fehlen.length === 0, `Textvergleich (${s.vorlage ? 'Vorlage' : 'content/'}): ${soll.length - fehlen.length} von ${soll.length}`, fehlen.join(' | ').slice(0, 900));
        if (s.aktiv) {
            pruefe(JSON.stringify(seite.nav) === JSON.stringify(UNTERNAV) && seite.aktiv.length === 1 && seite.aktiv[0] === s.aktiv,
                `Unternavigation (5 Einträge, aktiv: ${s.aktiv})`, seite.nav.join(', ') + ' / aktiv: ' + seite.aktiv.join(', '));
        }
        const hosts = [...new Set(b.anfragen.filter((u) => /^https?:/.test(u)).map((u) => new URL(u).host))];
        pruefe(hosts.length === 1 && hosts[0] === new URL(SHOP).host, 'nur der eigene Server (' + hosts.join(', ') + ')');
    }

    console.log('Links der Storytelling-Seiten');
    const eigene = [...alleLinks].filter((l) => l.startsWith(SHOP) && !l.includes('#'));
    let erreichbar = 0;
    const kaputt = [];
    const kopf = STAGING ? { Authorization: 'Basic ' + Buffer.from(AUTH.benutzer + ':' + AUTH.passwort).toString('base64') } : {};
    for (const l of eigene) {
        const r = await fetch(l, { headers: kopf, redirect: 'manual' });
        if (r.status === 200) { erreichbar += 1; } else { kaputt.push(r.status + ' ' + l); }
    }
    pruefe(kaputt.length === 0, `${erreichbar} von ${eigene.length} eigenen Links antworten mit 200 (fremde Ziele wie zmv-online.de werden nicht aufgerufen)`, kaputt.join(' | ').slice(0, 600));

    console.log('Mobil (390 px)');
    await b.groesse(390, 844, true);
    for (const s of SEITEN.filter((x) => x.aktiv || x.name === 'fachtagung' || x.name === 'interviews')) {
        await b.laden(SHOP + s.pfad);
        const m = await b.werte(`(() => {
            const nav = document.querySelector('[data-kt="unternavigation"]');
            const kacheln = [...document.querySelectorAll('[data-kt="bereich"], [data-kt="titelkarte"]')].map((k) => Math.round(k.getBoundingClientRect().left));
            return { quer: document.documentElement.scrollWidth > window.innerWidth + 1,
                     zeile: nav ? getComputedStyle(nav).flexWrap === 'nowrap' && getComputedStyle(nav).overflowX === 'auto' : null,
                     untereinander: new Set(kacheln).size <= 1 };
        })()`);
        pruefe(!m.quer && m.zeile !== false && m.untereinander, `${s.name}: keine waagerechte Scrollleiste${m.zeile === null ? '' : ', Unternavigation als scrollbare Zeile'}, Kacheln untereinander`, JSON.stringify(m));
    }

    if (BILDER) {
        mkdirSync(BILDER, { recursive: true });
        const name = STAGING ? 'staging' : 'lokal';
        for (const s of SEITEN.filter((x) => !x.ohneBilder)) {
            for (const [breite, mobilAn] of [[1440, false], [390, true]]) {
                await b.groesse(breite, 900, mobilAn);
                await b.laden(SHOP + s.pfad);
                await b.ganzeSeite(join(BILDER, `${name}-story-${s.name}-${breite}.png`));
                if (!s.vorlage) { continue; }
                const vorlageBild = `vorlage-${s.vorlage}-${breite}.png`;
                if (!existsSync(join(BILDER, vorlageBild))) {
                    await b.laden('file://' + vorlagen[s.vorlage]);
                    await b.ganzeSeite(join(BILDER, vorlageBild));
                }
                const vergleich = join(BILDER, `vergleich-${name}-story-${s.name}-${breite}.html`);
                writeFileSync(vergleich, `<!doctype html><meta charset="utf-8"><body style="margin:0;background:#ddd;font:14px sans-serif">
                    <div style="display:flex;gap:24px;align-items:flex-start;padding:16px">
                    <figure style="margin:0"><figcaption>Vorlage (${breite} px)</figcaption><img src="${vorlageBild}" style="display:block;width:${breite}px"></figure>
                    <figure style="margin:0"><figcaption>${STAGING ? 'Staging' : 'Lokal'} (${breite} px)</figcaption><img src="${name}-story-${s.name}-${breite}.png" style="display:block;width:${breite}px"></figure>
                    </div></body>`);
                await b.groesse(breite * 2 + 56, 900, false);
                await b.laden('file://' + vergleich);
                await b.ganzeSeite(join(BILDER, `vergleich-${name}-story-${s.name}-${breite}.png`));
            }
        }
        console.log('Bilder: ' + BILDER);
    }
} finally {
    await b.beenden();
}
console.log(`\n${gut} bestanden, ${schlecht.length} fehlgeschlagen`);
schlecht.forEach((s) => console.log('  - ' + s));
process.exit(schlecht.length ? 1 : 0);
