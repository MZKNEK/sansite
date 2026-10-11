// What of Skalpelator's picture shows on a card. The bot lays its frame
// (475x667) over the picture (448x650, from 13,13 of the frame), and the
// frame hides the picture's edges, so the picture only has to cover the part
// the frame leaves open: its pixels there that are not fully opaque. The
// picture saved stays 448x650, as the bot expects it.
import { boundsWhere } from '../../../shared/lib/cover.js';

export const AREA = { x: 13, y: 13, width: 448, height: 650 };
const whole = { x: 0, y: 0, width: AREA.width, height: AREA.height };

// the opening of a frame in pixels of the picture, from the frame's pixels
// over it (ImageData of AREA); the whole picture when nothing of it shows
export function frameOpening(frame) {
  return boundsWhere(frame, (alpha) => alpha < 255) ?? whole;
}

// The opening with a margin: scaled, the picture's own edge comes out soft,
// a pixel or two not fully opaque, which has to fall under the frame too
export const MARGIN = 2;
export function withMargin(bounds, by = MARGIN) {
  const x = Math.max(0, bounds.x - by), y = Math.max(0, bounds.y - by);
  return {
    x, y,
    width: Math.min(AREA.width, bounds.x + bounds.width + by) - x,
    height: Math.min(AREA.height, bounds.y + bounds.height + by) - y,
  };
}

// an opening that fits all of them, so the picture is right in any frame the
// card may get
export function unionBounds(a, b) {
  const x = Math.min(a.x, b.x), y = Math.min(a.y, b.y);
  return {
    x, y,
    width: Math.max(a.x + a.width, b.x + b.width) - x,
    height: Math.max(a.y + a.height, b.y + b.height) - y,
  };
}
