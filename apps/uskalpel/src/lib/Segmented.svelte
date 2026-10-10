<script>
  // A row of buttons for a short list: all the values in view, one click to choose.
  export let value;
  // values, or { value, label, title }
  export let options = [];
  export let label = '';
  // the values are words, not codes: set in Lato instead of the HUD font
  export let words = false;
  export let disabled = false;

  $: items = options.map((o) => (typeof o === 'object' ? o : { value: o, label: String(o) }));
</script>

<div class="seg" class:words role="radiogroup" aria-label={label}>
  {#each items as item}
    <button type="button" role="radio" aria-checked={item.value === value} title={item.title} {disabled}
      on:click={() => (value = item.value)}>{item.label}</button>
  {/each}
</div>
