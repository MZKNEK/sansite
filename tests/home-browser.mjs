// Exercise the real Morse input, register synchronization and home-only locale.
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

export async function testHome({ browser, base, site, php, errors }) {
  // The browser can pretend April while PHP's clock stays on the real day.
  // Use a server-signed fixture for this one seasonal witness; synchronization
  // and proof verification still go through the real PHP endpoints. It is made
  // anew for every call: a proof belongs to the first account that sends it,
  // and more than one visit earns the find here.
  function aprilProof() {
    const generated = spawnSync(php, ['-r', `
      define('SITE_DATA_DIR', $argv[1] . '/inc/data');
      require $argv[1] . '/inc/auth.php';
      require $argv[1] . '/inc/megastructure.php';
      setHud('900000000000000002', 'full', 'dev');
      echo megaProof('browser-register-secret', 'aprilfools', time());
    `, site], { encoding: 'utf8', windowsHide: true });
    assert.equal(generated.status, 0, generated.stderr);
    return generated.stdout;
  }

  async function visit(date, run, loggedIn = false, options = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 850 }, ...options });
    try {
      await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
      // true = the ordinary User; an id = that account (the owner is ...0001)
      if (loggedIn) await context.request.get(`${base}/__login?id=${loggedIn === true ? '900000000000000002' : loggedIn}`);
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.addInitScript(date => localStorage.setItem('safeguard-date', date), date);
      await page.route('**/status.php', route => route.fulfill({ json: { status: 'online', uptime: 100, down: '', issues: '' } }));
      await page.route('**/palette.php', route => route.fulfill({ json: { commands: [], versions: [] } }));
      const aprilRequests = [];
      await page.route('**/megastructure.php*', route => {
        if (new URL(route.request().url()).searchParams.get('witness') === 'aprilfools') {
          aprilRequests.push(route.request().url());
          return route.fulfill({ json: { proof: aprilProof() } });
        }
        return route.continue();
      });
      await page.goto(`${base}/`);
      await page.locator('#bot-status.online').waitFor();
      await run(page, context, aprilRequests);
    } finally { await context.close(); }
  }

  async function morse(page, word) {
    const code = { P: '.--.', O: '---', N: '-.', G: '--.' };
    const now = Date.now();
    await page.clock.install({ time: now });
    await page.clock.pauseAt(now + 1000);
    for (const letter of word) {
      for (const symbol of code[letter]) {
        await page.keyboard.down('Space');
        await page.clock.runFor(symbol === '.' ? 80 : 260);
        await page.keyboard.up('Space');
        await page.clock.runFor(100);
      }
      await page.clock.runFor(650);
    }
    await page.clock.runFor(1700);
    await page.clock.resume();
  }

  await visit('2026-07-15', async page => {
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl');
    assert.equal(await page.locator('.pp-overlay, .pp-paddle').count(), 0, 'the game starts hidden');
    await page.locator('.account-menu').waitFor();
    await morse(page, 'PONG');
    await page.getByRole('dialog', { name: 'Ping pong z Safeguardem' }).waitFor();
    await page.waitForFunction(() => JSON.parse(localStorage.getItem('safeguard'))?.sent?.pingpong);
    const finds = JSON.parse(readFileSync(path.join(site, 'inc/data/finds.json'), 'utf8'))['900000000000000002'];
    assert.ok(finds.pingpong, 'unlocking the game records a server-confirmed permanent find before scoring');
    assert.ok(!finds.aprilfools, 'playing outside April does not award the seasonal find');
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('sanakan:pong')));
    assert.equal(await page.locator('.pp-overlay').count(), 1, 'the game cannot open twice');
    await page.keyboard.press('Tab');
    assert.equal(await page.locator('.pp-close').evaluate(node => node === document.activeElement), true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.pp-overlay').count(), 0);
    await page.waitForTimeout(1100);
    assert.equal(await page.locator('.pp-overlay').count(), 0, 'the pending serve does not reopen a closed game');
  }, true);

  // 1 April 2029 is also Easter: Japanese takes precedence over its motto.
  await visit('2029-04-01', async (page, context, aprilRequests) => {
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'April starts in Polish');
    const normalAccent = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim());
    assert.match(await page.locator('img.logo').getAttribute('src'), /logo\.webp$/);
    assert.deepEqual(aprilRequests, [], 'visiting alone does not request the seasonal find');
    assert.equal(await page.evaluate(() => !!SanakanSafeguard.finds().aprilfools), false);
    assert.equal(await page.locator('.april-letter-original').textContent(), 'n');
    assert.equal(await page.locator('.april-letter-kana').textContent(), 'ン');
    assert.equal(await page.locator('.links a[href="./cmd/"]').textContent(), '01Polecenia');
    await page.getByRole('button', { name: 'Szukaj' }).click();
    await page.keyboard.press('Escape');
    await page.getByRole('heading', { name: 'Sanakan', exact: true }).click({ position: { x: 3, y: 3 } });
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'other letters do not activate the secret');
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).click();
    assert.equal(await page.locator('html').getAttribute('lang'), 'ja');
    await page.waitForFunction(() => document.querySelector('img.logo').complete && document.querySelector('img.logo').naturalWidth > 0);
    assert.match(await page.locator('img.logo').getAttribute('src'), /teto\.webp$/);
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '217, 75, 88');
    assert.match(await page.locator('.logo-slice').first().evaluate(node => getComputedStyle(node).backgroundImage), /teto\.webp/);
    await page.waitForFunction(() => !!SanakanSafeguard.finds().aprilfools);
    assert.equal(aprilRequests.length, 1, 'only activating the final letter witnesses the find');
    assert.equal(await page.locator('.april-letter').count(), 0, 'the switch is removed after activation');
    assert.equal(await page.title(), 'サナカン');
    assert.equal(await page.locator('h1').textContent(), 'サナカン');
    assert.equal(await page.locator('.kana').textContent(), 'SANAKAN');
    assert.equal(await page.locator('.kana').getAttribute('lang'), 'en');
    const literals = ['x$&', 'x$$', 'x$`', "x$'", '__proto__', 'constructor'];
    for (const literal of literals)
      assert.equal(await page.evaluate(text => SanakanHomeLanguage.text(text), literal), literal,
        'translation preserves literal replacement tokens and object-property names');
    const unsafeText = '<img src=x onerror="window.__localeInjected = true"> x$&';
    await page.evaluate(text => {
      window.__localeInjected = false;
      const box = document.createElement('div');
      box.id = 'locale-security-probe';
      const message = document.createElement('span');
      message.textContent = 'Zalogowano jako ' + text + '.';
      const status = document.createElement('span');
      status.title = 'Zaraz wracam: bot działa, ale ' + text + ' (kliknij, żeby zobaczyć dostępność)';
      const csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.value = text;
      const link = document.createElement('a');
      link.setAttribute('href', '/account/?probe=' + encodeURIComponent(text));
      box.append(message, status, csrf, link);
      document.body.appendChild(box);
    }, unsafeText);
    await page.waitForFunction(text => document.querySelector('#locale-security-probe span').textContent
      === text + 'としてログインしました。', unsafeText);
    const probe = await page.evaluate(async () => {
      await new Promise(resolve => requestAnimationFrame(resolve));
      const box = document.getElementById('locale-security-probe');
      const status = box.querySelectorAll('span')[1].title;
      return { text: box.firstElementChild.textContent, status, stable: SanakanHomeLanguage.text(status) === status,
        images: box.querySelectorAll('img').length, executed: window.__localeInjected,
        value: box.querySelector('input').value, href: box.querySelector('a').getAttribute('href') };
    });
    assert.equal(probe.text, unsafeText + 'としてログインしました。');
    assert.ok(probe.status.includes(unsafeText));
    assert.equal(probe.stable, true, 'observed attributes settle after translation');
    assert.equal(probe.images, 0, 'HTML-shaped text never creates elements');
    assert.equal(probe.executed, false, 'HTML-shaped text never executes');
    assert.equal(probe.value, unsafeText, 'translation does not modify form values');
    assert.equal(probe.href, '/account/?probe=' + encodeURIComponent(unsafeText), 'translation does not modify destinations');
    await page.locator('#locale-security-probe').evaluate(node => node.remove());
    assert.equal(await page.locator('#motto .sr-only').textContent(), '重そうだけど、飛べる');
    assert.ok(await page.locator('#motto .dot, #motto .dash').count() > 0, 'the Japanese motto still sends Morse');
    assert.equal(await page.locator('.links a[href="./cmd/"]').textContent(), '01コマンド');
    await page.getByRole('link', { name: 'Discordでログイン' }).waitFor();
    assert.match(await page.locator('#bot-status').getAttribute('title'), /オンライン/);
    assert.ok(!(await page.locator('#bot-status').getAttribute('title')).includes('kliknij'));
    assert.equal(await page.locator('.pp-overlay, .pp-paddle').count(), 0, 'April does not automatically open ping pong');
    await page.getByRole('button', { name: '検索' }).click();
    const search = page.getByRole('combobox');
    await search.fill('コマンド');
    await page.locator('#pl-list a[href$="/cmd/"]').waitFor();
    await page.keyboard.press('Escape');
    await page.locator('body').click({ position: { x: 5, y: 100 } });
    await morse(page, 'PONG');
    await page.getByRole('dialog', { name: 'セーフガードと卓球' }).waitFor();
    assert.equal(await page.locator('.pp-note').textContent(), 'マウス、タッチ、矢印キーで操作。5点先取。');
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 390, height: 844 });
    // Let resize listeners reposition seasonal decorations before measuring.
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
    const overflow = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth,
      elements: [...document.body.querySelectorAll('*')].filter(node => node.getBoundingClientRect().right > innerWidth + 1)
        .slice(0, 8).map(node => ({ tag: node.tagName, cls: node.className, text: node.textContent.slice(0, 80), right: node.getBoundingClientRect().right })) }));
    assert.ok(overflow.scroll <= overflow.width, 'Japanese navigation fits a phone: ' + JSON.stringify(overflow));
    await Promise.all([page.waitForNavigation(), page.locator('h1 .home-language-reset').click()]);
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'clicking the title restores Polish');
    assert.match(await page.locator('img.logo').getAttribute('src'), /logo\.webp$/);
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), normalAccent);
    assert.equal(await page.evaluate(() => !!SanakanSafeguard.finds().aprilfools), true, 'returning preserves the discovery');
    assert.equal(aprilRequests.length, 1, 'a saved find does not automatically activate the language');
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).click();
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Return to normal appearance' }).click()]);
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'the English subtitle also restores normal appearance');
    assert.equal(aprilRequests.length, 1, 'repeated switching does not award another find');
    await page.goto(`${base}/account/`);
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'the locale does not follow links to other pages');
  });

  await visit('2026-04-01', async page => {
    await page.locator('.account-menu').waitFor();
    const savedAccent = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim());
    assert.equal(savedAccent, '79, 209, 197', 'the account starts with its saved turquoise HUD');
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '217, 75, 88',
      'Japanese red overrides the saved HUD without changing the preference');
    await page.waitForFunction(() => JSON.parse(localStorage.getItem('safeguard'))?.sent?.aprilfools);
    assert.ok(JSON.parse(readFileSync(path.join(site, 'inc/data/finds.json'), 'utf8'))['900000000000000002'].aprilfools,
      'keyboard activation synchronizes the seasonal find to the account');
    await page.locator('.account-toggle').click();
    assert.equal(await page.locator('.account-name').textContent(), 'User', 'account names are preserved');
    await page.getByRole('link', { name: /プロフィール/ }).waitFor();
    await page.getByRole('button', { name: 'ログアウト' }).waitFor();
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Return to normal appearance' }).focus();
    await Promise.all([page.waitForNavigation(), page.keyboard.press('Enter')]);
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), savedAccent,
      'returning restores the account color and supports keyboard activation');
  }, true);

  await visit('2026-04-01', async page => {
    assert.equal(await page.locator('.april-letter-original').evaluate(node => getComputedStyle(node).animationName), 'none');
    assert.ok(Number(await page.locator('.april-letter-kana').evaluate(node => getComputedStyle(node).opacity)) > 0,
      'reduced motion keeps a static clue');
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).tap();
    assert.equal(await page.locator('html').getAttribute('lang'), 'ja', 'a touch activates the letter');
  }, false, { viewport: { width: 390, height: 844 }, hasTouch: true, reducedMotion: 'reduce' });

  await visit('2026-04-02', async page => {
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'April 2 returns to Polish');
    assert.equal(await page.locator('.links a[href="./cmd/"]').textContent(), '01Polecenia');
    assert.equal(await page.locator('.april-letter').count(), 0, 'the glitch is absent outside April 1');
  });

  // ---- The skin (inc/auth/skins.php, js/skins.js): the profile offers the
  // April look as a skin, unlocked by the find, worn on any day of the year
  // (and by the site's owner without it); the letter itself stays a visit's
  // own, as before.

  await visit('2026-07-15', async page => {
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.evaluate(() => !!SanakanSafeguard.finds().aprilfools), false, 'the owner has no find');
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    assert.equal(await select.inputValue(), '', 'nothing picked yet');
    assert.equal(await select.locator('optgroup').count(), 9, 'the picker groups the skins by their find');
    assert.equal(await select.locator('optgroup[label="Prima aprilis"] option').count(), 2);
    assert.equal(await select.locator('optgroup[label="Nowy rok"] option').count(), 2);
    assert.equal(await select.locator('optgroup[label="Piątek trzynastego"] option').count(), 2);
    assert.equal(await select.locator('optgroup[label="Urodziny"] option').count(), 2);
    assert.equal(await page.locator('.skin-form button[type=submit]').evaluate(node => node.classList.contains('primary')), true,
      'the save button looks like the one of the HUD');
    for (const key of ['teto', 'miku'])
      assert.equal(await select.locator(`option[value=${key}]`).evaluate(node => !node.disabled), true,
        'the owner has every skin: ' + key);
    assert.ok((await select.locator('option[value=miku]').textContent()).includes('turkusowa zieleń'),
      'and sees what Miku is without finding it');
    await select.selectOption('miku');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    assert.ok((await page.locator('#toast').textContent()).includes('Miku'), 'the profile says so');
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'miku', 'and wears its colours at once');
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '57, 197, 187');
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'miku');
    assert.equal(await page.locator('html').getAttribute('lang'), 'ja', 'the words come with the skin');
    assert.equal(await page.locator('h1').textContent(), 'サナカン');
    assert.match(await page.locator('img.logo').getAttribute('src'), /miku\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.locator('.home-language-reset').count(), 0, 'nothing to return from: the profile is the way');
    assert.equal(await page.locator('.april-letter').count(), 0, 'July has no glitch to click');
    assert.equal(await page.evaluate(() => !!SanakanSafeguard.finds().aprilfools), false, 'the owner keeps the find unearned');
  }, '900000000000000001');

  // With the skin worn, the name of 1 April is Japanese at once: the glitch
  // only waits on its last kana, and the click is for the find alone.
  await visit('2026-04-01', async (page, context) => {
    await context.addCookies([{ name: 'skin', value: 'miku', url: `${base}/` }]);
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'miku');
    assert.equal(await page.locator('html').getAttribute('lang'), 'ja', 'the skin is worn without a click');
    assert.equal(await page.locator('h1').textContent(), 'サナカンn', 'the name is Japanese, only its last kana glitching');
    assert.equal(await page.locator('.april-letter-original').textContent(), 'ン');
    assert.equal(await page.locator('.april-letter-kana').textContent(), 'n');
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).click();
    assert.equal(await page.locator('h1').textContent(), 'サナカン', 'nothing left to switch: the click is for the find');
    assert.equal(await page.locator('.home-language-reset').count(), 0, "the account's own skin has no way back here");
    await page.waitForFunction(() => JSON.parse(localStorage.getItem('safeguard'))?.sent?.aprilfools);
    assert.ok(JSON.parse(readFileSync(path.join(site, 'inc/data/finds.json'), 'utf8'))['900000000000000001'].aprilfools,
      'the owner earned the seasonal find too');
  }, '900000000000000001');

  await visit('2026-07-15', async page => {
    await page.locator('.account-menu').waitFor();
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    assert.equal(await select.locator('option[value=teto]').evaluate(node => !node.disabled), true, 'the earned find unlocked the skin');
    await select.selectOption('teto');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'teto');
    assert.equal(await page.locator('html').getAttribute('lang'), 'ja', 'July wears the April look');
    assert.equal(await page.locator('.links a[href="./cmd/"]').textContent(), '01コマンド', 'the skin speaks its words');
    // the colours go over the whole site (css/skins.css), the words stay home
    await page.goto(`${base}/404.html`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'teto', 'other pages wear the skin too');
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '217, 75, 88');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'the words stay on the home page');
    // the words can be kept off: the colours and the logo stay, the page speaks Polish
    await page.goto(`${base}/account/`);
    await page.locator('input[name=skinwords]').uncheck();
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'teto', 'the colours stay without the words');
    assert.match(await page.locator('img.logo').getAttribute('src'), /teto\.webp$/);
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'without the words the page speaks Polish');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.locator('.links a[href="./cmd/"]').textContent(), '01Polecenia');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false, 'nothing translates the page');
    await page.goto(`${base}/account/`);
    await select.selectOption('');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    assert.ok((await page.locator('#toast').textContent()).includes('Wyłączono skin'));
    assert.equal(await page.locator('html').getAttribute('data-skin'), null);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'taking the skin off restores the page');
    assert.match(await page.locator('img.logo').getAttribute('src'), /logo\.webp$/);
    await page.goto(`${base}/404.html`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), null, 'and the whole site with it');
    assert.ok(JSON.parse(readFileSync(path.join(site, 'inc/data/finds.json'), 'utf8'))['900000000000000002'].aprilfools,
      'the find stays on the account');
  }, '900000000000000002');

  await visit('2026-07-15', async (page, context) => {
    await page.locator('.account-menu').waitFor();
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    const locked = select.locator('option:disabled');
    assert.equal(await locked.count(), 18, 'all seasonal skins are locked without finds');
    for (const [group, hint] of [
      ['Prima aprilis', 'Zagubiony w tłumaczeniu'], ['Halloween', 'Noc form krzemowych'],
      ['Święta', 'Święta w Megastrukturze'], ['Wielkanoc', 'Pisanki z Megastruktury'],
      ['Nowy rok', 'Nowy cykl'], ['Piątek trzynastego', 'Piątek trzynastego'], ['Urodziny', 'Sto lat'], ['Sakura', 'Sakura'], ['Perseidy', 'Perseidy']
    ]) {
      const options = select.locator(`optgroup[label="${group}"] option:disabled`);
      assert.equal(await options.count(), 2);
      for (const text of await options.allTextContents())
        assert.equal(text, '??? · zablokowany: ' + hint, 'only the unlocking hint is visible');
    }
    assert.equal(await locked.evaluateAll(options => options.every(option => option.value === '' && !option.hasAttribute('data-skin-words'))), true,
      'locked options expose neither identifiers nor language metadata');
    const form = await page.locator('.skin-form').textContent();
    for (const name of ['Teto', 'Miku', 'Hu Tao', 'Rin', 'March 7th', 'Padoru', 'Reisen', 'Pekora', 'Furina', 'Albedo', 'Sadako', 'Lain', 'Cibo', 'Killy', 'Chisato', 'Yae Miko', 'Suisei', 'Hoshino Ai'])
      assert.equal(form.includes(name), false, 'the form keeps the name hidden: ' + name);
    // a form sent by hand cannot talk the server into a locked skin
    const me = await (await context.request.get(`${base}/account.php?finds`)).json();
    const refused = await context.request.post(`${base}/account/`, { form: { csrf: me.csrf, action: 'skin', skin: 'teto' } });
    assert.ok((await refused.text()).includes('Ten skin jest jeszcze zablokowany'), 'the server refuses the locked skin');
    await page.goto(`${base}/account/`);
    assert.equal(await select.inputValue(), '');
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), null, 'the page stays as it was');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl');
  }, '900000000000000004');

  // ---- Hu Tao and Rin, the skins of the Halloween find: colours and logo
  // alone (no words at all, whatever the translation switch says).

  const findsFile = path.join(site, 'inc/data/finds.json');
  const withHalloween = JSON.parse(readFileSync(findsFile, 'utf8'));
  withHalloween['900000000000000002'] = { ...(withHalloween['900000000000000002'] || {}), halloween: Math.floor(Date.now() / 1000) };
  writeFileSync(findsFile, JSON.stringify(withHalloween));

  await visit('2026-07-15', async page => {
    await page.locator('.account-menu').waitFor();
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    for (const key of ['tao', 'rin'])
      assert.equal(await select.locator(`option[value=${key}]`).evaluate(node => !node.disabled), true,
        'the Halloween find unlocked ' + key);
    await select.selectOption('tao');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    assert.equal(await page.locator('.skin-form input[name=skinwords]').isVisible(), false, 'a skin with no words offers no switch');
    assert.equal(await page.locator('.skin-form input[name=skinwords]').isDisabled(), true, 'the hidden switch is not submitted');
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'tao');
    assert.match(await page.locator('img.logo').getAttribute('src'), /tao\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '226, 98, 47');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'these skins speak no words');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.locator('.links a[href="./cmd/"]').textContent(), '01Polecenia');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false);
    await page.goto(`${base}/account/`);
    await select.selectOption('rin');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'rin');
    assert.match(await page.locator('img.logo').getAttribute('src'), /rin\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '240, 194, 50');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
  }, '900000000000000002');

  // ---- March 7th and Padoru, the skins of the Christmas find: colours and
  // logo alone as well.

  const withXmas = JSON.parse(readFileSync(findsFile, 'utf8'));
  withXmas['900000000000000002'] = { ...(withXmas['900000000000000002'] || {}), xmas: Math.floor(Date.now() / 1000) };
  writeFileSync(findsFile, JSON.stringify(withXmas));

  await visit('2026-07-15', async page => {
    await page.locator('.account-menu').waitFor();
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    for (const key of ['march7', 'padoru'])
      assert.equal(await select.locator(`option[value=${key}]`).evaluate(node => !node.disabled), true,
        'the Christmas find unlocked ' + key);
    await select.selectOption('march7');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'march7');
    assert.match(await page.locator('img.logo').getAttribute('src'), /march7\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '229, 115, 155');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'these skins speak no words');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    await page.goto(`${base}/account/`);
    await select.selectOption('padoru');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'padoru');
    assert.match(await page.locator('img.logo').getAttribute('src'), /padoru\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '223, 169, 63');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false);
  }, '900000000000000002');

  // ---- Reisen and Pekora, the skins of the Easter find: colours and logo
  // alone as well.

  const withEaster = JSON.parse(readFileSync(findsFile, 'utf8'));
  withEaster['900000000000000002'] = { ...(withEaster['900000000000000002'] || {}), easter: Math.floor(Date.now() / 1000) };
  writeFileSync(findsFile, JSON.stringify(withEaster));

  await visit('2026-07-15', async page => {
    await page.locator('.account-menu').waitFor();
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    for (const key of ['reisen', 'peko'])
      assert.equal(await select.locator(`option[value=${key}]`).evaluate(node => !node.disabled), true,
        'the Easter find unlocked ' + key);
    await select.selectOption('reisen');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'reisen');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/reisen\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '178, 133, 222');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'these skins speak no words');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    await page.goto(`${base}/account/`);
    await select.selectOption('peko');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'peko');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/peko\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '108, 159, 224');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false);
  }, '900000000000000002');

  // ---- Furina and Albedo, the skins of the New Year's find: colours and logo
  // alone as well.

  const withNewYear = JSON.parse(readFileSync(findsFile, 'utf8'));
  withNewYear['900000000000000002'] = { ...(withNewYear['900000000000000002'] || {}), newyear: Math.floor(Date.now() / 1000) };
  writeFileSync(findsFile, JSON.stringify(withNewYear));

  await visit('2026-07-15', async page => {
    await page.locator('.account-menu').waitFor();
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    for (const key of ['furi', 'albedo'])
      assert.equal(await select.locator(`option[value=${key}]`).evaluate(node => !node.disabled), true,
        'the New Year find unlocked ' + key);
    await select.selectOption('furi');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'furi');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/furi\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '79, 111, 229');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'these skins speak no words');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    await page.goto(`${base}/account/`);
    await select.selectOption('albedo');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'albedo');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/albedo\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '224, 192, 96');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false);
  }, '900000000000000002');

  // ---- Cibo and Killy, the skins of the birthday find: colours and logo
  // alone as well.

  const withBirthday = JSON.parse(readFileSync(findsFile, 'utf8'));
  withBirthday['900000000000000002'] = { ...(withBirthday['900000000000000002'] || {}), birthday: Math.floor(Date.now() / 1000) };
  writeFileSync(findsFile, JSON.stringify(withBirthday));

  await visit('2026-07-15', async page => {
    await page.locator('.account-menu').waitFor();
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    for (const key of ['cibo', 'killy'])
      assert.equal(await select.locator(`option[value=${key}]`).evaluate(node => !node.disabled), true,
        'the birthday find unlocked ' + key);
    await select.selectOption('cibo');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'cibo');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/cibo\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '194, 200, 217');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'these skins speak no words');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    await page.goto(`${base}/account/`);
    await select.selectOption('killy');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'killy');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/killy\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '102, 121, 155');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false);
  }, '900000000000000002');

  // ---- Sadako and Lain, the skins of Friday the 13th's find: colours and logo
  // alone as well.

  const withFriday13 = JSON.parse(readFileSync(findsFile, 'utf8'));
  withFriday13['900000000000000002'] = { ...(withFriday13['900000000000000002'] || {}), friday13: Math.floor(Date.now() / 1000) };
  writeFileSync(findsFile, JSON.stringify(withFriday13));

  await visit('2026-07-15', async page => {
    await page.locator('.account-menu').waitFor();
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    for (const key of ['sadako', 'lain'])
      assert.equal(await select.locator(`option[value=${key}]`).evaluate(node => !node.disabled), true,
        'the Friday the 13th find unlocked ' + key);
    await select.selectOption('sadako');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'sadako');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/sadako\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '139, 147, 189');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'these skins speak no words');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    await page.goto(`${base}/account/`);
    await select.selectOption('lain');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'lain');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/lain\.webp$/);
    await page.waitForFunction(() => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0; });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '167, 153, 164');
    assert.equal(await page.locator('h1').textContent(), 'Sanakan');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false);
    // back to the New Year's pick, which the April tests below read as the
    // account's own skin
    await page.goto(`${base}/account/`);
    await select.selectOption('albedo');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
  }, '900000000000000002');

  // On 1 April the glitched letter turns the page into the April look even when
  // the account wears a skin of its own: that visit only, back with a reload.
  await visit('2026-04-01', async (page, context) => {
    await context.addCookies([{ name: 'skin', value: 'albedo', url: `${base}/` }]);
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'albedo');
    assert.equal(await page.locator('.april-letter-original').textContent(), 'n', 'the glitch is the way in');
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).click();
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'teto', 'the click turns the April skin on');
    assert.equal(await page.locator('html').getAttribute('lang'), 'ja');
    assert.equal(await page.locator('h1').textContent(), 'サナカン');
    assert.match(await page.locator('img.logo').getAttribute('src'), /skins\/teto\.webp$/);
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '217, 75, 88');
    assert.equal(await page.evaluate(() => document.querySelector('meta[name="theme-color"]').content), 'rgb(217, 75, 88)',
      'the browser bar follows the skin (js/appearance.js)');
    assert.equal(await page.locator('.home-language-reset').count(), 2, 'a way back, as for a visitor without a skin');
    await Promise.all([page.waitForNavigation(), page.locator('h1 .home-language-reset').click()]);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'albedo', "the reload brings the account's own skin back");
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl');
  }, '900000000000000002');

  // The same with a skin that speaks Japanese but is kept silent (skinwords
  // off): it is not yet the April look, and the click turns its words back on
  // for the visit — the skin already worn included.
  await visit('2026-04-01', async page => {
    // the account picks Teto and turns its words off, as its profile does
    await page.goto(`${base}/account/`);
    await page.locator('select[name=skin]').selectOption('teto');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.locator('input[name=skinwords]').uncheck();
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    assert.equal(await page.locator('html').getAttribute('data-skin'), 'teto');
    assert.equal(await page.locator('html').getAttribute('lang'), 'pl', 'the words are kept off');
    assert.equal(await page.locator('h1').textContent(), 'Sanakanン', 'the Polish name with the glitch');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), false, 'nothing translates the page yet');
    await page.getByRole('button', { name: 'Zbadaj zakłócony znak' }).click();
    assert.equal(await page.locator('html').getAttribute('lang'), 'ja', 'the click speaks its words again');
    assert.equal(await page.locator('h1').textContent(), 'サナカン');
    assert.equal(await page.locator('.home-language-reset').count(), 2, 'a way back, as for a visitor without a skin');
    assert.equal(await page.evaluate(() => !!window.SanakanHomeLanguage), true, 'the translator is on for the visit');
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), '217, 75, 88');
    await page.waitForFunction(() => JSON.parse(localStorage.getItem('safeguard'))?.sent?.aprilfools, null, { timeout: 10000 });
  }, '900000000000000002');

  // Logging out takes the look's cookies along in the same answer (logout(),
  // forgetHudCookies() in inc/auth/session.php): the static page the form comes
  // back to is drawn without the account's skin, not only the one after it.
  await visit('2026-07-15', async page => {
    // a skin on the account, so the cookie is there to be taken along
    await page.goto(`${base}/account/`);
    await page.locator('select[name=skin]').selectOption('teto');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    await page.waitForFunction(() => document.documentElement.getAttribute('data-skin') === 'teto');
    await page.locator('.account-toggle').click();
    await Promise.all([page.waitForNavigation(), page.locator('.account-out').click()]);
    assert.equal(await page.locator('html').getAttribute('data-skin'), null, 'the skin went with the logout');
    assert.equal(await page.evaluate(() => document.cookie.includes('skin=')), false, 'and its cookie with it');
    await page.locator('#home-account .home-login').waitFor();
  }, '900000000000000002');
}
