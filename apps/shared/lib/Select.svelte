<script>
  // A list to choose from, in the style of the page: the system one cannot be
  // styled once open. The closed field is built like the link field: a picture,
  // the value with its position, an arrow. The open list shows all of its
  // values, as rows with numbers ("list") or two columns with pictures ("grid").
  import { tick } from 'svelte';

  export let value;
  // values, or { value, label }
  export let options = [];
  export let label = '';
  export let layout = 'list';
  // the picture of a value: CSS for its box (a background), or null for none
  export let icon = null;

  let open = false;
  let up = false;
  let active = -1;
  let root;
  let panel;

  $: items = options.map((o) => (typeof o === 'object' ? o : { value: o, label: String(o) }));
  $: index = items.findIndex((o) => o.value === value);
  $: current = items[index];

  const pos = (i) => String(i + 1).padStart(2, '0');

  async function show() {
    open = true;
    active = Math.max(0, index);
    await tick();
    place();
  }

  function hide() {
    open = false;
    up = false;
  }

  function choose(i) {
    value = items[i].value;
    hide();
    root.querySelector('.sel-btn').focus();
  }

  // upwards when there is no room for the whole list below, but more above
  function place() {
    if (!panel) return;
    const r = root.getBoundingClientRect(), need = panel.offsetHeight + 8;
    const below = innerHeight - r.bottom, above = r.top;
    up = below < need && above > below;
  }

  function onKeydown(e) {
    const n = items.length, cols = layout === 'grid' ? 2 : 1;
    if (!open) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
        e.preventDefault();
        show();
      }
      return;
    }
    let a = active;
    if (e.key === 'Escape' || e.key === 'Tab') return hide();
    else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); return choose(active); }
    else if (e.key === 'ArrowDown') a += cols;
    else if (e.key === 'ArrowUp') a -= cols;
    else if (e.key === 'ArrowRight' && cols > 1) a += 1;
    else if (e.key === 'ArrowLeft' && cols > 1) a -= 1;
    else if (e.key === 'Home') a = 0;
    else if (e.key === 'End') a = n - 1;
    else return;
    e.preventDefault();
    active = (a + n) % n;
  }

  // a click anywhere else closes it; the path is taken when the click starts,
  // so a list redrawn by the click still counts as inside
  function onWindowClick(e) {
    if (open && !e.composedPath().includes(root)) hide();
  }
</script>

<svelte:window on:click={onWindowClick} on:resize={place} />

<!-- svelte-ignore a11y-no-static-element-interactions -->
<div class="sel" class:open class:up bind:this={root} on:keydown={onKeydown}>
  <button type="button" class="sel-btn" aria-haspopup="listbox" aria-expanded={open}
    aria-label="{label}: {current?.label ?? ''}" on:click={() => (open ? hide() : show())}>
    <span class="sel-pre" aria-hidden="true">
      {#if icon}
        <span class="sel-ico" style={icon(value)}></span>
      {:else}
        <svg class="sel-list-ico" viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" /></svg>
      {/if}
    </span>
    <span class="sel-value">{current?.label ?? ''}</span>
    <span class="sel-pos" aria-hidden="true">{pos(index)}/{items.length}</span>
    <span class="sel-end" aria-hidden="true"><svg viewBox="0 0 10 6"><path d="M1 1l4 4 4-4" /></svg></span>
  </button>

  {#if open}
    <div class="sel-panel hud-corners {layout}" role="listbox" aria-label={label} bind:this={panel}>
      {#each items as item, i}
        <!-- svelte-ignore a11y-click-events-have-key-events -->
        <div class="sel-opt" role="option" tabindex="-1" aria-selected={i === index} class:active={i === active}
          on:click={() => choose(i)} on:mouseenter={() => (active = i)}>
          {#if layout === 'list'}
            <i>{pos(i)}</i>
          {:else if icon}
            <span class="sel-ico big" style={icon(item.value)}></span>
          {/if}
          <span>{item.label}</span>
        </div>
      {/each}
    </div>
  {/if}
</div>
