// Kleiner Treiber für ein eigenes Headless-Chrome über das DevTools-Protokoll (nur Node, ohne Pakete).
// Startet Chrome mit leerem Wegwerf-Profil (nie das Profil des Benutzers), Passwortschutz per Fetch.authRequired.
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

export async function starten({ benutzer, passwort } = {}) {
    const profil = mkdtempSync(join(tmpdir(), 'kt-chrome-'));
    const prozess = spawn(CHROME, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--hide-scrollbars', '--mute-audio', `--user-data-dir=${profil}`, '--remote-debugging-port=0', 'about:blank'],
        { stdio: ['ignore', 'ignore', 'pipe'] });
    const wsUrl = await new Promise((ok, fehler) => {
        let puffer = '';
        prozess.stderr.on('data', (d) => {
            puffer += d;
            const m = /DevTools listening on (ws:\/\/\S+)/.exec(puffer);
            if (m) { ok(m[1]); }
        });
        setTimeout(() => fehler(new Error('Chrome startet nicht')), 15000);
    });
    const basis = wsUrl.replace(/^ws:\/\//, 'http://').replace(/\/devtools\/browser\/.*/, '');
    const ziele = await (await fetch(basis + '/json/list')).json();
    const seite = ziele.find((z) => z.type === 'page');
    const ws = new WebSocket(seite.webSocketDebuggerUrl);
    await new Promise((ok) => ws.addEventListener('open', ok, { once: true }));
    let nr = 0;
    const offen = new Map();
    const hoerer = new Map();
    ws.addEventListener('message', (e) => {
        const n = JSON.parse(e.data);
        if (n.id && offen.has(n.id)) {
            const { ok, fehler } = offen.get(n.id);
            offen.delete(n.id);
            n.error ? fehler(new Error(n.error.message)) : ok(n.result);
        } else if (n.method) {
            (hoerer.get(n.method) || []).forEach((f) => f(n.params));
        }
    });
    const cdp = (method, params = {}) => new Promise((ok, fehler) => {
        const id = ++nr;
        offen.set(id, { ok, fehler });
        ws.send(JSON.stringify({ id, method, params }));
    });
    const auf = (method, f) => hoerer.set(method, [...(hoerer.get(method) || []), f]);

    await cdp('Page.enable');
    await cdp('Runtime.enable');
    await cdp('Network.enable');
    const anfragen = [];
    auf('Network.requestWillBeSent', (p) => anfragen.push(p.request.url));
    if (benutzer) {
        await cdp('Fetch.enable', { handleAuthRequests: true, patterns: [{ urlPattern: '*' }] });
        auf('Fetch.requestPaused', (p) => cdp('Fetch.continueRequest', { requestId: p.requestId }).catch(() => {}));
        auf('Fetch.authRequired', (p) => cdp('Fetch.continueWithAuth', {
            requestId: p.requestId, authChallengeResponse: { response: 'ProvideCredentials', username: benutzer, password: passwort },
        }).catch(() => {}));
    }

    const werte = async (ausdruck) => {
        const r = await cdp('Runtime.evaluate', { expression: ausdruck, awaitPromise: true, returnByValue: true });
        if (r.exceptionDetails) { throw new Error(r.exceptionDetails.text + ' ' + (r.exceptionDetails.exception?.description || '')); }
        return r.result.value;
    };
    const warte = (ms) => new Promise((ok) => setTimeout(ok, ms));

    return {
        cdp, auf, werte, warte, anfragen,
        async groesse(breite, hoehe = 900, mobil = false) {
            await cdp('Emulation.setDeviceMetricsOverride', { width: breite, height: hoehe, deviceScaleFactor: 1, mobile: mobil });
        },
        async laden(url) {
            const fertig = new Promise((ok) => auf('Page.loadEventFired', ok));
            await cdp('Page.navigate', { url });
            await Promise.race([fertig, warte(45000)]);   // nie endlos warten
            await werte('document.fonts.ready.then(() => true)');
            await warte(600);
        },
        async ganzeSeite(datei) {
            const { cssContentSize } = await cdp('Page.getLayoutMetrics');
            const bild = await cdp('Page.captureScreenshot', {
                format: 'png', captureBeyondViewport: true,
                clip: { x: 0, y: 0, width: Math.ceil(cssContentSize.width), height: Math.ceil(cssContentSize.height), scale: 1 },
            });
            const { writeFileSync } = await import('node:fs');
            writeFileSync(datei, Buffer.from(bild.data, 'base64'));
        },
        async taste(key, code, keyCode, mods = 0) {
            for (const type of ['keyDown', 'keyUp']) {
                await cdp('Input.dispatchKeyEvent', { type, key, code, windowsVirtualKeyCode: keyCode, nativeVirtualKeyCode: keyCode, modifiers: mods });
            }
        },
        async beenden() {
            try { ws.close(); } catch (e) { /* egal */ }
            prozess.kill('SIGKILL');
            await warte(300);
            rmSync(profil, { recursive: true, force: true });
        },
    };
}
