// Register migration and synchronization races, with the shipped JS and real PHP.
import assert from 'node:assert/strict';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

export async function testSafeguardSync({ browser, base, site, php, errors }) {
  const id = '900000000000000004';
  const generated = spawnSync(php, ['-r', `
    define('SITE_DATA_DIR', $argv[1] . '/inc/data');
    require $argv[1] . '/inc/auth.php';
    require $argv[1] . '/inc/megastructure.php';
    $secret = 'browser-register-secret';
    $id = $argv[2];
    $start = (new DateTimeImmutable('yesterday 03:02', new DateTimeZone('Europe/Warsaw')))->getTimestamp();
    $finds = $proofs = $legacy = [];
    foreach (['builders', 'floor0', 'maze', 'paper', 'gene', 'admin'] as $i => $key) {
      $time = $start + 31 * $i;
      $finds[$key] = $time;
      $proofs[$key] = megaProof($secret, $key, $time, null, $id);
      $nonce = '0123456789abcdef';
      $legacy[$key] = $time . '.' . $nonce . '.' . substr(megaHash($secret, 'proof:' . $key . ':' . $time . ':' . $nonce), 0, 32);
    }
    echo json_encode(['finds' => $finds, 'proofs' => $proofs, 'legacy' => $legacy,
      'fragments' => explode('-', geneSequence($secret)),
      'foreign' => megaProof($secret, 'gene', $finds['gene'], null, '900000000000000001'),
      'later' => megaProof($secret, 'builders', $start + 200, null, $id),
      'laterFloor' => megaProof($secret, 'floor0', $start + 231, null, $id)]);
  `, site, id], { encoding: 'utf8', windowsHide: true });
  assert.equal(generated.status, 0, generated.stderr);
  const fixture = JSON.parse(generated.stdout);
  const beforeGene = Object.fromEntries(Object.entries(fixture.finds).slice(0, 4));
  const geneState = {
    finds: { gene: fixture.finds.gene }, proofs: { gene: fixture.legacy.gene }, sent: {}
  };
  const findsFile = path.join(site, 'inc/data/finds.json');

  async function withRegister(state, had, run, loggedIn = true) {
    const all = existsSync(findsFile) ? JSON.parse(readFileSync(findsFile, 'utf8')) : {};
    all[id] = had;
    writeFileSync(findsFile, JSON.stringify(all));
    const context = await browser.newContext();
    try {
      await context.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
      if (loggedIn) assert.equal((await context.request.get(`${base}/__login?id=${id}`)).status(), 200);
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.addInitScript(state => {
        if (!localStorage.getItem('safeguard')) localStorage.setItem('safeguard', JSON.stringify(state));
      }, state);
      await run(page, context);
    } finally {
      await context.close();
    }
  }

  // The response has already been computed by PHP when held() resolves.
  // This forces the browser to receive an older answer after a new discovery.
  async function hold(page, method) {
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    let reached;
    const ready = new Promise(resolve => { reached = resolve; });
    let first = true;
    await page.route('**/account.php*', async route => {
      const request = route.request();
      if (first && request.method() === method
          && (method === 'POST' || new URL(request.url()).searchParams.has('finds'))) {
        first = false;
        const response = await route.fetch();
        reached(await response.json());
        await gate;
        await route.fulfill({ response });
      } else await route.continue();
    });
    return {
      release,
      async held() {
        let timer;
        try {
          return await Promise.race([ready, new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error(`Register ${method} never arrived`)), 10000);
          })]);
        } finally { clearTimeout(timer); }
      }
    };
  }

  async function recorded(page, expected) {
    await page.waitForFunction(keys => {
      const state = JSON.parse(localStorage.getItem('safeguard'));
      return keys.every(key => state?.sent?.[key]);
    }, Object.keys(expected));
    await page.waitForLoadState('networkidle');
    assert.deepEqual(JSON.parse(readFileSync(findsFile, 'utf8'))[id], expected, 'the account keeps the original proof times');
    const state = await page.evaluate(() => JSON.parse(localStorage.getItem('safeguard')));
    assert.deepEqual(state.retryProofs || {}, {}, 'confirmed finds no longer keep replacement proofs');
  }

  // A fresh browser recovers fragments from the account, and stale local
  // fragments never reveal discoveries the account lacks.
  for (const keys of [Object.keys(beforeGene), ['builders', 'paper'], []]) {
    const had = Object.fromEntries(keys.map(key => [key, fixture.finds[key]]));
    await withRegister({}, had, async page => {
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await page.goto(`${base}/500.html`);
      if (keys.length !== 4) await page.evaluate(() => {
        localStorage.setItem('safeguard-gene', JSON.stringify({ 1: 'FFFF', 2: 'FFFF', 3: 'FFFF', 4: 'FFFF' }));
      });
      await page.keyboard.type('netsphere');
      const input = page.locator('.ns-box input');
      async function command(text) {
        await input.fill(text);
        await input.press('Enter');
      }
      async function fragments(expected) {
        await command('wyczysc');
        await command('gen');
        await page.waitForFunction(() => document.querySelectorAll('.ns-row').length === 4);
        assert.deepEqual(await page.locator('.ns-row span').allTextContents(), expected);
      }
      const expected = ['builders', 'floor0', 'maze', 'paper'].map((key, i) => keys.includes(key) ? fixture.fragments[i] : '????');
      await fragments(expected);
      // Every command reads the current account, even while the terminal stays open.
      const all = JSON.parse(readFileSync(findsFile, 'utf8'));
      all[id] = {};
      writeFileSync(findsFile, JSON.stringify(all));
      await fragments(['????', '????', '????', '????']);
      await page.route('**/megastructure.php?fragments=*', route => route.abort('failed'));
      await command('wyczysc');
      await command('gen');
      await page.waitForFunction(() => document.querySelector('.ns-log').textContent.includes('Rejestr Safeguarda nie odpowiada.'));
      assert.equal(await page.locator('.ns-row').count(), 0, 'a failed request does not fall back to stale local fragments');
    });
  }

  // A replacement collected while logged out must survive navigation and login.
  await withRegister(geneState, beforeGene, async (page, context) => {
    await page.goto(`${base}/500.html`);
    const anonymous = page.waitForResponse(response => new URL(response.url()).searchParams.has('finds'));
    await page.evaluate(proof => {
      SanakanSafeguard.found('gene', proof);
      SanakanSafeguard.found('gene', proof);
    }, fixture.proofs.gene);
    assert.equal((await (await anonymous).json()).login, false);
    await page.waitForLoadState('networkidle');
    await context.request.get(`${base}/__login?id=${id}`);
    await page.reload();
    await recorded(page, { ...beforeGene, gene: fixture.finds.gene });
  }, false);

  // Several candidates may arrive while the old proof's rejection is in flight.
  await withRegister(geneState, beforeGene, async page => {
    const blocked = await hold(page, 'POST');
    try {
      await page.goto(`${base}/500.html`);
      assert.deepEqual((await blocked.held()).rejected, ['gene']);
      await page.evaluate(({ foreign, proofs }) => {
        SanakanSafeguard.found('gene', foreign);
        SanakanSafeguard.found('gene', proofs.gene);
        SanakanSafeguard.found('gene', proofs.gene);
      }, fixture);
    } finally { blocked.release(); }
    await recorded(page, { ...beforeGene, gene: fixture.finds.gene });
  });

  // Recollecting a valid prerequisite must keep its earliest proof: replacing
  // builders with the later one would put every following step out of order.
  await withRegister({ finds: fixture.finds, proofs: fixture.proofs, sent: {} }, {}, async page => {
    const blocked = await hold(page, 'GET');
    try {
      await page.goto(`${base}/500.html`);
      await blocked.held();
      await page.evaluate(proof => SanakanSafeguard.found('builders', proof), fixture.later);
    } finally { blocked.release(); }
    await recorded(page, fixture.finds);
  });

  // A rejected old prerequisite must not discard valid following steps while
  // its replacement is still waiting to be sent.
  await withRegister({ finds: fixture.finds, proofs: { ...fixture.proofs, builders: fixture.legacy.builders }, sent: {} }, {}, async page => {
    const blocked = await hold(page, 'POST');
    try {
      await page.goto(`${base}/500.html`);
      assert.ok((await blocked.held()).rejected.includes('floor0'));
      await page.evaluate(({ proofs, later, laterFloor }) => {
        SanakanSafeguard.found('builders', proofs.builders);
        SanakanSafeguard.found('builders', later);
        SanakanSafeguard.found('floor0', laterFloor);
      }, fixture);
    } finally { blocked.release(); }
    await recorded(page, fixture.finds);
  });

  // Another tab can replace the stored proof after this tab sent its request.
  await withRegister(geneState, beforeGene, async (page, context) => {
    const blocked = await hold(page, 'POST');
    try {
      await page.goto(`${base}/500.html`);
      await blocked.held();
      const tab = await context.newPage();
      await tab.route('**/register-storage-test', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html>' }));
      await tab.goto(`${base}/register-storage-test`);
      await tab.evaluate(proof => {
        const state = JSON.parse(localStorage.getItem('safeguard'));
        state.proofs.gene = proof;
        localStorage.setItem('safeguard', JSON.stringify(state));
      }, fixture.proofs.gene);
      await tab.close();
    } finally { blocked.release(); }
    await recorded(page, { ...beforeGene, gene: fixture.finds.gene });
  });

  // A rejection with no replacement still removes the invalid entry and stops.
  await withRegister(geneState, beforeGene, async page => {
    let posts = 0;
    page.on('request', request => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/account.php') posts++; });
    await page.goto(`${base}/500.html`);
    await page.waitForFunction(() => {
      const state = JSON.parse(localStorage.getItem('safeguard'));
      return !state.finds.gene && !state.proofs.gene;
    });
    await page.waitForLoadState('networkidle');
    assert.equal(posts, 1, 'an invalid proof without replacements does not retry indefinitely');
    assert.deepEqual(JSON.parse(readFileSync(findsFile, 'utf8'))[id], beforeGene);
  });
}
