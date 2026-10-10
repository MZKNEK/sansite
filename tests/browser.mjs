// Real browser + real PHP, entirely disposable data and gallery. Run after
// installing playwright in .preview/browser (see CI). No Discord or server.
import { createRequire } from 'node:module';
import { mkdtempSync, cpSync, mkdirSync, rmSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import assert from 'node:assert/strict';
const root = path.resolve(import.meta.dirname, '..');
const require = createRequire(path.join(root, '.preview/browser/entry.cjs'));
const { chromium } = require('playwright');
const site = mkdtempSync(path.join(tmpdir(), 'sanakan-browser-'));
for (const dir of ['inc', 'admin', 'account', 'js', 'css', 'fonts']) {
  cpSync(path.join(root, dir), path.join(site, dir), { recursive: true, filter: p => p !== path.join(root, 'inc/data') && p !== path.join(root, 'inc/config.php') });
}
mkdirSync(path.join(site, 'i'));
cpSync(path.join(root, 'i/index.php'), path.join(site, 'i/index.php'));
for (const file of ['account.php', 'favicon.svg']) cpSync(path.join(root, file), path.join(site, file));
const port = 41000 + Math.floor(Math.random() * 10000);
const base = `http://127.0.0.1:${port}`;
const php = process.env.PHP || 'php';
// Load only the extensions PHP does not have yet: loading one twice warns
// ("Module is already loaded"), which the check of the log below fails on.
const loaded = spawnSync(php, ['-r', 'echo json_encode([extension_loaded("gd"), extension_loaded("zip")]);'], { encoding: 'utf8' });
assert.equal(loaded.status, 0, loaded.stderr);
const [hasGd, hasZip] = JSON.parse(loaded.stdout);
const extensions = [...(hasGd ? [] : ['-d', 'extension=gd']), ...(hasZip ? [] : ['-d', 'extension=zip'])];
// The transport limits of the README's server, as the test uploads a file over
// PHP's default 2M.
const limits = ['-d', 'upload_max_filesize=64M', '-d', 'post_max_size=64M'];
const server = spawn(php, [...extensions, ...limits, '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-S', `127.0.0.1:${port}`, path.join(root, 'tests/browser-router.php')],
  { cwd: site, env: { ...process.env, SANAKAN_BROWSER_ROOT: site.replaceAll('\\', '/') }, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
let log = '';
server.stderr.on('data', data => { log += data; });
let browser;
try {
  for (let i = 0; i < 60; i++) {
    try { await fetch(`${base}/favicon.svg`); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
  }
  const edge = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
  browser = await chromium.launch({ headless: true, ...(existsSync(edge) ? { executablePath: edge } : {}) });
  const context = await browser.newContext();
  await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  let login = await (await context.request.get(`${base}/__login?id=900000000000000001`)).json();
  await page.goto(`${base}/admin/`);
  assert.equal(await page.locator('form[data-action="gallery-limit-defaults"]').count(), 1, 'global quota form renders');
  const defaults = page.locator('form[data-action="gallery-limit-defaults"]');
  await page.getByRole('button', { name: /Limity folderów kont/ }).click();
  await defaults.locator('[name=files]').fill('400');
  assert.ok((await page.locator('#quota-impact').textContent()).includes('na 0 z 1'));
  await defaults.locator('[name=files]').fill('250');
  assert.ok((await page.locator('#quota-impact').textContent()).includes('na 1 z 1'));
  await Promise.all([page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/admin/')), defaults.locator('button[type=submit]').click()]);
  await page.waitForLoadState('networkidle');
  assert.equal(JSON.parse(readFileSync(path.join(site, 'inc/data/settings.json'))).galleryLimitDefaults.values.files, 250);
  await page.goto(`${base}/admin/?konto=900000000000000004`);
  const form = page.locator('form[data-action="account-gallery-limits"]');
  await form.locator('[name=mode_trashBytes]').selectOption('override');
  await form.locator('[name=trashBytes]').fill('75');
  await Promise.all([page.waitForResponse(r => r.request().method() === 'POST'), form.locator('button[type=submit]').click()]);
  await page.waitForLoadState('networkidle');
  assert.equal(JSON.parse(readFileSync(path.join(site, 'inc/data/access.json'))).galleryLimitOverrides['900000000000000004'].values.trashBytes, 75 * 1048576);
  const stale = await context.request.post(`${base}/admin/`, { form: { csrf: login.csrf, action: 'account-gallery-limits-reset', id: '900000000000000004', revision: '0' } });
  assert.equal(stale.status(), 409, 'stale form cannot replace a newer policy');
  await page.goto(`${base}/admin/?konto=900000000000000004`);
  await Promise.all([page.waitForResponse(r => r.request().method() === 'POST'), page.locator('button[data-action="account-gallery-limits-reset"]').click()]);
  await page.waitForLoadState('networkidle');
  assert.deepEqual(JSON.parse(readFileSync(path.join(site, 'inc/data/access.json'))).galleryLimitOverrides['900000000000000004'].values, []);

  login = await (await context.request.get(`${base}/__login?id=900000000000000002`)).json();
  await page.goto(`${base}/i/?p=${encodeURIComponent(login.own + '/A')}`);
  assert.equal(await page.locator('#act-move').getAttribute('hidden'), null, 'own gallery shows move');
  const data = JSON.parse(await page.locator('#gallery-data').textContent());
  assert.ok(data.folders.every(folder => folder === login.own || folder.startsWith(login.own + '/')), 'targets contain only own folders');
  const own = login.own;
  const csrf = login.csrf;
  const request = fields => context.request.post(`${base}/i/`, { form: { csrf, ...fields } });
  const foreign = await request({ action: 'move', target: 'users/900000000000000001-User/B', 'items[]': `${own}/A/test.png` });
  assert.equal(foreign.status(), 403, 'forged foreign destination is forbidden');
  await page.locator('#act-select').click();
  await page.locator('.tile[data-rel]').first().click();
  await page.locator('#act-move').click();
  await page.locator('#form-move [name=target]').selectOption(`${own}/B`);
  const [moved] = await Promise.all([page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/i/')), page.locator('#form-move button[type=submit]').click()]);
  assert.equal(moved.status(), 200);
  await page.waitForLoadState('networkidle');
  assert.ok(existsSync(path.join(site, 'i/users/900000000000000002-User/B/test.png')));
  const denied = await context.request.post(`${base}/admin/`, { form: { csrf, action: 'account-gallery-limits-reset', id: '900000000000000002', revision: 0 } });
  assert.equal(denied.status(), 403);
  const badCsrf = await context.request.post(`${base}/i/`, { form: { csrf: 'bad', action: 'move', target: `${own}/A`, 'items[]': `${own}/B/test.png` } });
  assert.equal(badCsrf.status(), 403);
  await page.goto(`${base}/account/`);
  assert.equal(await page.locator('form[data-action="account-gallery-limits"]').count(), 0);
  assert.ok((await page.textContent('body')).includes('Limity i miejsce w galerii'));
  login = await (await context.request.get(`${base}/__login?id=900000000000000001`)).json();
  const quotaSet = await context.request.post(`${base}/admin/`, { form: { csrf: login.csrf, action: 'account-gallery-limits', id: '900000000000000002', revision: '0',
    mode_files: 'override', files: '1', mode_fileBytes: 'override', fileBytes: '1', mode_totalBytes: 'override', totalBytes: '1', mode_trashBytes: 'inherit', trashBytes: '50' } });
  assert.equal(quotaSet.status(), 200);
  login = await (await context.request.get(`${base}/__login?id=900000000000000002`)).json();
  const png = readFileSync(path.join(site, 'i/users/900000000000000002-User/B/test.png'));
  const file = { name: 'large.png', mimeType: 'image/png', buffer: Buffer.concat([png, Buffer.alloc(2 * 1048576)]) };
  const refused = await context.request.post(`${base}/i/`, { multipart: { csrf: login.csrf, action: 'upload', dir: `${own}/A`, file } });
  assert.equal(refused.status(), 413, 'ordinary upload respects account quotas');
  login = await (await context.request.get(`${base}/__login?id=900000000000000001`)).json();
  const allowed = await context.request.post(`${base}/i/`, { multipart: { csrf: login.csrf, action: 'upload', dir: `${own}/A`, file } });
  assert.equal(allowed.status(), 200, await allowed.text());
  assert.deepEqual(errors, []);
  assert.ok(!/PHP (Warning|Fatal|Deprecated)|Uncaught/.test(log), log);
  console.log('Browser OK: global defaults, individual trash quota, reset, revision conflict, own move, foreign target, CSRF, authorization, own profile.');
} catch (error) {
  console.error(log.split('\n').filter(line => /Warning|Fatal|Deprecated|Error|sanakan:/.test(line)).join('\n'));
  throw error;
} finally {
  if (browser) await browser.close();
  server.kill();
  await new Promise(resolve => server.once('close', resolve));
  rmSync(site, { recursive: true, force: true });
}
