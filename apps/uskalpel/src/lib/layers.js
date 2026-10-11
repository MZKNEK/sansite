// Where the pictures of USkalpelator's layers lie on the card, the way
// svelte-easy-crop places them: x and y move the centre of the picture away
// from the centre of the card (475x667), zoom scales it from the size it has
// on the card at zoom 1 (size, in pixels of the card). Scalp has to cover the
// whole opening of its frame's mask (bounds: every pixel the mask does not
// make fully transparent), so nothing behind it ever shows through; Top, a
// picture with its background cut out, goes where it is put.

import { boundsWhere, coverMinZoom, keepCovering } from '../../../shared/lib/cover.js';

export const CARD = { width: 475, height: 667 };
export const ZOOM = { min: 0.1, max: 10 };

// the box of the pixels of a mask that are not fully transparent, its soft
// edge included
export const alphaBounds = (imageData) => boundsWhere(imageData, (alpha) => alpha > 0);

// the least zoom at which Scalp still covers the opening
export function scalpMinZoom(limits) {
  return Math.max(ZOOM.min, coverMinZoom(limits));
}

// Scalp zoomed in enough and moved back just enough to cover the opening
export function limitScalp(layer, limits) {
  return keepCovering(layer, limits, CARD, scalpMinZoom(limits));
}

// One move of the layers being moved ('base', 'extra' or 'both'): shifted by
// dx, dy and scaled by factor around anchor, all in pixels of the card from
// its centre. Both layers get the same move, so they keep lying on each other
// as they did; where Scalp would leave its frame's opening, the move stops
// for both of them alike. limits ({ size, bounds } of Scalp) is null while
// Scalp's picture or its mask is not there yet, and Scalp then goes freely.
export function moveLayers(layers, which, { dx = 0, dy = 0, factor = 1, anchor = { x: 0, y: 0 } }, limits) {
  const moved = which === 'both' ? ['base', 'extra'] : [which];
  let lower = 0, upper = Infinity;
  for (const name of moved) {
    const min = name === 'base' && limits ? scalpMinZoom(limits) : ZOOM.min;
    lower = Math.max(lower, min / layers[name].zoom);
    upper = Math.min(upper, Math.max(ZOOM.max, min) / layers[name].zoom);
  }
  // covering the opening comes before the largest zoom
  factor = Math.max(lower, Math.min(factor, upper));

  const next = { ...layers };
  for (const name of moved) {
    const layer = layers[name];
    next[name] = {
      x: anchor.x + (layer.x - anchor.x) * factor + dx,
      y: anchor.y + (layer.y - anchor.y) * factor + dy,
      zoom: layer.zoom * factor,
    };
  }
  if (moved.includes('base') && limits) {
    const kept = limitScalp(next.base, limits);
    if (moved.includes('extra'))
      next.extra = { ...next.extra, x: next.extra.x + kept.x - next.base.x, y: next.extra.y + kept.y - next.base.y };
    next.base = kept;
  }
  return next;
}
