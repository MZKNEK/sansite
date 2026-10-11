<script>
  import { onMount, untrack } from 'svelte';
  import CardEditor from './lib/CardEditor.svelte';
  import { forgetPicture, closeRenderer } from '../../shared/lib/CanvasUtils.js';
  import { ownedUrls } from '../../shared/lib/ownedUrls.js';
  import { styleList, frameStyle, frameLayers } from './lib/frames.js';
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

  let editor = $state();
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

  let realPreview = $state(true);
  const urls = ownedUrls(forgetPicture);
  let destroyed = false;
  let zoomLevel = $state(1);
  const previewScales = [
    { value: 1, label: '100%' }, { value: 2, label: '200%' }, { value: 'fit', label: 'Dopasuj' },
  ];
  const syncLayers = () => editor?.sync();
  const moveCrop = (dx, dy) => editor?.move(dx, dy);
  const resetZoom = () => editor?.reset();
  function measureCrops(base, top) {
    pixelCrop = base;
    extraPixelCrop = top;
    isUpscaling = base.width < 475 || base.height < 667;
    isExtraUpscaling = top.width < 475 || top.height < 667;
  }
  onMount(() => {
    fetchVariants();
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      destroyed = true;
      variantsAbort?.abort();
      urls.releaseAll();
      closeRenderer();
    };
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
      crop = { x: 0, y: 0 };
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
      extraCrop = { x: 0, y: 0 };
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
    crop = { x: 0, y: 0 };
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
    if (!editMode || e.defaultPrevented || e.target.closest('input, textarea, select, [contenteditable="true"]')) return;
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) return;
    e.preventDefault();

    const step = e.shiftKey ? 10 : 1;

    if (e.key === 'ArrowLeft')  moveCrop(-step, 0);
    if (e.key === 'ArrowRight') moveCrop(step, 0);
    if (e.key === 'ArrowUp')    moveCrop(0, -step);
    if (e.key === 'ArrowDown')  moveCrop(0, step);
  }

  async function removeBg() {
    if (!image || bgRemoving) return;
    // the picture it starts from: when another is chosen meanwhile, the
    // result is for one no longer there and is dropped
    const source = image;
    const topRead = extraReads;
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
      if (image !== source || extraReads !== topRead) {
        bgProgressLabel = 'Pominięto: w międzyczasie zmienił się obraz lub warstwa Top';
        return;
      }
      extraImage = urls.replace(extraImage, urls.own(URL.createObjectURL(blob)));
      extraReads++;
      activeLayer = 'both';
      bgAutoLayer = true;
      extraCrop = { ...crop };
      extraZoom = curzoom;
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

  const composeCard = () => editor.exportCanvas();

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
                {#if isUpscaling || (hasExtraLayer && extraImage && isExtraUpscaling)}
                  <div class="hint">Obraz jest powiększany ponad rozdzielczość źródłową.</div>
                {/if}
                <div class="hint">Strzałki na klawiaturze też działają, z Shift po 10 px.</div>
                <div class="nudge-actions">
                  <button type="button" class="btn-muted" onclick={resetZoom}>Reset</button>
                  {#if hasExtraLayer && extraImage}
                    <button type="button" class="btn-sync" onclick={syncLayers} title={activeLayer === 'base' ? 'Scalp → Top: kopiuj położenie i szerokość obrazu' : 'Top → Scalp: kopiuj położenie i szerokość obrazu (z ograniczeniem do maski)'}>Sync {activeLayer === 'base' ? 'Scalp → Top' : 'Top → Scalp'}</button>
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
        <CardEditor bind:this={editor} {image} {extraImage} mask={currentMaskUrl} extraMask={extraMaskUrl}
          {hasExtraLayer} {editMode} {activeLayer} {bgAutoLayer} {sharpen} {realPreview}
          border={borderUri} back={backBorderUri} dere={dereUri} stats={showStats ? statsUri : ''}
          {zoomLevel} bind:crop bind:extraCrop bind:curzoom bind:extraZoom onmeasure={measureCrops} />
      </CardDrop>
      <div class="card-actions">
        <Segmented bind:value={zoomLevel} options={previewScales} label="Skala podglądu" />
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

  .card-col { min-width: 0; width: 100%; max-width: 950px; flex: 1; }
  .card-col :global(.card-drop) { width: 100%; min-width: 0; }
</style>
