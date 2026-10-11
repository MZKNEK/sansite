import { CARD, percentCrop } from './cropGeometry.js';
import { getCroppedCanvas } from '../../../shared/lib/CanvasUtils.js';

export function canvas() {
  const c = document.createElement('canvas');
  Object.assign(c, CARD);
  return c;
}

export function fastLayer(img, mask, state, size) {
  const c = canvas(), ctx = c.getContext('2d');
  const w = size.width * state.zoom, h = size.height * state.zoom;
  ctx.drawImage(img, (475 - w) / 2 + state.x, (667 - h) / 2 + state.y, w, h);
  ctx.globalCompositeOperation = 'destination-in';
  ctx.drawImage(mask, 0, 0, 475, 667);
  return c;
}

export async function qualityLayer(src, mask, state, size, sharpen) {
  return getCroppedCanvas(src, percentCrop(state, size), { ...CARD, mask, sharpen });
}

// Presentation ornaments never become part of the exported pixels.
export function composeLayers(base, top, auto, ornaments = {}) {
  const c = canvas(), ctx = c.getContext('2d');
  const draw = img => { if (img) ctx.drawImage(img, 0, 0, 475, 667); };
  draw(ornaments.back);
  if (auto) { draw(top); draw(base); } else { draw(base); draw(top); }
  draw(ornaments.border); draw(ornaments.dere); draw(ornaments.stats);
  return c;
}
