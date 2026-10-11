// A picture kept over the part of a crop area that shows, the way
// svelte-easy-crop places it: x and y move the centre of the picture away
// from the centre of the area, zoom scales it from the size it has in the
// area at zoom 1 (size, in pixels of the area). bounds is the part that has
// to stay covered, also in pixels of the area; the rest of it the frame hides.

// the box of the pixels whose alpha keep(alpha) takes, null for none
export function boundsWhere({ data, width, height }, keep) {
  let left = width, top = height, right = 0, bottom = 0;
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      if (!keep(data[(y * width + x) * 4 + 3])) continue;
      if (x < left) left = x;
      if (x >= right) right = x + 1;
      if (y < top) top = y;
      if (y >= bottom) bottom = y + 1;
    }
  }
  return right > left ? { x: left, y: top, width: right - left, height: bottom - top } : null;
}

// the least zoom at which the picture still covers bounds
export function coverMinZoom({ size, bounds }) {
  return Math.max(bounds.width / size.width, bounds.height / size.height);
}

// the picture zoomed in enough and moved back just enough to cover bounds
export function keepCovering(layer, limits, area, least = coverMinZoom(limits)) {
  const zoom = Math.max(layer.zoom, least);
  const { size, bounds } = limits;
  const halfW = size.width * zoom / 2, halfH = size.height * zoom / 2;
  const clamp = (n, lo, hi) => Math.min(hi, Math.max(lo, n));
  return {
    x: clamp(layer.x, bounds.x + bounds.width - area.width / 2 - halfW, bounds.x - area.width / 2 + halfW),
    y: clamp(layer.y, bounds.y + bounds.height - area.height / 2 - halfH, bounds.y - area.height / 2 + halfH),
    zoom,
  };
}
