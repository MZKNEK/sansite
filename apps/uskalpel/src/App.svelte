<script>
  import { onMount, tick, untrack } from 'svelte';
  import Cropper from "svelte-easy-crop";
  import { getCroppedImg, getCroppedCanvas, cropOnScreen, forgetPicture, closeRenderer } from '../../shared/lib/CanvasUtils.js';
  import { ownedUrls } from '../../shared/lib/ownedUrls.js';
  import { styleList, frameStyle, frameLayers } from './lib/frames.js';
  import { CARD, alphaBounds, moveLayers } from './lib/layers.js';
  import Segmented from '../../shared/lib/Segmented.svelte';
  import Switch from '../../shared/lib/Switch.svelte';
  import DropZone from '../../shared/lib/DropZone.svelte';
  import CardDrop from '../../shared/lib/CardDrop.svelte';
  import Select from '../../shared/lib/Select.svelte';
  import LinkField from '../../shared/lib/LinkField.svelte';
  import Header from '../../shared/lib/Header.svelte';
  import Footer from '../../shared/lib/Footer.svelte';
  import GallerySave from '../../shared/lib/GallerySave.svelte';
  import GalleryPick from '../../shared/lib/GalleryPick.svelte';

  // the pictures of the bot's cards, which the site mirrors from its repository (inc/pw.php)
  const pw = '/pw';

  let borders = [ 'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Jota', 'Lambda', 'Omega' ]
  let deres = [ 'Bodere', 'Dandere', 'Deredere', 'Kamidere', 'Kuudere', 'Mayadere', 'Tsundere', 'Yandere', 'Raito', 'Yami', 'Yato' ]

  // the dere's badge, cut out of the bot's picture of it (32x34 px at 221,628), in a 22 px box
  const dereIcon = (dere) => `background-image: url(${pw}/${dere}.png); background-size: 307.4px 431.6px; background-position: -142.4px -406.4px;`;
  let variantsMap = $state({});

  let editMode = $state(false);

  let pixelCrop = $state({ x: 0, y: 0, width: 475, height: 667 });
  let extraPixelCrop = $state({ x: 0, y: 0, width: 475, height: 667 });
  let crop = $state({ x: 0, y: 0 });
  let extraCrop = $state({ x: 0, y: 0 });
  let curzoom = $state(1);
  let extraZoom = $state(1);
  let isUpscaling = $state(false);
  let isExtraUpscaling = $state(false);

  let extraImage = $state(null);
  let activeLayer = $state('base');

  let bgModel = $state('small');
  let bgRemoving = $state(false);
  let bgProgress = $state(0);
  let bgProgressLabel = $state('');
  let bgAutoLayer = $state(false);

  // the crops in % of the pictures: unlike the pixels they are not rounded
  let percentCrop = null;
  let extraPercentCrop = null;
  let baseCropEl = $state(), extraCropEl = $state();
  // what the croppers show; their own numbers only if the screen has none
  const baseCrop = () => cropOnScreen(baseCropEl) ?? percentCrop;
  const topCrop = () => cropOnScreen(extraCropEl) ?? extraPercentCrop;
  // extra sharpening; without it the scaling keeps the picture as it is
  let sharpen = $state(0);
  const sharpenLevels = [
    { value: 0, label: 'Brak', title: 'Wierne skalowanie, bez wyostrzania' },
    { value: 0.3, label: 'Lekkie' },
    { value: 0.6, label: 'Średnie', title: 'Jak dawniej' },
    { value: 1, label: 'Mocne' },
  ];
  const bgModels = [
    { value: 'small', label: 'Szybki (~40 MB)' },
    { value: 'medium', label: 'Dokładny (~80 MB)' },
  ];
  const layers = [
    { value: 'extra', label: 'Top' },
    { value: 'base', label: 'Scalp' },
    { value: 'both', label: 'Obie' },
  ];

  // Real preview: the croppers show the pictures as the browser scales them, so
  // once a crop stops moving, the scaled and masked layers of the saved file
  // are put over them
  let realPreview = $state(true);
  let basePreview = $state('');
  let extraPreview = $state('');
  let previewStale = $state(true);
  let previewTimer;
  // grows with every change the preview has to follow, so a render begun
  // before it, which ends later, is dropped instead of shown as the new one
  let previewToken = 0;

  // the blob: addresses this app made (the previews, the picture without its
  // background), let go when they are replaced and when the app goes
  const urls = ownedUrls(forgetPicture);
  let destroyed = false;

  function schedulePreview() {
    previewStale = true;
    previewToken++;
    clearTimeout(previewTimer);
    if (!editMode || !realPreview || !percentCrop) return;
    previewTimer = setTimeout(updatePreview, 200);
  }

  async function updatePreview() {
    const token = previewToken;
    const withExtra = hasExtraLayer && extraImage && extraPercentCrop;
    const results = await Promise.allSettled([
      getCroppedImg(image, baseCrop(), { ...card, mask: currentMaskUrl, sharpen }),
      withExtra ? getCroppedImg(extraImage, topCrop(), { ...card, mask: extraMaskUrl, sharpen }) : Promise.resolve(''),
    ]);
    const [base, extra] = results.map((r) => r.status === 'fulfilled' && r.value ? urls.own(r.value) : '');
    // a failure leaves the croppers showing the pictures; saving reports it
    if (destroyed || token !== previewToken || results.some((r) => r.status === 'rejected')) {
      urls.release(base);
      urls.release(extra);
      return;
    }
    basePreview = urls.replace(basePreview, base);
    extraPreview = urls.replace(extraPreview, extra);
    previewStale = false;
  }


  // The layers where the croppers show them (lib/layers.js). The croppers
  // only show them: dragging, the wheel and pinching are the card's own
  // (below), so in Obie both layers move alike, by as much as the pointer
  // moved on the card at whatever scale it is shown.
  const layersNow = () => ({
    base: { x: crop.x, y: crop.y, zoom: curzoom },
    extra: { x: extraCrop.x, y: extraCrop.y, zoom: extraZoom },
  });
  function setLayers({ base, extra }) {
    if (base.x !== crop.x || base.y !== crop.y) crop = { x: base.x, y: base.y };
    if (base.zoom !== curzoom) curzoom = base.zoom;
    if (extra.x !== extraCrop.x || extra.y !== extraCrop.y) extraCrop = { x: extra.x, y: extra.y };
    if (extra.zoom !== extraZoom) extraZoom = extra.zoom;
  }
  // the layers that move: Top has none to move without its picture
  let moving = $derived(hasExtraLayer && extraImage ? activeLayer : 'base');

  // the size of a cropper's picture on the card at zoom 1, as svelte-easy-crop
  // lays it out (contained in the card, never enlarged)
  function shownSize(el) {
    const img = el?.querySelector('img');
    return img?.complete && img.width ? { width: img.width, height: img.height } : null;
  }
  // what Scalp has to cover: null until its picture and its mask are there
  function scalpLimits() {
    const size = shownSize(baseCropEl);
    return size && maskBounds ? { size, bounds: maskBounds } : null;
  }

  // one move of the layers being moved; with nothing to move it only brings
  // Scalp back over its frame's opening (with Top in Obie)
  function changeLayers(change = {}, which = moving) {
    setLayers(moveLayers(layersNow(), which, change, scalpLimits()));
  }
  // Scalp back over the opening when something else moved it: a new picture
  // (Scalp alone), another frame (with Top in Obie, so they stay together)
  const keepScalp = (which = 'base') => changeLayers({}, which);
  $effect(() => {
    maskBounds;
    untrack(() => keepScalp(moving === 'both' ? 'both' : 'base'));
  });

  // Sync puts one layer exactly over the other, the same part of the same
  // picture at the same size even when Top's file is larger or smaller:
  // Top onto Scalp while Scalp is moved, else Scalp onto Top
  function syncLayers() {
    const baseShown = shownSize(baseCropEl), extraShown = shownSize(extraCropEl);
    if (!baseShown || !extraShown) return;
    const ratio = baseShown.width / extraShown.width;
    const { base, extra } = layersNow();
    if (activeLayer === 'base') {
      setLayers({ base, extra: { x: base.x, y: base.y, zoom: base.zoom * ratio } });
    } else {
      // Scalp may have to stay over the opening, and Top follows it there
      const synced = { x: extra.x, y: extra.y, zoom: extra.zoom / ratio };
      setLayers({ base: synced, extra });
      changeLayers({}, 'both');
    }
  }

  function moveCrop(dx, dy) {
    changeLayers({ dx, dy });
  }

  // the whole card, which the masks of the frames cut
  const card = CARD;
  const maskCropSize = card;

  // The opening of the frame's mask, which Scalp has to cover. Read from the
  // mask itself, once for each frame.
  let maskBounds = $state(null);
  const maskBoundsOf = new Map();
  async function readMaskBounds(url) {
    const img = new Image();
    img.src = url;
    await img.decode();
    const canvas = document.createElement('canvas');
    canvas.width = card.width;
    canvas.height = card.height;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0, card.width, card.height);
    return alphaBounds(ctx.getImageData(0, 0, card.width, card.height));
  }
  $effect(() => {
    const url = currentMaskUrl;
    let gone = false;
    maskBounds = maskBoundsOf.get(url) ?? null;
    if (!maskBoundsOf.has(url)) {
      readMaskBounds(url).then((bounds) => {
        maskBoundsOf.set(url, bounds);
        if (!gone) maskBounds = bounds;
      }, (error) => console.error('mask:', url, error));
    }
    return () => { gone = true; };
  });


  // The card is shown at exactly 100% or 200%: one or two pixels of the
  // screen for each pixel of the card, whatever the screen's own scaling
  // (dpr), and starting on a whole pixel of the screen, so the browser does
  // not resample the layers and the frame each on its own and they meet as
  // in the saved file. 200% wider than the screen gives way to 100%; only a
  // screen narrower than the card at 100% gets it scaled down to fit.
  let dpr = $state((typeof window !== 'undefined') ? window.devicePixelRatio : 1);
  let zoomLevel = $state((typeof window !== 'undefined' && window.innerWidth < 900) ? 2 : 1);
  // the width the card can take: the page's, less its side margins
  let room = $state(typeof window !== 'undefined' ? document.documentElement.clientWidth - 32 : 1248);
  const fits = (level) => card.width * level / dpr <= room + 0.01;
  // 1 or 2, 0 for scaled to fit
  let shownLevel = $derived(fits(zoomLevel) ? zoomLevel : fits(1) ? 1 : 0);
  let shownScale = $derived(shownLevel ? shownLevel / dpr : room / card.width);
  // how far the card moves to start on a whole pixel of the screen
  let snap = $state({ x: 0, y: 0 });
  let snapFrame = 0;

  function alignToPixels() {
    cancelAnimationFrame(snapFrame);
    snapFrame = requestAnimationFrame(() => {
      if (!wrapperRef) return;
      const r = wrapperRef.getBoundingClientRect();
      const d = window.devicePixelRatio || 1;
      const x = Math.round(r.left * d) / d - r.left, y = Math.round(r.top * d) / d - r.top;
      if (Math.abs(x - snap.x) > 1e-3 || Math.abs(y - snap.y) > 1e-3) snap = { x, y };
    });
  }

  onMount(() => {
    calculateScaling();
    fetchVariants();

    // the layout moving the card, the page's width with or without its
    // scroll bar, scrolling it (it stays at the top of the screen)
    const layout = new ResizeObserver(() => { calculateScaling(); alignToPixels(); });
    layout.observe(document.documentElement);
    if (wrapperRef) layout.observe(wrapperRef);
    window.addEventListener('resize', calculateScaling);
    window.addEventListener('scroll', alignToPixels, { passive: true });
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      layout.disconnect();
      cancelAnimationFrame(snapFrame);
      window.removeEventListener('resize', calculateScaling);
      window.removeEventListener('scroll', alignToPixels);
      window.removeEventListener('keydown', handleKeyDown);
      // what is still under way finds the app gone and leaves its result
      destroyed = true;
      clearTimeout(previewTimer);
      previewToken++;
      variantsAbort?.abort();
      urls.releaseAll();
      closeRenderer();
    };
  });

  function calculateScaling() {
    dpr = window.devicePixelRatio || 1;
    room = document.documentElement.clientWidth - 32;
    alignToPixels();
  }

  // Dragging (mouse, pen, a finger), pinching and the wheel on the card move
  // the layers picked, in pixels of the card from its centre
  let looksEl = $state();
  const pointers = new Map();
  function cardPoint(e) {
    const r = looksEl.getBoundingClientRect();
    const scale = r.width / card.width;
    return { x: (e.clientX - r.left) / scale - card.width / 2, y: (e.clientY - r.top) / scale - card.height / 2 };
  }
  // where the pointers are together and how far apart two of them are
  function gesture() {
    const [a, b] = [...pointers.values()];
    return b ? { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, apart: Math.hypot(a.x - b.x, a.y - b.y) } : { ...a, apart: 0 };
  }
  function onPointerDown(e) {
    if (!editMode || (e.pointerType === 'mouse' && e.button !== 0) || pointers.size >= 2) return;
    e.preventDefault();
    looksEl.setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, cardPoint(e));
  }
  function onPointerMove(e) {
    if (!pointers.has(e.pointerId)) return;
    const before = gesture();
    pointers.set(e.pointerId, cardPoint(e));
    const after = gesture();
    changeLayers({
      dx: after.x - before.x,
      dy: after.y - before.y,
      factor: before.apart && after.apart ? after.apart / before.apart : 1,
      anchor: before,
    });
  }
  function onPointerUp(e) {
    pointers.delete(e.pointerId);
  }
  // the wheel zooms as svelte-easy-crop's did, around the pointer; in Obie
  // Scalp's zoom leads
  function onWheel(e) {
    if (!editMode) return;
    e.preventDefault();
    const lead = layersNow()[moving === 'extra' ? 'extra' : 'base'];
    const zoom = Math.max(lead.zoom - e.deltaY * 0.02 / 200, 0.01);
    changeLayers({ factor: zoom / lead.zoom, anchor: cardPoint(e) });
  }
  $effect(() => {
    const el = looksEl;
    if (!el) return;
    el.addEventListener('wheel', onWheel, { passive: false });
    return () => el.removeEventListener('wheel', onWheel);
  });
  // where the card is changes with what the panel shows
  $effect(() => {
    editMode, shownScale;
    tick().then(alignToPixels);
  });

  // How many styles each frame has: the site reads it from the bot's code
  // with the pictures (inc/pw.php), as { Delta: 8, ... }. It comes while the
  // editor is already in use, so only the styles change: the picture, the
  // crop and what was picked stay, and a style the frame does not have goes
  // back to 0 (frameStyle() in lib/frames.js). A failure says so and can be tried again.
  let variantsError = $state('');
  let variantsAbort = null;

  async function fetchVariants() {
    variantsAbort?.abort();
    const abort = variantsAbort = new AbortController();
    variantsError = '';
    try {
      const response = await fetch(`${pw}/variants.json`, { signal: abort.signal });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      variantsMap = await response.json();
    } catch (error) {
      if (!abort.signal.aborted)
        variantsError = 'Nie udało się pobrać stylów ramek.';
    }
  }

  let image = $state("https://sanakan.pl/i/ss/sUwh3io.webp");
  let isLocalFile = $state(false);
  let showStats = $state(false);
  let fileName = $state('');
  let extraFileName = $state('');

  let selectedBorder = $state('Delta');
  let selectedDere = $state('Mayadere');
  let selectedStyle = $state('2')

  let wrapperRef = $state();

  // the croppers call these with the crop itself (svelte-easy-crop 5 has no
  // events), from inside their own effect: untracked, or what they read and set
  // here would become what that effect depends on and run it again without end
  const onCrop = (e) => untrack(() => previewCrop(e));
  const onExtraCrop = (e) => untrack(() => previewExtraCrop(e));
  function previewCrop(e) {
    // after the cropper's own effect, which this is called from
    queueMicrotask(keepScalp);
    pixelCrop = e.pixels;
    percentCrop = e.percent;
    schedulePreview();
    isUpscaling = pixelCrop.width < 475 || pixelCrop.height < 667;
  }

  function previewExtraCrop(e) {
    extraPixelCrop = e.pixels;
    extraPercentCrop = e.percent;
    schedulePreview();
    isExtraUpscaling = extraPixelCrop.width < 475 || extraPixelCrop.height < 667;
  }

  // every file asked for counts, so one read more slowly than the next does
  // not put itself in its place when it ends
  let imageReads = 0;
  let extraReads = 0;

  function processFile(imageFile) {
    if (!imageFile.type.startsWith('image/')) {
      alert('Proszę przeciągnąć plik obrazu JPG lub PNG.');
      return;
    }
    const read = ++imageReads;
    let reader = new FileReader();
    reader.onload = e => {
      if (destroyed || read !== imageReads) return;
      image = e.target.result;
      curzoom = 1;
    };
    reader.readAsDataURL(imageFile);
  }

  function processExtraFile(file) {
    if (!file) return;
    const read = ++extraReads;
    let reader = new FileReader();
    reader.onload = e => {
      if (destroyed || read !== extraReads) return;
      extraImage = urls.replace(extraImage, e.target.result);
      activeLayer = 'extra';
      bgAutoLayer = false;
      extraZoom = 1;
    };
    reader.readAsDataURL(file);
  }

  // a file from the drop zone or dropped onto the card
  // a picture of the account's own folder in the gallery, by its link
  function onPick(url) {
    imageReads++;
    image = url;
    fileName = '';
    isLocalFile = false;
    curzoom = 1;
  }

  function onFile(file) {
    fileName = file.name;
    isLocalFile = true;
    processFile(file);
  }

  function onExtraFile(file) {
    extraFileName = file.name;
    processExtraFile(file);
  }

  let borderUri = $state("");
  let backBorderUri = $state("");
  let statsUri = $state("");
  let dereUri = $state("");

  // the pictures of the frame (lib/frames.js); a style the frame does not
  // have goes back to 0
  function updateData() {
    const style = frameStyle(selectedBorder, selectedStyle, variantsMap);
    if (!style.fits) selectedStyle = '0';
    const layers = frameLayers(pw, selectedBorder, selectedDere, style.uri);
    borderUri = layers.border;
    backBorderUri = layers.backBorder;
    statsUri = layers.stats;
    dereUri = layers.dere;
  }

  function handleKeyDown(e) {
    if (!editMode) return;

    const step = e.shiftKey ? 10 : 1;

    if (e.key === 'ArrowLeft')  moveCrop(-step, 0);
    if (e.key === 'ArrowRight') moveCrop(step, 0);
    if (e.key === 'ArrowUp')    moveCrop(0, -step);
    if (e.key === 'ArrowDown')  moveCrop(0, step);
  }

  // the layers moved back to the middle at zoom 1, Scalp as near to it as
  // still covers the opening (and Top in Obie as Scalp)
  function resetZoom() {
    const start = { x: 0, y: 0, zoom: 1 };
    const { base, extra } = layersNow();
    setLayers({ base: moving === 'extra' ? base : start, extra: moving === 'base' ? extra : start });
    changeLayers();
  }

  async function removeBg() {
    if (!image || bgRemoving) return;
    // the picture it starts from: when another is chosen meanwhile, the
    // result is for one no longer there and is dropped
    const source = image;
    bgRemoving = true;
    bgProgress = 0;
    bgProgressLabel = 'Inicjalizacja...';
    try {
      const imglyModule = await import('@imgly/background-removal');
      const removeBackground = imglyModule.default ?? imglyModule.removeBackground ?? imglyModule;
      const blob = await removeBackground(source, {
        model: bgModel,
        progress: (key, current, total) => {
          if (total > 0) {
            bgProgress = Math.round((current / total) * 100);
            bgProgressLabel = key.includes('fetch') ? 'Pobieranie modelu...' : 'Przetwarzanie...';
          }
        }
      });
      if (destroyed) return;
      if (image !== source) {
        bgProgressLabel = 'Pominięto: w międzyczasie zmienił się obraz';
        return;
      }
      extraImage = urls.replace(extraImage, urls.own(URL.createObjectURL(blob)));
      extraReads++;
      activeLayer = 'both';
      bgAutoLayer = true;
      bgProgressLabel = 'Gotowe!';
    } catch (e) {
      console.error('removeBg error:', e);
      bgProgressLabel = 'Błąd: ' + (e?.message || 'sprawdź konsolę');
      bgRemoving = false;
      bgProgress = 0;
    } finally {
      if (bgRemoving) {
        bgRemoving = false;
        bgProgress = 0;
      }
    }
  }

  // the card as it is saved: its layers, cropped and masked, on one canvas
  async function composeCard() {
    const canvas = document.createElement('canvas');
    canvas.width = 475;
    canvas.height = 667;
    const ctx = canvas.getContext('2d');

    // Kolejność warstw: gdy bgAutoLayer (removeBg), scalp jest nad top
    // Normalnie: top jest nad scalp. The layers are drawn straight from their
    // canvases, with no blob: address in between to let go of.
    const imgMain = editMode
      ? await getCroppedCanvas(image, baseCrop(), { ...card, mask: currentMaskUrl, sharpen })
      : await loadImg(image);

    if (hasExtraLayer && extraImage) {
      const imgExtra = await getCroppedCanvas(extraImage, topCrop(), { ...card, mask: extraMaskUrl, sharpen });
      if (bgAutoLayer) {
        // removeBg: najpierw top (wycięta postać), potem scalp (oryginał z maską) na wierzchu
        ctx.drawImage(imgExtra, 0, 0);
        ctx.drawImage(imgMain, 0, 0);
      } else {
        // normalnie: scalp, potem top
        ctx.drawImage(imgMain, 0, 0);
        ctx.drawImage(imgExtra, 0, 0);
      }
    } else {
      ctx.drawImage(imgMain, 0, 0);
    }
    return canvas;
  }

  // its pixels, for the gallery
  async function cardPixels() {
    const canvas = await composeCard();
    return canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
  }

  async function downloadImage() {
    try {
      const canvas = await composeCard();
      // Finalizacja pobierania
      const finalUrl = canvas.toDataURL('image/png');
      const a = document.createElement('a');
      a.href = finalUrl;
      a.download = `composition-${selectedBorder}.png`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    } catch (error) {
      console.error('Błąd pobierania:', error);
      alert('Nie udało się przygotować obrazka, spróbuj z innym lub użyj lokalnego pliku.');
    }
  }
  function loadImg(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = "anonymous";
      img.onload = () => resolve(img);
      img.onerror = (err) => {
        console.error("Błąd ładowania obrazu: " + src, err);
        reject(err);
      };
      img.src = src;
    });
  }

  let currentMaskUrl = $derived(`${import.meta.env.BASE_URL}masks/${selectedBorder}.webp`);
  let extraMaskUrl = $derived(`${import.meta.env.BASE_URL}masks/${selectedBorder}_top.webp`);
  let hasExtraLayer = $derived(['Delta', 'Eta', 'Omega'].includes(selectedBorder));
  $effect.pre(() => {
    if (!hasExtraLayer)  {
      activeLayer = 'base';
    }
  });
  let styles = $derived(Object.keys(variantsMap).length ? styleList(selectedBorder, variantsMap) : []);
  // the pictures of the card when the frame, the dere, the style or the
  // styles of the frames change: each read, as an effect follows only what it
  // reads; updateData() may put the style back to 0, so it runs untracked
  $effect.pre(() => {
    selectedBorder, selectedDere, selectedStyle, variantsMap;
    untrack(updateData);
  });
  // a new preview when what it shows changes (the crops ask for one themselves)
  $effect(() => {
    sharpen, realPreview, editMode, image, extraImage, currentMaskUrl, extraMaskUrl, hasExtraLayer;
    untrack(schedulePreview);
  });
