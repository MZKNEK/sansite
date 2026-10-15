import { discoveryContext } from './browser-context.mjs';
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
      skins: [['suisei', 'Suisei', '112, 151, 227'], ['hoshino', 'Hoshino Ai', '199, 63, 144']] },
    { key: 'fireflies', date: '2026-06-21', label: 'Złap świetlika', group: 'Noc świetlików',
      skins: [['firefly', 'Firefly', '168, 230, 207'], ['yoimiya', 'Yoimiya', '245, 130, 69']] }
  ];
  function fixture(name, change) {
    const file = path.join(site, 'inc/data', name + '.json');
    // PHP writes an empty map as []; turn it back into an object before
    // assigning account IDs, which JSON.stringify would drop from an array.
    const data = existsSync(file) ? { ...JSON.parse(readFileSync(file, 'utf8')) } : {};
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
  async function visit(date, run, { login = false, skin = '', finds = [], animations = true, ...options } = {}) {
    const context = await discoveryContext(browser, { viewport: { width: 1280, height: 850 }, ...options });
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
      fixture('snow', all => { if (animations) delete all[user]; else all[user] = true; });
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
    const pause = () => page.evaluate(() => document.getAnimations().filter(a => a.effect?.target.closest('.sg-season-background')).forEach(a => a.pause()));
    const resume = () => page.evaluate(() => document.getAnimations().filter(a => a.effect?.target.closest('.sg-season-background')).forEach(a => a.play()));
    const find = () => page.evaluate(key => {
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
    // A meteor shower has gaps between meteors: wait for one, then hold it still.
    for (let i = 0; i < 100; i++) {
      await pause();
      const point = await find();
      if (point) return point;
      await resume();
      await page.waitForTimeout(100);
    }
    return null;
  }
  async function movingComet(page) {
    return page.locator('.perseid-flight').first().evaluate(async mover => {
      // A known, fast flight in the left margin, away from the page's controls.
      mover.style.cssText = 'left:4vw;top:0';
      mover.style.setProperty('--season-angle', '0deg');
      mover.style.setProperty('--season-from', '-40vh');
      mover.style.setProperty('--season-to', '120vh');
      mover.style.setProperty('--season-bright', '1');
      mover.style.animation = 'perseid-flight 4s -1.8s linear infinite';
      const animation = mover.getAnimations()[0];
      // Keep the sampled position until input actually reaches the document.
      // Resume after the scene's hit test, so the held gesture still moves.
      animation.pause();
      animation.currentTime = 0;
      await animation.ready;
      document.addEventListener('pointerdown', () => animation.play(), { once: true });
      const r = mover.querySelector('button').getBoundingClientRect();
      const x = Math.round(r.left + r.width / 2), y = Math.round(r.top + r.height / 2);
      const below = document.elementFromPoint(x, y);
      if (!below || below.closest('a, button, input, select, textarea, .logo-wrap, .card, .notice-bar, .sg-toast'))
        throw new Error('The moving comet must start over empty background');
      return { x, y };
    });
  }

  for (const date of ['2026-06-10', '2026-12-24']) await visit(date, async (page, context, calls) => {
    const winter = date === '2026-12-24';
    assert.equal(await page.locator('.sg-snow:visible').count(), 1, 'a saved Christmas skin brings snow');
    assert.equal(await page.locator('.sg-snow i').count(), 28, 'winter and skin use one snowfall');
    await page.goto(`${base}/privacy/`);
    assert.equal(await page.locator('.sg-snow:visible').count(), 1, 'skin snow follows navigation');
    await page.goto(`${base}/account/`);
    const animations = page.locator('.skin-form [name=skinanimations]');
    await page.evaluate(() => { window.originalSnow = document.querySelector('.sg-snow'); });
    for (const id of ['padoru', 'fauna', 'padoru', '']) {
      await chooseSkin(page, id);
      const visible = !!id || winter;
      assert.equal(await page.locator('.sg-snow:visible').count(), visible ? 1 : 0);
      assert.equal(await animations.isVisible(), true, 'the animation switch is available for every skin');
      assert.equal(await page.evaluate(() => document.querySelector('.sg-snow') === window.originalSnow), true,
        'skin previews reuse the winter snow layer');
    }
    // A temporary preview rolls back its snowfall when the picker is cancelled.
    await page.locator('[data-skin-open]').click();
    await page.locator('[data-skin-tile=fauna]').click();
    assert.equal(await page.locator('.sg-snow:visible').count(), 1);
    await page.getByRole('button', { name: 'Anuluj', exact: true }).click();
    assert.equal(await page.locator('.sg-snow:visible').count(), winter ? 1 : 0);
    await chooseSkin(page, 'fauna');
    await animations.uncheck();
    assert.equal(await page.locator('.sg-snow:visible').count(), 0, 'the switch stops both sources of snow');
    await chooseSkin(page, 'padoru');
    assert.equal(await page.locator('.sg-snow:visible').count(), 0, 'a preview preserves the unsaved snow choice');
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    // The skin form saves the shared preference for subsequent pages too.
    assert.equal(JSON.parse(readFileSync(path.join(site, 'inc/data/snow.json'), 'utf8'))[user], true);
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    assert.equal(await page.locator('.sg-snow:visible').count(), 0, 'the saved preference stops skin and winter snow');
    await page.goto(`${base}/account/`);
    assert.equal(await animations.isChecked(), false, 'the skin form restores the saved animation choice');
    await animations.check();
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    assert.equal(await page.locator('.sg-snow:visible').count(), 1, 'animations can be enabled again');
    assert.deepEqual(calls, [], 'snow previews never award a find');
  }, { login: true, skin: 'fauna', finds: ['xmas'] });

  for (const [date, expected] of [['2026-12-19', 0], ['2026-12-20', 1], ['2026-03-20', 1], ['2026-03-21', 0]]) {
    await visit(date, async page => {
      assert.equal(await page.locator('.sg-snow:visible').count(), expected, 'the existing winter boundary on ' + date);
    });
  }

  await visit('2026-06-10', async page => {
    assert.equal(await page.locator('.sg-snow:visible').count(), 1);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.waitForFunction(() => !document.querySelector('.sg-snow:not([hidden])'));
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.locator('.sg-snow').waitFor({ state: 'visible' });
    assert.equal(await page.locator('.sg-snow').count(), 1, 'changing motion preference reuses the snowfall');
  }, { login: true, skin: 'padoru', finds: ['xmas'] });

  await visit('2026-06-10', async (page, context, calls) => {
    assert.equal(await page.locator('.sg-snow:visible').count(), 0, 'the legacy snow setting suppresses the initial snowfall');
    await page.goto(`${base}/account/`);
    const toggle = page.getByRole('checkbox', { name: /Animacje skina/ });
    assert.equal(await toggle.isChecked(), false, 'the old snow preference becomes the animation preference');
    for (const [skin, effect] of [['fauna', '.sg-snow'], ['padoru', '.sg-snow'], ['tomie', '.sg-ink'],
      ['lain', '.sg-wired'], ['echidna', '.sg-butterflies'], ['marisa', '.sg-magic'],
      ['ayame', '.sg-fireworks'], ['albedo', '.sg-fireworks'],
      ['reisen', '.sg-lavender'], ['peko', '.sg-rabbits'],
      ['cibo', '.sg-netsphere'], ['killy', '.sg-emitter'],
      ['march7', '.sg-sakura'], ['shogun', '.sg-sakura'], ['suisei', '.sg-perseids'], ['hoshino', '.sg-perseids'],
      ['firefly', '.sg-fireflies'], ['yoimiya', '.sg-fireflies']]) {
      await chooseSkin(page, skin);
      assert.equal(await page.locator(effect + ':visible').count(), 0, 'effects stay off after previewing ' + skin);
      await toggle.check();
      assert.equal(await page.locator(effect + ':visible').count(), 1, 'the switch enables ' + skin);
      await toggle.uncheck();
      assert.equal(await page.locator(effect + ':visible').count(), 0, 'the switch hides ' + skin);
    }
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    assert.equal(await toggle.isChecked(), false);
    assert.equal((await (await context.request.get(`${base}/account.php`)).json()).skinanimations, false);
    await page.goto(`${base}/privacy/`);
    assert.equal(await page.locator('.sg-season-background').count(), 0, 'the saved switch applies on other pages');
    // A HUD-only save must keep the independently saved animation preference.
    const me = await (await context.request.get(`${base}/account.php`)).json();
    await context.request.post(`${base}/account/`, { form: { csrf: me.csrf, action: 'hud', hud: 'default' } });
    assert.equal((await (await context.request.get(`${base}/account.php`)).json()).skinanimations, false);
    assert.deepEqual(calls, []);
  }, { login: true, skin: 'fauna', finds: ['xmas', 'friday13', 'halloween', 'newyear', 'easter', 'birthday', 'sakura', 'perseids', 'fireflies'], animations: false });

  for (const event of events) await visit(event.date, async (page, context, calls) => {
    const layer = page.locator('.sg-' + event.key);
    assert.equal(await layer.evaluate(el => el.getAnimations({ subtree: true }).filter(a => a instanceof CSSAnimation).length), 0,
      'turning skin animations off also stops the current event');
    const target = page.getByRole('button', { name: event.label });
    await target.focus();
    assert.equal(await target.locator('..').evaluate(el => getComputedStyle(el.closest('.seasonal-particle')).opacity), '1');
    await page.keyboard.press('Enter');
    // Finish the account write before the next visit replaces its fixtures.
    await page.waitForFunction(key => JSON.parse(localStorage.getItem('safeguard'))?.sent?.[key], event.key);
    assert.deepEqual(calls, [event.key], 'the event remains keyboard accessible with animations off');
  }, { login: true, animations: false });

  for (const date of ['2026-06-10', '2026-11-13', '2026-10-31', '2026-12-31', '2027-03-28', '2026-04-10', '2026-10-13']) await visit(date, async (page, context, calls) => {
    const seasonal = date === '2026-04-10' ? 1 : 0;
    await page.goto(`${base}/account/`);
    assert.equal(await page.locator('option[value="tomie"]').count(), 1, 'the decorative-skin fixture is unlocked on ' + date);
    for (const [skin, key] of [['tomie', 'ink'], ['lain', 'wired'], ['echidna', 'butterflies'], ['marisa', 'magic'],
      ['ayame', 'fireworks'], ['albedo', 'fireworks'], ['reisen', 'lavender'], ['peko', 'rabbits'],
      ['cibo', 'netsphere'], ['killy', 'emitter']]) {
      await chooseSkin(page, skin);
      const layer = page.locator('.sg-' + key);
      await layer.waitFor();
      assert.equal(await page.locator('.sg-season-background').count(), 1 + seasonal);
      assert.equal(await layer.getAttribute('aria-hidden'), 'true');
      assert.equal(await layer.locator('button, [tabindex]').count(), 0, 'skin decorations have no interactive targets');
      assert.equal(await layer.evaluate(el => getComputedStyle(el).pointerEvents), 'none');
      assert.ok(await layer.evaluate(el => el.getAnimations({ subtree: true }).length > 0), 'the skin effect is animated');
      if (key === 'netsphere' && date === '2026-06-10') {
        const phases = await layer.locator('.netsphere-signal').first().evaluate(el => {
          const animation = el.getAnimations()[0];
          const time = animation.currentTime;
          const timing = animation.effect.getTiming();
          const offsets = [0.25, 0.75].map(phase => {
            // Sample the next cycle so a negative delay never produces a
            // negative currentTime (before the animation's active interval).
            animation.currentTime = timing.delay + timing.duration * (1 + phase);
            return parseFloat(getComputedStyle(el).strokeDashoffset);
          });
          animation.currentTime = time;
          return offsets;
        });
        assert.ok(phases[0] - phases[1] > 0.45, 'Cibo’s signal travels along its route');
        assert.equal(await layer.locator('.netsphere-mesh').evaluate(svg => {
          const edges = new Set(svg.querySelector('.netsphere-links').getAttribute('d').match(/M[^M]+/g));
          return [...svg.querySelectorAll('.netsphere-signal')].every(packet => {
            const points = packet.getAttribute('d').split(/[ML]/).filter(Boolean);
            return points.slice(1).every((point, i) => edges.has('M' + points[i] + 'L' + point) || edges.has('M' + point + 'L' + points[i]));
          });
        }), true, 'signals follow connections in the generated network');
        assert.ok(await layer.locator('.netsphere-node').first().evaluate(el => Number(getComputedStyle(el).opacity)) < 0.2,
          'network points stay barely visible');
        await page.setViewportSize({ width: 800, height: 700 });
        await page.waitForFunction(() => document.querySelector('.netsphere-mesh').getAttribute('viewBox') === '0 0 800 700');
        await page.setViewportSize({ width: 1280, height: 850 });
        await page.waitForFunction(() => document.querySelector('.netsphere-mesh').getAttribute('viewBox') === '0 0 1280 850');
      }
      await page.evaluate(() => {
        Object.defineProperty(document, 'hidden', { value: true, configurable: true });
        document.dispatchEvent(new Event('visibilitychange'));
      });
      assert.equal(await layer.evaluate(el => el.getAnimations({ subtree: true }).every(animation => animation.playState === 'paused')), true,
        'hidden tabs pause every layer of the effect');
      await page.evaluate(() => {
        delete document.hidden;
        document.dispatchEvent(new Event('visibilitychange'));
      });
      await page.locator('.foot-search').click();
      await page.getByRole('combobox').waitFor();
      await page.keyboard.press('Escape');
      await page.emulateMedia({ reducedMotion: 'reduce' });
      if (key === 'fireworks') await page.waitForFunction(() => !document.querySelector('.sg-fireworks').children.length);
      assert.equal(await layer.evaluate(el => el.getAnimations({ subtree: true }).length), 0,
        'reduced motion leaves a stationary decoration');
      await page.emulateMedia({ reducedMotion: 'no-preference' });
      await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
      await page.goto(`${base}/`);
      await layer.waitFor();
      assert.equal(await page.locator('.sg-season-background').count(), 1 + seasonal, 'the saved effect follows navigation');
      if (date === '2026-10-13') assert.equal(await page.locator('.birthday-cake').count(), 1, 'skin effects preserve the birthday cake');
      if (date === '2026-12-31') {
        assert.equal(await page.locator('.sg-snow').count(), 1, 'skin decorations coexist with one winter snowfall');
        assert.equal(await page.locator('.ny-rocket').count(), 1, 'the New Year rocket remains available');
      }
      if (date === '2026-06-10') {
        await page.waitForFunction(() => getComputedStyle(document.querySelector('.links')).opacity === '1');
        if (key === 'fireworks') await page.waitForFunction(() => document.querySelector('.sg-fireworks').children.length > 1);
        await layer.evaluate((el, key) => el.getAnimations({ subtree: true }).forEach(animation => {
          animation.pause();
          const timing = animation.effect.getTiming();
          if (animation.animationName !== 'rabbit-cross') {
            const phase = key === 'fireworks' ? 0.45 : key === 'ink' ? 0.6 : 0.71;
            const cycle = timing.iterations === Infinity ? 1 : 0;
            animation.currentTime = timing.delay + timing.duration * (cycle + phase);
          }
        }), key);
        await page.screenshot({ path: path.join(process.cwd(), '.preview', skin + '-background-preview.png') });
      }
      await page.goto(`${base}/account/`);
      await layer.evaluate(el => { window.originalSkinDecoration = el; });
      await chooseSkin(page, '');
      assert.equal(await page.locator('.sg-season-background').count(), seasonal, 'removing the skin keeps only the event');
      await chooseSkin(page, skin);
      assert.equal(await layer.evaluate(el => el === window.originalSkinDecoration), true, 'previews reuse the decoration');
    }
    assert.deepEqual(calls, [], 'skin decorations never request a seasonal discovery');
  }, { login: true, skin: 'tomie', finds: ['friday13', 'halloween', 'newyear', 'easter', 'birthday'] });

  for (const skin of ['reisen', 'peko']) await visit('2027-03-28', async page => {
    // Use public pages with HUD boxes that can host the event's egg.
    await page.goto(`${base}/privacy/`);
    const egg = page.getByRole('button', { name: 'Pisanka', exact: true });
    await egg.click();
    assert.equal(await page.evaluate(() => JSON.parse(localStorage.getItem('safeguard-easter')).pages.includes('/privacy/')), true,
      'the skin background keeps the Easter egg clickable');
    await page.goto(`${base}/account/`);
    await page.getByRole('checkbox', { name: /Animacje skina/ }).uncheck();
    await Promise.all([page.waitForNavigation(), page.locator('.skin-form button[type=submit]').click()]);
    await page.goto(`${base}/cmd/`);
    assert.equal(await page.locator('.sg-season-background').count(), 0, 'disabling animations hides the Easter skin decoration');
    await egg.click();
    assert.equal(await page.evaluate(() => JSON.parse(localStorage.getItem('safeguard-easter')).pages.includes('/cmd/')), true,
      'Easter eggs remain available with skin animations off');
  }, { login: true, skin, finds: ['easter'] });

  for (const [skin, animations] of [['cibo', true], ['killy', false]]) await visit('2026-10-13', async page => {
    await page.route('**/megastructure.php*', route => new URL(route.request().url()).searchParams.get('witness') === 'birthday'
      ? route.fulfill({ json: { proof: proof('birthday') } }) : route.continue());
    await page.locator('.birthday-cake:not(.blown)').click();
    await page.waitForFunction(() => localStorage.getItem('safeguard-cake') === '2026');
    assert.equal(await page.locator('.birthday-cake.blown').count(), 1, 'the birthday cake remains clickable with skin animations ' + animations);
    assert.equal(await page.locator('.sg-season-background').count(), animations ? 1 : 0);
  }, { login: true, skin, finds: ['birthday'], animations });

  await visit('2026-12-31', async page => {
    await page.route('**/megastructure.php*', route => new URL(route.request().url()).searchParams.get('witness') === 'newyear'
      ? route.fulfill({ json: { proof: proof('newyear') } }) : route.continue());
    await page.getByRole('button', { name: 'Rakieta noworoczna: odpal' }).click();
    await page.waitForFunction(() => document.body.querySelectorAll(':scope > .sg-firework-trail').length > 0);
    await page.waitForFunction(() => JSON.parse(localStorage.getItem('safeguard'))?.sent?.newyear);
    assert.equal(await page.locator('.sg-fireworks').count(), 1, 'the event volley keeps the single skin background');
    assert.equal(await page.locator('.sg-snow').count(), 1);
  }, { login: true, skin: 'ayame', finds: ['newyear'] });

  await visit('2026-06-10', async page => {
    await page.goto(`${base}/account/`);
    for (const [skin, key, count] of [['echidna', 'butterflies', 4], ['marisa', 'magic', 14], ['reisen', 'lavender', 12], ['peko', 'rabbits', 2],
      ['cibo', 'netsphere', 15], ['killy', 'emitter', 9]]) {
      await chooseSkin(page, skin);
      const layer = page.locator('.sg-' + key);
      assert.equal(await layer.locator(key === 'netsphere' ? '.netsphere-node' : ':scope > i').count(), count, 'phones use fewer particles');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'the effect fits a phone');
      if (key === 'emitter') {
        assert.ok(await layer.locator('.emitter-dust').count() > 0);
        assert.ok(await layer.locator('.emitter-ember').count() > 0);
      }
      await page.emulateMedia({ reducedMotion: 'reduce' });
      assert.equal(await layer.evaluate(el => el.getAnimations({ subtree: true }).filter(a => a instanceof CSSAnimation).length), 0);
      await page.emulateMedia({ reducedMotion: 'no-preference' });
    }
  }, { login: true, skin: 'echidna', finds: ['halloween', 'newyear', 'easter', 'birthday'], viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });

  await visit('2026-06-10', async page => {
    await page.goto(`${base}/account/`);
    await chooseSkin(page, 'ayame');
    const layer = page.locator('.sg-fireworks');
    await layer.evaluate(el => { window.originalFireworks = el; });
    await chooseSkin(page, 'albedo');
    assert.equal(await layer.evaluate(el => el === window.originalFireworks), true, 'both New Year skins share one live firework layer');
    assert.equal(await layer.count(), 1);
    await page.waitForFunction(() => document.querySelector('.sg-fireworks').children.length > 1);
    const sparks = await layer.locator('.sg-spark').count();
    assert.ok(sparks >= 26 && sparks <= 29, 'phone bursts use fewer sparks');
    assert.equal(await layer.locator('.sg-spark').evaluateAll(bits => bits.every(bit =>
      ['rgb(233, 197, 107)', 'rgb(255, 240, 188)'].includes(getComputedStyle(bit).color))), true, 'all skin sparks are gold');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.waitForFunction(() => !document.querySelector('.sg-fireworks').children.length);
    assert.equal(await layer.evaluate(el => el.getAnimations({ subtree: true }).length), 0);
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.waitForFunction(() => document.querySelector('.sg-fireworks').children.length > 0);
    const toggle = page.getByRole('checkbox', { name: /Animacje skina/ });
    await toggle.uncheck();
    assert.equal(await page.locator('.sg-fireworks').count(), 0);
    await page.waitForTimeout(5500);
    assert.equal(await page.locator('.sg-spark').count(), 0, 'disabled backgrounds cancel launches and leave no stray sparks');
  }, { login: true, skin: 'ayame', finds: ['newyear'], viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });

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
          { sakura: 'sakura-fall', perseids: 'perseid-flight', fireflies: 'firefly-wander' }[event.key]);
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
    // Losing the last saved skin's find removes its background too.
    fixture('finds', all => { delete all[user]; });
    await page.goto(`${base}/`);
    await page.locator('.account-menu').waitFor();
    await page.waitForFunction(() => !document.documentElement.hasAttribute('data-skin'));
    assert.equal(await page.locator('.sg-season-background').count(), 0, 'an account reply removes a revoked skin background');
  }, { login: true, skin: 'march7', finds: ['sakura', 'perseids', 'fireflies'] });

  for (const [event, skin, find] of [[events[0], 'suisei', 'perseids'], [events[1], 'march7', 'sakura'], [events[2], 'suisei', 'perseids']]) {
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
    // Keep the trajectories reproducible while rotating the same live scene.
    await page.addInitScript(() => { Math.random = () => 0.5; });
    await page.reload();
    const flights = page.locator('.perseid-flight');
    const initial = await flights.evaluateAll(movers => movers.map(mover => {
      const animation = mover.getAnimations()[0];
      animation.pause();
      const timing = animation.effect.getTiming();
      animation.currentTime = timing.delay + timing.duration * 0.95;
      return { mover, animation };
    }).map(({ mover, animation }, i) => {
      window['seasonalFlight' + i] = { mover, animation };
      return mover.style.cssText;
    }));
    for (const viewport of [{ width: 390, height: 844 }, { width: 844, height: 390 }]) {
      await page.setViewportSize(viewport);
      await page.waitForFunction(({ width, height }) => innerWidth === width && innerHeight === height, viewport);
      // A resize event is delivered on the next rendering frame.
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      const positions = await flights.evaluateAll(movers => movers.map((mover, i) => {
        const saved = window['seasonalFlight' + i];
        const r = mover.querySelector('button').getBoundingClientRect();
        return { x: r.left + r.width / 2, y: r.top + r.height / 2,
          same: mover === saved.mover && mover.getAnimations()[0] === saved.animation,
          paused: saved.animation.playState === 'paused' };
      }));
      for (const position of positions) {
        assert.ok(position.x > 0 && position.x < viewport.width && position.y > 0 && position.y < viewport.height,
          `a meteor stays on screen near the end of its flight after rotation to ${viewport.width}x${viewport.height}: ${JSON.stringify(position)}`);
        assert.ok(position.same && position.paused, 'rotation preserves the particle and its paused animation');
      }
    }
    assert.deepEqual(await flights.evaluateAll(movers => movers.map(mover => mover.style.cssText)), initial,
      'rotating back restores the original trajectories without randomizing the shower');
    assert.deepEqual(calls, [], 'rotating the phone never awards a find');
  }, { viewport: { width: 844, height: 390 } });

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
    // A busy CI runner may deliver input well after the coordinates were read.
    await page.waitForTimeout(200);
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
    await page.waitForTimeout(200);
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
    ['2026-06-14', null], ['2026-06-15', 'fireflies'], ['2026-06-30', 'fireflies'], ['2026-07-01', null],
    ['2026-07-31', null], ['2026-08-01', 'perseids'], ['2026-08-15', 'perseids'], ['2026-08-16', null]
  ]) await visit(date, async (page, context, calls) => {
    assert.equal(await page.locator('.sg-season-background').count(), expected ? 1 : 0, 'the event boundary on ' + date);
    if (expected) assert.equal(await page.locator('.sg-' + expected).count(), 1);
    assert.deepEqual(calls, [], 'date changes alone never award a find');
  });
}
