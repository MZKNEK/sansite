// Regression cases for account/cookie disagreement, delayed responses and the
// profile's selection. All requests use the disposable PHP site of browser.mjs.
import assert from 'node:assert/strict';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';

export async function testSkinChanges({ browser, base, site, errors }) {
  const owner = '900000000000000001';
  const prefsFile = path.join(site, 'inc/data/skins.json');
  function update(name, change) {
    const file = path.join(site, 'inc/data', name + '.json');
    const all = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : {};
    change(all);
    writeFileSync(file, JSON.stringify(all));
  }
  function endSession(id) {
    update('sessions', all => {
      all.generations ||= {};
      all.generations[id] = (all.generations[id] || 0) + 1;
    });
  }
  async function visit({ skin = 'teto', words = true, cookieSkin = skin, cookieWords = words, date = '2026-07-15', delayed = false, pathname = '/', id = owner, finds = null, expired = false }, run) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 850 } });
    let release = () => {};
    try {
      await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
      await context.request.get(`${base}/__login?id=${id}`);
      update('skins', all => { all[id] = words ? skin : { skin, words: false }; });
      if (finds !== null) update('finds', all => { all[id] = finds; });
      if (cookieSkin) await context.addCookies([{ name: 'skin', value: cookieSkin, url: `${base}/` }]);
      if (!cookieWords) await context.addCookies([{ name: 'skinwords', value: 'off', url: `${base}/` }]);
      if (expired) endSession(id);
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.addInitScript(date => localStorage.setItem('safeguard-date', date), date);
      await page.route('**/status.php', route => route.fulfill({ json: { status: 'online', uptime: 100, down: '', issues: '' } }));
      await page.route('**/palette.php', route => route.fulfill({ json: { commands: [], versions: [] } }));
      if (delayed) {
        const gate = new Promise(resolve => { release = resolve; });
        await page.route('**/account.php?back=*', async route => { await gate; await route.continue(); });
      }
      await page.goto(base + pathname);
      await run(page, context, release);
    } finally {
      release();
      await context.close();
    }
  }
  const language = page => page.locator('html').getAttribute('lang');
  const accent = page => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim());

  async function defaultHome(page) {
    assert.equal(await page.locator('html').getAttribute('data-skin'), null);
    assert.equal(await language(page), 'pl');
    assert.match(await page.locator('img.logo').getAttribute('src'), /^\.\/logo\.webp$/);
    assert.equal(await page.locator('img.logo').getAttribute('alt'), 'Sanakan');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.locator('.kana').getAttribute('aria-hidden'), 'true');
    assert.equal(await page.locator('meta[property="og:locale"]').getAttribute('content'), 'pl_PL');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false);
    assert.equal(await page.evaluate(() => SanakanSkins.current()), '');
  }

  await visit({ delayed: true }, async (page, context, release) => {
    assert.equal(await language(page), 'ja', 'the old cookie is worn before the account is checked');
    endSession(owner);
    release();
    await page.locator('#home-account .home-login').waitFor();
    await defaultHome(page);
    assert.equal(await page.locator('.account-menu').count(), 0);
    assert.equal(await page.evaluate(() => document.cookie.includes('skin=')), false);
    assert.equal(await accent(page), '155, 89, 182');
  });

  await visit({ pathname: '/hunters/', expired: true }, async page => {
    assert.equal(await page.locator('.account-menu').count(), 0);
    assert.equal(await page.locator('html').getAttribute('data-hud'), 'default');
    assert.equal(await page.locator('html').getAttribute('data-skin'), null, 'a public PHP page with an expired session ignores the old skin cookie');
    assert.equal(await accent(page), '155, 89, 182');
  });

  await visit({ skin: '', cookieSkin: 'teto', delayed: true }, async (page, context, release) => {
    assert.equal(await language(page), 'ja');
    release();
    await page.locator('.account-menu').waitFor();
    await defaultHome(page);
  });

  const user = '900000000000000002';
  await visit({ id: user, finds: { aprilfools: Math.floor(Date.now() / 1000) }, delayed: true }, async (page, context, release) => {
    assert.equal(await language(page), 'ja');
    update('finds', all => { all[user] = {}; });
    release();
    await page.locator('.account-menu').waitFor();
    await defaultHome(page);
    assert.equal(JSON.parse(readFileSync(prefsFile, 'utf8'))[user], 'teto', 'revocation does not erase the saved preference');
  });

  await visit({ skin: '', cookieSkin: '', date: '2026-04-01', delayed: true }, async (page, context, release) => {
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).click();
    endSession(owner);
    release();
    await page.locator('#home-account .home-login').waitFor();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'teto', 'an anonymous reply keeps a deliberate April override');
    assert.equal(await language(page), 'ja');
    assert.equal(await page.locator('.home-language-reset').count(), 2);
  });

  await visit({ skin: 'albedo', date: '2026-04-01', delayed: true }, async (page, context, release) => {
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).click();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'teto');
    release();
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'teto', 'the late account response keeps the April override');
    assert.equal(await language(page), 'ja');
    assert.match(await page.locator('img.logo').getAttribute('src'), /teto\.webp$/);
    assert.equal(await page.locator('.home-language-reset').count(), 2);
    await page.reload();
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'albedo', 'the override ends on reload');
    assert.equal(await language(page), 'pl');
  });

  await visit({ words: true, cookieWords: false }, async page => {
    await page.locator('.account-menu').waitFor();
    assert.equal(await language(page), 'ja', 'server words=on corrects a stale off cookie for the same skin');
    assert.equal(await page.locator('h1').textContent(), 'サナカン');
    assert.equal(await page.evaluate(() => SanakanHomeLanguage.text('odebrano: SOS · odpowiedź: SAFEGUARD JUŻ IDZIE')),
      '受信： SOS · 応答： セーフガードが向かっています', 'decoded Morse replies keep their Japanese wording');
  });

  await visit({ words: false, cookieWords: true, delayed: true }, async (page, context, release) => {
    await page.locator('#bot-status.online').waitFor();
    assert.equal(await language(page), 'ja');
    await page.getByRole('button', { name: '検索' }).click();
    const search = page.getByRole('combobox');
    await search.fill('コマンド');
    await page.locator('#pl-list a[href$="/cmd/"]').waitFor();
    release();
    await page.locator('.account-menu').waitFor();
    assert.equal(await language(page), 'pl', 'server words=off restores the same skin without navigation');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.locator('.kana').textContent(), 'サナカン');
    assert.equal(await page.locator('.kana').getAttribute('aria-hidden'), 'true');
    assert.equal(await page.locator('meta[property="og:locale"]').getAttribute('content'), 'pl_PL');
    assert.equal(await page.locator('#bot-status').getAttribute('aria-label'), 'Dostępny: bot działa');
    assert.equal(await page.locator('#motto .sr-only').textContent(), 'Niby nie taki lekki, ale lata');
    assert.equal(await search.inputValue(), 'コマンド', 'language changes preserve what the visitor typed');
    await search.fill('Polecenia');
    assert.equal(await page.locator('#pl-list a[href$="/cmd/"] b').textContent(), 'Polecenia', 'the open search rebuilds its localized index');
    assert.match(await page.locator('img.logo').getAttribute('src'), /teto\.webp$/, 'the skin stays when only words change');
  });

  await visit({ skin: 'tao', cookieSkin: 'teto', delayed: true }, async (page, context, release) => {
    await page.locator('#bot-status.online').waitFor();
    await page.clock.install();
    await page.clock.pauseAt(Date.now() + 100);
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('sanakan:pong')));
    await page.getByRole('dialog', { name: 'セーフガードと卓球' }).waitFor();
    assert.equal(await page.locator('.pp-score').textContent(), 'あなた 0 : 0 セーフガード');
    release();
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'tao');
    assert.equal(await language(page), 'pl', 'a silent skin restores the original language');
    assert.match(await page.locator('img.logo').getAttribute('src'), /tao\.webp$/);
    await page.getByRole('dialog', { name: 'Ping pong z Safeguardem' }).waitFor();
    assert.equal(await page.locator('.pp-score').textContent(), 'Ty 0 : 0 Safeguard');
    assert.equal(await page.locator('.pp-note').textContent(), 'Mysz, palec albo strzałki. Do pięciu punktów.');
    assert.equal(await accent(page), '226, 98, 47');
  });

  await visit({ skin: 'miku', cookieSkin: '', pathname: '/privacy/' }, async page => {
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'miku', 'a static page applies the account skin without an initial cookie');
    assert.equal(await accent(page), '57, 197, 187');
    assert.equal(await language(page), 'pl');
    assert.equal(await page.evaluate(() => document.querySelector('meta[name="theme-color"]').content), 'rgb(57, 197, 187)');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false, 'static pages need only the shared appearance module');
  });

  await visit({ skin: 'tao', words: false, pathname: '/account/' }, async page => {
    const checkbox = page.locator('input[name=skinwords]');
    const select = page.locator('select[name=skin]');
    const save = page.locator('.skin-form button[type=submit]');
    assert.equal(await checkbox.isVisible(), false);
    assert.equal(await checkbox.isDisabled(), true);
    await select.selectOption('miku');
    assert.equal(await checkbox.isVisible(), true, 'the words switch appears before saving');
    assert.equal(await checkbox.isDisabled(), false);
    await checkbox.check();
    await Promise.all([page.waitForNavigation(), save.click()]);
    assert.equal(JSON.parse(readFileSync(prefsFile, 'utf8'))[owner], 'miku', 'the first save includes the newly shown switch');
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    assert.equal(await language(page), 'ja');
    await page.goto(`${base}/account/`);
    await select.selectOption('tao');
    assert.equal(await checkbox.isVisible(), false, 'the switch disappears as soon as a silent skin is selected');
    await Promise.all([page.waitForNavigation(), save.click()]);
    assert.equal(JSON.parse(readFileSync(prefsFile, 'utf8'))[owner], 'tao', 'a hidden switch preserves the words preference');
    await select.selectOption('teto');
    await checkbox.uncheck();
    await Promise.all([page.waitForNavigation(), save.click()]);
    assert.deepEqual(JSON.parse(readFileSync(prefsFile, 'utf8'))[owner], { skin: 'teto', words: false }, 'an unchecked visible switch is saved in one submission');
  });

  await visit({ finds: {}, pathname: '/account/' }, async (page, context) => {
    const select = page.locator('select[name=skin]');
    assert.equal(await select.locator('option:disabled').count(), 0, 'the real owner starts with every skin');
    const me = await (await context.request.get(`${base}/account.php`)).json();
    const preview = await context.request.post(`${base}/admin/`, { form: {
      csrf: me.csrf, action: 'test-rights', panel: '0', role: 'user', gallery: 'none', api: '0', private: '0', minutes: '15'
    } });
    assert.equal(preview.status(), 200);
    await page.reload();
    assert.equal(await page.locator('.test-bar').count(), 1);
    assert.equal(await select.locator('option:disabled').count(), 18, 'the preview has no owner bonus');
    assert.equal(await select.locator('option[value=teto]').count(), 0, 'locked options do not expose identifiers');
    assert.equal(await page.locator('html').getAttribute('data-skin'), null, 'the server-rendered empty pick beats the old cookie');
    const refused = await context.request.post(`${base}/account/`, { form: { csrf: me.csrf, action: 'skin', skin: 'tao' } });
    assert.ok((await refused.text()).includes('Ten skin jest jeszcze zablokowany'));
    assert.equal(JSON.parse(readFileSync(prefsFile, 'utf8'))[owner], 'teto', 'previewing and a refused save preserve the real pick');
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    await defaultHome(page);
    update('finds', all => { all[owner] = { aprilfools: Math.floor(Date.now() / 1000) }; });
    await page.goto(`${base}/account/`);
    assert.equal(await select.locator('option:disabled').count(), 16, 'only earned skins are exposed in the preview');
    assert.ok((await select.locator('option[value=teto]').textContent()).includes('Teto'));
    assert.ok((await select.locator('option[value=miku]').textContent()).includes('Miku'));
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Wróć do swoich' }).click()]);
    assert.equal(await select.locator('option:disabled').count(), 0, 'ending the preview restores all owner skins');
    assert.equal(await select.inputValue(), 'teto');
    assert.equal(await page.locator('.test-bar').count(), 0);
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    assert.equal(await language(page), 'ja', 'the saved look returns');
  });
}
