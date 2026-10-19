// The arithmetic of USkalpelator's background removal: the model's input, the
// stretched mask and the edge taking the figure's colour.
//   node --test apps/uskalpel/test/
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { letterbox, toPlanes, stretch, boxMean, decontaminate } from '../src/lib/matte.js';

test('letterbox fits the picture in the square, centred', () => {
  assert.deepEqual(letterbox(200, 100, 1024), { x: 0, y: 256, w: 1024, h: 512 });
  assert.deepEqual(letterbox(100, 200, 1024), { x: 256, y: 0, w: 512, h: 1024 });
  assert.deepEqual(letterbox(300, 300, 1024), { x: 0, y: 0, w: 1024, h: 1024 });
});

test('toPlanes makes three planes of normalised values', () => {
  // 2×2: red, green, blue, white
  const rgba = [255, 0, 0, 255, 0, 255, 0, 255, 0, 0, 255, 255, 255, 255, 255, 255];
  const planes = toPlanes(rgba, 2, [0.5, 0.5, 0.5], [1, 1, 1]);
  assert.deepEqual([...planes], [0.5, -0.5, -0.5, 0.5, -0.5, 0.5, -0.5, 0.5, -0.5, -0.5, 0.5, 0.5]);
});

test('stretch maps the lowest value to 0 and the highest to 1', () => {
  assert.deepEqual([...stretch(Float32Array.from([0.25, 0.5, 0.75]))], [0, 0.5, 1]);
  assert.deepEqual([...stretch(Float32Array.from([0.3, 0.3]))], [0, 0]);
});

test('boxMean averages the window, clipped at the edges', () => {
  const src = Float32Array.from([0, 0, 9, 0, 0]);
  assert.deepEqual([...boxMean(src, 5, 1, 1)], [0, 3, 3, 3, 0]);
  const flat = new Float32Array(12).fill(2);
  assert.ok(boxMean(flat, 4, 3, 2).every((v) => Math.abs(v - 2) < 1e-6));
});

test('decontaminate gives the edge the figure colour and leaves the rest', () => {
  // a row: solid red figure, a half-transparent pixel of the old green, background
  const W = 5, H = 1;
  const rgba = new Uint8ClampedArray([
    255, 0, 0, 255, 255, 0, 0, 255, 128, 128, 0, 255, 0, 255, 0, 255, 0, 255, 0, 255,
  ]);
  const alpha = Float32Array.from([1, 1, 0.5, 0, 0]);
  decontaminate(rgba, alpha, W, H, 3);
  assert.deepEqual([...rgba.slice(8, 11)], [255, 0, 0], 'the edge pixel takes the red');
  assert.deepEqual([...rgba.slice(0, 3)], [255, 0, 0], 'the solid figure stays');
  assert.deepEqual([...rgba.slice(12, 15)], [0, 255, 0], 'the transparent background is left alone');
});

test('decontaminate leaves an edge with no solid figure near', () => {
  const rgba = new Uint8ClampedArray([10, 20, 30, 255, 0, 0, 0, 255]);
  decontaminate(rgba, Float32Array.from([0.5, 0]), 2, 1, 1);
  assert.deepEqual([...rgba.slice(0, 3)], [10, 20, 30]);
});
