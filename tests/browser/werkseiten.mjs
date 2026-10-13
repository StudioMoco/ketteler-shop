// Ende-zu-Ende-Test der drei Werkseiten (AVR, ZMV, Eichstätter) im echten Browser:
//   node tests/browser/werkseiten.mjs [--staging] [--bilder <ordner>]
// Je Seite: lädt, genau ein h1, Texte der Vorlage vollständig (Hauptbereich, mit Preisen), Negativliste, Sprungmarken,
// keine Anfrage an fremde Server, Kaufen aus jeder Karte legt genau dieses Produkt in den Warenkorb (ohne
// Seitenwechsel), Mehrplatz-Link öffnet die Anfrage mit vorausgefülltem Werk; ZMV: Jahrgangsreiter per Maus und
// Tastatur ohne Neuladen; mobil (390 px): keine waagerechte Scrollleiste, Karten untereinander, hervorgehobene zuerst.
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

export const WERKSEITEN = [
    { name: 'avr', pfad: '/Kompendium-AVR-Caritas/', werk: 'avr', h1: 'Die AVR von A bis Z', karten: 2, anker: ['angebote', 'leseproben'] },
    { name: 'zmv', pfad: '/Zeitschrift-ZMV/', werk: 'zmv', h1: 'ZMV Die Mitarbeitervertretung', karten: 3, anker: ['angebote', 'probeheft', 'einzelhefte'] },
    { name: 'eichstaetter', pfad: '/Eichstaetter-Kommentar/', werk: 'eichstaetter', h1: 'Eichstätter Kommentar MAVO und KAGO', karten: 1, anker: ['angebote', 'leseproben'] },
];
// Was es im Shop nicht geben darf (Entscheidungen von Atilla, docs/plan.md)
// Begründete Abweichung: Die Testartikel tragen TEST-Nummern, die Vorlage zeigt die echten Bestell-Nummern
const NUMMERN = { '05029-D': 'TEST-O-AVR', '05029': 'TEST-P-AVR', '608082': 'TEST-O-EK' };
const VERBOTEN = ['05029-M', 'Zusätzliche digitale Lizenzen', 'ab 2. Lizenz', '59,90', '71,90', '44,90', 'Staffel', 'Gutschein-Code', 'Mengenrabatt'];

let gut = 0;
const schlecht = [];
const pruefe = (bedingung, name, info = '') => {
    if (bedingung) { gut += 1; console.log('  ok    ' + name); } else { schlecht.push(name); console.log('  FEHLER ' + name + (info ? '  (' + info + ')' : '')); }
};

