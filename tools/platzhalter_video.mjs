// Erzeugt die Platzhalter für das Video im Schaufenster (bis das echte Video da ist): kurzes MP4 ohne Ton, Vorschaubild
// und Untertitel (WebVTT). Aufnahme im eigenen Headless-Chrome per Canvas und MediaRecorder, keine weiteren Programme.
//   node tools/platzhalter_video.mjs
// Ergebnis: custom/plugins/KettelerEinrichtung/src/Resources/daten/medien/avr-2027-folge-1-platzhalter.{mp4,jpg,vtt}
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { starten } from '../tests/browser/cdp.mjs';

const ZIEL = resolve(dirname(fileURLToPath(import.meta.url)), '../custom/plugins/KettelerEinrichtung/src/Resources/daten/medien');
const NAME = 'avr-2027-folge-1-platzhalter';

const b = await starten();
try {
    const { video, poster } = await b.werte(`(async () => {
        const c = document.createElement('canvas'); c.width = 960; c.height = 540; document.body.appendChild(c);
        const g = c.getContext('2d');
        const bild = (t) => {
            g.fillStyle = '#f3ece2'; g.fillRect(0, 0, 960, 540);
            g.fillStyle = '#b7005c'; g.fillRect(0, 0, 12, 540);
            g.fillStyle = '#b7005c'; g.font = '28px Georgia, serif'; g.fillText('AVR 2027 nachgefragt', 80, 190);
            g.fillStyle = '#2a2326'; g.font = '56px Georgia, serif'; g.fillText('Folge 1', 80, 270);
            g.fillStyle = '#6b5f63'; g.font = '26px Helvetica, Arial, sans-serif'; g.fillText('Platzhalter. Das Video folgt.', 80, 330);
            g.fillStyle = '#b7005c'; g.fillRect(80, 400, 800 * t, 6);
        };
        bild(0);
        const poster = c.toDataURL('image/jpeg', 0.86);
        const strom = c.captureStream(25);
        const rec = new MediaRecorder(strom, { mimeType: 'video/mp4;codecs=avc1.42E01E', videoBitsPerSecond: 600000 });
        const teile = [];
        rec.ondataavailable = (e) => teile.push(e.data);
        const fertig = new Promise((ok) => { rec.onstop = ok; });
        rec.start();
        const start = performance.now();
        await new Promise((ok) => { const schritt = () => { const t = (performance.now() - start) / 4000; bild(Math.min(t, 1)); t < 1 ? requestAnimationFrame(schritt) : ok(); }; schritt(); });
        rec.stop(); await fertig;
        const blob = new Blob(teile, { type: 'video/mp4' });
        const daten = await new Promise((ok) => { const r = new FileReader(); r.onload = () => ok(r.result); r.readAsDataURL(blob); });
        return { video: daten.split(',')[1], poster: poster.split(',')[1] };
    })()`);
    mkdirSync(ZIEL, { recursive: true });
    writeFileSync(join(ZIEL, NAME + '.mp4'), Buffer.from(video, 'base64'));
    writeFileSync(join(ZIEL, NAME + '.jpg'), Buffer.from(poster, 'base64'));
    writeFileSync(join(ZIEL, NAME + '.vtt'), 'WEBVTT\n\n00:00:00.000 --> 00:00:04.000\nPlatzhalter. Das Video zur Folge 1 folgt.\n');
    console.log('geschrieben: ' + ZIEL + '/' + NAME + '.{mp4,jpg,vtt}');
} finally {
    await b.beenden();
}
