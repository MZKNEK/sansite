// Crop and resize of the card picture in one pass, straight from the source.
//
// Lanczos3 keeps fine detail (no blur), anti-ringing removes the light and dark
// halos it would leave along sharp lines, so no unsharp mask is needed. The
// crop is taken with sub-pixel precision; when it is (almost) the size of the
// output, it is snapped to whole pixels and copied 1:1. Outside the picture is
// transparent. Measured on test charts: no overshoot, detail kept at 88-100%
// (the loss only close to the resolution limit, as with any proper filter).

const WIN = 3;

function lanczos3(x) {
  if (x < 0) x = -x;
  if (x >= WIN) return 0;
  if (x < 1e-7) return 1;
  const p = Math.PI * x;
  return (Math.sin(p) / p) * (Math.sin(p / WIN) / (p / WIN));
}

// For each output pixel along one axis: the source pixels it reads (clamped to
// the source), their weights, the main lobe (the output may not leave the
// range of these pixels) and how much of the output pixel lies on the picture,
// which spans edge0..edge1 in source pixels.
function axisTaps(srcSize, edge0, edge1, start, length, outSize) {
  const scale = outSize / length;
  const fs = Math.min(1, scale);
  const support = WIN / fs;
  const taps = Math.ceil(2 * support) + 2;
  const index = new Int32Array(outSize * taps);
  const weight = new Float32Array(outSize * taps);
  const count = new Int32Array(outSize);
  const lobeFrom = new Int32Array(outSize);
  const lobeTo = new Int32Array(outSize);
  const cover = new Float32Array(outSize);
  let min = srcSize, max = 0;

  for (let i = 0; i < outSize; i++) {
    const c = start + (i + 0.5) / scale;
    const lo = Math.ceil(c - support - 0.5);
    const hi = Math.floor(c + support - 0.5);
    let n = 0, sum = 0, la = -1, lb = -1;
    for (let j = lo; j <= hi; j++) {
      const d = (j + 0.5 - c) * fs;
      const w = lanczos3(d);
      const v = j < 0 ? 0 : j >= srcSize ? srcSize - 1 : j;
      index[i * taps + n] = v;
      weight[i * taps + n] = w;
      if (v < min) min = v;
      if (v > max) max = v;
      if (Math.abs(d) < 1) { if (la < 0) la = n; lb = n + 1; }
      sum += w;
      n++;
    }
    for (let k = 0; k < n; k++) weight[i * taps + k] /= sum;
    count[i] = n;
    lobeFrom[i] = la < 0 ? 0 : la;
    lobeTo[i] = la < 0 ? n : lb;
    const a0 = start + i / scale, a1 = start + (i + 1) / scale;
    cover[i] = Math.max(0, Math.min(a1, edge1) - Math.max(a0, edge0)) / (a1 - a0);
  }
  return { taps, index, weight, count, lobeFrom, lobeTo, cover, min, max };
}

// a crop (almost) the size of the output is copied pixel for pixel: any
// sub-pixel shift would only blur it
function snap(start, length, outSize) {
  if (Math.abs(length - outSize) < 0.5) return [Math.round(start), outSize];
  return [start, length];
}

// Averages blocks of k×k source pixels (like mipmaps) over the region the crop
// needs, premultiplied. A big reduction goes through this first, so Lanczos
// works on a picture still at least 3 times the output: much faster, and the
// averaging costs under 5% of detail at the resolution limit.
function boxReduce(src, area, k, margin) {
  const W = src.width, H = src.height, data = src.data;
  const bx0 = Math.max(0, Math.floor(area.x - margin)), by0 = Math.max(0, Math.floor(area.y - margin));
  const bx1 = Math.min(W, Math.ceil(area.x + area.width + margin)), by1 = Math.min(H, Math.ceil(area.y + area.height + margin));
  const w = Math.max(1, Math.ceil((bx1 - bx0) / k)), h = Math.max(1, Math.ceil((by1 - by0) / k));
  const out = new Float32Array(w * h * 4), n = new Float32Array(w * h);
  for (let y = by0; y < by1; y++) {
    const rowOut = ((y - by0) / k | 0) * w;
    let p = (y * W + bx0) * 4;
    for (let x = bx0; x < bx1; x++, p += 4) {
      const m = rowOut + ((x - bx0) / k | 0), o = m * 4, al = data[p + 3] / 255;
      out[o] += data[p] * al;
      out[o + 1] += data[p + 1] * al;
      out[o + 2] += data[p + 2] * al;
      out[o + 3] += data[p + 3];
      n[m]++;
    }
  }
  for (let m = 0; m < n.length; m++) {
    const o = m * 4, c = n[m] || 1;
    out[o] /= c; out[o + 1] /= c; out[o + 2] /= c; out[o + 3] /= c;
  }
  return {
    pixels: { width: w, height: h, premultiplied: out },
    area: { x: (area.x - bx0) / k, y: (area.y - by0) / k, width: area.width / k, height: area.height / k },
    edges: [(0 - bx0) / k, (W - bx0) / k, (0 - by0) / k, (H - by0) / k],
  };
}