const b = await starten(AUTH);
const vorlagen = {};
try {
    // Texte der Vorlagen (Hauptbereich zwischen Werk-Kopf und Fuß; Platzhalter in eckigen Klammern und Zier-Cover zählen nicht)
    for (const w of WERKSEITEN) {
        vorlagen[w.name] = join(dirname(fileURLToPath(import.meta.url)), `.vorlage-werkseite-${w.name}.html`);
        execFileSync('python3', [join(WURZEL, 'tests/browser/vorlage_vorschau.py'), vorlagen[w.name], join(WURZEL, `docs/design/werkseite-${w.name}-entwurf.dc.html`)]);
    }
    await b.groesse(1440, 900);
    await b.cdp('Network.setCookie', { name: 'cookie-preference', value: '1', url: SHOP + '/' });

    for (const w of WERKSEITEN) {
        console.log(`Werkseite ${w.name}: ${SHOP}${w.pfad} (1440 px)`);
        await b.laden('file://' + vorlagen[w.name]);
        const vorlageTexte = await b.werte(`(() => {
            document.querySelectorAll('[data-cover], svg').forEach((e) => e.remove());
            const start = document.querySelector('nav[aria-label="Brotkrumen"]').closest('section');
            const teile = [];
            for (let e = start; e && e.tagName !== 'FOOTER'; e = e.nextElementSibling) { teile.push(e.innerText); }
            const zeilen = teile.join('\\n').split('\\n').map((z) => z.replace(/\\s+/g, ' ').trim()).filter(Boolean);
            return [...new Set(zeilen.filter((z) => !/^\\[.*\\]$/.test(z) && !/^\\[(Cover|Foto|Videobild)/.test(z)))];
        })()`);
        b.anfragen.length = 0;
        await b.laden(SHOP + w.pfad);
        const seite = await b.werte(`(() => {
            const main = document.querySelector('main') || document.body;
            return { h1: [...document.querySelectorAll('h1')].map((h) => h.innerText.trim()), text: main.innerText.replace(/\\s+/g, ' '),
                     anker: [...document.querySelectorAll('[id]')].map((e) => e.id), karten: document.querySelectorAll('[data-kt="angebot"]').length,
                     strich: /[–—]/.test(main.innerText.replace(/Die AVR von A bis Z - Digital|MAVO & KAGO - Online-Zugang/g, '')) };
        })()`);
        pruefe(seite.h1.length === 1 && seite.h1[0] === w.h1, 'genau ein h1: „' + w.h1 + '“', seite.h1.join(' | '));
        const ersetzt = vorlageTexte.map((t) => t.replace(/^Bestell-Nr\. (\S+)$/, (z, n) => 'Bestell-Nr. ' + (NUMMERN[n] || n)));
        const fehlen = ersetzt.filter((t) => !seite.text.includes(t));
        pruefe(fehlen.length === 0, `Textvergleich: ${vorlageTexte.length - fehlen.length} von ${vorlageTexte.length} Texten der Vorlage (mit Preisen)`, fehlen.join(' | ').slice(0, 900));
        const verboten = VERBOTEN.filter((v) => seite.text.includes(v));
        pruefe(verboten.length === 0, `Negativliste: ${VERBOTEN.length - verboten.length} von ${VERBOTEN.length} nicht vorhanden (05029-M, Mengenpreise, Gutschein-Seite)`, verboten.join(', '));
        pruefe(!seite.strich, 'kein Gedankenstrich im Hauptbereich (Artikelnamen der Vorlage ausgenommen)');
        pruefe(w.anker.every((a) => seite.anker.includes(a)), 'Sprungmarken ' + w.anker.map((a) => '#' + a).join(', '));
        pruefe(seite.karten === w.karten, `${w.karten} Kaufkarte(n)`, String(seite.karten));
        const hosts = [...new Set(b.anfragen.filter((u) => /^https?:/.test(u)).map((u) => new URL(u).host))];
        pruefe(hosts.length === 1 && hosts[0] === new URL(SHOP).host, 'nur der eigene Server (' + hosts.join(', ') + ')');

        // Kaufen aus jeder Karte: genau dieses Produkt im Warenkorb, ohne Seitenwechsel
        const nummern = await b.werte(`[...document.querySelectorAll('[data-kt="angebot"]')].filter((k) => k.querySelector('[data-kt="angebot-kaufen"]')).map((k) => k.dataset.nummer)`);
        const zeilenNummern = await b.werte(`[...document.querySelectorAll('[data-kt="zeile-kaufen"]')].map((k) => k.dataset.nummer)`);
        for (const [nummer, auswahl] of [...nummern.map((n) => [n, `[data-kt="angebot"][data-nummer="${n}"] [data-kt="angebot-kaufen"]`]), ...zeilenNummern.map((n) => [n, `[data-kt="zeile-kaufen"][data-nummer="${n}"]`])]) {
            await b.cdp('Network.clearBrowserCookies');
            await b.cdp('Network.setCookie', { name: 'cookie-preference', value: '1', url: SHOP + '/' });
            await b.laden(SHOP + w.pfad);
            const adresse = await b.werte('location.href');
            await b.werte(`document.querySelector('${auswahl}').click()`);
            let seitlich = false;
            for (let i = 0; i < 40 && !seitlich; i += 1) {
                await b.warte(300);
                seitlich = await b.werte(`!!document.querySelector('.offcanvas.show .line-item, .offcanvas.show .cart-item, .offcanvas.show [class*="line-item"]')`);
            }
            const ohneWechsel = (await b.werte('location.href')) === adresse;
            await b.laden(SHOP + '/checkout/cart');
            const korb = await b.werte(`document.querySelector('main').innerText`);
            const anzahl = await b.werte(`document.querySelectorAll('.line-item-product, .line-item.is-product, [class*="line-item-product"]').length`);
            pruefe(seitlich && ohneWechsel && korb.includes(nummer), `Kaufen: ${nummer} im Warenkorb (seitlich, ohne Seitenwechsel)`, `seitlich=${seitlich} ohneWechsel=${ohneWechsel} anzahl=${anzahl}`);
        }

        // Mehrplatz: Link öffnet die Anfrage mit vorausgefülltem Werk
        await b.laden(SHOP + w.pfad);
        const mehrplatz = await b.werte(`(document.querySelector('[data-kt="mehrplatz-link"]') || {}).href || ''`);
        await b.laden(mehrplatz);
        const werk = await b.werte(`(document.querySelector('[data-kt="mehrplatz-werk"]') || {}).value || ''`);
        pruefe(mehrplatz.includes('werk=' + w.werk) && werk === w.werk, 'Mehrplatz-Link öffnet die Anfrage mit Werk „' + w.werk + '“', mehrplatz + ' -> ' + werk);
        await b.laden(SHOP + w.pfad);
        const mehrplatzKarte = await b.werte(`[...document.querySelectorAll('[data-kt="angebot"]')].some((k) => /Mehrplatz|Lizenzen/.test(k.innerText))`);
        pruefe(!mehrplatzKarte, 'Mehrplatz nur als Hinweiszeile, nie als Karte');
    }

    console.log('ZMV: Jahrgangsreiter');
    await b.laden(SHOP + '/Zeitschrift-ZMV/');
    await b.werte(`window.__ktOhneNeuladen = 1`);
    const reiter = await b.werte(`[...document.querySelectorAll('[data-kt="jahrgang-reiter"]')].map((r) => r.innerText.trim())`);
    pruefe(reiter.length >= 3 && reiter[0] === '2026', 'Reiter ab dem neuesten Jahrgang: ' + reiter.join(', '));
    const sichtbar = `[...document.querySelectorAll('[data-kt="jahrgang"]')].filter((p) => !p.hidden).map((p) => p.dataset.jahr + ':' + p.querySelectorAll('[data-kt="heft"]').length).join(',')`;
    pruefe((await b.werte(sichtbar)).startsWith('2026:5'), 'zu Beginn 2026 mit 5 Heften', await b.werte(sichtbar));
    await b.werte(`document.querySelectorAll('[data-kt="jahrgang-reiter"]')[1].click()`);
    pruefe((await b.werte(sichtbar)) === '2025:6', 'Klick auf 2025 zeigt 6 Hefte 2025', await b.werte(sichtbar));
    await b.werte(`document.querySelectorAll('[data-kt="jahrgang-reiter"]')[1].focus()`);
    await b.taste('ArrowRight', 'ArrowRight', 39);
    const nachTaste = await b.werte(`({ sichtbar: ${sichtbar}, fokus: document.activeElement.innerText.trim(), gewaehlt: document.activeElement.getAttribute('aria-selected') })`);
    pruefe(nachTaste.sichtbar === '2024:6' && nachTaste.fokus === '2024' && nachTaste.gewaehlt === 'true', 'Pfeiltaste rechts wählt 2024 (Fokus und aria-selected)', JSON.stringify(nachTaste));
    await b.taste('Home', 'Home', 36);
    pruefe((await b.werte(sichtbar)).startsWith('2026'), 'Pos1 wählt wieder 2026');
    pruefe((await b.werte('window.__ktOhneNeuladen')) === 1, 'ohne Neuladen');
    const archiv = await b.werte(`(document.querySelector('[data-kt="archiv-link"]') || {}).href || ''`);
    pruefe(/ZMV|Archiv/i.test(archiv), 'Archiv-Kasten verlinkt die Archiv-Kategorie', archiv);

    console.log('Mobil (390 px)');
    await b.groesse(390, 844, true);
    for (const w of WERKSEITEN) {
        await b.laden(SHOP + w.pfad);
        const m = await b.werte(`(() => {
            const karten = [...document.querySelectorAll('[data-kt="angebot"]')].map((k) => ({ y: Math.round(k.getBoundingClientRect().top), x: Math.round(k.getBoundingClientRect().left), h: k.classList.contains('kt-angebot--hervorgehoben') }));
            const sortiert = [...karten].sort((a, c) => a.y - c.y);
            return { quer: document.documentElement.scrollWidth > window.innerWidth + 1, untereinander: new Set(karten.map((k) => k.x)).size <= 1,
                     zuerst: karten.some((k) => k.h) ? sortiert[0].h : true };
        })()`);
        pruefe(!m.quer && m.untereinander && m.zuerst, `${w.name}: keine waagerechte Scrollleiste, Karten untereinander, hervorgehobene zuerst`, JSON.stringify(m));
    }

    if (BILDER) {
        mkdirSync(BILDER, { recursive: true });
        const name = STAGING ? 'staging' : 'lokal';
        for (const w of WERKSEITEN) {
            for (const [breite, mobilAn] of [[1440, false], [390, true]]) {
                await b.groesse(breite, 900, mobilAn);
                await b.laden(SHOP + w.pfad);
                await b.ganzeSeite(join(BILDER, `${name}-werkseite-${w.name}-${breite}.png`));
                if (!existsSync(join(BILDER, `vorlage-werkseite-${w.name}-${breite}.png`))) {
                    await b.laden('file://' + vorlagen[w.name]);
                    await b.ganzeSeite(join(BILDER, `vorlage-werkseite-${w.name}-${breite}.png`));
                }
                const vergleich = join(BILDER, `vergleich-${name}-werkseite-${w.name}-${breite}.html`);
                writeFileSync(vergleich, `<!doctype html><meta charset="utf-8"><body style="margin:0;background:#ddd;font:14px sans-serif">
                    <div style="display:flex;gap:24px;align-items:flex-start;padding:16px">
                    <figure style="margin:0"><figcaption>Vorlage (${breite} px)</figcaption><img src="vorlage-werkseite-${w.name}-${breite}.png" style="display:block;width:${breite}px"></figure>
                    <figure style="margin:0"><figcaption>${STAGING ? 'Staging' : 'Lokal'} (${breite} px)</figcaption><img src="${name}-werkseite-${w.name}-${breite}.png" style="display:block;width:${breite}px"></figure>
                    </div></body>`);
                await b.groesse(breite * 2 + 56, 900, false);
                await b.laden('file://' + vergleich);
                await b.ganzeSeite(join(BILDER, `vergleich-${name}-werkseite-${w.name}-${breite}.png`));
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
