// Exercise the real Morse input, register synchronization and home-only locale.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

export async function testHome({ browser, base, site, php, errors }) {
  // The browser can pretend April while PHP's clock stays on the real day.
  // Use a server-signed fixture for this one seasonal witness; synchronization
  // and proof verification still go through the real PHP endpoints.
  const generated = spawnSync(php, ['-r', `
    define('SITE_DATA_DIR', $argv[1] . '/inc/data');
    require $argv[1] . '/inc/auth.php';
    require $argv[1] . '/inc/megastructure.php';
    setHud('900000000000000002', 'full', 'dev');
    echo megaProof('browser-register-secret', 'aprilfools', time());
  `, site], { encoding: 'utf8', windowsHide: true });
  assert.equal(generated.status, 0, generated.stderr);
  const aprilProof = generated.stdout;

  async function visit(date, run, loggedIn = false, options = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 850 }, ...options });
    try {
      await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
      if (loggedIn) await context.request.get(`${base}/__login?id=900000000000000002`);
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.addInitScript(date => localStorage.setItem('safeguard-date', date), date);
      await page.route('**/status.php', route => route.fulfill({ json: { status: 'online', uptime: 100, down: '', issues: '' } }));
      await page.route('**/palette.php', route => route.fulfill({ json: { commands: [], versions: [] } }));
      const aprilRequests = [];
      await page.route('**/megastructure.php*', route => {
        if (new URL(route.request().url()).searchParams.get('witness') === 'aprilfools') {
          aprilRequests.push(route.request().url());
          return route.fulfill({ json: { proof: aprilProof } });
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
}