/**
 * @param {ImageData} src - the source picture
 * @param {{x: number, y: number, width: number, height: number}} area - crop in source pixels, may be fractional and reach past the picture
 * @param {number} width - output width
 * @param {number} height - output height
 * @param {number} [sharpen] - extra unsharp mask, 0 = none
 * @returns {ImageData}
 */
export function resample(src, area, width, height, sharpen = 0) {
  let pixels = src, edges = [0, src.width, 0, src.height];
  const k = Math.floor(Math.min(area.width / width, area.height / height) / 3);
  if (k >= 2) {
    // after the averaging the scale is above 1/6, so Lanczos reads up to 6 * WIN pixels away
    const margin = (WIN * 6 + 2) * k;
    ({ pixels, area, edges } = boxReduce(src, area, k, margin));
  }

  const W = pixels.width, H = pixels.height;
  const [sx, sw] = snap(area.x, area.width, width);
  const [sy, sh] = snap(area.y, area.height, height);
  const X = axisTaps(W, edges[0], edges[1], sx, sw, width);
  const Y = axisTaps(H, edges[2], edges[3], sy, sh, height);
  const y0 = Y.min, rows = Y.max - Y.min + 1, x0 = X.min;

  // the horizontal pass reads a source row converted once to premultiplied
  // floats, as every source pixel is read by several taps
  const line = new Float32Array((X.max - x0 + 1) * 4);
  const fill = pixels.premultiplied
    ? (y) => line.set(pixels.premultiplied.subarray((y * W + x0) * 4, (y * W + x0) * 4 + line.length))
    : (y) => {
      const data = pixels.data;
      let p = (y * W + x0) * 4;
      for (let q = 0; q < line.length; q += 4, p += 4) {
        const al = data[p + 3] / 255;
        line[q] = data[p] * al;
        line[q + 1] = data[p + 1] * al;
        line[q + 2] = data[p + 2] * al;
        line[q + 3] = data[p + 3];
      }
    };

  const tmp = new Float32Array(rows * width * 4);
  convolve(X, x0, width, rows, (y) => { fill(y0 + y); return line; }, tmp, width * 4, 4);

  // vertical pass: the same, along the columns of tmp
  const outP = new Float32Array(width * height * 4);
  const column = new Float32Array(rows * 4);
  convolve(Y, y0, height, width, (i) => {
    for (let y = 0, o = i * 4; y < rows; y++, o += width * 4) {
      column[y * 4] = tmp[o]; column[y * 4 + 1] = tmp[o + 1]; column[y * 4 + 2] = tmp[o + 2]; column[y * 4 + 3] = tmp[o + 3];
    }
    return column;
  }, outP, 4, width * 4);

  // the part of a pixel past the edge of the picture is transparent
  for (let j = 0; j < height; j++) {
    for (let i = 0; i < width; i++) {
      const c = X.cover[i] * Y.cover[j];
      if (c === 1) continue;
      const q = (j * width + i) * 4;
      outP[q] *= c; outP[q + 1] *= c; outP[q + 2] *= c; outP[q + 3] *= c;
    }
  }

  if (sharpen > 0) unsharp(outP, width, height, sharpen);

  // back to straight alpha
  const out = new ImageData(width, height);
  const d = out.data;
  for (let q = 0; q < d.length; q += 4) {
    const a = Math.min(255, outP[q + 3]);
    if (a <= 0.01) continue;
    const s = 255 / a;
    d[q] = outP[q] * s;
    d[q + 1] = outP[q + 1] * s;
    d[q + 2] = outP[q + 2] * s;
    d[q + 3] = a;
  }
  return out;
}

