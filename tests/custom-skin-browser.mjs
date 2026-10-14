import { chooseSkin } from './skin-picker-browser.mjs';
// Completion reward: real profile forms, private gallery media and stale cookies.
import assert from 'node:assert/strict';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import path from 'node:path';

export async function testCustomSkin({ browser, base, site, php, errors }) {
  const user = '900000000000000002';
  const catalog = spawnSync(php, ['-r', 'require $argv[1]; echo json_encode([array_keys(SAFEGUARD_FINDS), array_keys(SAFEGUARD_SEASONAL), customSkinPrefs("0")]);', path.join(site, 'inc/auth.php')], { encoding: 'utf8', windowsHide: true });
  assert.equal(catalog.status, 0, catalog.stderr);
  const [normal, seasonal, defaults] = JSON.parse(catalog.stdout);
  const complete = Object.fromEntries([...normal, ...seasonal].map((key, i) => [key, Math.floor(Date.now() / 1000) - 10000 - i * 300]));
  function update(name, change) {
    const file = path.join(site, 'inc/data', name + '.json');
    const all = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : {};
    change(all);
    writeFileSync(file, JSON.stringify(all));
  }
  const context = await browser.newContext({ viewport: { width: 1280, height: 850 } });
  try {
    await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    const login = await (await context.request.get(`${base}/__login?id=${user}`)).json();
    const foreign = await (await context.request.get(`${base}/__login?id=900000000000000004`)).json();
    await context.request.get(`${base}/__login?id=${user}`);
    update('skins', all => { delete all[user]; });
    update('custom-skins', all => { delete all[user]; });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => localStorage.setItem('safeguard-date', '2026-07-15'));
    const select = page.locator('[data-skin-choice]');
    const editor = page.locator('[data-custom-editor]');
    const accent = () => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim());
    const save = () => Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    const form = {
      csrf: login.csrf, action: 'skin', skin: 'custom', skinwords_present: 'off', customimage: `${login.own}/A/test.png`,
      ...Object.fromEntries(Object.entries(defaults.colors).map(([key, value]) => [`customcolors[${key}]`, value]))
    };
    for (const missing of [normal, seasonal, [normal[0]], [seasonal[0]]]) {
      update('finds', all => { all[user] = Object.fromEntries(Object.entries(complete).filter(([key]) => !missing.includes(key))); });
      await page.goto(`${base}/account/`);
      assert.equal(await select.locator('option[value=custom]').evaluate(option => option.disabled), true);
      assert.ok((await select.locator('option[value=custom]').textContent()).includes('Własny skin'));
      assert.equal(await editor.count(), 0, 'locked accounts cannot edit a design');
      const refused = await context.request.post(`${base}/account/`, { form });
      assert.ok((await refused.text()).includes('Ten skin jest jeszcze zablokowany'));
    }
    update('finds', all => { all[user] = complete; });
    await page.reload();
    assert.equal(await editor.isVisible(), false);
    await chooseSkin(page, 'custom');
    assert.equal(await editor.isVisible(), true);
    assert.equal(await editor.evaluate(el => el.disabled), false);
    assert.equal(await page.locator('[name=skinwords]').isVisible(), false);
    await page.locator('[data-custom-image]').selectOption(`${login.own}/A/test.png`);
    await page.locator('[data-custom-color=accent]').fill('#123456');
    await page.locator('[data-custom-color=bg-dark]').fill('#010203');
    assert.equal(await accent(), '18, 52, 86', 'HEX input previews before saving');
    assert.equal(await page.locator('[data-custom-swatch=accent]').inputValue(), '#123456');
    await page.locator('[data-custom-swatch="accent-light"]').evaluate(input => {
      input.value = '#abcdef';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    assert.equal(await page.locator('[data-custom-color="accent-light"]').inputValue(), '#abcdef');
    await page.locator('[data-custom-logo]').evaluate(img => img.decode());
    // The editor must fit a phone, and changing skins must remove its variables.
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'mobile editor fits the viewport');
    await chooseSkin(page, 'miku');
    assert.equal(await editor.isVisible(), false);
    assert.equal(await accent(), '57, 197, 187');
    await chooseSkin(page, 'custom');
    assert.equal(await accent(), '18, 52, 86', 'switching back preserves the unfinished design');
    await save();
    assert.equal(await accent(), '18, 52, 86');
    const designFile = path.join(site, 'inc/data/custom-skins.json');
    const saved = JSON.parse(readFileSync(designFile, 'utf8'))[user];
    assert.equal(saved.image, `${login.own}/A/test.png`);
    assert.equal(saved.colors.accent, '#123456');
    assert.equal(saved.colors['bg-dark'], '#010203');
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    await page.locator('img.logo').evaluate(img => img.decode());
    assert.equal(await page.locator('img.logo').getAttribute('src'), `/i/${login.own}/A/test.png`);
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl');
    assert.equal(await accent(), '18, 52, 86');
    assert.ok((await page.locator('.logo-slice').first().evaluate(el => getComputedStyle(el).backgroundImage)).includes(`${login.own}/A/test.png`));
    // Static pages paint from cookies while the account reply is still pending.
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await page.route('**/account.php?back=*', async route => { await gate; await route.continue(); });
    await page.goto(`${base}/privacy/`, { waitUntil: 'domcontentloaded' });
    assert.equal(await accent(), '18, 52, 86');
    release();
    await page.locator('.account-menu').waitFor();
    await page.unroute('**/account.php?back=*');
    for (const bad of [
      { customimage: `${foreign.own}/A/test.png` },
      { 'customcolors[accent]': '#123456; color:red' }
    ]) {
      const response = await context.request.post(`${base}/account/`, { form: { ...form, ...bad } });
      assert.ok((await response.text()).includes('Nie udało się zapisać skina'));
      assert.deepEqual(JSON.parse(readFileSync(designFile, 'utf8'))[user], saved);
    }
    await page.goto(`${base}/account/`);
    await chooseSkin(page, 'teto');
    await save();
    assert.deepEqual(JSON.parse(readFileSync(designFile, 'utf8'))[user], saved, 'another skin keeps the custom design');
    await chooseSkin(page, 'custom');
    await save();
    // Deleted pictures fall back without disabling the chosen colours.
    update('custom-skins', all => { all[user].image = `${login.own}/A/deleted.png`; });
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.locator('img.logo').getAttribute('src'), './logo.webp');
    assert.equal(await accent(), '18, 52, 86');
    update('finds', all => { delete all[user][seasonal[0]]; });
    await page.evaluate(async () => {
      const me = await (await fetch('/account.php')).json();
      document.dispatchEvent(new CustomEvent('sanakan:account', { detail: me }));
    });
    assert.equal(await page.locator('html').getAttribute('data-skin'), null);
    assert.equal(await page.evaluate(() => document.documentElement.style.getPropertyValue('--custom-accent')), '');
    assert.equal((await context.cookies()).some(cookie => cookie.name === 'customskin'), false);
    assert.equal(JSON.parse(readFileSync(path.join(site, 'inc/data/skins.json'), 'utf8'))[user], 'custom');
    assert.equal(JSON.parse(readFileSync(designFile, 'utf8'))[user].colors.accent, '#123456');
  } finally {
    await context.close();
  }
}
