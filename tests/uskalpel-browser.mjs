// npm install --prefix .preview/browser playwright; build USkalpel first.
// Deterministic fixtures: no gallery account, model download or external requests.
import { createRequire } from 'node:module';
import { readFileSync, existsSync } from 'node:fs';
import { createServer } from 'node:http';
import path from 'node:path';
import assert from 'node:assert/strict';
const root = path.resolve(import.meta.dirname, '..');
const require = createRequire(path.join(root, '.preview/browser/entry.cjs'));
const playwright = require('playwright');
const kind = process.env.BROWSER || 'chromium';
const edge = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const server = createServer((req, res) => {
  const name = decodeURIComponent(req.url.split('?')[0]);
  const file = path.resolve(root, '.' + (name.endsWith('/') ? name + 'index.html' : name));
  if (!file.startsWith(root + path.sep) || !existsSync(file)) { res.writeHead(404); res.end(); return; }
  const mime = { '.js': 'text/javascript', '.css': 'text/css', '.html': 'text/html', '.webp': 'image/webp', '.wasm': 'application/wasm' };
  res.setHeader('Content-Type', mime[path.extname(file)] || 'application/octet-stream');
  res.end(readFileSync(file));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
let browser;
try {
  browser = await playwright[kind].launch({ headless: true, ...(kind === 'chromium' && existsSync(edge) ? { executablePath: edge } : {}) });
  const maker = await browser.newPage();
  const fixture = async (width, height, color) => Buffer.from(await maker.evaluate(({ width, height, color }) => {
    const c = document.createElement('canvas'); c.width = width; c.height = height;
    const ctx = c.getContext('2d');
    if (color) { ctx.fillStyle = color; ctx.fillRect(0, 0, width, height); }
    return c.toDataURL().split(',')[1];
  }, { width, height, color }), 'base64');
  const baseImage = await fixture(950, 1334, '#b03040');
  const topImage = await fixture(475, 667, '#3050c0');
  const transparent = await fixture(475, 667, null);
  await maker.close();
  for (const dpr of [1, 1.25, 2]) for (const width of [1280, 390]) {
    const context = await browser.newContext({ viewport: { width, height: 1000 }, deviceScaleFactor: dpr, hasTouch: true, acceptDownloads: true });
    await context.route('**/*', route => {
      const url = route.request().url();
      if (url.includes('/pw/variants.json')) return route.fulfill({ json: { Delta: 8, Eta: 4, Lambda: 3 } });
      if (url.includes('/pw/')) return route.fulfill({ contentType: 'image/png', body: transparent });
      if (url.includes('sUwh3io.webp')) return route.fulfill({ contentType: 'image/png', body: baseImage });
      // The model's deterministic result tests the app's auto-layer lifecycle.
      if (/\/assets\/dist-[^/]+\.js/.test(url)) return route.fulfill({ contentType: 'text/javascript', body: `export default async()=>await (await fetch('data:image/png;base64,${topImage.toString('base64')}')).blob();` });
      if (url.startsWith(origin)) return route.continue();
      return route.abort();
    });
    const page = await context.newPage(), errors = [];
    page.on('pageerror', e => { errors.push(e.message); console.error(e); });
    await page.goto(`${origin}/uskalpel/`);
    await page.locator('label.switch').filter({ hasText: 'Tryb edycji' }).click();
    const canvas = page.locator('canvas[aria-label="Podgląd karty"]');
    await page.waitForFunction(() => {
      const c = document.querySelector('canvas[aria-label="Podgląd karty"]');
      return c && c.getContext('2d').getImageData(230, 330, 1, 1).data[3] > 0;
    });
    const states = async () => (await page.locator('.info-label').allTextContents()).map(text => {
      const m = text.match(/X (-?\d+) · Y (-?\d+) · (\d+)×(\d+)/);
      return m ? { x: +m[1], y: +m[2], w: +m[3], h: +m[4] } : null;
    }).filter(Boolean);
    const layer = name => page.getByRole('radiogroup', { name: 'Warstwa do przesuwania' }).getByRole('radio', { name, exact: true });
    const previewScale = name => page.getByRole('radiogroup', { name: 'Skala podglądu' }).getByRole('radio', { name, exact: true });
    const scrollCard = async () => { await canvas.scrollIntoViewIfNeeded(); return canvas.boundingBox(); };
    const cdp = kind === 'chromium' ? await context.newCDPSession(page) : null;
    const touch = (type, points) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: points.map(([x, y], id) => ({ x, y, id })) });
    await page.locator('input[type=file]').nth(1).setInputFiles({ name: 'top.png', mimeType: 'image/png', buffer: topImage });
    await page.getByRole('button', { name: 'Sync Top → Scalp' }).waitFor();
    await page.waitForTimeout(150);
    await layer('Scalp').click();
    await page.getByRole('button', { name: 'Sync Scalp → Top' }).click();
    assert.deepEqual((await states()).map(s => [s.x, s.y]), [[0, 0], [0, 0]]);
    // Different source resolutions synchronize displayed geometry, not pixels.
    const synced = await states();
    assert.equal(synced[0].w, synced[1].w * 2);
    await layer('Obie').click();
    for (const level of [100, 200]) {
      await previewScale(`${level}%`).click();
      let box = await scrollCard();
      assert.ok(Math.abs(box.width * dpr - 475 * level / 100) < 0.1, `${width}/${dpr}: exact ${level}% width`);
      assert.ok(Math.abs(box.height * dpr - 667 * level / 100) < 0.1);
      // Zoom enough to allow a translation, then introduce an independent Top offset.
      await canvas.dispatchEvent('wheel', { deltaY: -500, clientX: box.x + 30, clientY: box.y + 30 });
      await layer('Top').click();
      await page.getByTitle('W prawo', { exact: true }).click();
      await layer('Obie').click();
      box = await scrollCard();
      const x = Math.max(1, box.x) + 50, y = Math.max(1, box.y) + 60;
      const before = await states();
      // Actual mouse input exercises pointer capture and movement outside the canvas.
      await page.mouse.move(x, y); await page.mouse.down(); await page.mouse.move(x + 20, y + 10, { steps: 3 }); await page.mouse.up();
      const after = await states();
      const expected = 20 / (level / 100 / dpr);
      assert.ok(Math.abs(after[0].x - before[0].x - expected) <= 1, 'mouse follows screen pixels');
      assert.ok(Math.abs((after[0].x - before[0].x) - (after[1].x - before[1].x)) <= 1, 'both move together (rounded labels)');
      if (cdp) {
        await touch('touchStart', [[x, y]]);
        await touch('touchMove', [[x + 12, y + 8]]);
        await touch('touchEnd', []);
      }
      const touched = await states();
      assert.ok(Math.abs((touched[0].x - after[0].x) - (touched[1].x - after[1].x)) <= 1);
      if (cdp) assert.ok(Math.abs(touched[0].x - after[0].x - 12 / (level / 100 / dpr)) <= 1);
      await canvas.focus(); await page.keyboard.press('Shift+ArrowRight');
      const keyed = await states(); assert.equal(keyed[0].x - touched[0].x, 10); assert.equal(keyed[1].x - touched[1].x, 10);
      if (cdp && level === 100) {
        await touch('touchStart', [[x, y], [x + 40, y]]);
        await touch('touchMove', [[x - 10, y], [x + 50, y]]);
        await touch('touchCancel', []);
        const pinched = await states();
        assert.ok(pinched[0].w < keyed[0].w, 'pinch zooms Scalp');
        assert.ok(pinched[1].w < keyed[1].w, 'pinch zooms Top');
        assert.ok(Math.abs(pinched[0].w / keyed[0].w - pinched[1].w / keyed[1].w) < 0.02);
      }
    }
    await previewScale('Dopasuj').click();
    const fit = await canvas.boundingBox();
    const viewport = await page.locator('.preview-viewport').boundingBox();
    assert.ok(fit.width <= viewport.width + 0.1);
    await page.getByRole('button', { name: 'Reset', exact: true }).click();
    await canvas.dispatchEvent('wheel', { deltaY: 10000, clientX: fit.x + 20, clientY: fit.y + 20 });
    for (const frame of ['Jota', 'Lambda', 'Delta']) {
      await page.getByRole('radiogroup', { name: 'Ramka', exact: true }).getByRole('radio', { name: frame, exact: true }).click();
      await page.waitForTimeout(200);
      assert.equal(await page.locator('.green-bg').count(), 0);
      const holes = await canvas.evaluate(async c => {
        const selected = [...document.querySelectorAll('[aria-label="Ramka"] button')].find(b => b.getAttribute('aria-checked') === 'true').textContent;
        const img = new Image(); img.src = `/uskalpel/masks/${selected}.webp`; await img.decode();
        const m = document.createElement('canvas'); m.width = 475; m.height = 667; const ctx = m.getContext('2d'); ctx.drawImage(img, 0, 0);
        const mask = ctx.getImageData(0, 0, 475, 667).data, pixels = c.getContext('2d').getImageData(0, 0, 475, 667).data;
        let holes = 0; for (let i = 3; i < mask.length; i += 4) if (mask[i] && !pixels[i]) holes++;
        return holes;
      });
      assert.equal(holes, 0, `${frame} has no exposed mask pixels`);
    }
    async function checkExport(label) {
      const download = page.waitForEvent('download');
      await page.getByRole('button', { name: 'Pobierz obrazek' }).click();
      const exported = readFileSync(await (await download).path()).toString('base64');
      const difference = await canvas.evaluate(async (c, png) => {
        const img = new Image(); img.src = `data:image/png;base64,${png}`; await img.decode();
        const copy = document.createElement('canvas'); copy.width = 475; copy.height = 667;
        copy.getContext('2d').drawImage(img, 0, 0);
        const a = copy.getContext('2d').getImageData(0, 0, 475, 667).data;
        const preview = new Image(); preview.src = c.toDataURL(); await preview.decode();
        copy.getContext('2d').clearRect(0, 0, 475, 667); copy.getContext('2d').drawImage(preview, 0, 0);
        const b = copy.getContext('2d').getImageData(0, 0, 475, 667).data;
        return a.reduce((max, v, i) => Math.max(max, Math.abs(v - b[i])), 0);
      }, exported);
      // Canvas/PNG premultiplied-alpha round trips may differ by one channel unit.
      assert.ok(difference <= 1, label);
    }
    await page.waitForTimeout(300);
    await checkExport('manual Top preview matches export');
    await page.getByRole('button', { name: 'Usuń tło ze scalpa' }).click();
    await page.getByText('✓ Gotowe!', { exact: true }).waitFor();
    await page.waitForTimeout(400);
    await checkExport('auto-layer preview matches export');
    assert.deepEqual(errors, []);
    console.log(`PASS ${kind}, viewport ${width}, DPR ${dpr}`);
    await context.close();
  }
} finally { await browser?.close(); await new Promise(resolve => server.close(resolve)); }
