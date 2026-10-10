<script>
  import { onMount, tick } from 'svelte';
  import Cropper from "svelte-easy-crop";
  import { getCroppedImg, cropOnScreen } from './lib/CanvasUtils.js';
  import Segmented from './lib/Segmented.svelte';
  import Switch from './lib/Switch.svelte';
  import DropZone from './lib/DropZone.svelte';
  import CardDrop from './lib/CardDrop.svelte';
  import Select from './lib/Select.svelte';
  import LinkField from './lib/LinkField.svelte';


  let borders = [ 'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Jota', 'Lambda', 'Omega' ]
  let deres = [ 'Bodere', 'Dandere', 'Deredere', 'Kamidere', 'Kuudere', 'Mayadere', 'Tsundere', 'Yandere', 'Raito', 'Yami', 'Yato' ]

  // the dere's badge, cut out of the bot's picture of it (32x34 px at 221,628), in a 22 px box
  const dereIcon = (dere) => `background-image: url(https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/${dere}.png); background-size: 307.4px 431.6px; background-position: -142.4px -406.4px;`;
  let variantsMap = {};
  
  let editMode = false;

  let pixelCrop = { x: 0, y: 0, width: 475, height: 667 };
  let extraPixelCrop = { x: 0, y: 0, width: 475, height: 667 };
  let crop = { x: 0, y: 0 };
  let extraCrop = { x: 0, y: 0 };
  let curzoom = 1;
  let extraZoom = 1;
  let isUpscaling = false;
  let isExtraUpscaling = false;

  let extraImage = null;
  let activeLayer = 'base';

  let bgModel = 'small';
  let bgRemoving = false;
  let bgProgress = 0;
  let bgProgressLabel = '';
  let bgAutoLayer = false;

  const year = new Date().getFullYear();

  // the crops in % of the pictures: unlike the pixels they are not rounded
  let percentCrop = null;
  let extraPercentCrop = null;
  let baseCropEl, extraCropEl;
  // what the croppers show; their own numbers only if the screen has none
  const baseCrop = () => cropOnScreen(baseCropEl) ?? percentCrop;
  const topCrop = () => cropOnScreen(extraCropEl) ?? extraPercentCrop;
  // extra sharpening; without it the scaling keeps the picture as it is
  let sharpen = 0;
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
  let realPreview = true;
  let basePreview = '';
  let extraPreview = '';
  let previewStale = true;
  let previewTimer;
  let previewToken = 0;

  function schedulePreview() {
    previewStale = true;
    clearTimeout(previewTimer);
    if (!editMode || !realPreview || !percentCrop) return;
    previewTimer = setTimeout(updatePreview, 200);
  }

  async function updatePreview() {
    const token = ++previewToken;
    try {
      const withExtra = hasExtraLayer && extraImage && extraPercentCrop;
      const [base, extra] = await Promise.all([
        getCroppedImg(image, baseCrop(), currentMaskUrl, sharpen),
        withExtra ? getCroppedImg(extraImage, topCrop(), extraMaskUrl, sharpen) : '',
      ]);
      if (token !== previewToken) {
        URL.revokeObjectURL(base);
        if (extra) URL.revokeObjectURL(extra);
        return;
      }
      if (basePreview) URL.revokeObjectURL(basePreview);
      if (extraPreview) URL.revokeObjectURL(extraPreview);
      basePreview = base;
      extraPreview = extra;
      previewStale = false;
    } catch (error) {
      // the croppers still show the pictures; saving reports the error
    }
  }

  
  async function syncLayers() {
    if (activeLayer === 'extra' || activeLayer === 'both') {
      // top jest dowodzący -> kopiuj top na scalp
      crop = { x: extraCrop.x, y: extraCrop.y };
      curzoom = extraZoom;
      await tick();
      curzoom = curzoom + 0.000001;
      await tick();
      curzoom = curzoom - 0.000001;
    } else {
      // scalp jest dowodzący -> kopiuj scalp na top
      extraCrop = { x: crop.x, y: crop.y };
      extraZoom = curzoom;
      await tick();
      extraZoom = extraZoom + 0.000001;
      await tick();
      extraZoom = extraZoom - 0.000001;
    }
  }

  async function moveCrop(dx, dy) {
    if (activeLayer === 'base' || activeLayer === 'both') {
      crop.x = Math.round(crop.x) + dx;
      crop.y = Math.round(crop.y) + dy;
      // Wymuś emisję cropcomplete przez mikrozmianę zoom
      await tick();
      curzoom = curzoom + 0.000001;
      await tick();
      curzoom = curzoom - 0.000001;
    }
    if (activeLayer === 'extra' || activeLayer === 'both') {
      extraCrop.x = Math.round(extraCrop.x) + dx;
      extraCrop.y = Math.round(extraCrop.y) + dy;
      await tick();
      extraZoom = extraZoom + 0.000001;
      await tick();
      extraZoom = extraZoom - 0.000001;
    }
  }

  const maskCropSize = { width: 475, height: 667 };

  $: currentMaskUrl = `${import.meta.env.BASE_URL}masks/${selectedBorder}.png`;
  $: extraMaskUrl = `${import.meta.env.BASE_URL}masks/${selectedBorder}_top.png`;
  $: hasExtraLayer = ['Delta', 'Eta', 'Omega'].includes(selectedBorder);
  $: if (!hasExtraLayer)  {
    activeLayer = 'base';
  }
  
  let dpr = (typeof window !== 'undefined') ? window.devicePixelRatio : 1;
  let zoomLevel = (typeof window !== 'undefined' && window.innerWidth < 900) ? 2 : 1;

  $: finalScale = (1 / dpr) * zoomLevel;
  // but never wider than the screen, less its side margins
  let winWidth = typeof window !== 'undefined' ? window.innerWidth : 1280;
  $: shownScale = Math.min(finalScale, (winWidth - 32) / 475);

  onMount(() => {
    calculateScaling();

    window.addEventListener('resize', calculateScaling);
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('resize', calculateScaling);
      window.removeEventListener('keydown', handleKeyDown);
    };
  });

  function calculateScaling() {
    dpr = window.devicePixelRatio || 1;
  }

  async function fetchVariants() {
    try {
      const response = await fetch('https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Extensions/CardExtension.cs');
      const text = await response.text();
      const startIndex = text.indexOf('public static int GetCardVariantsCount(this Card card)');
      if (startIndex !== -1) {
        const functionText = text.substring(startIndex);
        const endIndex = functionText.indexOf('}');
        if (endIndex !== -1) {
          const functionBody = functionText.substring(0, endIndex + 1);
          const lines = functionBody.split(/\r?\n/);
          for (const line of lines) {
            const match = line.match(/Quality\.(\w+)\s*=>\s*(\d+)/);
            if (match) {
              variantsMap = { ...variantsMap, [match[1]]: parseInt(match[2]) };
            }
          }
        }
      }
      initDefaults();
    } catch (error) {
      console.error('Error fetching variants:', error);
    }
  }

  onMount(fetchVariants);

  function getVariantsCount(border) {
    return variantsMap[border] || 0;
  }

  function getStyleList() {
    switch (selectedBorder) {
      case 'Delta':
      case 'Eta':
      case 'Lambda':
        return Array.from({length: getVariantsCount(selectedBorder) + 1}, (_, i) => i.toString());
      default:
        return null;
    }
  }

  let image = "https://sanakan.pl/i/ss/sUwh3io.png";
  let isLocalFile = false;
  let showStats = false;
  let fileName = '';
  let extraFileName = '';

  let selectedBorder = 'Delta';
  let selectedDere = 'Mayadere';
  let selectedStyle = '2'
  $: styles = Object.keys(variantsMap).length ? getStyleList() : [];
  
  let wrapperRef;

  function previewCrop(e) {
    pixelCrop = e.detail.pixels;
    percentCrop = e.detail.percent;
    schedulePreview();
    isUpscaling = pixelCrop.width < 475 || pixelCrop.height < 667;
  }

  function previewExtraCrop(e) {
    extraPixelCrop = e.detail.pixels;
    extraPercentCrop = e.detail.percent;
    schedulePreview();
    isExtraUpscaling = extraPixelCrop.width < 475 || extraPixelCrop.height < 667;
  }

  function processFile(imageFile) {
    if (!imageFile.type.startsWith('image/')) {
      alert('Proszę przeciągnąć plik obrazu JPG lub PNG.');
      return;
    }
    let reader = new FileReader();
    reader.onload = e => {
      image = e.target.result;
      curzoom = 1;
    };
    reader.readAsDataURL(imageFile);
  }
  
  function processExtraFile(file) {
    if (!file) return;
    let reader = new FileReader();
    reader.onload = e => {
      extraImage = e.target.result;
      activeLayer = 'extra';
      bgAutoLayer = false;
      extraZoom = 1;
    };
    reader.readAsDataURL(file);
  }

  function initDefaults() {
    selectedBorder = 'Delta';
    selectedDere = 'Mayadere';
    selectedStyle = '2';
    showStats = false;
    image = "https://sanakan.pl/i/ss/sUwh3io.png";
  }

  // a file from the drop zone or dropped onto the card
  function onFile(e) {
    fileName = e.detail.name;
    isLocalFile = true;
    processFile(e.detail);
  }

  function onExtraFile(e) {
    extraFileName = e.detail.name;
    processExtraFile(e.detail);
  }

  function getStyle() {
    let variantsCount = getVariantsCount(selectedBorder);
    if (variantsCount === 0) return "";
	
    let selectedStyleInt = parseInt(selectedStyle);
    if (selectedStyleInt > variantsCount || selectedStyleInt <= 0)
	{
      selectedStyle = '0';
      return "";
    }
    return selectedStyle;
  }

  function getBorder() {
    let styleUri = getStyle();
    switch (selectedBorder) {
      case 'Beta':
      case 'Epsilon':
      case 'Gamma':
      case 'Theta':
        return "";
      case 'Omega':
        return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/Border${styleUri}.webp`;
      default:
        return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/Border${styleUri}.png`;
    }
  }

  function getBackBorder() {
    let styleUri = getStyle();
    switch (selectedBorder) {
      case 'Jota':
          return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/Border/${selectedDere}.png`;
      case 'Delta':
      case 'Eta':
      case 'Lambda':
        return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/BorderBack${styleUri}.png`;
      case 'Omega':
        return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/BorderBack${styleUri}.webp`;
      default:
        return "";
    }
  }

  function getDere() {
    switch (selectedBorder) {
      case 'Beta':
      case 'Epsilon':
      case 'Gamma':
      case 'Theta':
        return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/Border/${selectedDere}.png`
      default:
        return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/Dere/${selectedDere}.png`;
    }
  }

  function getStats() {
    let styleUri = getStyle();
    switch (selectedBorder) {
      case 'Lambda':
      case 'Zeta':
        return "";
      case 'Gamma':
      case 'Jota':
      case 'Theta':
        return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/Stats/${selectedDere}.png`;
      case 'Beta':
      case 'Epsilon':
        if (selectedDere === 'Yami' || selectedDere === 'Raito' || selectedDere === 'Yato')
          return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/Stats/${selectedDere}.png`
      default:
        return `https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW/CG/${selectedBorder}/Stats${styleUri}.png`;
    }
  }

  let borderUri = "";
  let backBorderUri = "";
  let statsUri = "";
  let dereUri = "";

  function updateData() {
    styles = getStyleList();
    borderUri = getBorder();
    backBorderUri = getBackBorder();
    statsUri = getStats();
    dereUri = getDere();
  }

  $: if (selectedBorder || selectedDere || selectedStyle || Object.keys(variantsMap).length) updateData();

  $: sharpen, realPreview, editMode, image, extraImage, currentMaskUrl, extraMaskUrl, hasExtraLayer, schedulePreview();

  function handleKeyDown(e) {
    if (!editMode) return;
    
    const step = e.shiftKey ? 10 : 1;

    if (e.key === 'ArrowLeft')  moveCrop(-step, 0);
    if (e.key === 'ArrowRight') moveCrop(step, 0);
    if (e.key === 'ArrowUp')    moveCrop(0, -step);
    if (e.key === 'ArrowDown')  moveCrop(0, step);
  }

  async function resetZoom() {
    if (activeLayer === 'base' || activeLayer === 'both') {
      curzoom = 1;
      crop = { x: 0, y: 0 };
      await tick();
      curzoom = 1.000001;
      await tick();
      curzoom = 1;
    }
    if (activeLayer === 'extra' || activeLayer === 'both') {
      extraZoom = 1;
      extraCrop = { x: 0, y: 0 };
      await tick();
      extraZoom = 1.000001;
      await tick();
      extraZoom = 1;
    }
  }

  async function removeBg() {
    if (!image || bgRemoving) return;
    bgRemoving = true;
    bgProgress = 0;
    bgProgressLabel = 'Inicjalizacja...';
    try {
      const imglyModule = await import('@imgly/background-removal');
      const removeBackground = imglyModule.default ?? imglyModule.removeBackground ?? imglyModule;
      const blob = await removeBackground(image, {
        model: bgModel,
        progress: (key, current, total) => {
          if (total > 0) {
            bgProgress = Math.round((current / total) * 100);
            bgProgressLabel = key.includes('fetch') ? 'Pobieranie modelu...' : 'Przetwarzanie...';
          }
        }
      });
      const url = URL.createObjectURL(blob);
      extraImage = url;
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

  async function downloadImage() {
    try {
      const canvas = document.createElement('canvas');
      canvas.width = 475;
      canvas.height = 667;
      const ctx = canvas.getContext('2d');

      // Kolejność warstw: gdy bgAutoLayer (removeBg), scalp jest nad top
      // Normalnie: top jest nad scalp
      let mainImagePart;
      if (editMode) {
        mainImagePart = await getCroppedImg(image, baseCrop(), currentMaskUrl, sharpen);
      } else {
        mainImagePart = image;
      }
      const imgMain = await loadImg(mainImagePart);

      if (hasExtraLayer && extraImage) {
        const croppedExtra = await getCroppedImg(extraImage, topCrop(), extraMaskUrl, sharpen);
        const imgExtra = await loadImg(croppedExtra);
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

</script>
<svelte:window bind:innerWidth={winWidth} />

<header class="page-head">
  <div class="page-top">
    <a class="back hud-corners" href="/" title="Strona główna">&larr; Sanakan</a>
  </div>
  <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
  <h1 class="hud-title">USkalpelator</h1>
</header>

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
        <Switch label="Pokaż statystyki" bind:checked={showStats} />
      </section>

      <section class="group">
        <h2 class="group-title"><i>02</i>Obraz</h2>
        <DropZone bind:fileName on:file={onFile} accept=".jpg, .jpeg, .png, .webp" />
        {#if !isLocalFile}
          <div class="field"><span class="label">Link do obrazka</span><LinkField bind:value={image} label="Link do obrazka" placeholder="Wklej link do obrazka..." /></div>
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
              bind:fileName={extraFileName} on:file={onExtraFile} />
            <div class="field"><span class="label">Usuń tło</span><Segmented bind:value={bgModel} options={bgModels} label="Model usuwania tła" words disabled={bgRemoving} /></div>
            <div class="field"><span class="label"></span>
              <div class="bg-tools">
                <button type="button" class="btn-ai" on:click={removeBg} disabled={bgRemoving}>
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
                <button type="button" class="up" title="W górę" on:click={() => moveCrop(0, -1)}>▲</button>
                <button type="button" class="left" title="W lewo" on:click={() => moveCrop(-1, 0)}>◀</button>
                <button type="button" class="down" title="W dół" on:click={() => moveCrop(0, 1)}>▼</button>
                <button type="button" class="right" title="W prawo" on:click={() => moveCrop(1, 0)}>▶</button>
              </div>
              <div class="nudge-side">
                <div class="info-label">SCALP X {Math.round(crop.x)} · Y {Math.round(crop.y)} · {Math.round(pixelCrop.width)}×{Math.round(pixelCrop.height)}</div>
                {#if hasExtraLayer && extraImage}
                  <div class="info-label">TOP&nbsp;&nbsp; X {Math.round(extraCrop.x)} · Y {Math.round(extraCrop.y)} · {Math.round(extraPixelCrop.width)}×{Math.round(extraPixelCrop.height)}</div>
                {/if}
                <div class="hint">Strzałki na klawiaturze też działają, z Shift po 10 px.</div>
                <div class="nudge-actions">
                  <button type="button" class="btn-muted" on:click={resetZoom}>Reset</button>
                  {#if bgAutoLayer}
                    <button type="button" class="btn-sync" on:click={syncLayers}>Sync</button>
                  {/if}
                </div>
              </div>
            </div>
          </div>
        {/if}
      </section>
    </div>

    <div class="card-col">
      <CardDrop on:file={onFile}>
    <div class="scale-wrapper" bind:this={wrapperRef} style="width: {475 * shownScale}px; height: {667 * shownScale}px;">
      <div class="looks {editMode ? 'is-editing' : ''}" style="transform: scale({shownScale});">
        {#if editMode && hasExtraLayer && extraImage}
          <div class="cropper-container top" class:under-real={realPreview && extraPreview && !previewStale} bind:this={extraCropEl}
              style="--mask-url: url({extraMaskUrl}); pointer-events: {activeLayer === 'base' ? 'none' : 'auto'};">
            <Cropper 
              showGrid={false}
              image={extraImage} 
              bind:zoom={extraZoom} 
              bind:crop={extraCrop}
              disabled={activeLayer === 'base'}
              aspect={475/667}
              minZoom={0.1}
              maxZoom={10}
              zoomSpeed={0.02}
              cropSize={maskCropSize}
              on:cropcomplete={previewExtraCrop}
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
            <div class="green-bg" style="-webkit-mask-image: url({currentMaskUrl}); mask-image: url({currentMaskUrl}); -webkit-mask-size: 100% 100%; mask-size: 100% 100%; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat;"></div>
            <div class="cropper-container" class:under-real={realPreview && basePreview && !previewStale} bind:this={baseCropEl}
                style="--mask-url: url({currentMaskUrl}); pointer-events: {activeLayer === 'extra' ? 'none' : 'auto'};">
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
                on:cropcomplete={previewCrop} 
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
        <button type="button" on:click={() => zoomLevel = zoomLevel === 1 ? 2 : 1}>Skala: {zoomLevel * 100}%</button>
        {#if editMode}
          <button type="button" class="btn-go" on:click={downloadImage}>Pobierz obrazek</button>
        {/if}
      </div>
    </div>
  </div>
</main>

<footer class="site-foot"><span>&copy; 2017&ndash;{year} Sniku</span><i aria-hidden="true">&middot;</i><a href="/privacy/">Prywatność</a></footer>

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
    background: rgba(155, 89, 182, 0.15);
    overflow: hidden;
  }

  .bg-progress-bar {
    height: 100%;
    background: var(--accent);
    box-shadow: 0 0 8px rgba(155, 89, 182, 0.7);
    transition: width 0.3s ease;
  }

  .bg-status {
    font: 12px "Share Tech Mono", monospace;
    letter-spacing: 0.1em;
    color: var(--ok);
  }

  .bg-status.bad {
    color: var(--bad);
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

  /* Ukryj wbudowaną linię obszaru Croppera - zastępujemy własnym divem poza maską */
  :global(.reactEasyCrop_CropArea) {
    border: none !important;
    box-shadow: none !important;
    color: transparent !important;
  }
  
  .green-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 475px;
    height: 667px;
    background: #00ff00;
    z-index: 15;
    pointer-events: none;
  }

  .crop-border {
    position: absolute;
    top: 0;
    left: 0;
    width: 475px;
    height: 667px;
    outline: 1px solid rgba(182, 112, 211, 0.6);
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