</script>

<Header title="USkalpelator" />

<main class="content">
  <div class="app-layout">
    <div class="panel">
      <section class="group">
        <h2 class="group-title"><i>01</i>Karta</h2>
        <div class="field top"><span class="label">Ramka</span><Segmented bind:value={selectedBorder} options={borders} label="Ramka" words /></div>
        <div class="field"><span class="label">Dere</span><Select bind:value={selectedDere} options={deres} label="Dere" icon={dereIcon} /></div>
        {#if styles}
          <div class="field top"><span class="label">Styl</span><Segmented bind:value={selectedStyle} options={styles} label="Styl ramki" /></div>
        {/if}
        {#if variantsError}
          <div class="field"><span class="label"></span>
            <div class="variants-error">
              <span class="bg-status bad">✗ {variantsError}</span>
              <button type="button" class="btn-muted" onclick={fetchVariants}>Spróbuj ponownie</button>
            </div>
          </div>
        {/if}
        <Switch label="Pokaż statystyki" bind:checked={showStats} />
      </section>

      <section class="group">
        <h2 class="group-title"><i>02</i>Obraz</h2>
        <DropZone bind:fileName onfile={onFile} accept=".jpg, .jpeg, .png, .webp" />
        <GalleryPick onpick={onPick} />
        {#if !isLocalFile}
          <div class="field"><span class="label">Link do obrazka</span><LinkField bind:value={image} label="Link do obrazka" placeholder="https://…" /></div>
        {/if}
      </section>

      <section class="group">
        <h2 class="group-title"><i>03</i>Edycja</h2>
        <Switch label="Tryb edycji" bind:checked={editMode} />
        {#if editMode}
          <div class="field"><span class="label">Wyostrzenie</span><Segmented bind:value={sharpen} options={sharpenLevels} label="Wyostrzenie" words /></div>
          <Switch label="Podgląd wyniku" bind:checked={realPreview}
            title="Po puszczeniu kadru pokazuje go przeskalowanego dokładnie tak, jak w zapisanym pliku" />

          {#if hasExtraLayer}
            <DropZone compact title="Warstwa top" hint="obraz nad ramką: przeciągnij albo kliknij" accept=".jpg, .jpeg, .png, .webp"
              bind:fileName={extraFileName} onfile={onExtraFile} />
            <div class="field"><span class="label">Usuń tło</span><Segmented bind:value={bgModel} options={bgModels} label="Model usuwania tła" words disabled={bgRemoving} /></div>
            <div class="field"><span class="label"></span>
              <div class="bg-tools">
                <button type="button" class="btn-ai" onclick={removeBg} disabled={bgRemoving}>
                  {bgRemoving ? bgProgressLabel : 'Usuń tło ze scalpa'}
                </button>
                {#if bgRemoving}
                  <div class="bg-progress"><div class="bg-progress-bar" style="width: {bgProgress}%"></div></div>
                {/if}
                {#if !bgRemoving && bgProgressLabel}
                  <div class="bg-status" class:bad={bgProgressLabel.startsWith('Błąd')}>
                    {bgProgressLabel.startsWith('Błąd') ? '✗ ' : '✓ '}{bgProgressLabel}
                  </div>
                {/if}
              </div>
            </div>
            {#if extraImage}
              <div class="field"><span class="label">Przesuwasz</span><Segmented bind:value={activeLayer} options={layers} label="Warstwa do przesuwania" words /></div>
            {/if}
          {/if}

          <div class="field top"><span class="label">Położenie</span>
            <div class="nudge">
              <div class="dpad">
                <button type="button" class="up" title="W górę" onclick={() => moveCrop(0, -1)}>▲</button>
                <button type="button" class="left" title="W lewo" onclick={() => moveCrop(-1, 0)}>◀</button>
                <button type="button" class="down" title="W dół" onclick={() => moveCrop(0, 1)}>▼</button>
                <button type="button" class="right" title="W prawo" onclick={() => moveCrop(1, 0)}>▶</button>
              </div>
              <div class="nudge-side">
                <div class="info-label">SCALP X {Math.round(crop.x)} · Y {Math.round(crop.y)} · {Math.round(pixelCrop.width)}×{Math.round(pixelCrop.height)}</div>
                {#if hasExtraLayer && extraImage}
                  <div class="info-label">TOP&nbsp;&nbsp; X {Math.round(extraCrop.x)} · Y {Math.round(extraCrop.y)} · {Math.round(extraPixelCrop.width)}×{Math.round(extraPixelCrop.height)}</div>
                {/if}
                <div class="hint">Strzałki na klawiaturze też działają, z Shift po 10 px.</div>
                <div class="nudge-actions">
                  <button type="button" class="btn-muted" onclick={resetZoom}>Reset</button>
                  {#if hasExtraLayer && extraImage}
                    <button type="button" class="btn-sync" onclick={syncLayers}
                      title={activeLayer === 'base' ? 'Ustaw Top dokładnie na Scalpie' : 'Ustaw Scalp dokładnie pod Topem'}>Sync</button>
                  {/if}
                </div>
              </div>
            </div>
          </div>
        {/if}
      </section>
    </div>

    <div class="card-col">
      <CardDrop onfile={onFile}>
    <div class="scale-wrapper" bind:this={wrapperRef} style="width: {475 * shownScale}px; height: {667 * shownScale}px;">
      <div class="looks" class:is-editing={editMode} class:fitted={!shownLevel} bind:this={looksEl}
        style="transform: translate({snap.x}px, {snap.y}px) scale({shownScale});"
        onpointerdown={onPointerDown} onpointermove={onPointerMove} onpointerup={onPointerUp} onpointercancel={onPointerUp}
        onlostpointercapture={onPointerUp}>
        {#if editMode && hasExtraLayer && extraImage}
          <div class="cropper-container top" class:under-real={realPreview && extraPreview && !previewStale} bind:this={extraCropEl}
              style="--mask-url: url({extraMaskUrl});">
            <Cropper 
              showGrid={false}
              image={extraImage} 
              bind:zoom={extraZoom} 
              bind:crop={extraCrop}
              aspect={475/667}
              minZoom={0.1}
              maxZoom={10}
              zoomSpeed={0.02}
              cropSize={maskCropSize}
              oncropcomplete={onExtraCrop}
              restrictPosition={false}
            />
          </div>
          {#if isExtraUpscaling}
            <div class="upscale-border"></div>
          {/if}
          {#if realPreview && extraPreview}
            <img src={extraPreview} class="real real-top" class:stale={previewStale} alt="" />
          {/if}
        {/if}

          {#if backBorderUri}
            <img src={backBorderUri} class="back" alt="BorderBack" />
          {/if}

          {#if editMode}
            <div class="cropper-container" class:under-real={realPreview && basePreview && !previewStale} bind:this={baseCropEl}
                style="--mask-url: url({currentMaskUrl});">
              <Cropper 
                showGrid={false}
                {image} 
                bind:zoom={curzoom} 
                bind:crop={crop} 
                minZoom={0.1}
                maxZoom={10}
                zoomSpeed={0.02}
                aspect={475/667}
                cropSize={maskCropSize} 
                oncropcomplete={onCrop} 
                restrictPosition={false} 
              />
            </div>
            {#if realPreview && basePreview}
              <img src={basePreview} class="real real-base" class:stale={previewStale} alt="" />
            {/if}
            <div class="crop-border"></div>
            {#if isUpscaling}
              <div class="upscale-border"></div>
            {/if}
          {:else}
            <img src={image} class="scalp" alt="Scalpel" />
          {/if}

          {#if borderUri}
            <img src={borderUri} class="border" alt="Border" />
          {/if}

          {#if dereUri}
            <img src={dereUri} class="dere" alt="Dere" />
          {/if}

          {#if showStats && statsUri}
            <img src={statsUri} class="stats" alt="Stats" />
          {/if}

      </div>
    </div>
      </CardDrop>
      <div class="card-actions">
        <button type="button" onclick={() => zoomLevel = shownLevel === 1 ? 2 : 1} disabled={!fits(2)}
          title={fits(2) ? 'Dokładnie 1 albo 2 piksele ekranu na piksel karty' : '200% nie mieści się na tym ekranie'}
          >Skala: {shownLevel ? `${shownLevel * 100}%` : 'dopasowana'}</button>
        {#if editMode}
          <button type="button" class="btn-go" onclick={downloadImage}>Pobierz obrazek</button>
          <GallerySave pixels={cardPixels} prefix={'uskalpel-' + selectedBorder} />
        {/if}
      </div>
    </div>
  </div>
</main>

<Footer />

<style>
  /* removing the background: the button, then its progress or result */
  .bg-tools {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }

  /* the loading bar of the Safeguard scanner */
  .bg-progress {
    width: 100%;
    height: 6px;
    background: rgba(var(--accent-rgb), 0.15);
    overflow: hidden;
  }

  .bg-progress-bar {
    height: 100%;
    background: var(--accent);
    box-shadow: 0 0 8px rgba(var(--accent-rgb), 0.7);
    transition: width 0.3s ease;
  }

  .bg-status {
    font: 12px "Sanakan Mono", monospace;
    letter-spacing: 0.1em;
    color: var(--ok);
  }

  .bg-status.bad {
    color: var(--bad);
  }

  /* the styles of the frames that did not come, and a way to ask again */
  .variants-error {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }

  .variants-error button {
    padding: 4px 14px;
    font-size: 12px;
  }

  /* moving the crop pixel by pixel: arrows laid out as on a keyboard, the
     position beside them */
  .nudge {
    display: flex;
    align-items: flex-start;
    gap: 16px;
  }

  .dpad {
    display: grid;
    grid-template-columns: repeat(3, 34px);
    grid-template-rows: repeat(2, 30px);
    gap: 4px;
    flex: none;
  }

  /* small buttons, so shorter corners */
  .dpad button {
    --arm: 6px;
    padding: 0;
    letter-spacing: 0;
  }

  .dpad button:hover:not(:disabled) {
    --arm: 9px;
  }

  .dpad .up { grid-area: 1 / 2; }
  .dpad .left { grid-area: 2 / 1; }
  .dpad .down { grid-area: 2 / 2; }
  .dpad .right { grid-area: 2 / 3; }

  .nudge-side {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
  }

  .info-label {
    font: 12px "JetBrains Mono", Consolas, monospace;
    color: rgba(220, 221, 222, 0.55);
    white-space: nowrap;
  }

  .hint {
    font-size: 12px;
    color: rgba(220, 221, 222, 0.45);
  }

  .nudge-actions {
    display: flex;
    gap: 8px;
    margin-top: 6px;
  }

  .nudge-actions button {
    padding: 4px 14px;
    font-size: 12px;
  }

  .scale-wrapper {
    display: flex;
    justify-content: flex-start;
    align-items: flex-start;
    overflow: visible;
    transition: width 0.2s, height 0.2s;
  }

  .looks {
    position: relative;
    width: 475px; 
    height: 667px; 
    background-color: transparent;
    transform-origin: top left;
    flex-shrink: 0;
  }

  /* the card takes the pointer: the croppers under it only show the layers */
  .looks.is-editing {
    cursor: move;
    touch-action: none;
    user-select: none;
    -webkit-user-select: none;
  }

  .looks img {
    position: absolute;
    top: 0;
    left: 0;
    width: 475px;
    height: 667px;
    pointer-events: none;
    display: block;
    image-rendering: pixelated;
  }

  /* scaled to fit a narrow screen, by no whole number: smoothly */
  .looks.fitted img {
    image-rendering: auto;
  }

  .back { z-index: 10; }
  .scalp, .cropper-container:not(.top) { z-index: 20; }
  .top, .cropper-container.top { z-index: 30; }
  .border { z-index: 40; }
  /* the real preview of each layer, right over its cropper; it lets the mouse
     through and hides while a crop moves */
  .real-base { z-index: 25; }
  .real-top { z-index: 35; }
  .real.stale { visibility: hidden; }
  /* under the real preview the cropper's own picture would show through
     transparent parts; it stays there, unseen, for the mouse */
  .cropper-container.under-real :global(img) { opacity: 0; }
  .dere { z-index: 50; }
  .stats { z-index: 60; }

  .upscale-border {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    outline: 4px solid var(--bad);
    outline-offset: -4px;
    pointer-events: none;
    z-index: 999;
  }

  /* svelte-easy-crop asks for a layer of its own for the picture, and the
     layers over it (the frame, the dere) then get theirs, which the browser
     puts down on a half pixel: blurred, with a seam where they meet the
     layers under the picture. Painted with the rest of the card, everything
     lands on whole pixels. */
  :global(.svelte-easy-crop-image) {
    will-change: auto !important;
  }

  /* Ukryj wbudowaną linię obszaru Croppera - zastępujemy własnym divem poza maską */
  :global(.svelte-easy-crop-area) {
    border: none !important;
    box-shadow: none !important;
    color: transparent !important;
  }

  .crop-border {
    position: absolute;
    top: 0;
    left: 0;
    width: 475px;
    height: 667px;
    outline: 1px solid rgba(var(--accent-light-rgb), 0.6);
    outline-offset: -1px;
    pointer-events: none;
    z-index: 999;
  }

  .cropper-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 475px;
    height: 667px;
    pointer-events: none;
  }

  .looks.is-editing .cropper-container {
    -webkit-mask-image: var(--mask-url);
    mask-image: var(--mask-url);
    -webkit-mask-size: 100% 100%;
    mask-size: 100% 100%;
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
  }
</style>
