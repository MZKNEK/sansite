// USkalpelator's layers: Scalp kept over its frame's opening, and both
// layers moved alike.
//   node --test apps/uskalpel/test/
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { CARD, alphaBounds, scalpMinZoom, limitScalp, moveLayers } from '../src/lib/layers.js';

// the opening of Delta's mask
const delta = { x: 31, y: 55, width: 418, height: 576 };
const full = { x: 0, y: 0, ...CARD };
const near = (a, b) => assert.ok(Math.abs(a - b) < 1e-9, `${a} is not ${b}`);

function covers(layer, { size, bounds }) {
  const halfW = size.width * layer.zoom / 2, halfH = size.height * layer.zoom / 2;
  const cx = CARD.width / 2 + layer.x, cy = CARD.height / 2 + layer.y;
  assert.ok(cx - halfW <= bounds.x + 1e-9 && cx + halfW >= bounds.x + bounds.width - 1e-9, 'covers across');
  assert.ok(cy - halfH <= bounds.y + 1e-9 && cy + halfH >= bounds.y + bounds.height - 1e-9, 'covers down');
}

test('the opening of a mask: every pixel not fully transparent', () => {
  const data = new Uint8ClampedArray(10 * 8 * 4);
  data[(2 * 10 + 1) * 4 + 3] = 1;
  data[(6 * 10 + 8) * 4 + 3] = 255;
  assert.deepEqual(alphaBounds({ data, width: 10, height: 8 }), { x: 1, y: 2, width: 8, height: 5 });
  assert.equal(alphaBounds({ data: new Uint8ClampedArray(16), width: 2, height: 2 }), null);
});

test("Scalp is never smaller than Delta's opening of 418 px", () => {
  // a wide picture: as wide as the card at zoom 1, so 418 px at the least
  const limits = { size: { width: 475, height: 300 }, bounds: delta };
  near(scalpMinZoom(limits), 576 / 300);
  const kept = limitScalp({ x: 0, y: 0, zoom: 0.5 }, limits);
  near(kept.zoom, 576 / 300);
  covers(kept, limits);
  // a tall one
  const tall = { size: { width: 300, height: 667 }, bounds: delta };
  near(scalpMinZoom(tall), 418 / 300);
});

test('Scalp is moved back over the opening, from any side', () => {
  for (const size of [{ width: 475, height: 300 }, { width: 200, height: 667 }, CARD]) {
    for (const bounds of [delta, full, { x: 12, y: 102, width: 100, height: 410 }]) {
      const limits = { size, bounds };
      for (const [x, y] of [[4000, 0], [-4000, 0], [0, 3000], [0, -3000], [999, -999]])
        covers(limitScalp({ x, y, zoom: 0.2 }, limits), limits);
    }
  }
});

test('Scalp already over the opening stays where it is', () => {
  const limits = { size: CARD, bounds: delta };
  assert.deepEqual(limitScalp({ x: 10, y: -20, zoom: 1.5 }, limits), { x: 10, y: -20, zoom: 1.5 });
});

test('both layers move alike and stop together at every edge', () => {
  const limits = { size: CARD, bounds: delta };
  const layers = { base: { x: 0, y: 0, zoom: 1.2 }, extra: { x: 70, y: -30, zoom: 2 } };
  for (const [dx, dy] of [[9999, 0], [-9999, 0], [0, 9999], [0, -9999]]) {
    const { base, extra } = moveLayers(layers, 'both', { dx, dy }, limits);
    covers(base, limits);
    near(extra.x - base.x, 70);
    near(extra.y - base.y, -30);
    assert.equal(extra.zoom, 2);
  }
  const { base, extra } = moveLayers(layers, 'both', { dx: 5, dy: -3 }, limits);
  assert.deepEqual([base.x, base.y, extra.x, extra.y], [5, -3, 75, -33]);
});

test('zooming both keeps them on each other, down to what Scalp allows', () => {
  const limits = { size: CARD, bounds: full };
  const layers = { base: { x: 20, y: 10, zoom: 3 }, extra: { x: 80, y: -20, zoom: 2 } };
  const { base, extra } = moveLayers(layers, 'both', { factor: 0.001, anchor: { x: 100, y: -50 } }, limits);
  near(base.zoom, 1);
  near(extra.zoom / base.zoom, 2 / 3);
  covers(base, limits);
  // the same scale around the same point, then the same shift back
  near((extra.x - base.x) / (80 - 20), 1 / 3);
  near((extra.y - base.y) / (-20 - 10), 1 / 3);
});

test('a factor of 1 only brings Scalp back over the opening', () => {
  const limits = { size: CARD, bounds: delta };
  const layers = { base: { x: 0, y: 0, zoom: 0.5 }, extra: { x: 0, y: 0, zoom: 0.5 } };
  const { base, extra } = moveLayers(layers, 'both', {}, limits);
  covers(base, limits);
  assert.deepEqual(extra, base, 'the same picture stays on Scalp');
});

test('Top alone goes freely and leaves Scalp be', () => {
  const layers = { base: { x: 0, y: 0, zoom: 1 }, extra: { x: 0, y: 0, zoom: 1 } };
  const { base, extra } = moveLayers(layers, 'extra', { dx: 2000, factor: 0.5 }, { size: CARD, bounds: delta });
  assert.deepEqual(base, layers.base);
  assert.deepEqual(extra, { x: 2000, y: 0, zoom: 0.5 });
  near(moveLayers(layers, 'extra', { factor: 0.001 }, null).extra.zoom, 0.1);
});

test('Scalp goes freely while its picture or mask is not there', () => {
  const layers = { base: { x: 0, y: 0, zoom: 1 }, extra: { x: 0, y: 0, zoom: 1 } };
  assert.deepEqual(moveLayers(layers, 'base', { dx: 900 }, null).base, { x: 900, y: 0, zoom: 1 });
});
