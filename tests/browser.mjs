// Real browser + real PHP, entirely disposable data and gallery. Run after
// installing playwright in .preview/browser (see CI). No Discord or server.
import { createRequire } from 'node:module';
import { mkdtempSync, cpSync, mkdirSync, rmSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import assert from 'node:assert/strict';
import { testSafeguardSync } from './safeguard-browser.mjs';
import { testHome } from './home-browser.mjs';
const root = path.resolve(import.meta.dirname, '..');
const require = createRequire(path.join(root, '.preview/browser/entry.cjs'));
const { chromium } = require('playwright');
const site = mkdtempSync(path.join(tmpdir(), 'sanakan-browser-'));
for (const dir of ['inc', 'admin', 'account', 'js', 'css', 'fonts']) {
  cpSync(path.join(root, dir), path.join(site, dir), { recursive: true, filter: p => p !== path.join(root, 'inc/data') && p !== path.join(root, 'inc/config.php') });
}
mkdirSync(path.join(site, 'i'));
cpSync(path.join(root, 'i/index.php'), path.join(site, 'i/index.php'));
for (const file of ['index.html', 'logo.webp', 'teto.webp', 'status.php', 'account.php', 'megastructure.php', 'favicon.svg', '401.html', '403.html', '404.html', '500.html']) cpSync(path.join(root, file), path.join(site, file));
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
  // the panel without a login: the site's 401 page
  const anonymous = await context.request.get(`${base}/admin/?konto=900000000000000004`, { headers: { Accept: 'text/html' } });
  assert.equal(anonymous.status(), 401, 'panel without a login');
  assert.match(await anonymous.text(), /Identyfikacja wymagana/, 'the 401 page');
  // Exercise the shipped client: concurrent anonymous redemptions share the
  // first CSRF session, and a reply lost after the write can be retried safely.
  const registerContext = await browser.newContext();
  try {
    await registerContext.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    const registerPage = await registerContext.newPage();
    registerPage.on('pageerror', error => errors.push(error.message));
    await registerPage.goto(`${base}/500.html`);
    const ticket = await registerPage.evaluate(() => SanakanSafeguard.ticket('gbe'));
    assert.ok(ticket);
    await registerPage.waitForTimeout(2200);
    let csrfRequests = 0;
    registerPage.on('request', request => {
      if (new URL(request.url()).searchParams.has('csrf')) csrfRequests++;
    });
    const replies = await registerPage.evaluate(ticket => Promise.all([
      SanakanSafeguard.mega({ witness: 'gbe', ticket }),
      SanakanSafeguard.mega({ witness: 'gbe', ticket })
    ]), ticket);
    assert.ok(replies[0]?.proof);
    assert.deepEqual(replies[1], replies[0]);
    assert.equal(csrfRequests, 1, 'concurrent changes share the initial anonymous session');
    let loseReply = true;
    await registerPage.route('**/megastructure.php*', async route => {
      if (loseReply && route.request().method() === 'POST') {
        loseReply = false;
        await route.fetch();
        await route.abort('failed');
      } else await route.continue();
    });
    const recovered = await registerPage.evaluate(ticket => SanakanSafeguard.mega({ witness: 'gbe', ticket }), ticket);
    assert.deepEqual(recovered, replies[0], 'the client recovers the proof after losing a successful reply');
    assert.equal(loseReply, false, 'the test interrupted the real POST response');
  } finally {
    await registerContext.close();
  }
  await testSafeguardSync({ browser, base, site, php, errors });
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

  mkdirSync(path.join(site, 'i/private/sub'), { recursive: true });
  writeFileSync(path.join(site, 'i/private/sub/secret.png'), png);
  const sharesBefore = existsSync(path.join(site, 'inc/data/shares.json'))
    ? JSON.parse(readFileSync(path.join(site, 'inc/data/shares.json'))) : {};
  login = await (await context.request.get(`${base}/__login?id=900000000000000003`)).json();
  for (const dir of ['private', 'private/sub']) {
    const share = await context.request.post(`${base}/i/`, { form: { csrf: login.csrf, action: 'share', dir, days: '1' } });
    assert.equal(share.status(), 403, 'a gallery admin without private access cannot share ' + dir);
    assert.equal((await share.json()).ok, false);
  }
  assert.deepEqual(existsSync(path.join(site, 'inc/data/shares.json'))
    ? JSON.parse(readFileSync(path.join(site, 'inc/data/shares.json'))) : {}, sharesBefore, 'refused shares create no tokens');
  const publicShare = await context.request.post(`${base}/i/`, { form: { csrf: login.csrf, action: 'share', dir: own + '/B', days: '1' } });
  assert.equal(publicShare.status(), 200, 'gallery admins can still share a visible folder');
  assert.equal((await publicShare.json()).ok, true);

  login = await (await context.request.get(`${base}/__login?id=900000000000000001`)).json();
  const privateShare = await context.request.post(`${base}/i/`, { form: { csrf: login.csrf, action: 'share', dir: 'private/sub', days: '1' } });
  assert.equal(privateShare.status(), 200, 'an authorized admin can still share a private folder');
  const { token } = await privateShare.json();
  assert.ok(token);
  const guest = await browser.newContext();
  try {
    assert.equal((await guest.request.get(`${base}/i/private/sub/secret.png`)).status(), 404);
    assert.equal((await guest.request.get(`${base}/i/?s=${token}`)).status(), 200);
    assert.deepEqual(await (await guest.request.get(`${base}/i/private/sub/secret.png`)).body(), png);
    const ownImage = await guest.request.get(`${base}/i/${own}/B/test.png`);
    assert.equal(ownImage.status(), 200, 'account image links still work without a login');
    assert.deepEqual(await ownImage.body(), png);
    const escaped = await guest.request.get(`${base}/i/${own}/..%5C900000000000000001-User%5CA%5Ctest.png`);
    assert.equal(escaped.status(), 404, 'a public token cannot read another account through encoded backslashes');
  } finally {
    await guest.close();
  }

  const renameDir = path.join(site, 'i/users/900000000000000002-User/B');
  writeFileSync(path.join(renameDir, 'rename.png'), 'SOURCE');
  writeFileSync(path.join(renameDir, 'taken.png'), 'TARGET');
  const rename = (from, to) => context.request.post(`${base}/i/`, { form: {
    csrf: login.csrf, action: 'rename', 'items[]': `${own}/B/${from}`, name: to
  } });
  assert.equal((await rename('rename.png', 'taken.png')).status(), 409);
  assert.equal(readFileSync(path.join(renameDir, 'rename.png'), 'utf8'), 'SOURCE');
  assert.equal(readFileSync(path.join(renameDir, 'taken.png'), 'utf8'), 'TARGET');
  // Linux permits both spellings; Windows normally refers to one directory entry.
  if (!existsSync(path.join(renameDir, 'Rename.png'))) {
    writeFileSync(path.join(renameDir, 'Rename.png'), 'CASE TARGET');
    assert.equal((await rename('rename.png', 'Rename.png')).status(), 409, 'a case-only name collision cannot overwrite a separate file');
    assert.equal(readFileSync(path.join(renameDir, 'rename.png'), 'utf8'), 'SOURCE');
    assert.equal(readFileSync(path.join(renameDir, 'Rename.png'), 'utf8'), 'CASE TARGET');
  }
  writeFileSync(path.join(renameDir, 'case-only.png'), 'CASE ONLY');
  assert.equal((await rename('case-only.png', 'Case-Only.png')).status(), 200, 'a case-only rename without a collision still works');
  assert.equal(readFileSync(path.join(renameDir, 'Case-Only.png'), 'utf8'), 'CASE ONLY');
  await testHome({ browser, base, site, php, errors });
  assert.deepEqual(errors, []);
  assert.ok(!/PHP (Warning|Fatal|Deprecated)|Uncaught/.test(log), log);
  console.log('Browser OK: register migration, synchronization races, CSRF and retries, quotas, own move, authorization, profile, private sharing, public token confinement, rename collisions.');
} catch (error) {
  console.error(log.split('\n').filter(line => /Warning|Fatal|Deprecated|Error|sanakan:/.test(line)).join('\n'));
  throw error;
} finally {
  if (browser) await browser.close();
  server.kill();
  await new Promise(resolve => server.once('close', resolve));
  rmSync(site, { recursive: true, force: true });
}
