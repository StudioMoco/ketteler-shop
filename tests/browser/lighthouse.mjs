// Lighthouse auf Staging (Gesamtauftrag E): Startseite, Werkseite, MAV Einstieg, Artikelseite, Warenkorb, je Desktop und
// Mobil. Nutzt Lighthouse aus dem lokalen npx-Zwischenspeicher (LIGHTHOUSE=<pfad zu cli/index.js> zum Überschreiben) und
// das lokale Chrome. Der Passwortschutz von Staging geht über eine Kopfzeilen-Datei (600), die danach gelöscht wird.
//   node tests/browser/lighthouse.mjs [--staging] [--ziel <datei.md>]
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync, existsSync } from 'node:fs';
import { homedir, tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const STAGING = process.argv.includes('--staging');
const zielIndex = process.argv.indexOf('--ziel');
const ZIEL = zielIndex > 0 ? resolve(process.argv[zielIndex + 1]) : join(WURZEL, 'docs/design/lighthouse.md');
const text = STAGING
    ? execFileSync('ssh', [process.env.KETTELER_SERVER || 'ketteler', 'cat /opt/ketteler-shop/.env'], { encoding: 'utf8' })
    : readFileSync(join(WURZEL, '.env'), 'utf8');
const ENV = Object.fromEntries(text.split('\n').filter((z) => z.includes('=') && !z.startsWith('#')).map((z) => [z.slice(0, z.indexOf('=')), z.slice(z.indexOf('=') + 1).replace(/^'|'$/g, '')]));
const SHOP = (ENV.APP_URL || 'http://localhost:8000').replace(/\/$/, '');

function lighthousePfad() {
    if (process.env.LIGHTHOUSE) { return process.env.LIGHTHOUSE; }
    const basis = join(homedir(), '.npm/_npx');
    for (const d of existsSync(basis) ? readdirSync(basis) : []) {
        const p = join(basis, d, 'node_modules/lighthouse/cli/index.js');
        if (existsSync(p)) { return p; }
    }
    throw new Error('Lighthouse nicht gefunden (LIGHTHOUSE=… setzen)');
}

const SEITEN = [
    ['Startseite', '/'],
    ['Werkseite ZMV', '/Zeitschrift-ZMV/'],
    ['MAV Einstieg', '/MAV-Einstieg/'],
    ['Artikelseite', '/Arbeitszeit-und-Dienstplanung-Test/TEST-PH-ARBEITSZEIT'],
    ['Warenkorb', '/checkout/cart'],
];
const ordner = mkdtempSync(join(tmpdir(), 'kt-lighthouse-'));
const kopf = join(ordner, 'kopf.json');
writeFileSync(kopf, JSON.stringify(STAGING ? { Authorization: 'Basic ' + Buffer.from(ENV.STG_BASICAUTH_BENUTZER + ':' + ENV.STG_BASICAUTH_PASSWORT).toString('base64') } : {}), { mode: 0o600 });
const zeilen = [];
try {
    for (const [name, pfad] of SEITEN) {
        for (const art of ['desktop', 'mobil']) {
            const aus = join(ordner, 'bericht.json');
            const argumente = [lighthousePfad(), SHOP + pfad, '--quiet', '--output=json', '--output-path=' + aus, '--extra-headers=' + kopf,
                '--chrome-flags=--headless=new --no-first-run --no-default-browser-check', '--only-categories=performance,accessibility,best-practices,seo'];
            if (art === 'desktop') { argumente.push('--preset=desktop'); }
            execFileSync(process.execPath, argumente, { env: { ...process.env, CHROME_PATH: process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' }, stdio: ['ignore', 'ignore', 'pipe'] });
            const b = JSON.parse(readFileSync(aus, 'utf8'));
            const wert = (k) => Math.round((b.categories[k]?.score ?? 0) * 100);
            const zeile = { name, art, leistung: wert('performance'), barriere: wert('accessibility'), praxis: wert('best-practices'), seo: wert('seo'),
                lcp: b.audits['largest-contentful-paint']?.displayValue ?? '', cls: b.audits['cumulative-layout-shift']?.displayValue ?? '',
                maengel: Object.values(b.audits).filter((a) => a.score !== null && a.score < 0.9 && ['accessibility', 'seo'].some((k) => b.categories[k].auditRefs.some((r) => r.id === a.id && r.weight > 0))).map((a) => a.title).slice(0, 4) };
            zeilen.push(zeile);
            console.log(`  ${name} (${art}): Leistung ${zeile.leistung}, Barrierefreiheit ${zeile.barriere}, Praxis ${zeile.praxis}, SEO ${zeile.seo}, LCP ${zeile.lcp}, CLS ${zeile.cls}`);
        }
    }
} finally {
    rmSync(ordner, { recursive: true, force: true });
}
const md = ['# Lighthouse ' + (STAGING ? 'Staging' : 'lokal') + ' (' + new Date().toLocaleString('de-DE', { timeZone: 'Europe/Berlin' }) + ')', '',
    'Lighthouse ' + JSON.parse(readFileSync(join(dirname(lighthousePfad()), '../package.json'), 'utf8')).version + ', Desktop mit `--preset=desktop`, Mobil mit der Vorgabe (gedrosseltes Netz). Staging ist absichtlich `noindex` (Passwortschutz, robots.txt), das senkt den SEO-Wert.', '',
    '| Seite | Gerät | Leistung | Barrierefreiheit | Bewährte Praktiken | SEO | LCP | CLS | Hinweise (Barrierefreiheit, SEO) |', '|---|---|---|---|---|---|---|---|---|',
    ...zeilen.map((z) => `| ${z.name} | ${z.art} | ${z.leistung} | ${z.barriere} | ${z.praxis} | ${z.seo} | ${z.lcp} | ${z.cls} | ${z.maengel.join('; ')} |`)];
writeFileSync(ZIEL, md.join('\n') + '\n');
console.log('Bericht: ' + ZIEL);
