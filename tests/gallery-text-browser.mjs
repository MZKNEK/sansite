import { discoveryContext } from './browser-context.mjs';
import { existsSync, readFileSync, writeFileSync, statSync, readdirSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';

export async function testGalleryText({ browser, base, site, errors }) {
  const context = await discoveryContext(browser);
  try {
    await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    const id = '900000000000000004';
    const login = await (await context.request.get(`${base}/__login?id=${id}`)).json();
    const dir = path.join(site, `i/users/${id}-User/A`);
    const original = Buffer.alloc(1048576 - statSync(path.join(dir, 'test.png')).size, 'a');
    const replacement = Buffer.alloc(original.length, 'b');
    const upload = (name, buffer, extra = {}) => context.request.post(`${base}/i/`, { multipart: {
      csrf: login.csrf, action: 'upload', dir: `${login.own}/A`, ...extra,
      file: { name, mimeType: 'text/plain', buffer }
    } });
    const created = await upload('notes.txt', original);
    assert.equal(created.status(), 200, await created.text());
    const createdBody = await created.text();
    assert.ok(createdBody.startsWith('{'), createdBody);
    const { url } = JSON.parse(createdBody);
    const cached = await context.request.get(`${base}${url}`);
    const oldEtag = cached.headers().etag;
    assert.deepEqual(await cached.body(), original);
    assert.match(cached.headers()['cache-control'], /max-age=0/);

    // Fill both quotas: a replacement credits the previous file's bytes/count.
    const accessPath = path.join(site, 'inc/data/access.json');
    const access = existsSync(accessPath) ? JSON.parse(readFileSync(accessPath)) : {};
    access.galleryLimitOverrides ||= {};
    access.galleryLimitOverrides[id] = { revision: 1, values: {
      files: 2, totalBytes: 1048576, fileBytes: 2097152, trashBytes: 1048576
    } };
    writeFileSync(accessPath, JSON.stringify(access));
    const purgesPath = path.join(site, 'inc/data/browser-purges.jsonl');
    const purges = () => existsSync(purgesPath) ? readFileSync(purgesPath, 'utf8') : '';
    const before = purges();
    const conflict = await upload('notes.txt', replacement);
    assert.equal(conflict.status(), 409);
    assert.equal((await conflict.json()).needsReplacement, true);
    assert.deepEqual(readFileSync(path.join(dir, 'notes.txt')), original);

    const tooBig = await upload('notes.txt', Buffer.alloc(original.length + 1, 'b'), { replace: '1' });
    assert.equal(tooBig.status(), 413, 'only the extra bytes count toward the space limit');
    const invalid = await upload('notes.txt', Buffer.from([0xff, 0]), { replace: '1' });
    assert.equal(invalid.status(), 400, 'a replacement still validates its content');
    assert.deepEqual(readFileSync(path.join(dir, 'notes.txt')), original);

    await page.goto(`${base}/i/?p=${encodeURIComponent(`${login.own}/A`)}`);
    const dialog = page.locator('#dlg-upload-replace');
    const chooseFile = () => page.locator('#upload-input').setInputFiles({ name: 'notes.txt', mimeType: 'text/plain', buffer: replacement });
    await chooseFile();
    await dialog.waitFor({ state: 'visible' });
    assert.match(await dialog.textContent(), /notes\.txt/);
    await Promise.all([page.waitForNavigation(), dialog.getByRole('button', { name: 'Nie', exact: true }).click()]);
    assert.deepEqual(readFileSync(path.join(dir, 'notes.txt')), original, 'No leaves the original intact');
    assert.equal(purges(), before, 'refused, invalid and skipped uploads never purge');
    assert.match(await page.textContent('body'), /Pominięto 1 plik/);

    await chooseFile();
    await dialog.waitFor({ state: 'visible' });
    await Promise.all([page.waitForNavigation(), dialog.getByRole('button', { name: 'Tak', exact: true }).click()]);
    assert.deepEqual(readFileSync(path.join(dir, 'notes.txt')), replacement, 'Yes replaces under the same name');
    assert.deepEqual(readdirSync(dir).sort(), ['notes.txt', 'test.png'], 'no numbered duplicate or staging file');
    assert.match(await page.textContent('body'), /Podmieniono 1 plik/);
    const refreshed = await context.request.get(`${base}${url}`, { headers: { 'If-None-Match': oldEtag } });
    assert.equal(refreshed.status(), 200, 'old validators must not return a stale 304');
    assert.notEqual(refreshed.headers().etag, oldEtag);
    assert.notEqual(refreshed.headers()['last-modified'], cached.headers()['last-modified']);
    assert.deepEqual(await refreshed.body(), replacement);
    const batches = purges().slice(before.length).trim().split('\n').map(line => JSON.parse(line));
    assert.deepEqual(batches, [{ files: [`https://sanakan.pl${url}`] }], 'purges the public token URL in Cloudflare');

    mkdirSync(path.join(dir, 'folder.txt'));
    assert.equal((await upload('folder.txt', Buffer.from('text'), { replace: '1' })).status(), 409, 'a directory cannot be replaced');
    const forbidden = await upload('notes.txt', replacement, { replace: '1', dir: 'private', csrf: 'bad' });
    assert.equal(forbidden.status(), 403);
    assert.deepEqual(readFileSync(path.join(dir, 'notes.txt')), replacement);
  } finally {
    await context.close();
  }
}
