// USkalpelator's background removal, in the browser. IMG.LY's two general
// models go through its library; SkyTNT's model for anime characters and
// BRIA's RMBG-1.4 through onnxruntime itself, the same one IMG.LY's library
// loads. All of them and onnxruntime's WebAssembly come from the site's
// /models/ (tools/bg-models.mjs), listed in its resources.json the way IMG.LY's
// library reads it, in pieces the browser keeps for a year. Whichever model
// cut the figure out, the half-transparent edge takes the figure's colour
// instead of the old background's (matte.js).
import { letterbox, toPlanes, stretch, decontaminate } from './matte.js';

const MODELS = '/models/';
const S = 1024;

// icons: paths in a 16 px box, drawn with the button's colour (Segmented)
export const bgModels = [
  { value: 'small', label: 'Szybki', tag: 'IMG.LY', icon: 'M9.5 1.5 3.5 9h4l-1 5.5 6-7.5h-4z',
    title: 'IMG.LY ISNet, skwantowany (44 MB): ogólny, najszybszy' },
  { value: 'medium', label: 'Dokładny', tag: 'IMG.LY', icon: 'M8 3.5a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9M8 1v3M8 12v3M1 8h3M12 8h3',
    title: 'IMG.LY ISNet fp16 (88 MB): ogólny, dokładniejszy' },
  { value: 'anime', label: 'Anime', tag: 'SkyTNT', icon: 'M8 1.5 9.6 6.4 14.5 8 9.6 9.6 8 14.5 6.4 9.6 1.5 8 6.4 6.4z',
    title: 'SkyTNT anime-seg fp16 (88 MB): trenowany na postaciach z anime' },
  { value: 'rmbg', label: 'RMBG', tag: 'BRIA 1.4', icon: 'M8 2 14.5 5.5 8 9 1.5 5.5zM1.5 9 8 12.5 14.5 9',
    title: 'BRIA RMBG-1.4 fp16 (88 MB): ogólny; licencja BRIA tylko do użytku niekomercyjnego' },
];

// the models run here: their key in resources.json and how they see the picture
const OWN = {
  anime: { key: '/models/skytnt_isnetis_fp16', fit: 'letterbox', mean: [0, 0, 0], std: [1, 1, 1] },
  rmbg: { key: '/models/bria_rmbg_1.4_fp16', fit: 'stretch', mean: [0.5, 0.5, 0.5], std: [1, 1, 1], stretch: true },
};

let listPromise;
function resourceList() {
  listPromise ??= fetch(MODELS + 'resources.json').then((res) => {
    if (!res.ok) throw new Error('Nie ma listy modeli (' + res.status + ')');
    return res.json();
  });
  listPromise.catch(() => { listPromise = null; });
  return listPromise;
}

// one file of resources.json, its pieces fetched side by side
async function resource(key, onFetch) {
  const entry = (await resourceList())[key];
  if (!entry) throw new Error('Nie ma modelu ' + key);
  let done = 0;
  const parts = await Promise.all(entry.chunks.map(async (chunk) => {
    const res = await fetch(MODELS + chunk.name);
    if (!res.ok) throw new Error('Nie udało się pobrać modelu (' + res.status + ')');
    const part = await res.blob();
    done += part.size;
    onFetch?.(done / entry.size);
    return part;
  }));
  return new Blob(parts, { type: entry.mime });
}

let ortPromise;
function onnxruntime() {
  ortPromise ??= (async () => {
    const mod = await import('onnxruntime-web');
    const ort = mod.default ?? mod;
    const [wasm, mjs] = await Promise.all(['wasm', 'mjs'].map(async (ext) =>
      URL.createObjectURL(await resource('/onnxruntime-web/ort-wasm-simd-threaded.' + ext))));
    ort.env.wasm.wasmPaths = { wasm, mjs };
    return ort;
  })();
  ortPromise.catch(() => { ortPromise = null; });
  return ortPromise;
}

const sessions = new Map();
function session(model, onFetch) {
  if (!sessions.has(model)) {
    const p = (async () => {
      const ort = await onnxruntime();
      const bytes = new Uint8Array(await (await resource(OWN[model].key, onFetch)).arrayBuffer());
      return ort.InferenceSession.create(bytes, { executionProviders: ['wasm'], graphOptimizationLevel: 'all' });
    })();
    sessions.set(model, p);
    p.catch(() => sessions.delete(model));
  }
  return sessions.get(model);
}

