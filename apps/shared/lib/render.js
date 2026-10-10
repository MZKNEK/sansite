// Renders the crop of the card: the same code makes the real preview and the
// saved file, in a worker (render.worker.js) or, without one, on the page.
import { resample } from './resample.js';

const makeCanvas = (w, h) => {
  if (typeof OffscreenCanvas !== 'undefined') return new OffscreenCanvas(w, h);
  const c = document.createElement('canvas');
  c.width = w;
  c.height = h;
  return c;
};

// The last pictures stay decoded, so moving the crop does not load them again:
// at most CACHE_PICTURES of them and CACHE_BYTES of pixels in all, the one used
// longest ago going first (a photo of 24 MP alone takes 96 MB).
const CACHE_PICTURES = 4;
const CACHE_BYTES = 256 * 1024 * 1024;
const cache = new Map();
let cachedBytes = 0;

function forget(url) {
  const entry = cache.get(url);
  if (!entry) return;
  cachedBytes -= entry.bytes;
  cache.delete(url);
}

// drops the oldest until the cache fits; the one asked for now stays
function trim(keep) {
  for (const url of [...cache.keys()]) {
    if (cache.size <= CACHE_PICTURES && cachedBytes <= CACHE_BYTES) return;
    if (url !== keep) forget(url);
  }
}

function pixelsOf(url) {
  let entry = cache.get(url);
  if (entry) {
    // used now: the newest again
    cache.delete(url);
    cache.set(url, entry);
    return entry.job;
  }
  entry = { bytes: 0 };
  entry.job = (async () => {
    const res = await fetch(url, { mode: 'cors' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const bitmap = await createImageBitmap(await res.blob());
    const g = makeCanvas(bitmap.width, bitmap.height).getContext('2d', { willReadFrequently: true });
    g.drawImage(bitmap, 0, 0);
    bitmap.close();
    return g.getImageData(0, 0, g.canvas.width, g.canvas.height);
  })();
  cache.set(url, entry);
  entry.job.then((data) => {
    if (cache.get(url) !== entry) return;
    entry.bytes = data.data.length;
    cachedBytes += entry.bytes;
    trim(url);
  }, () => forget(url));
  trim(url);
  return entry.job;
}

// how many pictures the cache holds and their bytes (for the tests)
export function cachedPictures() {
  return { count: cache.size, bytes: cachedBytes };
}

// the pixels of a picture no longer shown (its blob: address was let go), or all
export function forgetPixels(url = null) {
  if (url === null) {
    cache.clear();
    cachedBytes = 0;
  } else {
    forget(url);
  }
}

/**
 * @param {{src: string, percent: {x: number, y: number, width: number, height: number},
 *   width: number, height: number, mask?: string, sharpen?: number}} job
 *   percent: the crop from svelte-easy-crop in % of the picture (not rounded,
 *   unlike its pixels); mask: picture whose alpha cuts the result
 * @returns {Promise<ImageData>}
 */
export async function renderCrop({ src, percent, width, height, mask = null, sharpen = 0 }) {
  const pixels = await pixelsOf(src);
  const W = pixels.width, H = pixels.height;
  // the width sets the scale; the height follows the card exactly, about the
  // same centre, so the picture is never stretched by rounding
  const area = { x: percent.x / 100 * W, width: percent.width / 100 * W };
  area.height = area.width * height / width;
  area.y = (percent.y + percent.height / 2) / 100 * H - area.height / 2;

  const out = resample(pixels, area, width, height, sharpen);

  if (mask) {
    let m = await pixelsOf(mask);
    if (m.width !== width || m.height !== height) {
      const g = makeCanvas(width, height).getContext('2d', { willReadFrequently: true });
      const bitmap = await createImageBitmap(m);
      g.drawImage(bitmap, 0, 0, width, height);
      bitmap.close();
      m = g.getImageData(0, 0, width, height);
    }
    const d = out.data, md = m.data;
    for (let q = 3; q < d.length; q += 4) d[q] = d[q] * md[q] / 255;
  }
  return out;
}
