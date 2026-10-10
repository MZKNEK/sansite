<script>
  // The place for a picture file: a click opens the file picker, or the file
  // is dropped here. Sends the file in a "file" event.
  import { createEventDispatcher } from 'svelte';

  export let title = 'Przeciągnij obraz';
  export let hint = 'albo kliknij, by wybrać plik';
  export let accept = '.jpg, .jpeg, .png, .webp, .gif';
  // a smaller one, in a single row
  export let compact = false;
  // name of the last file, shown instead of the hint
  export let fileName = '';

  const dispatch = createEventDispatcher();
  let input;
  let over = false;
  let depth = 0;

  function take(file) {
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('Proszę przeciągnąć plik obrazu JPG lub PNG.');
      return;
    }
    fileName = file.name;
    dispatch('file', file);
  }

  function onKeydown(e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    e.preventDefault();
    input.click();
  }

  // the counter keeps the highlight while the file moves over the texts inside
  function onDragEnter() {
    depth++;
    over = true;
  }

  function onDragLeave() {
    if (--depth <= 0) {
      depth = 0;
      over = false;
    }
  }

  function onDrop(e) {
    depth = 0;
    over = false;
    take(e.dataTransfer.files[0]);
  }
</script>

<div class="drop hud-corners" class:compact class:over role="button" tabindex="0" aria-label="{title}, {hint}"
  on:click={() => input.click()}
  on:keydown={onKeydown}
  on:dragenter|preventDefault={onDragEnter}
  on:dragover|preventDefault
  on:dragleave={onDragLeave}
  on:drop|preventDefault={onDrop}>
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5" /><path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4" /></svg>
  <span class="drop-text">
    <b>{title}</b>
    {#if fileName}<small class="file" title={fileName}>{fileName}</small>{:else}<small>{hint}</small>{/if}
  </span>
  <input bind:this={input} type="file" {accept} hidden
    on:click|stopPropagation
    on:change={(e) => { take(e.currentTarget.files[0]); e.currentTarget.value = ''; }} />
</div>
