// Verwaltung im echten Browser: Erlebniswelt „Startseite“ öffnen, die sieben KETTELER-Blöcke müssen im Editor stehen,
// das Einstellungsfenster eines Blocks öffnet sich, keine Fehler in der Konsole. Anmeldung über die Admin-API mit den
// Zugangsdaten aus der .env (bzw. auf Staging per SSH), nur im Prozess, nie ausgegeben.
//   node tests/browser/verwaltung.mjs [--staging] [--bild <datei.png>]
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { starten } from './cdp.mjs';

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const STAGING = process.argv.includes('--staging');
const bildIndex = process.argv.indexOf('--bild');
const text = STAGING
    ? execFileSync('ssh', [process.env.KETTELER_SERVER || 'ketteler', 'cat /opt/ketteler-shop/.env'], { encoding: 'utf8' })
    : readFileSync(join(WURZEL, '.env'), 'utf8');
const ENV = Object.fromEntries(text.split('\n').filter((z) => z.includes('=') && !z.startsWith('#')).map((z) => [z.slice(0, z.indexOf('=')), z.slice(z.indexOf('=') + 1).replace(/^'|'$/g, '')]));
const SHOP = (ENV.APP_URL || 'http://localhost:8000').replace(/\/$/, '');
const AUTH = STAGING ? { benutzer: ENV.STG_BASICAUTH_BENUTZER, passwort: ENV.STG_BASICAUTH_PASSWORT } : {};

let gut = 0;
const schlecht = [];
const pruefe = (b, name, info = '') => { if (b) { gut += 1; console.log('  ok    ' + name); } else { schlecht.push(name); console.log('  FEHLER ' + name + (info ? '  (' + info + ')' : '')); } };

const token = await (await fetch(SHOP + '/api/oauth/token', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ grant_type: 'password', client_id: 'administration', scopes: 'write', username: 'admin', password: ENV.SHOP_ADMIN_PASSWORT }),
})).json();
pruefe(!!token.access_token, 'Anmeldung an der Admin-API');
const seite = await (await fetch(SHOP + '/api/search/cms-page', {
    method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token.access_token },
    body: JSON.stringify({ filter: [{ type: 'equals', field: 'name', value: 'Startseite' }], limit: 1 }),
})).json();
const seitenId = seite.data?.[0]?.id;
const werkseite = await (await fetch(SHOP + '/api/search/cms-page', {
    method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token.access_token },
    body: JSON.stringify({ filter: [{ type: 'equals', field: 'name', value: 'Werkseite' }], limit: 1 }),
})).json();
const werkseitenId = werkseite.data?.[0]?.id;
// Neueste Bestellung mit einem Online-Zugang (AVR Digital), für die Karte „Online-Zugänge (Lizenzen)“
const { createHash } = await import('node:crypto');
const bestellung = await (await fetch(SHOP + '/api/search/order', {
    method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token.access_token },
    body: JSON.stringify({ filter: [{ type: 'equals', field: 'lineItems.productId', value: createHash('md5').update('ketteler.artikel.TEST-O-AVR').digest('hex') }],
        sort: [{ field: 'createdAt', order: 'DESC' }], limit: 1 }),
})).json();
const bestellungId = bestellung.data?.[0]?.id;

