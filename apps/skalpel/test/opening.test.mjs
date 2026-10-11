// Skalpelator's picture kept over what its frames leave open.
//   node --test apps/skalpel/test/
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { AREA, frameOpening, unionBounds, withMargin } from '../src/lib/opening.js';
import { coverMinZoom, keepCovering } from '../../shared/lib/cover.js';

// the opening of all the bot's frames (A.png … SSS.png) over the picture
const opening = { x: 10, y: 13, width: 427, height: 614 };
const near = (a, b) => assert.ok(Math.abs(a - b) < 1e-9, `${a} is not ${b}`);

// a frame over the picture: opaque but for a box of alpha (a soft edge too)
function frame(box, alpha = 0) {
  const data = new Uint8ClampedArray(AREA.width * AREA.height * 4).fill(255);
  for (let y = box.y; y < box.y + box.height; y++)
    for (let x = box.x; x < box.x + box.width; x++) data[(y * AREA.width + x) * 4 + 3] = alpha;
  return { data, width: AREA.width, height: AREA.height };
}

test("a frame's opening: every pixel it does not cover fully", () => {
  assert.deepEqual(frameOpening(frame(opening)), opening);
  assert.deepEqual(frameOpening(frame(opening, 61)), opening, 'half covered still shows');
  assert.deepEqual(frameOpening(frame({ x: 0, y: 0, width: 0, height: 0 })), { x: 0, y: 0, width: 448, height: 650 });
});

test('the opening of several frames fits each of them', () => {
  assert.deepEqual(unionBounds(opening, { x: 20, y: 5, width: 400, height: 700 }), { x: 10, y: 5, width: 427, height: 700 });
});

test("the margin for the picture's soft edge stays within the picture", () => {
  assert.deepEqual(withMargin(opening), { x: 8, y: 11, width: 431, height: 618 });
  assert.deepEqual(withMargin({ x: 1, y: 0, width: 447, height: 650 }), { x: 0, y: 0, width: 448, height: 650 });
});

test('the picture may leave the edges the frame hides, never the opening', () => {
  // a picture of the card's shape, at zoom 1 as large as the whole area
  const limits = { size: { width: 448, height: 650 }, bounds: opening };
  near(coverMinZoom(limits), 427 / 448);
  // moved as far left as it goes: its right edge on the opening's
  const left = keepCovering({ x: -999, y: 0, zoom: 1 }, limits, AREA);
  near(AREA.width / 2 + left.x + 448 / 2, opening.x + opening.width);
  assert.ok(left.x < 0, 'further than the whole area would allow');
  const down = keepCovering({ x: 0, y: 999, zoom: 1 }, limits, AREA);
  near(AREA.height / 2 + down.y - 650 / 2, opening.y);
});
