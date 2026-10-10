<script>
  import Segmented from '../../../shared/lib/Segmented.svelte';
  import Select from '../../../shared/lib/Select.svelte';

  let starCount = [ 0, 1, 2, 3, 4, 5, 6 ]

  let starColors = [ 'Bronze', 'Silver', 'Gold', 'Blue', 'Green', 'Red', 'Purple', 'Black', 'White', 'Candy', 'Vapor', 'Neon', 'Rainbow' ]

  let starShapes = [ 'Triangle', 'Rhombus', 'Pentagon',  'Star', 'DualStar' ]

  let starTypes = [ 'Full', 'Empty' ]

  // the bot's stars, which the site mirrors from its repository (inc/pw.php)
  const pwStarsBaseUrl = '/pw/stars';

  let starShape = $state('Star');
  let starColor = $state('Blue');
  let starType = $state('Full');

  // the star of a shape and colour, as the bot draws it; Rainbow is a star of
  // its own, the last one, with no shapes: always its one picture (13_1)
  const starFile = (shape, color, type) =>
    `${pwStarsBaseUrl}/${type}/${starColors.indexOf(color)+1}_${color === 'Rainbow' ? 1 : starShapes.indexOf(shape)+1}.png`;
  const starIcon = (shape, color, type) => `background-image: url(${starFile(shape, color, type)});`;

  // how many stars and the picture of one, for the card
  let { count: starCnt = $bindable(0), value: selectedValue = $bindable() } = $props();
  $effect.pre(() => {
    selectedValue = starFile(starShape, starColor, starType);
  });



</script>

<div class="field"><span class="label">Gwiazdki</span><Segmented bind:value={starCnt} options={starCount} label="Gwiazdki" /></div>

{#if starCnt > 0}
  <div class="field"><span class="label">Kolor</span><Select bind:value={starColor} options={starColors} label="Kolor gwiazdek" layout="grid"
    icon={(color) => starIcon(starShape, color, starType)} /></div>

  {#if starColor !== 'Rainbow'}
    <div class="field"><span class="label">Kształt</span><Select bind:value={starShape} options={starShapes} label="Kształt gwiazdek" layout="grid"
      icon={(shape) => starIcon(shape, starColor, starType)} /></div>
  {/if}

  <div class="field"><span class="label">Typ</span><Segmented bind:value={starType} options={starTypes} label="Typ gwiazdek" words /></div>
{/if}
