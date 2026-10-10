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

// the last few pictures stay decoded, so moving the crop does not load them again
const cache = new Map();

function pixelsOf(url) {
  if (!cache.has(url)) {
    const job = (async () => {
      const res = await fetch(url, { mode: 'cors' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const bitmap = await createImageBitmap(await res.blob());
      const g = makeCanvas(bitmap.width, bitmap.height).getContext('2d', { willReadFrequently: true });
      g.drawImage(bitmap, 0, 0);
      bitmap.close();
      return g.getImageData(0, 0, g.canvas.width, g.canvas.height);
    })();
    cache.set(url, job);
    job.catch(() => cache.delete(url));
    while (cache.size > 4) cache.delete(cache.keys().next().value);
  }
  return cache.get(url);
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
      g.drawImage(await createImageBitmap(m), 0, 0, width, height);
      m = g.getImageData(0, 0, width, height);
    }
    const d = out.data, md = m.data;
    for (let q = 3; q < d.length; q += 4) d[q] = d[q] * md[q] / 255;
  }
  return out;
}
