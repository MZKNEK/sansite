import { discoveryContext } from './browser-context.mjs';
import assert from 'node:assert/strict';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';

// Existing skin regressions use the same panel controls as a visitor.
export async function chooseSkin(page, id) {
  await page.locator('[data-skin-open]').click();
  await page.locator(`[data-skin-tile="${id}"]`).click();
  await page.locator('[data-skin-confirm]').click();
}

export async function testSkinPicker({ browser, base, site, errors }) {
  function update(name, id, value) {
    const file = path.join(site, 'inc/data', name + '.json');
    const all = existsSync(file) ? { ...JSON.parse(readFileSync(file, 'utf8')) } : {};
    all[id] = value;
    writeFileSync(file, JSON.stringify(all));
  }
  const context = await discoveryContext(browser, { viewport: { width: 1280, height: 850 } });
  try {
    await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    const user = '900000000000000002';
    await context.request.get(`${base}/__login?id=${user}`);
    update('finds', user, { aprilfools: Math.floor(Date.now() / 1000) - 86400 });
    update('skins', user, '');
    update('hud', user, { mode: 'full', color: 'dev' });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => {
      if (!localStorage.getItem('safeguard-date')) localStorage.setItem('safeguard-date', '07-15');
    });
    await page.goto(`${base}/account/`);
    const dialog = page.getByRole('dialog', { name: 'Wybierz skin' });
    const select = page.locator('[data-skin-choice]');
    const current = page.locator('[data-skin-current-name]');
    const variant = page.locator('[data-hud-choice]');
    const color = page.locator('[data-hud-color]');
    const accent = () => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim());
    assert.equal(await page.locator('[data-skin-native]').isVisible(), false);
    assert.equal(await current.textContent(), 'Domyślny');
    assert.equal(await variant.isVisible(), true);
    assert.equal(await color.isVisible(), true);
    await page.locator('[data-skin-open]').click();
    assert.equal(await dialog.isVisible(), true);
    assert.ok(await dialog.evaluate(el => {
      const panel = getComputedStyle(el);
      const menu = getComputedStyle(document.querySelector('.account-drop'));
      const button = el.querySelector('.skin-dialog-close').getBoundingClientRect();
      const icon = el.querySelector('.skin-dialog-close svg').getBoundingClientRect();
      return panel.backgroundColor === menu.backgroundColor && panel.borderTopColor === menu.borderTopColor
        && Math.abs(icon.x + icon.width / 2 - button.x - button.width / 2) < .5
        && Math.abs(icon.y + icon.height / 2 - button.y - button.height / 2) < .5;
    }), 'picker uses account panel colours and centres its close icon');
    assert.equal(await page.locator('[data-skin-filter=unlocked]').getAttribute('aria-pressed'), 'true');
    assert.equal(await dialog.locator('.skin-tile:visible').count(), 4, 'default, named reward and two earned skins');
    assert.deepEqual(await dialog.locator('.skin-tile').evaluateAll(tiles => tiles.slice(0, 2).map(tile => tile.getAttribute('data-skin-tile'))), ['', 'custom']);
    const custom = dialog.locator('[data-skin-custom]');
    assert.equal(await custom.isVisible(), true);
    assert.equal(await custom.isDisabled(), true);
    assert.ok((await custom.textContent()).includes('Własny skin'));
    assert.equal(await dialog.locator('[data-skin-tile=miku] .skin-swatches i').first().evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(57, 197, 187)', 'swatches use the skin CSS, independently of the current skin');
    await dialog.locator('[data-skin-tile=miku]').click();
    assert.equal(await accent(), '57, 197, 187');
    assert.equal(await variant.isVisible(), false, 'a skin preview hides the default HUD controls');
    assert.equal(await color.isVisible(), false);
    assert.equal(await select.inputValue(), '', 'a preview does not change the form');
    assert.equal(await page.locator('[data-skin-selection-name]').textContent(), 'Miku');
    await page.keyboard.press('Escape');
    await dialog.waitFor({ state: 'hidden' });
    await page.waitForFunction(() => !document.documentElement.hasAttribute('data-skin'));
    assert.equal(await dialog.isVisible(), false);
    assert.equal(await page.locator('html').getAttribute('data-skin'), null);
    assert.equal(await current.textContent(), 'Domyślny');
    assert.equal(await variant.isVisible(), true, 'cancelling restores the default skin controls');
    assert.equal(await variant.inputValue(), 'full');
    assert.equal(await color.inputValue(), 'dev');
    assert.equal(await page.locator('[data-skin-open]').evaluate(el => el === document.activeElement), true, 'Escape restores focus');
    await page.locator('[data-skin-open]').click();
    await page.keyboard.press('ArrowDown');
    assert.equal(await dialog.locator('[data-skin-tile=miku]').evaluate(el => el === document.activeElement), true, 'vertical arrows follow the grid columns');
    await page.keyboard.press('ArrowUp');
    await page.keyboard.press('ArrowRight');
    assert.equal(await dialog.locator('[data-skin-tile=teto]').evaluate(el => el === document.activeElement), true, 'arrows skip disabled tiles');
    await page.keyboard.press('Space');
    assert.equal(await page.locator('[data-skin-selection-name]').textContent(), 'Teto');
    await dialog.getByRole('button', { name: 'Anuluj', exact: true }).click();
    await page.locator('[data-skin-open]').click();
    await page.locator('[data-skin-filter=all]').click();
    assert.equal(await dialog.locator('.skin-tile:visible').count(), 20);
    assert.equal(await dialog.locator('[data-skin-locked] img').count(), 0, 'locked skins do not expose their artwork');
    assert.ok((await dialog.locator('[data-skin-locked]').allTextContents()).every(text => text.includes('???')));
    await page.setViewportSize({ width: 390, height: 844 });
    const bounds = await dialog.boundingBox();
    assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= 390);
    assert.ok(bounds.y >= 0 && bounds.y + bounds.height <= 844);
    assert.equal(await page.locator('[data-skin-grid]').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length), 2);
    await dialog.locator('[data-skin-tile=miku]').click();
    await page.locator('[data-skin-confirm]').click();
    assert.equal(await select.inputValue(), 'miku');
    assert.equal(await current.textContent(), 'Miku');
    assert.equal(await variant.isVisible(), false);
    assert.equal(await page.locator('[name=skinwords]').isVisible(), true);
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    assert.equal(await current.textContent(), 'Miku');
    assert.equal(await variant.isVisible(), false, 'saved skins hide the controls after navigation too');
    assert.equal(await page.locator('.hud-form button[type=submit]').isVisible(), false, 'a skin hides the empty HUD save button');
    await page.setViewportSize({ width: 1280, height: 850 });
    await page.locator('[data-skin-open]').click();
    await dialog.locator('[data-skin-tile=teto]').click();
    await page.mouse.click(5, 5);
    await dialog.waitFor({ state: 'hidden' });
    await page.waitForFunction(() => document.documentElement.getAttribute('data-skin') === 'miku');
    assert.equal(await dialog.isVisible(), false, 'the backdrop cancels the preview');
    assert.equal(await accent(), '57, 197, 187');
    assert.equal(await select.inputValue(), 'miku');
    assert.equal(await page.locator('body').evaluate(el => el.classList.contains('skin-picker-open')), false);
    await page.evaluate(() => localStorage.setItem('safeguard-date', '12-25'));
    await page.reload();
    assert.equal(await variant.isVisible(), false);
    assert.equal(await page.locator('.skin-form [data-skin-animations-pref]').isVisible(), true, 'animations belong to skin settings');
    await page.locator('[name=skinanimations]').uncheck();
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    assert.deepEqual(JSON.parse(readFileSync(path.join(site, 'inc/data/hud.json'), 'utf8'))[user], { mode: 'full', color: 'dev' }, 'saving skin animations preserves the HUD settings');
    await chooseSkin(page, '');
    assert.equal(await variant.isVisible(), true);
    assert.equal(await color.isVisible(), true);
    assert.equal(await variant.inputValue(), 'full');
    assert.equal(await color.inputValue(), 'dev');
  } finally {
    await context.close();
  }
  // The original select remains usable if JavaScript is disabled.
  const plain = await discoveryContext(browser, { javaScriptEnabled: false });
  try {
    await plain.request.get(`${base}/__login?id=900000000000000002`);
    const page = await plain.newPage();
    await page.goto(`${base}/account/`);
    assert.equal(await page.locator('[data-skin-native]').isVisible(), true);
    await page.locator('[data-skin-choice]').selectOption('teto');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    assert.equal(await page.locator('[data-skin-choice]').inputValue(), 'teto');
  } finally {
    await plain.close();
  }
}
