<script>
  import { untrack } from 'svelte';
  import Stars from './lib/StarSettings.svelte';
  import Segmented from '../../shared/lib/Segmented.svelte';
  import Switch from '../../shared/lib/Switch.svelte';
  import DropZone from '../../shared/lib/DropZone.svelte';
  import CardDrop from '../../shared/lib/CardDrop.svelte';
  import Select from '../../shared/lib/Select.svelte';
  import LinkField from '../../shared/lib/LinkField.svelte';
  import Header from '../../shared/lib/Header.svelte';
  import GallerySave from '../../shared/lib/GallerySave.svelte';
  import { cardName } from '../../shared/lib/account.js';

  import Cropper from "svelte-easy-crop";
	import { getCroppedImg, getMirroredImg, cropOnScreen } from "../../shared/lib/CanvasUtils.js"

  import cardboard  from './assets/empty.png'
  import def        from './assets/shield.png'
  import fire       from './assets/fire.png'
  import health     from './assets/heart.png'

  let borders = [ 'SSS', 'SS', 'S', 'A', 'B', 'C', 'D', 'E' ]

  const pwAssetsBaseUrl = 'https://raw.githubusercontent.com/MZKNEK/sanakan/master/src/Pictures/PW';

  let deres = [ 'Bodere', 'Dandere', 'Deredere', 'Kamidere', 'Kuudere', 'Mayadere',
    'Tsundere', 'Yandere', 'Raito', 'Yami', 'Yato' ]

  // the dere's badge, cut out of the bot's picture of it (32x34 px at 221,628), in a 22 px box
  const dereIcon = (dere) => `background-image: url(${pwAssetsBaseUrl}/${dere}.png); background-size: 307.4px 431.6px; background-position: -142.4px -406.4px;`;

  let image = "https://sanakan.pl/i/ss/fga432a.png";
  let customBorder = "";
  let showStats = false;
  let editMode = false;
  let localImage = false;
  let mirrorImage = false;
  let fileName = '';

  let starCntComp = 0;
  let selectedStarComp;
  let selectedBorder =  'C';
  let selectedDere = 'Kamidere';

  let pixelCrop, profilePicture, style, borderColor;
  // bound to the cropper, which takes no undefined for them
  let minzoom = 1, curzoom = 1;

  // the crop in % of the picture: unlike pixelCrop it is not rounded
  let cropPercent = null;
  let canvaEl;
  // what the cropper shows; its own numbers only if the screen has none
  const currentCrop = () => cropOnScreen(canvaEl) ?? cropPercent;
  // the picture of the card, under its frame
  const card = { width: 448, height: 650 };
  // extra sharpening; without it the scaling keeps the picture as it is
  let sharpen = 0;
  const sharpenLevels = [
    { value: 0, label: 'Brak', title: 'Wierne skalowanie, bez wyostrzania' },
    { value: 0.3, label: 'Lekkie' },
    { value: 0.6, label: 'Średnie', title: 'Jak dawniej' },
    { value: 1, label: 'Mocne' },
  ];

  // Real preview: the cropper shows the picture as the browser scales it, so
  // once the crop stops moving, the scaled crop of the saved file is put over it
  let realPreview = true;
  let previewUrl = '';
  let previewStale = true;
  let previewTimer;
  let previewToken = 0;

  function schedulePreview() {
    previewStale = true;
    clearTimeout(previewTimer);
    if (!editMode || !realPreview || !cropPercent) return;
    previewTimer = setTimeout(updatePreview, 200);
  }

  async function updatePreview() {
    const token = ++previewToken;
    try {
      const url = await getCroppedImg(image, currentCrop(), { ...card, sharpen });
      if (token !== previewToken) { URL.revokeObjectURL(url); return; }
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = url;
      previewStale = false;
    } catch (error) {
      // the cropper still shows the picture; saving reports the error
    }
  }

  $: sharpen, realPreview, editMode, image, schedulePreview();

  const year = new Date().getFullYear();

  // on narrow screens the card (481 px with its frame) is scaled down to fit
  let winWidth = typeof window !== 'undefined' ? window.innerWidth : 1280;
  $: fitScale = Math.min(1, (winWidth - 32) / 481);

  // the card as it is saved, as a blob: address
  const renderCard = () => getCroppedImg(image, currentCrop(), { ...card, sharpen });

  // the same as a Blob, for the gallery
  async function cardBlob() {
    const url = await renderCard();
    try {
      return await (await fetch(url)).blob();
    } finally {
      URL.revokeObjectURL(url);
    }
  }

  async function downloadImage() {
    try {
      const croppedImage = await renderCard();
      const downloadLink = document.createElement("a");
      downloadLink.href = croppedImage;
      downloadLink.download = "skalpelek.png";
      downloadLink.click();
    } catch (error) {
      alert("Nie udało się pobrać obrazka, spróbuj z innym lub użyj lokalnego pliku.");
    }
	}

  async function toMirrorImage() {
    try {
      const croppedImage = await getMirroredImg(image);
      image = croppedImage;
      localImage = true;
    } catch (error) {
      alert("Nie udało się pobrać obrazka, spróbuj z innym lub użyj lokalnego pliku.");
    }
  }

  // a file from the drop zone or dropped onto the card
  function onFile(e) {
    fileName = e.detail.name;
    readImageFile(e.detail);
  }

  function readImageFile(imageFile) {
    if (!imageFile.type.startsWith('image/')) {
      alert('Proszę przeciągnąć plik obrazu JPG lub PNG.');
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      const result = event.target?.result;
      if (typeof result !== 'string') return;

      image = result;
      localImage = true;
      editMode = false;
      minzoom = 1;
      curzoom = 1;
    };
    reader.readAsDataURL(imageFile);
  }

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

    const dratio = 448 / 650;
    const nratio = profilePicture.naturalWidth / profilePicture.naturalHeight;
    const mratio = dratio / nratio;
    minzoom = mratio > 1 ? mratio : 1;
    curzoom = curzoom < minzoom ? minzoom : curzoom;

		profilePicture.style=`margin: ${hd}px 0 0 ${wd}px; width: ${wdn}px;`
	}