// One pass of the filter over `lines` lines: line(l) returns the source line
// (premultiplied RGBA floats, starting at source index `first`); output pixel i
// of line l goes to out[l * lineStride + i * pixelStride].
function convolve(T, first, outSize, lines, line, out, lineStride, pixelStride) {
  const { taps, index, weight, count, lobeFrom, lobeTo } = T;
  const off = new Int32Array(index.length);
  for (let k = 0; k < off.length; k++) off[k] = (index[k] - first) * 4;
  for (let l = 0; l < lines; l++) {
    const src = line(l);
    for (let i = 0; i < outSize; i++) {
      const base = i * taps, n = count[i];
      let r = 0, g = 0, b = 0, a = 0;
      for (let t = 0; t < n; t++) {
        const q = off[base + t], w = weight[base + t];
        r += src[q] * w;
        g += src[q + 1] * w;
        b += src[q + 2] * w;
        a += src[q + 3] * w;
      }
      // anti-ringing: keep within the range of the main lobe
      let r0 = 1e9, r1 = -1e9, g0 = 1e9, g1 = -1e9, b0 = 1e9, b1 = -1e9, a0 = 1e9, a1 = -1e9;
      for (let t = lobeFrom[i], e = lobeTo[i]; t < e; t++) {
        const q = off[base + t];
        const R = src[q], G = src[q + 1], B = src[q + 2], A = src[q + 3];
        if (R < r0) r0 = R; if (R > r1) r1 = R;
        if (G < g0) g0 = G; if (G > g1) g1 = G;
        if (B < b0) b0 = B; if (B > b1) b1 = B;
        if (A < a0) a0 = A; if (A > a1) a1 = A;
      }
      const o = l * lineStride + i * pixelStride;
      out[o] = r < r0 ? r0 : r > r1 ? r1 : r;
      out[o + 1] = g < g0 ? g0 : g > g1 ? g1 : g;
      out[o + 2] = b < b0 ? b0 : b > b1 ? b1 : b;
      out[o + 3] = a < a0 ? a0 : a > a1 ? a1 : a;
    }
  }
}

// unsharp mask on premultiplied values: Gaussian of sigma 0.7 px
function unsharp(p, width, height, amount) {
  const sigma = 0.7, rad = 2;
  const k = [];
  let s = 0;
  for (let t = -rad; t <= rad; t++) { const v = Math.exp(-(t * t) / (2 * sigma * sigma)); k.push(v); s += v; }
  for (let t = 0; t < k.length; t++) k[t] /= s;
  const tmp = new Float32Array(p.length), blur = new Float32Array(p.length);
  for (let j = 0; j < height; j++) for (let i = 0; i < width; i++) for (let c = 0; c < 3; c++) {
    let v = 0;
    for (let t = -rad; t <= rad; t++) { const x = Math.min(width - 1, Math.max(0, i + t)); v += p[(j * width + x) * 4 + c] * k[t + rad]; }
    tmp[(j * width + i) * 4 + c] = v;
  }
  for (let j = 0; j < height; j++) for (let i = 0; i < width; i++) for (let c = 0; c < 3; c++) {
    let v = 0;
    for (let t = -rad; t <= rad; t++) { const y = Math.min(height - 1, Math.max(0, j + t)); v += tmp[(y * width + i) * 4 + c] * k[t + rad]; }
    blur[(j * width + i) * 4 + c] = v;
  }
  for (let q = 0; q < p.length; q += 4) {
    const a = p[q + 3];
    for (let c = 0; c < 3; c++) {
      const v = p[q + c] + amount * (p[q + c] - blur[q + c]);
      p[q + c] = v < 0 ? 0 : v > a ? a : v;
    }
  }
}
