// Bildschirmfotos für die Abnahme (Gesamtauftrag E): Warenkorb, Kasse (Gast-Anmeldung und Bestellübersicht), Kundenkonto
// und eine Bestellbestätigung aus Mailpit, je bei 1440 und 390 px. Es entsteht keine Bestellung. Die Anmeldung läuft über
// ZITADEL mit dem Testkonto privat-de@ketteler.test; das Passwort liest das Skript zur Laufzeit aus der .env.
//   node tests/browser/abnahmebilder.mjs [--staging] --bilder <ordner>
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { starten } from './cdp.mjs';

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const STAGING = process.argv.includes('--staging');
const BILDER = resolve(process.argv[process.argv.indexOf('--bilder') + 1]);
const text = STAGING
    ? execFileSync('ssh', [process.env.KETTELER_SERVER || 'ketteler', 'cat /opt/ketteler-shop/.env'], { encoding: 'utf8' })
    : readFileSync(join(WURZEL, '.env'), 'utf8');
const ENV = Object.fromEntries(text.split('\n').filter((z) => z.includes('=') && !z.startsWith('#')).map((z) => [z.slice(0, z.indexOf('=')), z.slice(z.indexOf('=') + 1).replace(/^'|'$/g, '')]));
const SHOP = (ENV.APP_URL || 'http://localhost:8000').replace(/\/$/, '');
const MAILPIT = (ENV.KETTELER_MAILPIT_URL || 'http://localhost:' + (ENV.MAILPIT_PORT || '8025')).replace(/\/$/, '');
const AUTH = STAGING ? { benutzer: ENV.STG_BASICAUTH_BENUTZER, passwort: ENV.STG_BASICAUTH_PASSWORT } : {};
const KOPF = STAGING ? { Authorization: 'Basic ' + Buffer.from(AUTH.benutzer + ':' + AUTH.passwort).toString('base64') } : {};
const name = STAGING ? 'staging' : 'lokal';
mkdirSync(BILDER, { recursive: true });

const b = await starten(AUTH);
const bilder = [];
async function foto(datei, url) {
    for (const [breite, mobil] of [[1440, false], [390, true]]) {
        await b.groesse(breite, 900, mobil);
        if (url) { await b.laden(url); }
        await b.ganzeSeite(join(BILDER, `${name}-${datei}-${breite}.png`));
        bilder.push(`${name}-${datei}-${breite}.png`);
    }
}
try {
    await b.cdp('Network.setCookie', { name: 'cookie-preference', value: '1', url: SHOP + '/' });
    await b.groesse(1440, 900);
    // Warenkorb mit AVR Print (aus der Karte der Werkseite) und Kasse als Gast (ohne Bestellung)
    await b.laden(SHOP + '/Kompendium-AVR-Caritas/');
    await b.werte(`document.querySelector('[data-kt="angebot"][data-nummer="TEST-P-AVR"] [data-kt="angebot-kaufen"]').click()`);
    await b.warte(2500);
    await foto('warenkorb', SHOP + '/checkout/cart');
    await foto('kasse-anmeldung', SHOP + '/checkout/register');
    // Kundenkonto (Anmeldung über ZITADEL)
    await b.laden(SHOP + '/account/oidc/anmelden');
    await b.warte(1500);
    await b.werte(`(() => { const f = document.querySelector('#loginName'); if (f) { f.value = 'privat-de@ketteler.test'; f.form.submit(); } })()`);
    await b.warte(2500);
    await b.werte(`(() => { const f = document.querySelector('#password'); if (f) { f.value = ${JSON.stringify(ENV.KETTELER_TESTKUNDE_PASSWORT || '')}; f.form.submit(); } })()`);
    await b.warte(4000);
    const angemeldet = (await b.werte('location.href')).startsWith(SHOP + '/account');
    console.log(angemeldet ? 'Angemeldet als privat-de@ketteler.test' : 'Anmeldung nicht abgeschlossen: ' + (await b.werte('location.pathname')));
    if (angemeldet) {
        await foto('konto', SHOP + '/account');
        await foto('konto-bestellungen', SHOP + '/account/order');
        await b.laden(SHOP + '/Kompendium-AVR-Caritas/');
        await b.werte(`document.querySelector('[data-kt="angebot"][data-nummer="TEST-P-AVR"] [data-kt="angebot-kaufen"]').click()`);
        await b.warte(2500);
        await foto('kasse-uebersicht', SHOP + '/checkout/confirm');
    }
    // Neueste Bestellbestätigung aus Mailpit als Seite (die Mail selbst, wie sie ankommt)
    const suche = await (await fetch(MAILPIT + '/api/v1/search?query=' + encodeURIComponent('subject:"beim KETTELER-Verlag"') + '&limit=1', { headers: KOPF })).json();
    const id = suche.messages?.[0]?.ID;
    if (id) {
        const mail = await (await fetch(MAILPIT + '/api/v1/message/' + id, { headers: KOPF })).json();
        const datei = join(BILDER, `${name}-mail-bestellbestaetigung.html`);
        writeFileSync(datei, mail.HTML);
        await foto('mail-bestellbestaetigung', 'file://' + datei);
        console.log('Mail: ' + mail.Subject);
    }
} finally {
    await b.beenden();
}
console.log('Bilder: ' + bilder.join(', '));
