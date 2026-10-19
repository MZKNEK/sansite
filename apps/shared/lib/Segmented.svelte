<script>
  // A row of buttons for a short list: all the values in view, one click to choose.
  let {
    value = $bindable(),
    // values, or { value, label, title, icon, tag }: icon is the path of a
    // 16 px icon drawn before the label in the button's colour, tag a short
    // note after it
    options = [],
    label = '',
    // the values are words, not codes: set in JetBrains Mono, as the site's
    // plain buttons, instead of the HUD font
    words = false,
    disabled = false
  } = $props();

  let items = $derived(options.map((o) => (typeof o === 'object' ? o : { value: o, label: String(o) })));
</script>

<div class="seg" class:words role="radiogroup" aria-label={label}>
  {#each items as item}
    <button type="button" role="radio" aria-checked={item.value === value} title={item.title} {disabled}
      onclick={() => (value = item.value)}>
      {#if item.icon}<svg class="seg-icon" viewBox="0 0 16 16" aria-hidden="true"><path d={item.icon} /></svg>{/if}{item.label}{#if item.tag}<small class="seg-tag">{item.tag}</small>{/if}
    </button>
  {/each}
</div>
