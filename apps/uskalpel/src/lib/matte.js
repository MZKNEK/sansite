// The arithmetic of the background removal (background.js), apart from the
// browser: the model's input from the picture's pixels, its mask stretched to
// the full range, and the colours of the half-transparent edge.

// where a W×H picture lies in an S×S square when fitted in with bars
// (SkyTNT's model sees it so, as its author's code does)
export function letterbox(W, H, S) {
  const [w, h] = H > W ? [Math.round(S * W / H), S] : [S, Math.round(S * H / W)];
  return { x: Math.floor((S - w) / 2), y: Math.floor((S - h) / 2), w, h };
}

// RGBA pixels of an S×S square as the model's input: three planes of
// (value / 255 - mean) / std
export function toPlanes(rgba, S, mean, std) {
  const N = S * S, data = new Float32Array(3 * N);
  for (let i = 0; i < N; i++) {
    for (let c = 0; c < 3; c++) data[c * N + i] = (rgba[i * 4 + c] / 255 - mean[c]) / std[c];
  }
  return data;
}

// a mask stretched from its lowest to its highest value onto 0..1 (as BRIA's
// code does with RMBG's), in place
export function stretch(mask) {
  let lo = Infinity, hi = -Infinity;
  for (const v of mask) {
    if (v < lo) lo = v;
    if (v > hi) hi = v;
  }
  for (let i = 0; i < mask.length; i++) mask[i] = hi > lo ? (mask[i] - lo) / (hi - lo) : 0;
  return mask;
}

// the mean over a (2r+1)² window, clipped at the edges, into out (tmp is
// scratch of the same length)
export function boxMean(src, W, H, r, out = new Float32Array(W * H), tmp = new Float32Array(W * H)) {
  for (let y = 0; y < H; y++) {
    const o = y * W;
    let s = 0;
    for (let x = 0; x <= Math.min(r, W - 1); x++) s += src[o + x];
    for (let x = 0; x < W; x++) {
      tmp[o + x] = s / (Math.min(x + r, W - 1) - Math.max(x - r, 0) + 1);
      if (x + r + 1 < W) s += src[o + x + r + 1];
      if (x - r >= 0) s -= src[o + x - r];
    }
  }
  for (let x = 0; x < W; x++) {
    let s = 0;
    for (let y = 0; y <= Math.min(r, H - 1); y++) s += tmp[y * W + x];
    for (let y = 0; y < H; y++) {
      out[y * W + x] = s / (Math.min(y + r, H - 1) - Math.max(y - r, 0) + 1);
      if (y + r + 1 < H) s += tmp[(y + r + 1) * W + x];
      if (y - r >= 0) s -= tmp[(y - r) * W + x];
    }
  }
  return out;
}

// The half-transparent pixels of the edge (alpha above 0 and below 0.95) take
// the colour of the solid figure near them, the mean of the pixels within r
// whose alpha is at least 0.95, instead of their own, which is partly the old
// background's: no halo of it around the figure. One with no solid pixel near
// keeps its colour. rgba is changed in place, alpha (0..1) is not.
export function decontaminate(rgba, alpha, W, H, r = Math.max(3, Math.round(Math.min(W, H) / 300))) {
  const n = W * H;
  const solid = new Float32Array(n), weight = new Float32Array(n);
  const channel = new Float32Array(n), mean = new Float32Array(n), tmp = new Float32Array(n);
  for (let i = 0; i < n; i++) solid[i] = alpha[i] >= 0.95 ? 1 : 0;
  boxMean(solid, W, H, r, weight, tmp);
  for (let c = 0; c < 3; c++) {
    for (let i = 0; i < n; i++) channel[i] = solid[i] * rgba[i * 4 + c];
    boxMean(channel, W, H, r, mean, tmp);
    for (let i = 0; i < n; i++) {
      if (alpha[i] > 0 && alpha[i] < 0.95 && weight[i] > 0) rgba[i * 4 + c] = mean[i] / weight[i];
    }
  }
  return rgba;
}
