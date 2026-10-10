// The shared code of the croppers that runs without a page: which blob:
// addresses an app lets go of, and how much the renderer keeps decoded.
//   node --test apps/shared/test/
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { ownedUrls } from '../lib/ownedUrls.js';

// what URL.revokeObjectURL was called with
const revoked = [];
URL.revokeObjectURL = (url) => revoked.push(url);

test('only the addresses the app made are let go', () => {
  revoked.length = 0;
  const released = [];
  const urls = ownedUrls((url) => released.push(url));
  const mine = urls.own('blob:a');
  urls.release('https://sanakan.pl/i/x.webp');
  urls.release('data:image/png;base64,AA');
  urls.release(mine);
  urls.release(mine);
  assert.deepEqual(revoked, ['blob:a'], 'a link or data: is not the app\'s, and one goes once');
  assert.deepEqual(released, ['blob:a'], 'the renderer hears of it');
});

test('replace lets the old one go, not the same one twice', () => {
  revoked.length = 0;
  const urls = ownedUrls();
  let shown = urls.own('blob:1');
  shown = urls.replace(shown, urls.own('blob:2'));
  shown = urls.replace(shown, shown);
  assert.equal(shown, 'blob:2');
  assert.deepEqual(revoked, ['blob:1']);
  urls.releaseAll();
  assert.deepEqual(revoked, ['blob:1', 'blob:2']);
  assert.equal(urls.size, 0, 'nothing left after the app goes');
});

// a page without a browser: every "picture" is $side × $side pixels
function fakeBrowser(sides) {
  globalThis.fetch = async (url) => ({ ok: true, blob: async () => url });
  globalThis.createImageBitmap = async (url) => {
    const side = sides[url] ?? 2;
    return { width: side, height: side, close() {} };
  };
  globalThis.ImageData = class {
    constructor(width, height) {
      this.width = width;
      this.height = height;
      this.data = new Uint8ClampedArray(width * height * 4);
    }
  };
  globalThis.OffscreenCanvas = class {
    constructor(width, height) {
      this.width = width;
      this.height = height;
    }
    getContext() {
      const canvas = this;
      return {
        canvas,
        drawImage() {},
        getImageData: (x, y, w, h) => ({ width: w, height: h, data: new Uint8ClampedArray(w * h * 4) }),
      };
    }
  };
}

test('the renderer keeps few pictures and few bytes decoded', async () => {
  // 6000 × 6000 is 144 MB of pixels: two of them are over the 256 MB
  fakeBrowser({ 'big:1': 6000, 'big:2': 6000 });
  const { renderCrop, cachedPictures, forgetPixels } = await import('../lib/render.js');
  const job = (src) => renderCrop({ src, percent: { x: 0, y: 0, width: 100, height: 100 }, width: 2, height: 2 });

  for (const src of ['a', 'b', 'c', 'd', 'e', 'f'])
    await job(src);
  assert.equal(cachedPictures().count, 4, 'four pictures at most');

  forgetPixels();
  await job('big:1');
  await job('big:2');
  const { count, bytes } = cachedPictures();
  assert.equal(count, 1, 'the older big one went');
  assert.ok(bytes <= 256 * 1024 * 1024);

  forgetPixels('big:2');
  assert.deepEqual(cachedPictures(), { count: 0, bytes: 0 }, 'a picture let go leaves no pixels behind');
});
