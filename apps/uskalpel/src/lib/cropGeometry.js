export const CARD = { width: 475, height: 667 };
export const FULL = { x: 0, y: 0, ...CARD };

// Include every nonzero alpha pixel, including the antialiased fringe.
export function alphaBounds({ data, width, height }) {
  let left = width, top = height, right = 0, bottom = 0;
  for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
    if (!data[(y * width + x) * 4 + 3]) continue;
    left = Math.min(left, x); top = Math.min(top, y);
    right = Math.max(right, x + 1); bottom = Math.max(bottom, y + 1);
  }
  return right > left ? { x: left, y: top, width: right - left, height: bottom - top } : { x: 0, y: 0, width: 0, height: 0 };
}

export function fittedSize(image) {
  const ratio = Math.min(CARD.width / image.width, CARD.height / image.height);
  return { width: image.width * ratio, height: image.height * ratio };
}

export function minZoom(size, bounds) {
  return Math.max(bounds.width / size.width, bounds.height / size.height, 0.01);
}

export function constrain(state, size, bounds) {
  const zoom = Math.max(state.zoom, minZoom(size, bounds));
  const w = size.width * zoom, h = size.height * zoom;
  const clamp = (n, lo, hi) => Math.max(lo, Math.min(hi, n));
  return {
    zoom,
    x: clamp(state.x, bounds.x + bounds.width - CARD.width / 2 - w / 2, bounds.x - CARD.width / 2 + w / 2),
    y: clamp(state.y, bounds.y + bounds.height - CARD.height / 2 - h / 2, bounds.y - CARD.height / 2 + h / 2),
  };
}

export function percentCrop(state, size) {
  const w = size.width * state.zoom, h = size.height * state.zoom;
  return { x: (w / 2 - CARD.width / 2 - state.x) / w * 100,
    y: (h / 2 - CARD.height / 2 - state.y) / h * 100,
    width: CARD.width / w * 100, height: CARD.height / h * 100 };
}

// Shared translation, scale factor and correction keep the two layers registered.
export function transformLayers(base, top, mode, size, bounds, { dx = 0, dy = 0, factor = 1, anchor = { x: 0, y: 0 } } = {}) {
  const useBase = mode !== 'extra', useTop = mode !== 'base';
  let lower = 0, upper = Infinity;
  if (useBase) { lower = minZoom(size, bounds) / base.zoom; upper = Math.max(10, minZoom(size, bounds), base.zoom) / base.zoom; }
  if (useTop) { lower = Math.max(lower, 0.01 / top.zoom); upper = Math.min(upper, Math.max(10, top.zoom) / top.zoom); }
  factor = Math.max(lower, Math.min(upper, factor));
  const transform = s => ({ zoom: s.zoom * factor, x: anchor.x + (s.x - anchor.x) * factor + dx, y: anchor.y + (s.y - anchor.y) * factor + dy });
  let nextBase = useBase ? transform(base) : base;
  let nextTop = useTop ? transform(top) : top;
  if (useBase) {
    const limited = constrain(nextBase, size, bounds);
    if (useTop) nextTop = { ...nextTop, x: nextTop.x + limited.x - nextBase.x, y: nextTop.y + limited.y - nextBase.y };
    nextBase = limited;
  }
  return [nextBase, nextTop];
}
