import assert from 'node:assert/strict';

export async function testDiscoveryStorage({ browser, base, errors }) {
  const keys = ['safeguard', 'safeguard-gene', 'safeguard-lore', 'safeguard-easter', 'safeguard-snake', 'safeguard-hat', 'safeguard-cake'];
  const context = await browser.newContext({ viewport: { width: 320, height: 740 } });
  await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
  await context.addInitScript(keys => {
    window.progressAccess = [];
    for (const name of ['getItem', 'setItem']) {
      const original = Storage.prototype[name];
      Storage.prototype[name] = function (key, ...args) {
        if (keys.includes(key)) window.progressAccess.push([name, key]);
        return original.call(this, key, ...args);
      };
    }
  }, keys);
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  try {
    await page.goto(`${base}/privacy/`);
    assert.equal(await page.locator('.discovery-prompt').count(), 0, 'no prompt just for visiting');
    assert.deepEqual(await page.evaluate(() => progressAccess), [], 'no discovery storage is read before choosing');
    await page.goto(`${base}/404.html`);
    const prompt = page.locator('.discovery-prompt');
    await prompt.waitFor();
    assert.deepEqual(await page.evaluate(() => progressAccess), [], 'first discovery stays in memory');
    await page.locator('.sg-find-toast').evaluate(el => Promise.all(el.getAnimations().map(animation => animation.finished.catch(() => {}))));
    const toast = await page.locator('.sg-find-toast').boundingBox();
    assert.equal(Math.round(toast.x), 16, 'discovery notices stay at the left edge');
    assert.ok(Math.abs(toast.y + toast.height / 2 - 370) < 2, 'discovery notices sit halfway down the viewport');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'prompt fits a narrow phone');
    const yes = await prompt.locator('[data-discovery-choice="yes"]').boundingBox();
    const no = await prompt.locator('[data-discovery-choice="no"]').boundingBox();
    assert.ok(Math.abs(yes.width - no.width) < 1 && yes.height === no.height, 'equal choice buttons');
    await prompt.locator('[data-discovery-choice="no"]').click();
    assert.equal(await prompt.count(), 0);
    assert.ok(await page.evaluate(() => SanakanSafeguard.finds().lost), 'declining keeps this page playable');
    assert.equal(await page.evaluate(() => localStorage.getItem('safeguard')), null);
    await page.goto(`${base}/privacy/#odkrycia`);
    assert.deepEqual(await page.evaluate(() => SanakanSafeguard.finds()), {}, 'unsaved discoveries do not survive navigation');
    assert.equal(await page.locator('[data-discovery-choice="no"]').getAttribute('aria-pressed'), 'true');
    await page.locator('[data-discovery-choice="yes"]').click();
    await page.goto(`${base}/404.html`);
    await page.waitForFunction(() => JSON.parse(localStorage.getItem('safeguard') || '{}').finds?.lost);
    await page.reload();
    assert.ok(await page.evaluate(() => SanakanSafeguard.finds().lost), 'opted-in discovery survives reload');
    assert.equal(await prompt.count(), 0, 'a remembered choice is not asked again');
    await page.waitForFunction(async () => {
      const response = await caches.match(location.origin + '/sw.js?discovery-choice', { cacheName: 'sanakan-discovery-choice' });
      return navigator.serviceWorker.controller && response && await response.text() === 'yes';
    });
    await context.setOffline(true);
    await page.goto(`${base}/privacy/`);
    assert.ok(await page.evaluate(() => caches.has('sanakan-offline-seen')), 'offline discovery is recorded when saving is enabled');
    await context.setOffline(false);
    await page.goto(`${base}/privacy/`);
    await page.waitForFunction(() => !!SanakanSafeguard.finds().offline);
    const second = await context.newPage();
    second.on('pageerror', error => errors.push(error.message));
    await second.goto(`${base}/privacy/#odkrycia`);
    await second.evaluate(keys => {
      for (const key of keys.filter(key => key !== 'safeguard')) localStorage.setItem(key, '{}');
      localStorage.setItem('gallery-volume', '0.5');
    }, keys);
    await second.locator('[data-discovery-choice="no"]').click();
    await page.waitForFunction(() => SanakanDiscoveryStorage.choice() === 'no');
    assert.ok(await second.evaluate(keys => keys.every(key => localStorage.getItem(key) === null), keys), 'withdrawal clears only discovery progress');
    assert.equal(await second.evaluate(() => localStorage.getItem('gallery-volume')), '0.5');
    await second.waitForFunction(async () => {
      const response = await caches.match(location.origin + '/sw.js?discovery-choice', { cacheName: 'sanakan-discovery-choice' });
      return response && await response.text() === 'no' && !await caches.has('sanakan-offline-seen');
    });
    await context.setOffline(true);
    await second.goto(`${base}/privacy/`);
    assert.equal(await second.evaluate(() => caches.has('sanakan-offline-seen')), false, 'declining also disables the offline discovery marker');
    await context.setOffline(false);
    await page.evaluate(() => SanakanSafeguard.found('console', 'in-memory-proof'));
    assert.equal(await page.evaluate(() => localStorage.getItem('safeguard')), null, 'another tab cannot resume saving after withdrawal');
    await page.goto(`${base}/404.html`);
    await page.waitForFunction(() => !!SanakanSafeguard.finds().lost);
    assert.equal(await page.locator('.discovery-prompt').count(), 0, 'declining is remembered');
  } finally { await context.close(); }

  const legacy = await browser.newContext();
  try {
    await legacy.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    await legacy.addInitScript(() => {
      if (!localStorage.getItem('legacy-seeded')) {
        localStorage.setItem('safeguard', JSON.stringify({ finds: { console: 1 }, proofs: { console: 'legacy-proof' }, sent: {} }));
        localStorage.setItem('legacy-seeded', 'yes');
      }
    });
    const page = await legacy.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/404.html`);
    await page.locator('.discovery-prompt [data-discovery-choice="yes"]').click();
    assert.ok(await page.evaluate(() => {
      const finds = JSON.parse(localStorage.getItem('safeguard')).finds;
      return finds.console === 1 && finds.lost;
    }), 'opting in preserves old progress and the pending discovery');
  } finally { await legacy.close(); }

  const member = await browser.newContext();
  try {
    await member.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    const page = await member.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await member.request.get(`${base}/__login?id=900000000000000002`);
    await page.goto(`${base}/privacy/`);
    await page.waitForFunction(() => SanakanDiscoveryStorage.choice() === 'yes');
    assert.equal(await page.evaluate(() => localStorage.getItem('sanakan-discovery-account')), 'yes');
    assert.equal(await page.locator('.discovery-prompt').count(), 0, 'login enables saving without asking');
    await member.clearCookies();
    await page.goto(`${base}/404.html`);
    await page.waitForFunction(() => JSON.parse(localStorage.getItem('safeguard') || '{}').finds?.lost);
    assert.equal(await page.locator('.discovery-prompt').count(), 0, 'saving stays enabled after logout');
    await page.goto(`${base}/privacy/`);
    await page.locator('[data-discovery-choice="no"]').click();
    await member.request.get(`${base}/__login?id=900000000000000002`);
    await page.reload();
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.evaluate(() => SanakanDiscoveryStorage.choice()), 'no', 'a later login respects an explicit opt-out');
  } finally { await member.close(); }

  // An opt-out made before the first login in this browser survives it too.
  const declined = await browser.newContext();
  try {
    await declined.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    const page = await declined.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/privacy/`);
    await page.locator('[data-discovery-choice="no"]').click();
    await declined.request.get(`${base}/__login?id=900000000000000002`);
    await page.reload();
    await page.locator('.account-menu').waitFor();
    await page.waitForFunction(() => localStorage.getItem('sanakan-discovery-account') === 'yes');
    assert.equal(await page.evaluate(() => SanakanDiscoveryStorage.choice()), 'no', 'the first login keeps an earlier opt-out');
    assert.equal(await page.locator('[data-discovery-choice="no"]').getAttribute('aria-pressed'), 'true');
  } finally { await declined.close(); }
}
