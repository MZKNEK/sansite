<script>
  import { onMount, untrack } from 'svelte';
  import Stars from './lib/StarSettings.svelte';
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
  import DraftNote from '../../shared/lib/DraftNote.svelte';
  import { draftKeeper } from '../../shared/lib/draft.js';

  import Cropper from "svelte-easy-crop";
	import { getCroppedImg, getCroppedCanvas, getMirroredImg, cropOnScreen, forgetPicture, closeRenderer } from "../../shared/lib/CanvasUtils.js"
  import { ownedUrls } from '../../shared/lib/ownedUrls.js';
  import { coverMinZoom, keepCovering } from '../../shared/lib/cover.js';
  import { AREA, frameOpening, unionBounds, withMargin } from './lib/opening.js';

  import cardboard  from './assets/empty.webp'
  import def        from './assets/shield.webp'
  import fire       from './assets/fire.webp'
  import health     from './assets/heart.webp'

  let borders = [ 'SSS', 'SS', 'S', 'A', 'B', 'C', 'D', 'E' ]

  // the pictures of the bot's cards, which the site mirrors from its repository (inc/pw.php)
  const pwAssetsBaseUrl = '/pw';

  let deres = [ 'Bodere', 'Dandere', 'Deredere', 'Kamidere', 'Kuudere', 'Mayadere',
    'Tsundere', 'Yandere', 'Raito', 'Yami', 'Yato' ]

  // the dere's badge, cut out of the bot's picture of it (32x34 px at 221,628), in a 22 px box
  const dereIcon = (dere) => `background-image: url(${pwAssetsBaseUrl}/${dere}.png); background-size: 307.4px 431.6px; background-position: -142.4px -406.4px;`;

  let image = $state("https://sanakan.pl/i/ss/fga432a.webp");
  let customBorder = $state("");
  let showStats = $state(false);
  let editMode = $state(false);
  let localImage = $state(false);
  let mirrorImage = $state(false);
  let fileName = $state('');

  let starCntComp = $state(0);
  let selectedStarComp = $state();
  let starShape = $state('Star');
  let starColor = $state('Blue');
  let starType = $state('Full');
  let selectedBorder =  $state('C');
  let selectedDere = $state('Kamidere');

  let pixelCrop, profilePicture = $state(), style, borderColor = $state();
  // bound to the cropper, which takes no undefined for them
  let minzoom = $state(1), curzoom = $state(1);
  // where the cropper has the picture: its centre from the centre of the card
  let crop = $state({ x: 0, y: 0 });
  // a new picture starts covering the whole of the card, as it always did
  let fitPending = true;

  // the crop in % of the picture: unlike pixelCrop it is not rounded
  let cropPercent = null;
  let canvaEl = $state();
  // what the cropper shows; its own numbers only if the screen has none
  const currentCrop = () => cropOnScreen(canvaEl) ?? cropPercent;
  // the picture of the card, under its frame
  const card = { width: 448, height: 650 };
  // extra sharpening; without it the scaling keeps the picture as it is
  let sharpen = $state(0);
  const sharpenLevels = [
    { value: 0, label: 'Brak', title: 'Wierne skalowanie, bez wyostrzania' },
    { value: 0.3, label: 'Lekkie' },
    { value: 0.6, label: 'Średnie', title: 'Jak dawniej' },
    { value: 1, label: 'Mocne' },
  ];

  // Real preview: the cropper shows the picture as the browser scales it, so
  // once the crop stops moving, the scaled crop of the saved file is put over it
  let realPreview = $state(true);
  let previewUrl = $state('');
  let previewStale = $state(true);
  let previewTimer;
  // grows with every change the preview has to follow, so a render begun
  // before it, which ends later, is dropped instead of shown as the new one
  let previewToken = 0;

  // the blob: addresses this app made (the preview, the mirrored picture, the
  // file being downloaded), let go when they are replaced and when the app goes
  const urls = ownedUrls(forgetPicture);
  let destroyed = false;

  // The work kept as a draft in the browser (draft.js), so a Discord login,
  // which leaves the page, or a reload starts where it was: the picture, the
  // crop and what was picked. Nothing is written until the draft is read,
  // and one read after the visitor already chose a picture is not used.
  const draft = draftKeeper('skalpel');
  let draftRead = $state(false);
  let draftSavedAt = $state(0);

  onMount(() => {
    draft.load().then(restoreDraft);
    return () => {
      // what is still under way finds the app gone and leaves its result
      destroyed = true;
      draft.close();
      clearTimeout(previewTimer);
      previewToken++;
      urls.releaseAll();
      closeRenderer();
    };
  });

  function restoreDraft(kept) {
    const picture = kept?.pictures.image;
    if (destroyed || imageReads > 0 || !kept) {
      if (picture?.own) URL.revokeObjectURL(picture.src);
      draftRead = !destroyed;
      return;
    }
    const s = kept.settings;
    if (picture) image = picture.own ? urls.own(picture.src) : picture.src;
    customBorder = s.customBorder ?? customBorder;
    showStats = s.showStats ?? showStats;
    localImage = s.localImage ?? localImage;
    mirrorImage = s.mirrorImage ?? mirrorImage;
    fileName = s.fileName ?? fileName;
    starCntComp = s.stars ?? starCntComp;
    starShape = s.starShape ?? starShape;
    starColor = s.starColor ?? starColor;
    starType = s.starType ?? starType;
    selectedBorder = s.border ?? selectedBorder;
    selectedDere = s.dere ?? selectedDere;
    sharpen = s.sharpen ?? sharpen;
    realPreview = s.realPreview ?? realPreview;
    if (s.crop) {
      // the crop as it was, not the fit of a new picture
      crop = { x: s.crop.x, y: s.crop.y };
      curzoom = s.crop.zoom;
      fitPending = false;
    }
    editMode = s.editMode ?? editMode;
    draftSavedAt = kept.savedAt;
    draftRead = true;
  }

  $effect(() => {
    if (!draftRead) return;
    draft.save({
      customBorder, showStats, localImage, mirrorImage, fileName,
      stars: starCntComp, starShape, starColor, starType,
      border: selectedBorder, dere: selectedDere, editMode, sharpen, realPreview,
      crop: { x: crop.x, y: crop.y, zoom: curzoom },
    }, { image });
  });

  async function startOver() {
    await draft.clear();
    location.reload();
  }

  function schedulePreview() {
    previewStale = true;
    previewToken++;
    clearTimeout(previewTimer);
    if (!editMode || !realPreview || !cropPercent) return;
    previewTimer = setTimeout(updatePreview, 200);
  }

  async function updatePreview() {
    const token = previewToken;
    try {
      const url = urls.own(await getCroppedImg(image, currentCrop(), { ...card, sharpen }));
      if (destroyed || token !== previewToken) { urls.release(url); return; }
      previewUrl = urls.replace(previewUrl, url);
      previewStale = false;
    } catch (error) {
      // the cropper still shows the picture; saving reports the error
    }
  }

  // a new preview when what it shows changes (the crop asks for one itself)
  $effect(() => {
    sharpen, realPreview, editMode, image;
    untrack(schedulePreview);
  });

  // on narrow screens the card (481 px with its frame) is scaled down to fit
  let winWidth = $state(typeof window !== 'undefined' ? window.innerWidth : 1280);
  let fitScale = $derived(Math.min(1, (winWidth - 32) / 481));

  // the card as it is saved, as a blob: address
  const renderCard = () => getCroppedImg(image, currentCrop(), { ...card, sharpen });

  // its pixels, for the gallery
  async function cardPixels() {
    const canvas = await getCroppedCanvas(image, currentCrop(), { ...card, sharpen });
    return canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
  }

  async function downloadImage() {
    try {
      const croppedImage = urls.own(await renderCard());
      const downloadLink = document.createElement("a");
      downloadLink.href = croppedImage;
      downloadLink.download = "skalpelek.png";
      downloadLink.click();
      // let go once the download has begun: before the click there would be
      // nothing to save, and some browsers read it a moment after
      setTimeout(() => urls.release(croppedImage), 10000);
    } catch (error) {
      alert("Nie udało się pobrać obrazka, spróbuj z innym lub użyj lokalnego pliku.");
    }
	}

  async function toMirrorImage() {
    // the picture it starts from: when another is chosen meanwhile, the
    // mirrored one is for a picture no longer there and is dropped
    const source = image;
    try {
      const mirrored = urls.own(await getMirroredImg(source));
      if (destroyed || image !== source) {
        urls.release(mirrored);
        return;
      }
      // the mirrored picture before it, if this was one, is no longer shown
      image = urls.replace(image, mirrored);
      localImage = true;
    } catch (error) {
      alert("Nie udało się pobrać obrazka, spróbuj z innym lub użyj lokalnego pliku.");
    }
  }

  // a file from the drop zone or dropped onto the card
  function onFile(file) {
    fileName = file.name;
    readImageFile(file);
  }

  // a picture of the account's own folder in the gallery, by its link
  function onPick(url) {
    imageReads++;
    image = urls.replace(image, url);
    fileName = '';
    localImage = false;
    editMode = false;
    minzoom = 1;
    curzoom = 1;
    fitPending = true;
  }

  // every file asked for counts, so one read more slowly than the next does
  // not put itself in its place when it ends
  let imageReads = 0;

  function readImageFile(imageFile) {
    if (!imageFile.type.startsWith('image/')) {
      alert('Proszę przeciągnąć plik obrazu JPG lub PNG.');
      return;
    }

    const read = ++imageReads;
    const reader = new FileReader();
    reader.onload = (event) => {
      const result = event.target?.result;
      if (typeof result !== 'string' || destroyed || read !== imageReads) return;

      image = urls.replace(image, result);
      localImage = true;
      editMode = false;
      minzoom = 1;
      curzoom = 1;
      fitPending = true;
    };
    reader.readAsDataURL(imageFile);
  }

  // What of the picture the frames leave open (lib/opening.js): of all the
  // bot's frames, as a card can change its frame, and of the frame from the
  // link too. The picture can be moved and zoomed out as far as it still
  // covers that; until it is known, or when the frame from the link cannot be
  // read (another site), it covers the whole of the card, as it always did.
  let opening = $state(null);
  const openingOf = new Map();
  async function readOpening(url) {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = url;
    await img.decode();
    const canvas = document.createElement('canvas');
    canvas.width = AREA.x + AREA.width;
    canvas.height = AREA.y + AREA.height;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0);
    return frameOpening(ctx.getImageData(AREA.x, AREA.y, AREA.width, AREA.height));
  }
  $effect(() => {
    const frames = [...borders.map(getBorderImageUrl), ...(customBorder ? [customBorder] : [])];
    let gone = false;
    opening = null;
    Promise.all(frames.map((url) => openingOf.get(url) ?? readOpening(url).then((bounds) => {
      openingOf.set(url, bounds);
      return bounds;
    }))).then((all) => {
      if (!gone) opening = withMargin(all.reduce(unionBounds));
    }, () => {});
    return () => { gone = true; };
  });

  // the picture back over the opening after the cropper moved or zoomed it
  // (it lets it go anywhere, restrictPosition being off), and the least zoom
  // that still covers the opening for its wheel and pinch
  function keepCover() {
    const img = canvaEl?.querySelector('img');
    if (!img?.complete || !img.width) return;
    const size = { width: img.width, height: img.height };
    const limits = { size, bounds: opening ?? { x: 0, y: 0, ...card } };
    const least = coverMinZoom(limits);
    if (minzoom !== least) minzoom = least;
    let layer = { x: crop.x, y: crop.y, zoom: curzoom };
    if (fitPending) {
      fitPending = false;
      layer = { x: 0, y: 0, zoom: coverMinZoom({ size, bounds: { x: 0, y: 0, ...card } }) };
    }
    const kept = keepCovering(layer, limits, card);
    if (kept.x !== crop.x || kept.y !== crop.y) crop = { x: kept.x, y: kept.y };
    if (kept.zoom !== curzoom) curzoom = kept.zoom;
  }
  $effect(() => {
    opening;
    untrack(keepCover);
  });

  function getBorderImageUrl(border) {
    return `${pwAssetsBaseUrl}/${border}.png`;
  }

  function getDereImageUrl(dere) {
    return `${pwAssetsBaseUrl}/${dere}.png`;
  }

  // the cropper calls it with the crop itself (svelte-easy-crop 5 has no events),
  // from inside its own effect: untracked, or what it reads and sets here would
  // become what that effect depends on and run it again without end
  const onCrop = (e) => untrack(() => previewCrop(e));
  function previewCrop(e) {
		pixelCrop = e.pixels;
		cropPercent = e.percent;
		schedulePreview();
		const { x, y, width } = e.pixels;
		const scale = 448 / width;

    const hd = -y*scale;
    const wd = -x*scale - 448 / 2;
    const wdn = profilePicture.naturalWidth * scale;

    borderColor = (pixelCrop.width < 448 || pixelCrop.height < 650) ? "#e86262" : "";

    // after the cropper's own effect, which this is called from
    queueMicrotask(keepCover);

		profilePicture.style=`margin: ${hd}px 0 0 ${wd}px; width: ${wdn}px;`
	}
