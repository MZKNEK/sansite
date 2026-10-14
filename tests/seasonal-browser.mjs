import { chooseSkin } from './skin-picker-browser.mjs';
// Seasonal background finds: real pointer/keyboard input, server-signed proof
// fixtures for the pretend browser date, and real PHP account synchronization.
import assert from 'node:assert/strict';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import path from 'node:path';

export async function testSeasonalBackground({ browser, base, site, php, errors }) {
  const user = '900000000000000002';
  const events = [
    { key: 'sakura', date: '2026-04-10', label: 'Złap płatek sakury', group: 'Sakura',
      skins: [['march7', 'March 7th', '245, 179, 206'], ['shogun', 'Raiden Shogun', '110, 82, 196']] },
    { key: 'perseids', date: '2026-08-10', label: 'Złap kometę', group: 'Perseidy',
      skins: [['suisei', 'Suisei', '112, 151, 227'], ['hoshino', 'Hoshino Ai', '199, 63, 144']] }
  ];
  function fixture(name, change) {
    const file = path.join(site, 'inc/data', name + '.json');
    const data = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : {};
    change(data);
    writeFileSync(file, JSON.stringify(data));
  }
  function proof(key) {
    const made = spawnSync(php, ['-r', `
      define('SITE_DATA_DIR', $argv[1] . '/inc/data');
      require $argv[1] . '/inc/auth.php';
      require $argv[1] . '/inc/megastructure.php';
      echo megaProof('browser-register-secret', $argv[2], time());
    `, site, key], { encoding: 'utf8', windowsHide: true });
    assert.equal(made.status, 0, made.stderr);
    return made.stdout;
  }
  async function visit(date, run, { login = false, skin = '', finds = [], ...options } = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 850 }, ...options });
    try {
      await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
      fixture('finds', all => {
        delete all[user];
        if (finds.length) all[user] = Object.fromEntries(finds.map(key => [key, Math.floor(Date.now() / 1000)]));
      });
      fixture('skins', all => {
        delete all[user];
        if (skin) all[user] = skin;
      });
      if (login) await context.request.get(`${base}/__login?id=${user}`);
      if (skin) await context.request.get(`${base}/account.php`); // seed the saved appearance cookies
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.addInitScript(date => localStorage.setItem('safeguard-date', date), date);
      await page.route('**/status.php', route => route.fulfill({ json: { status: 'online', uptime: 100, down: '', issues: '' } }));
      await page.route('**/palette.php', route => route.fulfill({ json: { commands: [], versions: [] } }));
      const calls = [];
      const network = { fail: false };
      await page.route('**/megastructure.php*', route => {
        const key = new URL(route.request().url()).searchParams.get('witness');
        if (!events.some(event => event.key === key)) return route.continue();
        calls.push(key);
        return network.fail ? route.fulfill({ status: 503, json: {} }) : route.fulfill({ json: { proof: proof(key) } });
      });
      await page.goto(`${base}/`);
      await page.locator('#bot-status.online').waitFor();
      if (login) await page.locator('.account-menu').waitFor();
      await run(page, context, calls, network);
    } finally { await context.close(); }
  }
  async function backgroundTarget(page, key) {
    return page.evaluate(key => {
      document.getAnimations().filter(a => a.effect?.target.closest('.sg-season-background')).forEach(a => a.pause());
      for (const button of document.querySelectorAll(`.sg-${key} .seasonal-catch`)) {
        const r = button.getBoundingClientRect();
        const x = r.left + r.width / 2, y = r.top + r.height / 2;
        if (x < 8 || x > innerWidth - 8 || y < 8 || y > innerHeight - 8) continue;
        if (getComputedStyle(button.closest('.seasonal-particle')).opacity < 0.1) continue;
        const below = document.elementFromPoint(x, y);
        if (!below || below.closest('a, button, input, select, textarea, .logo-wrap, .card, .notice-bar, .sg-toast')) continue;
        return { x, y, target: below.tagName };
      }
      return null;
    }, key);
  }
  async function movingComet(page) {
    return page.locator('.perseid-flight').first().evaluate(mover => {
      // Start a real flight in the left margin, away from the page's controls.
      mover.style.left = '4vw';
      mover.style.animation = 'perseid-flight 10s -7s linear infinite';
      const animation = mover.getAnimations()[0];
      animation.currentTime = 0;
      if (animation.playState !== 'running') throw new Error('The comet must keep moving during the gesture');
      const r = mover.querySelector('button').getBoundingClientRect();
      const x = Math.round(r.left + r.width / 2), y = Math.round(r.top + r.height / 2);
      const below = document.elementFromPoint(x, y);
      if (!below || below.closest('a, button, input, select, textarea, .logo-wrap, .card, .notice-bar, .sg-toast'))
        throw new Error('The moving comet must start over empty background');
      return { x, y };
    });
  }

  await visit('2026-06-10', async (page, context, calls) => {
    await page.locator('.sg-sakura').waitFor();
    assert.equal(await page.locator('.sg-season-background').count(), 1, 'a saved Sakura skin brings petals outside April');
    await page.goto(`${base}/privacy/`);
    await page.locator('.sg-sakura').waitFor();
    assert.equal(await page.locator('.sg-season-background').count(), 1, 'the skin background follows navigation');
    await page.goto(`${base}/account/`);
    const select = page.locator('select[name=skin]');
    for (const event of events) {
      for (const [id] of event.skins) {
        await chooseSkin(page, id);
        const layer = page.locator('.sg-' + event.key);
        await layer.waitFor();
        assert.equal(await page.locator('.sg-season-background').count(), 1, 'preview replaces the previous skin background');
        assert.equal(await layer.getAttribute('aria-hidden'), 'true', 'outside the season the skin background is decorative');
        assert.equal(await layer.locator('.seasonal-catch:not(:disabled)').count(), 0);
        assert.equal(await page.getByRole('button', { name: event.label }).count(), 0, 'decorations add no keyboard stop');
        assert.equal(await layer.locator('.seasonal-particle').first().evaluate(el => getComputedStyle(el).animationName),
          event.key === 'sakura' ? 'sakura-fall' : 'perseid-flight');
        if (event.key === 'perseids') {
          assert.equal(await layer.locator('.perseid-star').count(), 56);
          assert.equal(await layer.locator('.perseid-star').first().evaluate(el => getComputedStyle(el).animationName), 'perseid-twinkle');
        }
        await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
        await page.goto(`${base}/`);
        await layer.waitFor();
        assert.equal(await page.locator('.sg-season-background').count(), 1, 'saving the skin keeps its background on home');
        const point = await backgroundTarget(page, event.key);
        assert.ok(point);
        await page.mouse.click(point.x, point.y);
        await page.waitForLoadState('networkidle');
        assert.deepEqual(calls, [], 'a skin background outside the season never asks for a discovery');
        await page.goto(`${base}/account/`);
      }
    }
    // Repeated previews reuse a scene, including after it was detached.
    await chooseSkin(page, 'march7');
    await page.locator('.sg-sakura').waitFor();
    await page.evaluate(() => { window.originalPetals = document.querySelector('.sg-sakura'); });
    await chooseSkin(page, 'suisei');
    await page.locator('.sg-perseids').waitFor();
    await chooseSkin(page, 'shogun');
    await page.locator('.sg-sakura').waitFor();
    assert.equal(await page.evaluate(() => document.querySelector('.sg-sakura') === window.originalPetals), true);
    await chooseSkin(page, '');
    await page.waitForFunction(() => !document.querySelector('.sg-season-background'));
    assert.equal(await page.locator('body').evaluate(el => el.classList.contains('seasonal-background')), false);
    // The last saved skin is still Hoshino; losing its find removes the background too.
    fixture('finds', all => { delete all[user]; });
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    await page.waitForFunction(() => !document.documentElement.hasAttribute('data-skin'));
    assert.equal(await page.locator('.sg-season-background').count(), 0, 'an account reply removes a revoked skin background');
  }, { login: true, skin: 'march7', finds: ['sakura', 'perseids'] });

  for (const [event, skin, find] of [[events[0], 'suisei', 'perseids'], [events[1], 'march7', 'sakura']]) {
    await visit(event.date, async (page, context, calls) => {
      await page.locator('.sg-' + event.key).waitFor();
      assert.equal(await page.locator('.sg-season-background').count(), 2, 'the seasonal event stays alongside another skin background');
      assert.equal(await page.locator('.sg-' + find).getAttribute('aria-hidden'), 'true');
      await page.getByRole('button', { name: event.label }).focus();
      await page.keyboard.press('Enter');
      await page.waitForFunction(key => JSON.parse(localStorage.getItem('safeguard'))?.sent?.[key], event.key);
      assert.deepEqual(calls, [event.key], 'the seasonal discovery remains available while wearing another background');
      await page.goto(`${base}/account/`);
      await chooseSkin(page, '');
      await page.waitForFunction(() => document.querySelectorAll('.sg-season-background').length === 1);
      assert.equal(await page.locator('.sg-' + event.key).count(), 1, 'removing the skin keeps the seasonal background');
    }, { login: true, skin, finds: [find] });
  }

  await visit('2026-08-10', async (page, context, calls) => {
    let point = await movingComet(page);
    await page.mouse.move(point.x, point.y);
    await page.mouse.down();
    await page.mouse.move(point.x + 40, point.y);
    await page.mouse.move(point.x, point.y);
    await page.mouse.up();
    await page.waitForLoadState('networkidle');
    assert.deepEqual(calls, [], 'dragging away and back cancels the catch');

    point = await movingComet(page);
    await page.mouse.move(point.x, point.y);
    await page.mouse.down();
    assert.deepEqual(calls, [], 'pressing alone does not award the find');
    await page.waitForTimeout(180);
    const r = await page.locator('.perseid-comet').first().boundingBox();
    assert.ok(point.x < r.x || point.x > r.x + r.width || point.y < r.y || point.y > r.y + r.height,
      'the animated comet has left the pressed point before release');
    await page.mouse.up();
    await page.waitForFunction(() => !!SanakanSafeguard.finds().perseids);
    assert.deepEqual(calls, ['perseids'], 'release catches the comet hit at the start of the press');
  });

  await visit('2026-08-10', async (page, context, calls) => {
    const touch = await context.newCDPSession(page);
    async function start(point) {
      await touch.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ ...point, id: 1 }] });
    }
    async function end(type = 'touchEnd') {
      await touch.send('Input.dispatchTouchEvent', { type, touchPoints: [] });
    }
    await page.evaluate(() => { document.body.style.minHeight = '200vh'; });
    let point = await movingComet(page);
    await start(point);
    for (const dy of [40, 80, 120]) {
      await touch.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: point.x, y: point.y - dy, id: 1 }] });
    }
    await end();
    await page.waitForFunction(() => scrollY > 0);
    await page.waitForLoadState('networkidle');
    assert.deepEqual(calls, [], 'swiping from a comet scrolls without awarding a find');

    // Reset the scrolled layout before starting a fresh tap.
    await page.reload();
    point = await movingComet(page);
    await start(point);
    await end('touchCancel');
    await page.waitForLoadState('networkidle');
    assert.deepEqual(calls, [], 'a cancelled touch gives no find');

    point = await movingComet(page);
    await start(point);
    assert.deepEqual(calls, [], 'a finger still held down gives no find');
    await page.waitForTimeout(180);
    const r = await page.locator('.perseid-comet').first().boundingBox();
    assert.ok(point.x < r.x || point.x > r.x + r.width || point.y < r.y || point.y > r.y + r.height,
      'the comet has moved away while the finger stays still');
    await end();
    await page.waitForFunction(() => !!SanakanSafeguard.finds().perseids);
    assert.deepEqual(calls, ['perseids'], 'a real touch catches the moving comet exactly once');
  }, { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });

  for (const event of events) {
    await visit(event.date, async (page, context, calls, network) => {
      const layer = page.locator(`.sg-${event.key}`);
      await layer.waitFor();
      assert.deepEqual(calls, [], 'visiting the event alone gives no find');
      if (event.key === 'perseids') {
        assert.equal(await page.locator('.perseid-star').count(), 56);
        assert.equal(await page.locator('.perseid-star').first().evaluate(el => getComputedStyle(el).animationName), 'perseid-twinkle');
      }
      await page.goto(`${base}/account/`);
      const select = page.locator('select[name=skin]');
      assert.equal(await select.locator(`optgroup[label="${event.group}"] option:disabled`).count(), 2);
      for (const [id, name] of event.skins) {
        assert.equal(await select.locator(`option[value="${id}"]`).count(), 0);
        assert.equal((await page.locator('.skin-form').textContent()).includes(name), false);
      }
      await page.goto(`${base}/`);
      await page.locator('.account-menu').waitFor();
      // Place a decoration over a real control: the search must still open.
      const particle = layer.locator('.seasonal-particle').first();
      const original = await particle.getAttribute('style');
      await particle.evaluate(el => {
        const r = document.querySelector('.foot-search').getBoundingClientRect();
        el.style.cssText = `left:${r.left}px;top:${r.top}px;animation:none;transform:none;opacity:1`;
      });
      await page.locator('.foot-search').click();
      await page.getByRole('combobox').waitFor();
      assert.deepEqual(calls, [], 'a decoration never intercepts a control or awards its find');
      await page.keyboard.press('Escape');
      await particle.evaluate((el, style) => el.setAttribute('style', style), original);
      const point = await backgroundTarget(page, event.key);
      assert.ok(point, 'a moving target is visible on the background: ' + event.key);
      network.fail = true;
      await page.mouse.click(point.x, point.y);
      await page.locator('.sg-toast').filter({ hasText: 'Safeguard nie odebrał sygnału' }).waitFor();
      assert.equal(await page.evaluate(key => !!SanakanSafeguard.finds()[key], event.key), false, 'a lost witness gives no find');
      assert.equal(await layer.getAttribute('aria-busy'), null, 'the failed click is retryable');
      network.fail = false;
      await page.mouse.click(point.x, point.y);
      await page.waitForFunction(key => JSON.parse(localStorage.getItem('safeguard'))?.sent?.[key], event.key);
      assert.deepEqual(calls, [event.key, event.key]);
      const finds = JSON.parse(readFileSync(path.join(site, 'inc/data/finds.json'), 'utf8'))[user];
      assert.ok(finds[event.key], 'the server account receives the witnessed discovery');
      assert.equal(await layer.locator('.seasonal-catch:not(:disabled)').count(), 0);
      for (const [id, name, rgb] of event.skins) {
        await page.goto(`${base}/account/`);
        assert.ok((await select.locator(`option[value="${id}"]`).textContent()).includes(name));
        await chooseSkin(page, id);
        await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
        await page.goto(`${base}/`);
        await page.locator('.account-menu').waitFor();
        await page.waitForFunction(id => { const logo = document.querySelector('img.logo'); return logo.complete && logo.naturalWidth > 0 && logo.src.endsWith('/skins/' + id + '.webp'); }, id);
        assert.equal(await page.locator('html').getAttribute('data-skin'), id);
        assert.equal(await page.locator('.sg-season-background').count(), 1, 'a skin and its current season share one background');
        assert.equal(await page.locator('html').getAttribute('lang'), 'pl');
        assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb').trim()), rgb);
        assert.match(await page.locator('.logo-slice').first().evaluate(el => getComputedStyle(el).backgroundImage), new RegExp(id + '\\.webp'));
      }
      await page.evaluate(() => document.getAnimations().filter(a => a.effect?.target.closest('.sg-season-background')).forEach(a => a.pause()));
      await page.screenshot({ path: path.join(process.cwd(), '.preview', event.key + '-preview.png') });
    }, { login: true });

    await visit(event.date, async (page, context, calls) => {
      const target = page.getByRole('button', { name: event.label });
      assert.equal(await page.locator('.seasonal-particle').first().evaluate(el => getComputedStyle(el).animationName), 'none');
      if (event.key === 'perseids') assert.equal(await page.locator('.perseid-star').first().evaluate(el => getComputedStyle(el).animationName), 'none');
      await target.focus();
      await page.keyboard.press('Enter');
      await page.waitForFunction(key => !!SanakanSafeguard.finds()[key], event.key);
      assert.deepEqual(calls, [event.key], 'a stationary target remains keyboard accessible');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'the background fits a phone');
      await context.request.get(`${base}/__login?id=${user}`);
      await page.reload();
      await page.waitForFunction(key => JSON.parse(localStorage.getItem('safeguard'))?.sent?.[key], event.key);
      assert.deepEqual(calls, [event.key], 'the anonymous proof is synchronized after login without another catch');
    }, { viewport: { width: 390, height: 844 }, hasTouch: true, reducedMotion: 'reduce' });
  }

  for (const [date, expected] of [
    ['2026-03-31', null], ['2026-04-01', 'sakura'], ['2026-04-15', 'sakura'], ['2026-04-16', null],
    ['2026-07-31', null], ['2026-08-01', 'perseids'], ['2026-08-15', 'perseids'], ['2026-08-16', null]
  ]) await visit(date, async (page, context, calls) => {
    assert.equal(await page.locator('.sg-season-background').count(), expected ? 1 : 0, 'the event boundary on ' + date);
    if (expected) assert.equal(await page.locator('.sg-' + expected).count(), 1);
    assert.deepEqual(calls, [], 'date changes alone never award a find');
  });
}
