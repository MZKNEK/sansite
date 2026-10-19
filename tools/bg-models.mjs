#!/usr/bin/env node
// Puts the models of USkalpelator's background removal into models/, which
// the site serves at /models/ (server/nginx/sanakan.conf) and deploy.sh sends
// on its own, as they are kept out of git:
//   - IMG.LY's two general models (Szybki, Dokładny), from IMG.LY's CDN at the
//     version of @imgly/background-removal the app has,
//   - SkyTNT's model for anime characters (Apache-2.0), converted to fp16,
//   - BRIA's RMBG-1.4 in fp16 (BRIA's licence: non-commercial use only),
//   - onnxruntime's WebAssembly, from the onnxruntime-web the app has.
// Every file is cut into pieces of 4 MiB named by the SHA-256 of their content
// (.bin, kept for a year), and models/resources.json lists them the way
// IMG.LY's library reads it (its publicPath); the app reads the same list for
// the other two (apps/uskalpel/src/lib/background.js). A model already listed
// with all its pieces is kept as it is. Downloads are checked against the
// hashes below or IMG.LY's own. The fp16 conversion needs Python with onnx and
// onnxconverter-common (pip install onnx onnxconverter-common; PYTHON picks
// the interpreter). Run after installing the app's packages:
//   node tools/bg-models.mjs            what is missing
//   node tools/bg-models.mjs --force    all of it again
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, unlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const root = path.resolve(import.meta.dirname, '..');
const out = path.join(root, 'models');
const app = path.join(root, 'apps/uskalpel/node_modules');
const CHUNK = 4 * 1024 * 1024;
const force = process.argv.includes('--force');

const sha256 = (data) => createHash('sha256').update(data).digest('hex');
const version = (pkg) => JSON.parse(readFileSync(path.join(app, pkg, 'package.json'), 'utf8')).version;

async function download(url, hash) {
  console.log('  pobieram ' + url);
  const res = await fetch(url);
  if (!res.ok) throw new Error(`${url}: ${res.status}`);
  const data = Buffer.from(await res.arrayBuffer());
  if (hash && sha256(data) !== hash) throw new Error(`${url}: zła suma SHA-256`);
  return data;
}

// writes one piece unless it is there already
function writePiece(data) {
  const hash = sha256(data);
  const file = path.join(out, hash + '.bin');
  if (!existsSync(file)) writeFileSync(file, data);
  return { hash, name: hash + '.bin' };
}

function cut(data, mime) {
  const chunks = [];
  for (let at = 0; at < data.length; at += CHUNK) {
    const end = Math.min(at + CHUNK, data.length);
    chunks.push({ ...writePiece(data.subarray(at, end)), offsets: [at, end] });
  }
  return { chunks, size: data.length, mime };
}

function toFp16(data) {
  const dir = mkdtempSync(path.join(tmpdir(), 'bg-models-'));
  try {
    writeFileSync(path.join(dir, 'in.onnx'), data);
    const script = [
      'import sys, onnx',
      'from onnxconverter_common import float16',
      'm = onnx.load(sys.argv[1])',
      'onnx.save(float16.convert_float_to_float16(m, keep_io_types=True), sys.argv[2])',
    ].join('\n');
    const python = process.env.PYTHON || (process.platform === 'win32' ? 'python' : 'python3');
    // -W ignore: a warning for every weight too small for fp16, rounded to 1e-7
    const run = spawnSync(python, ['-I', '-W', 'ignore', '-c', script, path.join(dir, 'in.onnx'), path.join(dir, 'out.onnx')], { stdio: 'inherit' });
    if (run.status !== 0) throw new Error('konwersja do fp16 nie wyszła (pip install onnx onnxconverter-common)');
    return readFileSync(path.join(dir, 'out.onnx'));
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

const imgly = version('@imgly/background-removal');
const ort = version('onnxruntime-web');
const imglyData = `https://staticimgly.com/@imgly/background-removal-data/${imgly}/dist/`;
const hf = (repo, rev, file) => `https://huggingface.co/${repo}/resolve/${rev}/${file}`;

// key in resources.json -> how to make its entry
const models = {
  '/models/isnet_quint8': () => fromImgly('/models/isnet_quint8'),
  '/models/isnet_fp16': () => fromImgly('/models/isnet_fp16'),
  '/models/skytnt_isnetis_fp16': async () => cut(toFp16(await download(
    hf('skytnt/anime-seg', '493cb60893f47441b26ec4fb9a306bce9e342982', 'isnetis.onnx'),
    'f15622d853e8260172812b657053460e20806f04b9e05147d49af7bed31a6e99')), 'application/octet-stream'),
  '/models/bria_rmbg_1.4_fp16': async () => cut(await download(
    hf('briaai/RMBG-1.4', '2ceba5a5efaec153162aedea169f76caf9b46cf8', 'onnx/model_fp16.onnx'),
    '9fdfdb41866d872e0acf4a010c35c1a8547bf0eebe0d1544406bbf1c824cb59d'), 'application/octet-stream'),
  '/onnxruntime-web/ort-wasm-simd-threaded.wasm': () =>
    cut(readFileSync(path.join(app, 'onnxruntime-web/dist/ort-wasm-simd-threaded.wasm')), 'application/wasm'),
  '/onnxruntime-web/ort-wasm-simd-threaded.mjs': () =>
    cut(readFileSync(path.join(app, 'onnxruntime-web/dist/ort-wasm-simd-threaded.mjs')), 'text/javascript'),
};

let imglyList;
async function fromImgly(key) {
  imglyList ??= JSON.parse((await download(imglyData + 'resources.json')).toString('utf8'));
  const entry = imglyList[key];
  if (!entry) throw new Error(`IMG.LY ${imgly} nie ma ${key}`);
  const chunks = [];
  for (const chunk of entry.chunks) {
    // IMG.LY names its pieces by their SHA-256 too
    const file = path.join(out, chunk.name + '.bin');
    if (!existsSync(file)) writeFileSync(file, await download(imglyData + chunk.name, chunk.name));
    chunks.push({ hash: chunk.name, name: chunk.name + '.bin', offsets: chunk.offsets });
  }
  return { chunks, size: entry.size, mime: entry.mime };
}

mkdirSync(out, { recursive: true });
const listFile = path.join(out, 'resources.json');
const old = existsSync(listFile) ? JSON.parse(readFileSync(listFile, 'utf8')) : {};
const list = {};
for (const [key, make] of Object.entries(models)) {
  const kept = old[key];
  // the WebAssembly follows the app's onnxruntime-web, so it is always made anew (it is quick)
  const ours = key.startsWith('/onnxruntime-web/');
  if (!force && !ours && kept?.chunks.every((c) => existsSync(path.join(out, c.name)))) {
    list[key] = kept;
    continue;
  }
  console.log(key);
  list[key] = await make();
}
writeFileSync(listFile, JSON.stringify(list, null, 2) + '\n');

// pieces no model needs any more go (deploy.sh leaves the server's alone)
const used = new Set(Object.values(list).flatMap((e) => e.chunks.map((c) => c.name)));
for (const file of readdirSync(out)) {
  if (file.endsWith('.bin') && !used.has(file)) {
    unlinkSync(path.join(out, file));
    console.log('usunięty niepotrzebny ' + file);
  }
}
const total = Object.values(list).reduce((s, e) => s + e.size, 0);
console.log(`models/: ${Object.keys(list).length} plików, ${(total / 1e6).toFixed(0)} MB (IMG.LY ${imgly}, onnxruntime-web ${ort})`);