</script>

<svelte:window bind:innerWidth={winWidth} />

<Header title="Skalpelator" />

<main class="content">
  <div class="app-layout">
    <div class="panel">
      {#if draftSavedAt}
        <DraftNote savedAt={draftSavedAt} onreset={startOver} />
      {/if}
      <section class="group">
        <h2 class="group-title"><i>01</i>Karta</h2>
        <div class="field"><span class="label">Ramka</span><Segmented bind:value={selectedBorder} options={borders} label="Ramka" /></div>
        <div class="field"><span class="label">Dere</span><Select bind:value={selectedDere} options={deres} label="Dere" icon={dereIcon} /></div>
        <Stars bind:value={selectedStarComp} bind:count={starCntComp} bind:shape={starShape} bind:color={starColor} bind:type={starType}/>
        <div class="field"><span class="label">Link do ramki</span><LinkField bind:value={customBorder} label="Link do ramki" placeholder="https://… (opcjonalnie)" /></div>
        <Switch label="Pokaż statystyki" bind:checked={showStats} />
      </section>

      <section class="group">
        <h2 class="group-title"><i>02</i>Obraz</h2>
        <DropZone bind:fileName onfile={onFile} />
        <GalleryPick onpick={onPick} />
        {#if !localImage}
          <div class="field"><span class="label">Link do obrazka</span><LinkField bind:value={image} label="Link do obrazka" placeholder="https://…" /></div>
        {/if}
        <Switch label="Odbicie lustrzane" bind:checked={mirrorImage} onchange={() => toMirrorImage()} />
      </section>

      <section class="group">
        <h2 class="group-title"><i>03</i>Edycja</h2>
        <Switch label="Tryb edycji" bind:checked={editMode} onchange={() => { borderColor = ""; crop = { x: 0, y: 0 }; }} />
        {#if editMode}
          <div class="field"><span class="label">Wyostrzenie</span><Segmented bind:value={sharpen} options={sharpenLevels} label="Wyostrzenie" words /></div>
          <Switch label="Podgląd wyniku" bind:checked={realPreview}
            title="Po puszczeniu kadru pokazuje go przeskalowanego dokładnie tak, jak w zapisanym pliku" />
        {/if}
      </section>
    </div>

    <div class="card-col">
      <CardDrop onfile={onFile}>
        <div class="card-fit" style="width: {481 * fitScale}px; height: {673 * fitScale}px;">
        <div class="looks" style="border-color: {borderColor}; transform: scale({fitScale});" >
          <img src={cardboard} class="cardboard" alt="Cardboard" />
          {#if editMode}
            <div class="wrapper">
              <img bind:this={profilePicture} src={image} class="wrapper_img" alt="Scalpel" style={style}/>
            </div>
            <div class="canva" class:under-real={realPreview && previewUrl && !previewStale} bind:this={canvaEl}>
              <Cropper {image} showGrid={false} bind:crop bind:zoom={curzoom} bind:minZoom={minzoom} maxZoom={5} zoomSpeed={0.05} cropSize={{width:448, height:650}} restrictPosition={false} oncropcomplete={onCrop} />
            </div>
            {#if realPreview && previewUrl}
              <img src={previewUrl} class="real" class:stale={previewStale} alt="" />
            {/if}
          {:else}
            <img src={image} class="scalp" alt="Scalpel" />
          {/if}
            {#if customBorder}
              <img src={customBorder} class="border" alt="Border" />
            {:else}
              <img src={getBorderImageUrl(selectedBorder)} class="border" alt="Border" />
              <img src={getDereImageUrl(selectedDere)} class="stats" alt="Dere" />

              {#if showStats}
                <img src={def} class="stats" alt="Defense" />
                <img src={fire} class="stats" alt="Attack" />
                <img src={health} class="stats" alt="Health" />
              {/if}

              {#if starCntComp > 0}
                {#each {length: starCntComp} as _, i}
                  <img src={selectedStarComp} class="star" alt="Star" style="left: {239 - (19 * starCntComp) + (38 * i)}px;"/>
                {/each}
              {/if}

            {/if}
        </div>
        </div>
      </CardDrop>
      {#if editMode}
        <div class="card-actions">
          <button type="button" class="btn-go" onclick={async () => {downloadImage()}}>Pobierz obrazek</button>
          <GallerySave pixels={cardPixels} prefix="skalpel" />
        </div>
      {/if}
    </div>
  </div>
</main>

<Footer />

<style>
  .looks {
    position: relative;
    transform-origin: top left;
    width: 475px;
    height: 667px;
  }
  .canva {
    position: absolute;
    width: 448px;
    height: 650px;
    top: 13px;
    left: 13px;
    z-index: 0;
  }
  .cardboard {
    position: absolute;
    pointer-events: none;
    top: 13px;
    left: 13px;
  }
  .wrapper {
    position: absolute;
    top: 13px;
    left: 13px;
    width: 448px;
    height: 650px;
    overflow: hidden;
    z-index: -1;
  }
  .wrapper_img {
    position: absolute;
  }
  /* the real preview, over the cropper and under the frame; it lets the mouse
     through to the cropper and hides while the crop moves */
  .real {
    position: absolute;
    top: 13px;
    left: 13px;
    width: 448px;
    height: 650px;
    pointer-events: none;
    z-index: 0;
  }
  .real.stale {
    visibility: hidden;
  }
  /* under the real preview the cropper's own picture would show through
     transparent parts; it stays there, unseen, for the mouse */
  .canva.under-real :global(img) {
    opacity: 0;
  }
  .scalp {
    position: absolute;
    top: 13px;
    left: 13px;
    width: 448px;
    height: auto;
    pointer-events: none;
    clip-path: xywh(0 0 100% 650px);
  }
  .border {
    position: relative;
    pointer-events: none;
    z-index: 1;
  }
  .star {
    position: absolute;
    pointer-events: none;
    z-index: 3;
    top: 30px;
  }
  .stats {
    position: absolute;
    pointer-events: none;
    z-index: 3;
    top: 0px;
    left: 0px;
  }
</style>
