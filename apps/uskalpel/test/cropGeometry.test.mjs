import { test } from 'node:test';
import assert from 'node:assert/strict';
import { CARD, FULL, alphaBounds, fittedSize, minZoom, constrain, percentCrop, transformLayers } from '../src/lib/cropGeometry.js';

const delta = { x: 31, y: 55, width: 418, height: 576 };
const zero = { x: 0, y: 0, zoom: 1 };
function covers(state, size, mask) {
  const w = size.width * state.zoom, h = size.height * state.zoom;
  assert.ok(237.5 + state.x - w / 2 <= mask.x + 1e-9);
  assert.ok(237.5 + state.x + w / 2 >= mask.x + mask.width - 1e-9);
  assert.ok(333.5 + state.y - h / 2 <= mask.y + 1e-9);
  assert.ok(333.5 + state.y + h / 2 >= mask.y + mask.height - 1e-9);
}
test('alpha bounds include partial alpha and off-centre masks', () => {
  const data = new Uint8ClampedArray(10 * 8 * 4);
  data[(2 * 10 + 1) * 4 + 3] = 1;
  data[(6 * 10 + 8) * 4 + 3] = 255;
  assert.deepEqual(alphaBounds({ data, width: 10, height: 8 }), { x: 1, y: 2, width: 8, height: 5 });
});
test('portrait, landscape, tiny and extreme aspect ratios cover every mask after zoom/position correction', () => {
  for (const image of [{ width: 300, height: 1200 }, { width: 1800, height: 600 }, { width: 12, height: 8 }, { width: 1, height: 10000 }]) {
    const size = fittedSize(image);
    for (const mask of [delta, FULL, { x: 12, y: 102, width: 100, height: 410 }]) {
      assert.equal(minZoom(size, mask), Math.max(mask.width / size.width, mask.height / size.height));
      const corrected = constrain({ x: 4000, y: -3000, zoom: 0.01 }, size, mask);
      covers(corrected, size, mask);
      covers(constrain(corrected, size, FULL), size, FULL);
    }
  }
});
test('both layers stop together at all four limits, preserving their offset', () => {
  const b = { ...zero, zoom: 3 }, t = { x: 70, y: -30, zoom: 2 };
  for (const [dx, dy] of [[9999, 0], [-9999, 0], [0, 9999], [0, -9999]]) {
    const [base, top] = transformLayers(b, t, 'both', CARD, delta, { dx, dy });
    covers(base, CARD, delta);
    assert.equal(top.x - base.x, 70);
    assert.equal(top.y - base.y, -30);
  }
});
test('joint anchored zoom preserves registration even at minimum zoom', () => {
  const b = { x: 20, y: 10, zoom: 3 }, t = { x: 80, y: -20, zoom: 2 };
  const [base, top] = transformLayers(b, t, 'both', CARD, delta, { factor: 0.001, anchor: { x: 200, y: -100 } });
  const factor = base.zoom / b.zoom;
  assert.ok(Math.abs(top.zoom / base.zoom - 2 / 3) < 1e-9);
  assert.ok(Math.abs(top.x - base.x - 60 * factor) < 1e-9);
  assert.ok(Math.abs(top.y - base.y + 30 * factor) < 1e-9);
  covers(base, CARD, delta);
});
test('transparent Top is free to leave mask while Scalp stays unchanged', () => {
  const [base, top] = transformLayers(zero, zero, 'extra', CARD, FULL, { dx: 2000, factor: 0.1 });
  assert.deepEqual(base, zero);
  assert.equal(top.x, 2000);
  assert.equal(top.zoom, 0.1);
});
test('crop percentages reconstruct the same card rectangle without rounding', () => {
  const state = { x: 23.4, y: -41.2, zoom: 1.37 }, size = fittedSize({ width: 1400, height: 900 });
  const p = percentCrop(state, size);
  assert.ok(Math.abs(p.width / 100 * size.width * state.zoom - 475) < 1e-9);
  assert.ok(Math.abs(p.height / 100 * size.height * state.zoom - 667) < 1e-9);
  assert.ok(Math.abs(p.x / 100 * size.width * state.zoom - (size.width * state.zoom / 2 - 237.5 - state.x)) < 1e-9);
});
