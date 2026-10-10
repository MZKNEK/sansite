<script>
  // A field for the address of a picture, built like the lists: a link icon,
  // the address, whether it gives a picture (✓ with its size, or ✗) and a
  // button to clear it.
  import { onDestroy } from 'svelte';

  let { value = $bindable(''), placeholder = '', label = '' } = $props();

  let input = $state();
  let status = $state('');
  let size = $state('');
  let timer;
  let probe;

  // waits for the typing to stop, then tries to load the picture
  function check(url) {
    clearTimeout(timer);
    if (probe) probe.onload = probe.onerror = null;
    if (!url) {
      status = '';
      return;
    }
    status = 'wait';
    timer = setTimeout(() => {
      const img = new Image();
      probe = img;
      img.onload = () => {
        status = 'ok';
        size = `${img.naturalWidth}×${img.naturalHeight}`;
      };
      img.onerror = () => {
        status = 'bad';
      };
      img.src = url;
    }, 300);
  }

  onDestroy(() => clearTimeout(timer));
  $effect(() => {
    check(value);
  });
</script>

<div class="link-field">
  <span class="link-pre" aria-hidden="true">
    <svg viewBox="0 0 24 24"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1" /><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" /></svg>
  </span>
  <input class="bare" bind:this={input} bind:value {placeholder} aria-label={label} spellcheck="false" />
  {#if status}
    <span class="link-state {status}" aria-live="polite">
      {status === 'ok' ? `✓ ${size}` : status === 'bad' ? '✗ BŁĄD' : '…'}
    </span>
  {/if}
  {#if value}
    <button type="button" class="link-clear" title="Wyczyść" onclick={() => { value = ''; input.focus(); }}>✕</button>
  {/if}
</div>
