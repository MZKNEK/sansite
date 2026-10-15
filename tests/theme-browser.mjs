// Theme regressions use real computed colours, including inherited surfaces,
// native options, modal content and the production builds of both croppers.
import assert from 'node:assert/strict';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
const originalDarkPalettes = JSON.parse(readFileSync(new URL('./theme-colors.json', import.meta.url), 'utf8'));

async function preview(page, skin) {
  await page.evaluate(async skin => {
    SanakanAppearance.preview(skin);
    // Wait for the actual CSS transitions, including a delayed first frame.
    await Promise.all(document.getAnimations().filter(animation => animation instanceof CSSTransition).map(animation => animation.finished.catch(() => {})));
  }, skin);
}

async function contrast(page, selector, minimum = 4.5, property = 'color') {
  const results = await page.locator(selector).evaluateAll((elements, property) => {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const rgba = value => {
      ctx.clearRect(0, 0, 1, 1);
      ctx.fillStyle = value;
      ctx.fillRect(0, 0, 1, 1);
      return [...ctx.getImageData(0, 0, 1, 1).data].map((v, i) => i === 3 ? v / 255 : v);
    };
    const over = (fg, bg) => fg.slice(0, 3).map((v, i) => v * fg[3] + bg[i] * (1 - fg[3])).concat(1);
    const lum = rgb => {
      const c = rgb.slice(0, 3).map(v => v / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
      return .2126 * c[0] + .7152 * c[1] + .0722 * c[2];
    };
    return elements.filter(e => e.getClientRects().length || e.tagName === 'OPTION').slice(0, 12).map(e => {
      // Check both ends of the page gradient. Other actual surfaces are
      // composited over them, including HUD fills and modal backgrounds.
      const root = getComputedStyle(document.documentElement);
      const ratios = ['--bg-dark', '--bg-mid'].map(key => {
        let bg = rgba(root.getPropertyValue(key));
        const ancestors = [];
        for (let a = e; a; a = a.parentElement) ancestors.unshift(a);
        for (const a of ancestors) bg = over(rgba(getComputedStyle(a).backgroundColor), bg);
        const fg = over(rgba(getComputedStyle(e)[property]), bg);
        const a = lum(fg), b = lum(bg);
        return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
      });
      return { text: (e.textContent || e.getAttribute('aria-label') || e.placeholder || e.tagName).trim().slice(0, 50), ratio: Math.min(...ratios) };
    });
  }, property);
  assert.ok(results.length, `No visible theme check for ${selector} on ${page.url()}`);
  for (const result of results) assert.ok(result.ratio >= minimum, `${selector}: ${result.text}, contrast ${result.ratio.toFixed(2)} < ${minimum} on ${page.url()}`);
}

export async function testTheme({ browser, base, site, errors }) {
  const owner = '900000000000000001';
  const prefs = path.join(site, 'inc/data/skins.json');
  const setSkin = skin => {
    const all = existsSync(prefs) ? JSON.parse(readFileSync(prefs, 'utf8')) : {};
    all[owner] = skin;
    writeFileSync(prefs, JSON.stringify(all));
  };
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
  try {
    await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    const login = await (await context.request.get(`${base}/__login?id=${owner}`)).json();
    setSkin('echidna');
    await context.request.get(`${base}/account.php`);
    writeFileSync(path.join(site, 'inc/data/bot-commands.json'), JSON.stringify({
      prefix: 's.', modules: [{ name: 'Zabawy', subModules: [{ prefix: '', commands: [{
        name: 'drobne', description: 'dodaje dzienną dawkę drobniaków do twojego portfela',
        aliases: ['drobne', 'daily'], attributes: [{ name: 'user', description: 'nazwa użytkownika' }], example: ''
      }] }] }]
    }));
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => localStorage.setItem('safeguard-date', '2026-07-15'));
    await page.route('**/status.php', route => route.fulfill({ json: { status: 'online', uptime: 100, down: '', issues: '' } }));
    await page.route('**/palette.php', route => route.fulfill({ json: { commands: [], versions: [] } }));
    await page.route('**/read_swagger.php', route => route.fulfill({ json: {
      openapi: '3.0.0', info: { title: 'Theme test', version: '1.0', description: 'Local documentation fixture.' },
      paths: {
        '/debug/kill': { post: { tags: ['Debug'], summary: 'Stop the bot', responses: { 200: { description: 'OK' } } } },
        '/health': { get: { tags: ['Health'], summary: 'Bot health', responses: { 200: { description: 'OK' } } } },
        '/theme': Object.fromEntries(['get', 'put', 'patch', 'delete', 'options', 'head'].map(method => [method, {
          tags: ['Theme'], summary: 'Theme endpoint', parameters: [{ name: 'q', in: 'query', schema: { type: 'string' } }], responses: { 200: { description: 'OK' } }
        }]))
      },
      components: { schemas: { Theme: { type: 'object', properties: { name: { type: 'string' } } } } }
    } }));

    await page.goto(base + '/');
    await page.locator('.account-menu').waitFor();
    const skins = await page.evaluate(() => ['', ...Object.keys(SanakanSkinCatalog).filter(id => id !== 'custom')]);
    await preview(page, '');
    assert.deepEqual(await page.evaluate(() => ({
      title: getComputedStyle(document.querySelector('.hud-title')).color,
      tag: getComputedStyle(document.querySelector('.tag')).color,
      kana: getComputedStyle(document.querySelector('.kana')).color,
      corners: getComputedStyle(document.querySelector('.links a')).getPropertyValue('--corner').trim(),
      menu: getComputedStyle(document.querySelector('.account-drop')).backgroundColor,
      online: getComputedStyle(document.querySelector('.bot-status')).backgroundColor
    })), {
      title: 'rgb(239, 226, 247)', tag: 'rgb(182, 112, 211)', kana: 'rgb(155, 89, 182)',
      corners: 'rgba(182, 112, 211, 0.9)', menu: 'rgb(24, 24, 30)', online: 'rgb(35, 165, 90)'
    }, 'the default HUD retains its original purple colours');
    for (const skin of skins) {
      await preview(page, skin);
      if (originalDarkPalettes[skin]) {
        const expected = originalDarkPalettes[skin];
        assert.deepEqual(await page.evaluate(keys => {
          const style = getComputedStyle(document.documentElement);
          return Object.fromEntries(keys.map(key => [key, style.getPropertyValue(key).trim()]));
        }, Object.keys(expected)), expected, `${skin} retains its palette from before the shared theme refactor`);
        const hex = expected['--accent-light'];
        const accent = `rgb(${[1, 3, 5].map(offset => parseInt(hex.slice(offset, offset + 2), 16)).join(', ')})`;
        assert.equal(await page.locator('.tag').evaluate(e => getComputedStyle(e).color), accent, `${skin} keeps its own accent without greying it`);
      }
      await contrast(page, '.links a:not(.swap-alt), .hud-title, .tag');
      await page.locator('.account-toggle').click();
      await contrast(page, '.account-drop a');
      await page.keyboard.press('Escape');
      // A hover must not turn readable dark text white on a light fill.
      await page.locator('.links a').first().hover();
      await page.waitForTimeout(220);
      await contrast(page, '.links a:hover');
      await page.mouse.move(0, 0);
    }
    await page.keyboard.press('Control+k');
    await page.locator('.pl-list b').first().waitFor();
    const overlayColors = {};
    for (const skin of skins) {
      await preview(page, skin);
      await page.locator('.pl-list b').first().waitFor();
      await page.waitForTimeout(220);
      await contrast(page, '.pl-list b');
      const panel = await page.locator('.pl-box').evaluate(e => {
        const c = document.createElement('canvas').getContext('2d');
        c.fillStyle = getComputedStyle(e).backgroundColor;
        c.fillRect(0, 0, 1, 1);
        return [...c.getImageData(0, 0, 1, 1).data];
      });
      overlayColors[skin] = await page.locator('.pl-box').evaluate(e => getComputedStyle(e).backgroundColor);
      if (skin === 'echidna') assert.ok(Math.min(...panel.slice(0, 3)) > 200, 'Echidna keeps a light search panel');
      else assert.ok(Math.max(...panel.slice(0, 3)) < 64 && (panel[0] * .2126 + panel[1] * .7152 + panel[2] * .0722) < 40, `${skin || 'default'} has a deep, tinted search panel: ${panel}`);
    }
    await page.keyboard.press('Escape');
    await page.keyboard.type('netsphere');
    await page.locator('.ns-prompt input').waitFor();
    for (const skin of skins) {
      await preview(page, skin);
      await page.waitForTimeout(220);
      assert.equal(await page.locator('.ns-box').evaluate(e => getComputedStyle(e).backgroundColor), overlayColors[skin], `${skin || 'default'}: console and Ctrl+K share their panel colour`);
      await contrast(page, '.ns-title, .ns-label, .ns-result, .ns-prompt input, .ns-out:not(.dim), .ns-close');
    }
    await page.keyboard.press('Escape');

    await page.goto(base + '/cmd/');
    await page.locator('.cmd-desc').first().waitFor();
    for (const skin of skins.filter(id => id !== 'echidna')) {
      await preview(page, skin);
      await page.waitForTimeout(220);
      const greyText = await page.evaluate(() => Object.fromEntries([
        ['description', '.cmd-desc'], ['parameter', '.cmd-params li'], ['copy', '.cmd-example .copy']
      ].map(([name, selector]) => {
        const element = document.querySelector(selector);
        if (!element) return [name, `Missing ${selector}`];
        // Chromium can serialize an ending colour transition as oklab even
        // when its pixels already equal the final rgba colour.
        const canvas = document.createElement('canvas').getContext('2d');
        canvas.fillStyle = getComputedStyle(element).color;
        canvas.fillRect(0, 0, 1, 1);
        return [name, [...canvas.getImageData(0, 0, 1, 1).data]];
      })));
      for (const [name, alpha] of [['description', 217], ['parameter', 179], ['copy', 153]]) {
        const expected = [220, 221, 222, alpha];
        // Translucent canvas pixels are rounded when unpremultiplied.
        assert.ok(Array.isArray(greyText[name]) && greyText[name].every((value, i) => Math.abs(value - expected[i]) <= 1), `${skin || 'default'} keeps its original grey ${name}: ${greyText[name]}`);
      }
    }
    await preview(page, 'echidna');
    await contrast(page, '.cmd-desc, .cmd-params li');
    await contrast(page, '.cmd-aliases code');
    for (const skin of ['', 'miku', 'ayame', 'echidna']) {
      await preview(page, skin);
      assert.equal(await page.locator('.toolbar').evaluate(e => getComputedStyle(e).backgroundColor), overlayColors[skin], 'command search toolbar shares the Ctrl+K surface');
      await contrast(page, '.search input');
    }

    for (const url of ['/account/', '/admin/', '/hunters/', '/state/', '/state/versions/', '/cmd/', '/cmd/changes/', '/privacy/', '/401.html', '/403.html', '/404.html', '/500.html']) {
      await page.goto(base + url);
      assert.equal(await page.locator('html').getAttribute('data-skin'), 'echidna', url);
      await contrast(page, '.hud-title');
      assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme), 'light', url);
      await page.setViewportSize({ width: 390, height: 844 });
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Mobile overflow: ${url}`);
      await page.setViewportSize({ width: 1280, height: 900 });
    }
    await page.goto(base + '/account/');
    await contrast(page, '.hud-select option');
    await contrast(page, '[data-skin-open]');
    await page.locator('[data-skin-open]').click();
    await contrast(page, '.skin-dialog h2, .skin-tile:not(:disabled) > b');
    await page.keyboard.press('Escape');
    await page.goto(base + '/admin/');
    const qualityCard = page.locator('.card').filter({ has: page.locator('.quality-form') });
    if ((await qualityCard.getAttribute('class')).includes('collapsed')) await qualityCard.locator('h2').click();
    await contrast(page, '.quality-form input[type=number]');
    await page.locator('.quality-form input[type=number]').first().focus();
    await page.waitForTimeout(220);
    await contrast(page, '.quality-form input[type=number]:focus', 3, 'borderTopColor');

    await page.goto(`${base}/i/?p=${encodeURIComponent(login.own + '/A')}`);
    await page.locator('.tile[data-rel]').first().click();
    await contrast(page, '.viewer-name, .viewer-details, .viewer-nav');
    await page.keyboard.press('Escape');
    await page.locator('#act-mkdir').click();
    await contrast(page, '.ex-dialog[open] h2, .ex-dialog[open] p, .ex-dialog[open] input');
    await page.keyboard.press('Escape');

    for (const skin of ['', 'echidna']) {
      await preview(page, skin);
      await page.setViewportSize({ width: 320, height: 844 });
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'gallery search fits a 320px screen');
    }
    await page.setViewportSize({ width: 1280, height: 900 });

    // Files without thumbnails keep dark media surfaces even in a light skin.
    writeFileSync(path.join(site, `i/users/${owner}-User/A/readme.txt`), 'Theme fixture');
    await page.goto(`${base}/i/?p=${encodeURIComponent(login.own + '/A')}&q=readme`);
    for (const skin of ['', 'miku', 'echidna']) {
      await preview(page, skin);
      await contrast(page, '.placeholder');
      await contrast(page, '.placeholder b');
    }
    await contrast(page, '.where');

    await page.goto(base + '/api/');
    await page.locator('.opblock-get .opblock-summary').last().click();
    await contrast(page, '.opblock-tag, .opblock-summary-path, .api-toolbar input, .swagger-ui input');
    await page.locator('#api-search').fill('theme');
    assert.equal(await page.locator('#api-search').inputValue(), 'theme');
    await contrast(page, '#api-search');
    await page.locator('#api-search').fill('');
    for (const skin of skins) {
      await preview(page, skin);
      assert.equal(await page.locator('.api-toolbar').evaluate(e => getComputedStyle(e).backgroundColor), overlayColors[skin], 'API search toolbar shares the Ctrl+K surface');
      await contrast(page, '.opblock-tag, .opblock-summary-path, .api-toolbar input, .swagger-ui input, .opblock-summary-method');
      const debug = page.locator('.opblock-tag').filter({ hasText: 'Debug' });
      await debug.locator('a').hover();
      await page.waitForTimeout(220);
      assert.ok(await debug.evaluate(e => {
        const style = getComputedStyle(e);
        const probe = document.createElement('span');
        probe.style.color = 'var(--link)';
        e.append(probe);
        const expected = getComputedStyle(probe).color;
        probe.remove();
        const canvas = document.createElement('canvas').getContext('2d');
        canvas.fillStyle = style.backgroundColor;
        canvas.fillRect(0, 0, 1, 1);
        const alpha = canvas.getImageData(0, 0, 1, 1).data[3] / 255;
        return alpha > 0 && alpha < .15 && style.color === expected && getComputedStyle(e.querySelector('a')).color === expected;
      }), `${skin || 'default'}: hovered Debug stays accented on a subtle translucent fill`);
      await contrast(page, '.opblock-tag:hover a');
      await page.mouse.move(0, 0);
    }
    const methodTones = await page.locator('.opblock').evaluateAll(elements => Object.fromEntries(elements.map(e => {
      const style = getComputedStyle(e);
      return [e.querySelector('.opblock-summary-method').textContent.toLowerCase(), style.getPropertyValue('--api-method').trim()];
    })));
    await page.goto(base + '/state/');
    for (const skin of ['', 'miku', 'tomie', 'echidna']) {
      await preview(page, skin);
      for (const [status, method, tone] of [['ok', 'post', 'success'], ['warn', 'put', 'warning'], ['fail', 'delete', 'danger'], ['planned', 'get', 'info']]) {
        const result = await page.locator(`.timeline-legend .${status}`).evaluate((e, tone) => {
          const probe = document.createElement('span');
          probe.style.backgroundColor = `color-mix(in srgb, var(--${tone}-tone) 90%, transparent)`;
          e.append(probe);
          const expected = getComputedStyle(probe).backgroundColor;
          probe.remove();
          return { actual: getComputedStyle(e).backgroundColor, expected, tone: getComputedStyle(e).getPropertyValue(`--${tone}-tone`).trim() };
        }, tone);
        assert.equal(result.actual, result.expected, `${skin || 'default'}: ${status} bar uses a muted fill`);
        assert.equal(result.tone, methodTones[method], `${status} status and ${method} method share one colour`);
      }
    }

    await page.goto(base + '/404.html');
    await page.locator('.mega-you').waitFor();
    for (const skin of skins) {
      await preview(page, skin);
      if (skin !== 'echidna') {
        assert.ok(await page.locator('.mega-map').evaluate(e => getComputedStyle(e).color === `rgba(${getComputedStyle(e).getPropertyValue('--accent-light-rgb').trim()}, 0.55)`), `${skin || 'default'}: the maze keeps its dim skin accent`);
      }
      await contrast(page, '.mega-you, .mega-wall, .mega-where, .mega-note');
    }
    await preview(page, '');
    assert.equal(await page.locator('.mega-map').evaluate(e => getComputedStyle(e).backgroundColor), 'color(srgb 0.0313726 0.0352941 0.0352941 / 0.6)');
    assert.equal(await page.locator('.mega-note').evaluate(e => getComputedStyle(e).color), 'color(srgb 0.941176 0.768627 0.760784 / 0.8)');

    // Render the actual interactive floor-zero maze, including explored
    // cells and its network-coloured terminal, from a local server fixture.
    await page.route('**/megastructure.php?*', route => {
      const query = new URL(route.request().url()).searchParams;
      return route.fulfill({ json: query.has('at') ? { zero: {
        fragment: 'ABCD', proof: 'theme-proof', maze: { width: 9, height: 3, start: [1, 1], sight: {
          x: 0, y: 0, rows: ['#########', '#       #', '#########'], end: [7, 1]
        } }
      } } : {} });
    });
    await page.goto(base + '/404.html');
    await page.locator('.mega-maze .mega-dim').first().waitFor();
    await preview(page, '');
    assert.equal(await page.locator('.mega-dim').first().evaluate(e => getComputedStyle(e).color), 'rgba(182, 112, 211, 0.2)');
    const position = await page.locator('.mega-map').textContent();
    await page.keyboard.press('ArrowRight');
    assert.equal((await page.locator('.mega-map').textContent()).indexOf('@'), position.indexOf('@') + 1, 'the recoloured maze still moves');
    for (const skin of ['', 'miku', 'echidna']) {
      await preview(page, skin);
      await contrast(page, '.mega-you, .mega-end');
    }

    await page.addInitScript(() => {
      if (location.pathname !== '/403.html') return;
      const state = JSON.parse(localStorage.getItem('safeguard') || '{}');
      state.finds = { ...state.finds, gene: 1 };
      localStorage.setItem('safeguard', JSON.stringify(state));
    });
    await page.goto(base + '/403.html');
    await page.locator('#gene.gene').waitFor();
    await preview(page, '');
    assert.equal(await page.locator('#gene').evaluate(e => getComputedStyle(e).color), 'rgb(79, 209, 197)', 'recognised gene uses network green');
    await preview(page, 'echidna');
    await contrast(page, '#gene');

    await page.goto(base + '/500.html');
    await page.locator('#failure-mark').click();
    const snakePixel = (x, y) => page.locator('.snake canvas').evaluate((e, [x, y]) => [...e.getContext('2d').getImageData(Math.floor(e.width * x / 20), Math.floor(e.height * y / 16), 1, 1).data], [x, y]);
    await preview(page, '');
    assert.deepEqual((await snakePixel(6.5, 8.5)).slice(0, 3), [255, 255, 255], 'original white snake head');
    assert.deepEqual((await snakePixel(5.5, 8.5)).slice(0, 3), [240, 163, 94], 'original amber snake body');
    for (const skin of ['', 'miku', 'tomie', 'echidna']) {
      await preview(page, skin);
      await contrast(page, '.snake h2, .snake-score b');
    }
    assert.deepEqual((await snakePixel(6.5, 8.5)).slice(0, 3), [27, 26, 32], 'light skin redraws the ready snake head with dark ink');
    await page.evaluate(() => document.documentElement.style.setProperty('--snake-body', '#3377cc'));
    await page.waitForTimeout(30);
    assert.ok((await snakePixel(5.5, 8.5)).slice(0, 3).every((value, i) => Math.abs(value - [51, 119, 204][i]) <= 1), 'one palette change repaints the translucent snake while the game is idle');
    await page.evaluate(() => document.documentElement.style.removeProperty('--snake-body'));
    await page.keyboard.press('Escape');
    await preview(page, 'miku');
    assert.equal(await page.locator('.snake').count(), 0, 'closing the game removes its colour observer');

    for (const app of ['skalpel', 'uskalpel']) {
      await page.goto(`${base}/${app}/`);
      await page.locator('.sel-btn').first().waitFor();
      assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme), 'light', app);
      await contrast(page, '.hud-title, .field > .label, .sel-value, .link-field input');
      await contrast(page, '.sel-pos');
      await page.locator('.sel-btn').first().click();
      await contrast(page, '.sel-opt');
      await contrast(page, '.sel-opt i');
      await page.keyboard.press('ArrowDown');
      await page.keyboard.press('Enter');
      assert.equal(await page.locator('.sel-panel').count(), 0, 'cropper list still chooses with the keyboard');
      await page.locator('.sel-btn').first().focus();
      await contrast(page, '.sel-btn:focus', 3, 'outlineColor');
      for (const skin of ['miku', 'tomie', 'echidna']) {
        await preview(page, skin);
        await contrast(page, '.hud-title, .sel-value, .link-field input');
      }
      await page.setViewportSize({ width: 390, height: 844 });
      await page.waitForTimeout(300);
      const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(e => e.getBoundingClientRect().right > innerWidth + 1 && getComputedStyle(e).position !== 'absolute').slice(0, 8).map(e => `${e.tagName}.${e.className}: ${e.getBoundingClientRect().right}`));
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${app}: ${overflow.join(', ')}`);
      await page.setViewportSize({ width: 1280, height: 900 });
      const galleryButton = page.locator('.btn-pick');
      await galleryButton.focus();
      await page.keyboard.press('Enter');
      await page.locator('.pick-item span').first().waitFor();
      const galleryDialog = page.getByRole('dialog', { name: 'Obrazek z mojej galerii' });
      assert.ok(await galleryDialog.evaluate(e => e.contains(document.activeElement)), `${app}: opening the gallery moves focus inside`);
      await page.locator('.pick-item').last().focus();
      await page.keyboard.press('Tab');
      assert.ok(await galleryDialog.evaluate(e => e.contains(document.activeElement)), `${app}: Tab stays inside the gallery`);
      await page.locator('.pick-close').focus();
      await page.keyboard.press('Shift+Tab');
      assert.ok(await galleryDialog.evaluate(e => e.contains(document.activeElement)), `${app}: Shift+Tab stays inside the gallery`);
      await contrast(page, '.pick-head > span, .pick-item span, .pick-close');
      await page.locator('.pick-item').first().hover();
      await page.waitForTimeout(220);
      await contrast(page, '.pick-item:hover span');
      await page.keyboard.press('Escape');
      assert.equal(await galleryDialog.count(), 0, `${app}: Escape closes the gallery`);
      assert.ok(await galleryButton.evaluate(e => e === document.activeElement), `${app}: closing the gallery restores focus`);
      await page.getByText('Tryb edycji', { exact: true }).click();
      await contrast(page, '.btn-save, .btn-go');
    }

    await page.goto(base + '/');
    await page.locator('.account-menu').waitFor();
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('sanakan:pong')));
    for (const [skin, expected] of [['', [232, 98, 98]], ['echidna', [169, 44, 47]]]) {
      await preview(page, skin);
      // The game's next frame must use the current scheme, including a
      // light-dark() danger colour that canvas cannot resolve on its own.
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      const paddles = await page.locator('.pp-table').evaluate(canvas => {
        const ctx = canvas.getContext('2d');
        return [17, 463].map(x => {
          const pixels = ctx.getImageData(Math.floor(x * canvas.width / 480), 0, 1, canvas.height).data;
          const colors = [];
          for (let y = 0; y < canvas.height; y++) {
            if (pixels[y * 4 + 3] === 255) colors.push([...pixels.slice(y * 4, y * 4 + 3)]);
          }
          return colors;
        });
      });
      assert.ok(paddles[1].some(rgb => rgb.every((v, i) => v === expected[i])), `${skin || 'default'}: opponent paddle has the resolved danger colour`);
      assert.ok(!paddles[0].some(rgb => rgb.every((v, i) => v === expected[i])), 'the two paddles stay distinct');
    }
    await page.keyboard.press('Escape');

    // Light custom designs require an explicit base. Background colours alone
    // must not change the dark styles inherited by a legacy design.
    await page.goto(base + '/account/');
    const colors = { accent: '#426c47', 'accent-light': '#9cb8a2', 'accent-dark': '#29432c', 'bg-dark': '#e7e3ea', 'bg-mid': '#f8f6fa', text: '#1b1a20', 'home-title': '#232721' };
    await page.evaluate(colors => SanakanAppearance.preview('custom', { colors, image: '' }), colors);
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme), 'dark');
    await page.evaluate(colors => SanakanAppearance.preview('custom', { colors, image: '', base: 'light' }), colors);
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme), 'light');
    await contrast(page, '.hud-select option, [data-skin-open]');
    await page.locator('.skin-form button[type=submit]').hover();
    await page.waitForTimeout(220);
    await contrast(page, '.skin-form button[type=submit]:hover');
    await preview(page, 'miku');
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme), 'dark');
    assert.equal(await page.locator('html').getAttribute('data-skin-base'), null);

    await page.goto(base + '/offline.html');
    await contrast(page, 'p:not(.note), dt, dd:not(.dim)');
    assert.equal(await page.evaluate(() => document.styleSheets.length), 1, 'offline has its own embedded palette');
    console.log(`Theme OK: ${skins.length} palettes, shared pages, dialogs, forms, API, offline and both production croppers.`);
  } finally {
    await context.close();
  }
}