</script>

<svelte:window bind:innerWidth={winWidth} />

<Header title="Skalpelator" />

<main class="content">
  <div class="app-layout">
    <div class="panel">
      <section class="group">
        <h2 class="group-title"><i>01</i>Karta</h2>
        <div class="field"><span class="label">Ramka</span><Segmented bind:value={selectedBorder} options={borders} label="Ramka" /></div>
        <div class="field"><span class="label">Dere</span><Select bind:value={selectedDere} options={deres} label="Dere" icon={dereIcon} /></div>
        <Stars bind:value={selectedStarComp} bind:count={starCntComp}/>
        <div class="field"><span class="label">Link do ramki</span><LinkField bind:value={customBorder} label="Link do ramki" placeholder="adres własnej ramki (opcjonalnie)" /></div>
        <Switch label="Pokaż statystyki" bind:checked={showStats} />
      </section>

      <section class="group">
        <h2 class="group-title"><i>02</i>Obraz</h2>
        <DropZone bind:fileName on:file={onFile} />
        {#if !localImage}
          <div class="field"><span class="label">Link do obrazka</span><LinkField bind:value={image} label="Link do obrazka" placeholder="https://…" /></div>
        {/if}
        <Switch label="Odbicie lustrzane" bind:checked={mirrorImage} on:change={() => toMirrorImage()} />
      </section>

      <section class="group">
        <h2 class="group-title"><i>03</i>Edycja</h2>
        <Switch label="Tryb edycji" bind:checked={editMode} on:change={() => borderColor = ""} />
        {#if editMode}
          <div class="field"><span class="label">Wyostrzenie</span><Segmented bind:value={sharpen} options={sharpenLevels} label="Wyostrzenie" words /></div>
          <Switch label="Podgląd wyniku" bind:checked={realPreview}
            title="Po puszczeniu kadru pokazuje go przeskalowanego dokładnie tak, jak w zapisanym pliku" />
        {/if}
      </section>
    </div>

    <div class="card-col">
      <CardDrop on:file={onFile}>
        <div class="card-fit" style="width: {481 * fitScale}px; height: {673 * fitScale}px;">
        <div class="looks" style="border-color: {borderColor}; transform: scale({fitScale});" >
          <img src={cardboard} class="cardboard" alt="Cardboard" />
          {#if editMode}
            <div class="wrapper">
              <img bind:this={profilePicture} src={image} class="wrapper_img" alt="Scalpel" style={style}/>
            </div>
            <div class="canva" class:under-real={realPreview && previewUrl && !previewStale} bind:this={canvaEl}>
              <Cropper {image} showGrid={false} crop={{x:0, y:0}} bind:zoom={curzoom} bind:minZoom={minzoom} maxZoom={5} zoomSpeed={0.05} cropSize={{width:448, height:650}} restrictPosition={true} oncropcomplete={onCrop} />
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
          <button type="button" class="btn-go" on:click={async () => {downloadImage()}}>Zapisz</button>
          <GallerySave render={cardBlob} name={() => cardName('skalpel')} />
        </div>
      {/if}
    </div>
  </div>
</main>

<footer class="site-foot"><span>&copy; 2017&ndash;{year} Sniku</span><i aria-hidden="true">&middot;</i><a href="/privacy/">Prywatność</a></footer>

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