const b = await starten(AUTH);
const fehler = [];
b.auf('Runtime.exceptionThrown', (p) => fehler.push(p.exceptionDetails.exception?.description || p.exceptionDetails.text));
b.auf('Runtime.consoleAPICalled', (p) => { if (p.type === 'error') { fehler.push(p.args.map((a) => a.value || a.description).join(' ')); } });
try {
    await b.groesse(1440, 1000);
    // Anmeldung der Verwaltung als Cookie vorab setzen, dann direkt den Editor laden (Hash-Wechsel löst kein Laden aus)
    const bearer = { access: token.access_token, refresh: token.refresh_token, expiry: Math.round(Date.now() / 1000) + token.expires_in };
    await b.cdp('Network.setCookie', { name: 'bearerAuth', value: encodeURIComponent(JSON.stringify(bearer)), url: SHOP + '/admin' });
    await b.laden(SHOP + '/admin#/sw/cms/detail/' + seitenId);
    let anzahl = 0;
    for (let i = 0; i < 60 && anzahl < 7; i += 1) {
        await b.warte(1000);
        anzahl = await b.werte(`document.querySelectorAll('.ketteler-cms-el').length`);
    }
    pruefe(anzahl === 7, 'Editor zeigt die sieben KETTELER-Blöcke', String(anzahl));
    const titel = await b.werte(`[...document.querySelectorAll('.ketteler-cms-el')].map((e) => e.innerText.split('\\n')[0]).join(' | ')`);
    console.log('  Blöcke: ' + titel);
    // Einstellungen des ersten Blocks öffnen (Element anklicken, dann Einstellungen im Seitenmenü)
    // Wie ein Mensch mit der Maus: Block anklicken (auswählen), dann das Zahnrad „Einstellungen“ des Slots
    const klick = async (selektor) => {
        const r = await b.werte(`(() => { const e = ${selektor}; if (!e) { return null; } e.scrollIntoView({ block: 'center' }); const q = e.getBoundingClientRect(); return { x: q.x + q.width / 2, y: q.y + q.height / 2 }; })()`);
        if (!r) { return false; }
        for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) {
            await b.cdp('Input.dispatchMouseEvent', { type, x: r.x, y: r.y, button: 'left', clickCount: 1 });
        }
        return true;
    };
    await klick(`document.querySelector('.ketteler-cms-el')`);
    await b.warte(1200);
    await klick(`document.querySelector('.ketteler-cms-el').closest('.sw-cms-slot').querySelector('.sw-cms-slot__settings-action')`);
    let felder = 0;
    for (let i = 0; i < 15 && felder === 0; i += 1) {
        await b.warte(500);
        felder = await b.werte(`document.querySelectorAll('.ketteler-cms-el-config .ketteler-schema-feld').length`);
    }
    pruefe(felder > 5, 'Einstellungsfenster des Schaufensters mit Feldern', String(felder));
    // Alle eigenen Blöcke mit deutschem Namen und Vorschau registriert; Layout „Werkseite“ mit seinen zehn Plätzen
    const bloecke = await b.werte(`Object.values(Shopware.Service('cmsService').getCmsBlockRegistry()).filter((x) => x.name.startsWith('ketteler-')).map((x) => x.label + (x.previewComponent ? '' : ' (ohne Vorschau)'))`);
    pruefe(bloecke.length === 24 && bloecke.every((l) => l.startsWith('KETTELER: ') && !l.includes('ohne Vorschau')), '24 KETTELER-Blöcke mit Namen und Vorschau', bloecke.join(' | '));
    // Layout „Werkseite“: innerhalb der Verwaltung wechseln (ein zweiter Start der Verwaltung scheitert an Shopware selbst)
    await b.werte(`(document.querySelector('.sw-modal__close') || { click() {} }).click()`);
    await b.warte(800);
    await b.werte(`Shopware.Application.view.router.push({ name: 'sw.cms.index' }).then(() => true)`);
    await b.warte(1500);
    await b.werte(`(document.querySelector('.sw-modal .sw-button--primary, .sw-modal .sw-button--danger') || { click() {} }).click()`);
    await b.warte(1500);
    await b.werte(`Shopware.Application.view.router.push({ name: 'sw.cms.detail', params: { id: '${werkseitenId}' } }).then(() => true)`);
    let plaetze = 0;
    for (let i = 0; i < 60 && plaetze !== 10; i += 1) {
        await b.warte(1000);
        plaetze = await b.werte(`document.querySelector('.sw-cms-detail') && document.body.innerText.includes('Werkseite') ? document.querySelectorAll('.ketteler-cms-el').length : 0`);
    }
    pruefe(plaetze === 10, 'Layout „Werkseite“ im Editor mit zehn Plätzen', String(plaetze));
    if (bestellungId) {
        await b.werte(`Shopware.Application.view.router.push({ name: 'sw.order.detail', params: { id: '${bestellungId}' } }).then(() => true)`);
        let karte = '';
        for (let i = 0; i < 40 && !karte; i += 1) {
            await b.warte(1000);
            karte = await b.werte(`(document.querySelector('[data-kt="lizenzen-bestellung"]') || {}).innerText || ''`);
        }
        pruefe(/TEST-O-AVR/.test(karte) && /Lizenz angelegt|wartet|Fehler|beendet/.test(karte), 'Bestellung: Karte „Online-Zugänge (Lizenzen)“ mit Stand je Position', karte.slice(0, 200));
    }
    if (bildIndex > 0) {
        await b.ganzeSeite(resolve(process.argv[bildIndex + 1]));
    }
    const echt = fehler.filter((f) => /ketteler|Ketteler/.test(f));
    pruefe(echt.length === 0, 'keine Fehler der Ketteler-Skripte in der Konsole', echt.join(' | ').slice(0, 400));
} finally {
    await b.beenden();
}

console.log(`\n${gut} bestanden, ${schlecht.length} fehlgeschlagen`);
process.exit(schlecht.length ? 1 : 0);