function canvas(w, h) {
  const c = document.createElement('canvas');
  c.width = w;
  c.height = h;
  return c;
}
const context = (c) => c.getContext('2d', { willReadFrequently: true });

// a grey S×S mask (0..1), its part `area` laid over the whole W×H picture,
// as the alpha of every pixel (0..1)
function maskToAlpha(mask, area, W, H) {
  const m = canvas(S, S);
  const md = context(m).createImageData(S, S);
  for (let i = 0; i < mask.length; i++) {
    const v = Math.max(0, Math.min(255, Math.round(mask[i] * 255)));
    md.data[i * 4] = md.data[i * 4 + 1] = md.data[i * 4 + 2] = v;
    md.data[i * 4 + 3] = 255;
  }
  context(m).putImageData(md, 0, 0);
  const full = canvas(W, H);
  const ctx = context(full);
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(m, area.x, area.y, area.w, area.h, 0, 0, W, H);
  return channel(full, 0);
}

function channel(c, k) {
  const d = context(c).getImageData(0, 0, c.width, c.height).data;
  const a = new Float32Array(c.width * c.height);
  for (let i = 0; i < a.length; i++) a[i] = d[i * 4 + k] / 255;
  return a;
}

async function ownAlpha(bitmap, model, progress) {
  const spec = OWN[model];
  const s = await session(model, (f) => progress('fetch', f));
  progress('compute', null);
  const W = bitmap.width, H = bitmap.height;
  const square = canvas(S, S);
  const ctx = context(square);
  ctx.imageSmoothingQuality = 'high';
  let area = { x: 0, y: 0, w: S, h: S };
  if (spec.fit === 'letterbox') {
    area = letterbox(W, H, S);
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, S, S);
  }
  ctx.drawImage(bitmap, area.x, area.y, area.w, area.h);
  const ort = await onnxruntime();
  const input = toPlanes(ctx.getImageData(0, 0, S, S).data, S, spec.mean, spec.std);
  const out = await s.run({ [s.inputNames[0]]: new ort.Tensor('float32', input, [1, 3, S, S]) });
  let mask = out[s.outputNames[0]].data;
  if (!(mask instanceof Float32Array)) mask = Float32Array.from(mask);
  if (spec.stretch) stretch(mask);
  return maskToAlpha(mask, area, W, H);
}

// IMG.LY's mask, laid over the W×H picture (as decoded here, should its
// library read the picture's size differently)
async function imglyAlpha(blob, W, H, model, progress) {
  const { segmentForeground } = await import('@imgly/background-removal');
  const mask = await segmentForeground(blob, {
    publicPath: new URL(MODELS, location.origin).href,
    model,
    progress: (key, current, total) => {
      if (total > 0) progress(key.startsWith('fetch') ? 'fetch' : 'compute', current / total);
    },
  });
  const bm = await createImageBitmap(mask, { premultiplyAlpha: 'none', colorSpaceConversion: 'none' });
  const c = canvas(W, H);
  const ctx = context(c);
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(bm, 0, 0, W, H);
  bm.close();
  return channel(c, 3);
}

// The picture at `source` (an address) with its background removed, as a
// PNG. progress(stage, fraction): 'fetch' while the model downloads, then
// 'compute' (fraction null when unknown).
export async function removeBackground(source, model, progress = () => {}) {
  const blob = await (await fetch(source)).blob();
  const bitmap = await createImageBitmap(blob);
  try {
    const W = bitmap.width, H = bitmap.height;
    const alpha = OWN[model] ? await ownAlpha(bitmap, model, progress) : await imglyAlpha(blob, W, H, model, progress);
    progress('compute', null);
    const out = canvas(W, H);
    const ctx = context(out);
    ctx.drawImage(bitmap, 0, 0);
    const img = ctx.getImageData(0, 0, W, H);
    decontaminate(img.data, alpha, W, H);
    for (let i = 0; i < alpha.length; i++) img.data[i * 4 + 3] = Math.round(alpha[i] * 255);
    ctx.putImageData(img, 0, 0);
    return await new Promise((resolve, reject) =>
      out.toBlob((b) => (b ? resolve(b) : reject(new Error('Nie udało się zapisać obrazka'))), 'image/png'));
  } finally {
    bitmap.close();
  }
}
