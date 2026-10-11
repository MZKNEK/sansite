<script>
  import { onMount, untrack } from 'svelte';
  import { CARD, alphaBounds, fittedSize, constrain, percentCrop, transformLayers } from './cropGeometry.js';
  import { canvas, fastLayer, qualityLayer, composeLayers } from './cardRenderer.js';
  let { image, extraImage, mask, extraMask, hasExtraLayer, editMode, activeLayer, bgAutoLayer,
    sharpen, realPreview, border, back, dere, stats, zoomLevel,
    crop = $bindable(), extraCrop = $bindable(), curzoom = $bindable(), extraZoom = $bindable(),
    onmeasure } = $props();
  let surface = $state(), viewport = $state(), wrapper = $state();
  let available = $state(475), dpr = $state(1), offset = $state({ x: 0, y: 0 });
  let assets = $state.raw(null), ornaments = $state.raw({});
  let error = $state(''), ornamentError = $state('');
  let renderVersion = 0;
  let dragging = $state(false);
  const pointers = new Map();
  let scale = $derived(zoomLevel === 'fit' ? Math.min(1 / dpr, available / 475) : zoomLevel / dpr);
  let base = $derived({ ...crop, zoom: curzoom });
  let top = $derived({ ...extraCrop, zoom: extraZoom });
  let mode = $derived(hasExtraLayer && extraImage ? activeLayer : 'base');

  function load(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Nie udało się wczytać obrazu lub maski.'));
      img.src = src;
    });
  }

  $effect(() => {
    const src = image, extra = hasExtraLayer && extraImage, m = mask, tm = extraMask;
    let cancelled = false;
    assets = null;
    error = '';
    Promise.all([load(src), load(m), extra ? load(extra) : null, extra ? load(tm) : null]).then(([img, maskImg, topImg, topMask]) => {
      if (cancelled) return;
      const c = canvas(), ctx = c.getContext('2d');
      ctx.drawImage(maskImg, 0, 0, 475, 667);
      const bounds = alphaBounds(ctx.getImageData(0, 0, 475, 667));
      assets = { img, maskImg, topImg, topMask, size: fittedSize(img), topSize: topImg ? fittedSize(topImg) : CARD, bounds, src, extra, m, tm };
    }).catch(e => { if (!cancelled) error = e.message; });
    return () => { cancelled = true; };
  });

  $effect(() => {
    const sources = { border, back, dere, stats };
    let cancelled = false;
    ornaments = {};
    ornamentError = '';
    Promise.all(Object.entries(sources).map(async ([key, src]) => [key, src ? await load(src).catch(() => null) : null])).then(entries => {
      if (cancelled) return;
      ornaments = Object.fromEntries(entries);
      if (entries.some(([key, img]) => sources[key] && !img)) ornamentError = 'Nie udało się wczytać wszystkich grafik ramki.';
    });
    return () => { cancelled = true; };
  });

  function commit(b, t) {
    if (crop.x !== b.x || crop.y !== b.y) crop = { x: b.x, y: b.y };
    if (curzoom !== b.zoom) curzoom = b.zoom;
    if (extraCrop.x !== t.x || extraCrop.y !== t.y) extraCrop = { x: t.x, y: t.y };
    if (extraZoom !== t.zoom) extraZoom = t.zoom;
  }
  $effect.pre(() => {
    if (assets) commit(constrain(base, assets.size, assets.bounds), top);
  });
  $effect(() => {
    if (!assets) return;
    const b = percentCrop(base, assets.size), t = percentCrop(top, assets.topSize);
    const pixels = (p, img) => ({ width: p.width * img.width / 100, height: p.height * img.height / 100 });
    const bp = pixels(b, assets.img), tp = assets.topImg ? pixels(t, assets.topImg) : CARD;
    untrack(() => onmeasure?.(bp, tp));
  });

  export function move(dx, dy) { update({ dx, dy }); }
  function update(change) {
    if (!assets) return;
    commit(...transformLayers(base, top, mode, assets.size, assets.bounds, change));
  }
  export function reset() {
    if (!assets) return;
    const zero = { x: 0, y: 0, zoom: 1 };
    commit(mode !== 'extra' ? constrain(zero, assets.size, assets.bounds) : base, mode !== 'base' ? zero : top);
  }
  export function sync() {
    if (!assets?.topImg) return;
    // Match displayed width rather than raw pixels; aspect ratios stay intact.
    if (mode === 'base') commit(base, { ...base, zoom: base.zoom * assets.size.width / assets.topSize.width });
    else commit(constrain({ ...top, zoom: top.zoom * assets.topSize.width / assets.size.width }, assets.size, assets.bounds), top);
  }
  export async function exportCanvas() {
    if (!assets) throw new Error('Obraz lub maska jeszcze się ładuje.');
    const a = assets;
    const [b, t] = await Promise.all([
      qualityLayer(a.src, a.m, base, a.size, sharpen),
      a.topImg ? qualityLayer(a.extra, a.tm, top, a.topSize, sharpen) : null,
    ]);
    return composeLayers(b, t, bgAutoLayer);
  }

  $effect(() => {
    const a = assets, b = base, t = top, target = surface, editing = editMode,
      auto = bgAutoLayer, decoration = ornaments, sharp = sharpen, hq = realPreview, moving = dragging;
    const version = ++renderVersion;
    if (!target) return;
    const ctx = target.getContext('2d');
    ctx.clearRect(0, 0, 475, 667);
    if (!a) return;
    const paint = (main, extra) => {
      const composed = composeLayers(main, extra, auto, decoration);
      ctx.putImageData(composed.getContext('2d').getImageData(0, 0, 475, 667), 0, 0);
    };
    if (!editing) { paint(a.img, null); return; }
    paint(fastLayer(a.img, a.maskImg, b, a.size), a.topImg ? fastLayer(a.topImg, a.topMask, t, a.topSize) : null);
    let timer;
    if (hq && !moving) timer = setTimeout(async () => {
      try {
        const [main, extra] = await Promise.all([qualityLayer(a.src, a.m, b, a.size, sharp), a.topImg ? qualityLayer(a.extra, a.tm, t, a.topSize, sharp) : null]);
        if (version === renderVersion) paint(main, extra);
      } catch { if (version === renderVersion) error = 'Nie udało się wyrenderować podglądu wysokiej jakości.'; }
    }, 120);
    return () => { clearTimeout(timer); renderVersion++; };
  });

  function point(e) {
    const rect = surface.getBoundingClientRect();
    return { x: (e.clientX - rect.left) / scale - 237.5, y: (e.clientY - rect.top) / scale - 333.5 };
  }
  function gesture() {
    const [a, b] = [...pointers.values()];
    return b ? { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, distance: Math.hypot(a.x - b.x, a.y - b.y) } : { ...a, distance: 0 };
  }
  function down(e) {
    if (!editMode || !assets || (e.pointerType === 'mouse' && e.button !== 0)) return;
    e.preventDefault();
    surface.focus({ preventScroll: true });
    surface.setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, point(e));
    dragging = true;
  }
  function motion(e) {
    if (!pointers.has(e.pointerId)) return;
    const before = gesture();
    pointers.set(e.pointerId, point(e));
    const after = gesture();
    update({ dx: after.x - before.x, dy: after.y - before.y, factor: before.distance > 0 ? after.distance / before.distance : 1, anchor: before });
  }
  function up(e) {
    pointers.delete(e.pointerId);
    if (surface.hasPointerCapture(e.pointerId)) surface.releasePointerCapture(e.pointerId);
    dragging = pointers.size > 0;
  }
  function wheel(e) {
    if (!editMode || !assets) return;
    e.preventDefault();
    update({ factor: Math.exp(-e.deltaY * (e.deltaMode === 1 ? 16 : e.deltaMode === 2 ? 667 : 1) * 0.002), anchor: point(e) });
  }
  onMount(() => {
    const measure = () => { available = viewport.clientWidth; dpr = window.devicePixelRatio || 1; };
    const align = () => {
      const rect = wrapper.getBoundingClientRect();
      offset = { x: Math.round(rect.x * dpr) / dpr - rect.x, y: Math.round(rect.y * dpr) / dpr - rect.y };
    };
    let media;
    const watchDpr = () => {
      media?.removeEventListener('change', watchDpr);
      measure();
      media = matchMedia(`(resolution: ${dpr}dppx)`);
      media.addEventListener('change', watchDpr);
    };
    const observer = new ResizeObserver(() => { measure(); align(); });
    observer.observe(viewport); observer.observe(wrapper);
    watchDpr();
    surface.addEventListener('wheel', wheel, { passive: false });
    window.addEventListener('resize', measure);
    window.addEventListener('scroll', align, true);
    return () => {
      observer.disconnect(); media?.removeEventListener('change', watchDpr);
      surface.removeEventListener('wheel', wheel);
      window.removeEventListener('resize', measure); window.removeEventListener('scroll', align, true);
    };
  });
</script>

<div class="preview-viewport" bind:this={viewport}>
  <div class="scale-wrapper" bind:this={wrapper} style:width="{475 * scale}px" style:height="{667 * scale}px">
    <canvas bind:this={surface} width="475" height="667" class:editing={editMode}
      style:width="{475 * scale}px" style:height="{667 * scale}px" style:translate="{offset.x}px {offset.y}px"
      aria-label="Podgląd karty" tabindex="0"
      onpointerdown={down} onpointermove={motion} onpointerup={up} onpointercancel={up} onlostpointercapture={up}></canvas>
  </div>
</div>
{#if zoomLevel === 'fit'}<div class="preview-note">Dopasuj: {Math.round(scale * dpr * 100)}%</div>{/if}
{#if error || ornamentError}<div role="status" class="preview-note">{error || ornamentError}</div>{/if}

<style>
  .preview-viewport { max-width: 100%; width: 100%; overflow: auto; max-height: 85vh; }
  .scale-wrapper { position: relative; margin: 0 auto; }
  canvas { display: block; image-rendering: pixelated; }
  canvas.editing { touch-action: none; cursor: move; }
  .preview-note { font-size: 12px; margin-top: 8px; }
</style>
