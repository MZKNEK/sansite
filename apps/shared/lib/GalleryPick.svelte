<script>
  // "Z mojej galerii": the pictures of the account's own folder in the gallery
  // (i/?pick), newest first, to crop one of them. They are on the site itself,
  // so the crop reads them without asking another server. Shown only to an
  // account with a folder of its own.
  import { tick } from 'svelte';
  import { account } from './account.js';


  // called with the link of the chosen picture
  let { onpick } = $props();

  let open = $state(false);
  let loading = $state(false);
  let error = $state('');
  let pictures = $state([]);
  let dialog = $state();

  async function show() {
    open = true;
    loading = true;
    error = '';
    await tick();
    dialog.showModal();
    try {
      const res = await fetch('/i/?pick', { credentials: 'same-origin', cache: 'no-store' });
      const answer = await res.json().catch(() => null);
      if (!answer?.ok) throw new Error(answer?.message || `Galeria nie odpowiedziała (HTTP ${res.status}).`);
      pictures = answer.pictures;
    } catch (e) {
      error = e.message;
    }
    loading = false;
  }

  function hide() {
    dialog.close();
    open = false;
  }

  function choose(picture) {
    hide();
    onpick?.(picture.url);
  }

  function keydown(e) {
    if (e.key !== 'Tab') return;
    const controls = dialog.querySelectorAll('button:not(:disabled), a[href]');
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }
</script>

{#if $account?.own}
  <button type="button" class="btn-pick" onclick={show}>Z mojej galerii</button>
{/if}

{#if open}
  <dialog class="pick-back" bind:this={dialog} aria-label="Obrazek z mojej galerii"
    onclose={() => { open = false; }} onkeydown={keydown}
    onclick={(e) => { if (e.target === e.currentTarget) hide(); }}>
    <div class="pick hud-corners">
      <div class="pick-head">
        <span>Moja galeria{pictures.length && !loading ? ` · ${pictures.length}` : ''}</span>
        <button type="button" class="pick-close" title="Zamknij (Esc)" onclick={hide}>✕</button>
      </div>
      {#if loading}
        <p class="pick-note">Wczytuję…</p>
      {:else if error}
        <p class="pick-note bad">{error}</p>
      {:else if !pictures.length}
        <p class="pick-note">W twoim folderze nie ma jeszcze obrazków. Dodaj je w <a href="/i/">galerii</a> albo zapisz kartę przyciskiem „Do galerii”.</p>
      {:else}
        <div class="pick-grid">
          {#each pictures as picture (picture.url)}
            <button type="button" class="pick-item" title="{picture.folder}/{picture.name}" onclick={() => choose(picture)}>
              <img src={picture.thumb ?? picture.url} alt="" loading="lazy" />
              <span>{picture.name}</span>
            </button>
          {/each}
        </div>
      {/if}
    </div>
  </dialog>
{/if}

<style>
  .btn-pick {
    width: 100%;
  }

  .pick-back {
    position: fixed;
    inset: 0;
    z-index: 50;
    width: 100%;
    height: 100%;
    max-width: none;
    max-height: none;
    margin: 0;
    border: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: var(--scrim);
    color: inherit;
  }

  .pick-back::backdrop {
    background: transparent;
  }

  .pick {
    --fill: var(--surface-raised);
    display: flex;
    flex-direction: column;
    width: min(880px, 100%);
    max-height: min(720px, 100%);
    border: 1px solid rgba(var(--accent-rgb), 0.3);
    box-shadow: 0 12px 30px var(--shadow);
    text-align: left;
  }

  .pick-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(var(--accent-rgb), 0.22);
    font: 13px "Sanakan Mono", monospace;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--link);
  }

  .pick-close {
    padding: 2px 10px;
  }

  .pick-note {
    margin: 0;
    padding: 24px 16px;
    color: var(--text-secondary);
  }

  .pick-note.bad {
    color: var(--lock-text);
  }

  .pick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
    padding: 14px 16px 16px;
    overflow-y: auto;
  }

  /* a tile like the gallery's: the thumbnail and the name under it */
  .pick-item {
    --corner: rgba(var(--accent-light-rgb), 0.35);
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 6px;
    font: 11px/1.3 "JetBrains Mono", Consolas, monospace;
    letter-spacing: normal;
    text-transform: none;
  }

  .pick-item img {
    width: 100%;
    aspect-ratio: 448 / 650;
    object-fit: cover;
    background: var(--media-matte);
  }

  .pick-item span {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }
</style>
