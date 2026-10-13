// Beim Überfahren nichts unterstreichen (nur Farbe): jeder sichtbare Link und Knopf auf Startseite, Kategorieseite,
// Werkseiten (ZMV, AVR), Artikelseite und Warenkorb wird mit der Maus überfahren, dann text-decoration-line geprüft. Erlaubt ist ein
// Unterstrich nur, wenn das Element schon ohne Maus unterstrichen ist (dauerhafte Textlinks).
//   node tests/browser/unterstreichen.mjs [--staging]
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { starten } from './cdp.mjs';

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const STAGING = process.argv.includes('--staging');
const text = STAGING
    ? execFileSync('ssh', [process.env.KETTELER_SERVER || 'ketteler', 'cat /opt/ketteler-shop/.env'], { encoding: 'utf8' })
    : readFileSync(join(WURZEL, '.env'), 'utf8');
const ENV = Object.fromEntries(text.split('\n').filter((z) => z.includes('=') && !z.startsWith('#')).map((z) => [z.slice(0, z.indexOf('=')), z.slice(z.indexOf('=') + 1).replace(/^'|'$/g, '')]));
const SHOP = (ENV.APP_URL || 'http://localhost:8000').replace(/\/$/, '');
const AUTH = STAGING ? { benutzer: ENV.STG_BASICAUTH_BENUTZER, passwort: ENV.STG_BASICAUTH_PASSWORT } : {};

const b = await starten(AUTH);
let geprueft = 0;
const fehler = [];
async function seite(name, url) {
    await b.laden(url);
    await b.cdp('Network.setCookie', { name: 'cookie-preference', value: '1', url: SHOP + '/' });
    const anzahl = await b.werte(`(window.__kt = [...document.querySelectorAll('a[href], button')].filter((e) => {
        const r = e.getBoundingClientRect(); const s = getComputedStyle(e);
        return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && !e.closest('[hidden], .offcanvas:not(.show), .modal:not(.show), .cookie-permission-container');
    })).length`);
    let fehlerSeite = 0;
    for (let i = 0; i < anzahl; i += 1) {
        await b.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 1, y: 1 });
        const r = await b.werte(`(() => { const e = window.__kt[${i}]; if (!e || !e.isConnected) { return null; } e.scrollIntoView({ block: 'center' });
            const q = e.getBoundingClientRect(); return { x: q.x + Math.min(q.width / 2, 20), y: q.y + q.height / 2, ruhe: getComputedStyle(e).textDecorationLine };
        })()`);
        if (!r) { continue; }
        await b.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r.x, y: r.y });
        const ueber = await b.werte(`(() => { const e = window.__kt[${i}]; const d = getComputedStyle(e).textDecorationLine;
            const kind = [...e.querySelectorAll('*')].some((k) => getComputedStyle(k).textDecorationLine.includes('underline'));
            return { d, kind, text: (e.innerText || e.getAttribute('aria-label') || e.tagName).trim().slice(0, 40) }; })()`);
        geprueft += 1;
        const unterstrichen = ueber.d.includes('underline') || ueber.kind;
        if (unterstrichen && !r.ruhe.includes('underline')) {
            fehlerSeite += 1;
            fehler.push(name + ': „' + ueber.text + '“');
        }
    }
    console.log(`  ${name}: ${anzahl} Links und Knöpfe überfahren, ${fehlerSeite} beim Überfahren unterstrichen`);
}

try {
    await b.groesse(1440, 900);
    await seite('Startseite', SHOP + '/');
    await seite('Kategorie Praxishandbücher', SHOP + '/Praxishandbuecher/');
    const artikel = await b.werte(`(document.querySelector('.kt-karte-titel') || document.querySelector('.product-name') || {}).href || ''`);
    await seite('Werkseite ZMV', SHOP + '/Zeitschrift-ZMV/');
    await seite('Werkseite AVR', SHOP + '/Kompendium-AVR-Caritas/');
    await seite('Artikelseite', artikel);
    await b.werte(`(() => { const f = document.querySelector('form[data-add-to-cart], .buy-widget form'); if (f) { f.requestSubmit ? f.requestSubmit() : f.submit(); } })()`);
    await b.warte(2500);
    await seite('Warenkorb', SHOP + '/checkout/cart');
} finally {
    await b.beenden();
}
console.log(`\n${geprueft - fehler.length} von ${geprueft} Links und Knöpfen ohne Unterstrich beim Überfahren (dauerhafte Textlinks ausgenommen)`);
fehler.slice(0, 30).forEach((f) => console.log('  - ' + f));
process.exit(fehler.length ? 1 : 0);
